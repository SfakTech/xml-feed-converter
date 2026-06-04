# PHP XML Converter

A PHP script that normalizes XML product feeds from different suppliers into a unified format.

## How it works

Different suppliers use different field names for the same data (e.g. `ean`, `barcode`, `EAN`, `Barcode_Item` all mean the same thing). This tool reads the field mappings defined in `mapping.php` and converts any XML feed into a consistent output format.

**Output format:**
```xml
<products>
  <product id="...">
    <name><![CDATA[...]]></name>
    <link><![CDATA[...]]></link>
    <category><![CDATA[...]]></category>
    <availability>...</availability>
    <quantity>...</quantity>
    <manufacturer><![CDATA[...]]></manufacturer>
    <weight>...</weight>
    <attributes>
      <attribute name="...">...</attribute>
    </attributes>
    <images>
      <image>...</image>
    </images>
    <mpn>...</mpn>
    <sku>...</sku>
    <ean>...</ean>
    <dimensions>...</dimensions>
    <special_price>...</special_price>
    <regular_price>...</regular_price>
    <reseller_price>...</reseller_price>
    <description><![CDATA[...]]></description>
  </product>
</products>
```

## Requirements

- PHP 7.4+
- `ext-xmlreader` and `ext-xmlwriter` (included in most PHP installations)

## Usage

Place your XML files in the input folder and run:

```bash
php converter.php --input ./xml --output ./output
```

| Argument   | Description                        | Default    |
|------------|------------------------------------|------------|
| `--input`  | Folder containing source XML files | `./xml`    |
| `--output` | Folder for converted XML files     | `./output` |

## Adding new field mappings

Open `mapping.php` and add the new field name to the relevant array:

```php
'ean' => ['ean', 'EAN', 'barcode', 'your_new_field_name'],
```

## Supported root elements

The converter automatically detects products inside these XML tags:
`product`, `Product`, `item`, `post`, `entry`, `Table`
