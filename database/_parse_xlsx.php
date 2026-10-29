<?php
$path = 'c:\\Users\\Lenovo\\Downloads\\Inventory.xlsx';
$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    fwrite(STDERR, "Cannot open $path\n");
    exit(1);
}
echo "=== FILES ===\n";
for ($i = 0; $i < $zip->numFiles; $i++) {
    echo $zip->getNameIndex($i) . "\n";
}

$wb = $zip->getFromName('xl/workbook.xml');
echo "\n=== WORKBOOK ===\n";
echo $wb, "\n";

$ss = $zip->getFromName('xl/sharedStrings.xml');
if ($ss !== false) {
    echo "\n=== SHARED STRINGS (truncated) ===\n";
    echo substr($ss, 0, 8000), "\n";
}

$rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
echo "\n=== RELS ===\n";
echo $rels, "\n";

$zip->close();
