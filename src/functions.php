<?php

const VALID_ELEMENTS = ['product', 'Product', 'item', 'post', 'entry', 'Table'];

function getMappedValue(SimpleXMLElement $product, array $fields): string
{
    $namespaces = $product->getNamespaces(true);
    $g = $product->children($namespaces['g'] ?? null);

    foreach ($fields as $field) {
        if (!empty($product->$field)) {
            return (string)$product->$field;
        }
        if (!empty($g->$field)) {
            return (string)$g->$field;
        }
    }
    return '';
}

function convertXml(string $inputPath, string $outputPath, array $mapping): int
{
    $count = 0;

    $reader = new XMLReader();
    if (!$reader->open($inputPath)) {
        throw new RuntimeException("Cannot open file: $inputPath");
    }

    $xml = new XMLWriter();
    $xml->openURI($outputPath);
    $xml->setIndent(true);
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('products');

    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || !in_array($reader->name, VALID_ELEMENTS)) {
            continue;
        }

        $product = new SimpleXMLElement($reader->readOuterXml());
        $id = getMappedValue($product, $mapping['id']);

        $xml->startElement('product');
        $xml->writeAttribute('id', $id);

        foreach (['name', 'link', 'category', 'manufacturer', 'weight'] as $cdataField) {
            $xml->startElement($cdataField);
            $xml->writeCdata(getMappedValue($product, $mapping[$cdataField]));
            $xml->endElement();
        }

        $xml->writeElement('availability', getMappedValue($product, $mapping['availability']));
        $xml->writeElement('quantity', getMappedValue($product, $mapping['quantity']));

        $xml->startElement('attributes');
        foreach ($mapping['attributes'] as $attrName) {
            $value = getMappedValue($product, [$attrName]);
            if ($value !== '') {
                $xml->startElement('attribute');
                $xml->writeAttribute('name', $attrName);
                $xml->text($value);
                $xml->endElement();
            }
        }
        $xml->endElement();

        $xml->startElement('images');
        foreach ($mapping['images'] as $imgField) {
            $imgValue = getMappedValue($product, [$imgField]);
            if ($imgValue !== '') {
                foreach (preg_split('/[,|]/', $imgValue) as $imgUrl) {
                    $imgUrl = trim($imgUrl);
                    if ($imgUrl !== '') {
                        $xml->startElement('image');
                        $xml->text($imgUrl);
                        $xml->endElement();
                    }
                }
            }
        }
        $xml->endElement();

        foreach (['mpn', 'sku', 'ean', 'dimensions', 'special_price', 'regular_price', 'reseller_price'] as $field) {
            $xml->writeElement($field, getMappedValue($product, $mapping[$field]));
        }

        $xml->startElement('description');
        $xml->writeCdata(getMappedValue($product, $mapping['description']));
        $xml->endElement();

        $xml->endElement();
        $count++;
    }

    $xml->endElement();
    $xml->endDocument();
    $xml->flush();
    $reader->close();

    return $count;
}

function analyzeXml(string $filePath, array $mapping): array
{
    $reader = new XMLReader();
    if (!$reader->open($filePath)) {
        throw new RuntimeException("Cannot open file: $filePath");
    }

    $fieldNames = [];

    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT && in_array($reader->name, VALID_ELEMENTS)) {
            $product = new SimpleXMLElement($reader->readOuterXml());

            foreach ($product->children() as $child) {
                $fieldNames[$child->getName()] = true;
            }

            $namespaces = $product->getNamespaces(true);
            foreach ($namespaces as $prefix => $ns) {
                foreach ($product->children($ns) as $child) {
                    $fieldNames[$prefix . ':' . $child->getName()] = true;
                }
            }
            break;
        }
    }
    $reader->close();

    // Build reverse mapping: lowercase field => standard field name
    $reverseMapping = [];
    foreach ($mapping as $standardField => $possibleFields) {
        foreach ($possibleFields as $field) {
            $reverseMapping[strtolower($field)] = $standardField;
        }
    }

    $matched   = [];
    $unmatched = [];

    foreach (array_keys($fieldNames) as $field) {
        $lower = strtolower($field);
        $clean = strtolower(preg_replace('/^[a-z]+:/', '', $field));

        if (isset($reverseMapping[$lower])) {
            $matched[$field] = $reverseMapping[$lower];
        } elseif (isset($reverseMapping[$clean])) {
            $matched[$field] = $reverseMapping[$clean];
        } else {
            $bestScore    = 0;
            $bestStandard = null;
            foreach ($reverseMapping as $knownField => $standardField) {
                similar_text($lower, $knownField, $pct);
                if ($pct > $bestScore) {
                    $bestScore    = $pct;
                    $bestStandard = $standardField;
                }
                similar_text($clean, $knownField, $pct);
                if ($pct > $bestScore) {
                    $bestScore    = $pct;
                    $bestStandard = $standardField;
                }
            }
            $unmatched[$field] = [
                'suggestion' => $bestStandard,
                'confidence' => round($bestScore),
            ];
        }
    }

    return [
        'total'     => count($fieldNames),
        'matched'   => $matched,
        'unmatched' => $unmatched,
    ];
}

function saveMappingFile(array $mapping): bool
{
    $lines = ["<?php\n\n\$mapping = [\n"];

    foreach ($mapping as $key => $values) {
        $quoted  = array_map(fn($v) => "'" . addslashes($v) . "'", $values);
        $lines[] = "    '$key' =>\n    [" . implode(', ', $quoted) . "],\n\n";
    }

    $lines[] = "];\n";

    return file_put_contents(__DIR__ . '/mapping.php', implode('', $lines)) !== false;
}
