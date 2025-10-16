<?php

require "mapping.php";

$inputDir = 'C:\\Users\\Dtek\\Desktop\\websites\\php_xml\\xml';
$outputDir = 'C:\\Users\\Dtek\\Desktop\\websites\\php_xml\\output';

$validElements = ['product', 'Product', 'item', 'post', 'entry', 'Table'];

function getMappedValue(SimpleXMLElement $product, array $fields)
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

$files = glob($inputDir . '/*.xml');

foreach ($files as $filePath) {
    $reader = new XMLReader();
    $reader->open($filePath);

    $xml = new XMLWriter();
    $xml->openURI($outputDir . '/' . basename($filePath));
    $xml->setIndent(true);
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('products');

    while ($reader->read()) {
        if ($reader->nodeType == XMLReader::ELEMENT && in_array($reader->name, $validElements)) {

            $product = new SimpleXMLElement($reader->readOuterXml());

            $id = getMappedValue($product, $mapping['id']);

            $xml->startElement('product');
            $xml->writeAttribute('id', $id);

            $xml->startElement('name');
            $xml->writeCdata(getMappedValue($product, $mapping['name']));
            $xml->endElement();

            $xml->startElement('link');
            $xml->writeCdata(getMappedValue($product, $mapping['link']));
            $xml->endElement();

            $xml->startElement('category');
            $xml->writeCdata(getMappedValue($product, $mapping['category']));
            $xml->endElement();

            $xml->writeElement('availability', getMappedValue($product, $mapping['availability']));
            $xml->writeElement('quantity', getMappedValue($product, $mapping['quantity']));

            $xml->startElement('manufacturer');
            $xml->writeCdata(getMappedValue($product, $mapping['manufacturer']));
            $xml->endElement();

            $xml->startElement('weight');
            $xml->writeCdata(getMappedValue($product, $mapping['weight']));
            $xml->endElement();

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
                    $imgUrls = preg_split('/[,\|]/', $imgValue);
                    foreach ($imgUrls as $imgUrl) {
                        $imgUrl = trim($imgUrl);
                        if($imgUrl !== '') {
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
        }
    }

    $xml->endElement();
    $xml->endDocument();
    $xml->flush();

    $reader->close();

    echo "New XML converterd successfully: " . basename($filePath) . "\n";
}
