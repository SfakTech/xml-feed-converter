<?php

require 'src/mapping.php';
require 'src/functions.php';

$options  = getopt('', ['input:', 'output:']);
$inputDir  = rtrim($options['input']  ?? './xml', '/\\');
$outputDir = rtrim($options['output'] ?? './output', '/\\');

if (!is_dir($inputDir)) {
    die("Error: input folder not found: $inputDir\n");
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
    die("Error: could not create output folder: $outputDir\n");
}

$files = glob($inputDir . '/*.xml');

if (empty($files)) {
    die("No XML files found in: $inputDir\n");
}

foreach ($files as $filePath) {
    $outputPath = $outputDir . '/' . basename($filePath);
    try {
        $count = convertXml($filePath, $outputPath, $mapping);
        echo "Converted: " . basename($filePath) . " ($count products)\n";
    } catch (Exception $e) {
        echo "Error: " . basename($filePath) . " — " . $e->getMessage() . "\n";
    }
}
