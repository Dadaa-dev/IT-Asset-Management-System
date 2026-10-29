<?php
$base = __DIR__ . '/_xlsx/xl';
$ssXml = simplexml_load_file($base . '/sharedStrings.xml');
$strings = [];
foreach ($ssXml->si as $si) {
    $t = '';
    if (isset($si->t)) $t = (string)$si->t;
    else foreach ($si->r as $r) $t .= (string)$r->t;
    $strings[] = trim($t);
}
$xml = simplexml_load_file($base . '/worksheets/sheet4.xml');
foreach ($xml->sheetData->row as $row) {
    $rnum = (int)$row['r'];
    if ($rnum < 10 || $rnum > 16) continue;
    echo "R$rnum\n";
    foreach ($row->c as $c) {
        $ref = (string)$c['r'];
        $t = (string)$c['t'];
        $v = (string)$c->v;
        if ($t === 's') $v = $strings[(int)$v] ?? '';
        echo "  $ref = $v\n";
    }
}

echo "\n--- sheet3 remaining ---\n";
$xml = simplexml_load_file($base . '/worksheets/sheet3.xml');
foreach ($xml->sheetData->row as $row) {
    $rnum = (int)$row['r'];
    if ($rnum < 54 || $rnum > 80) continue;
    $parts = [];
    foreach ($row->c as $c) {
        $ref = (string)$c['r'];
        $t = (string)$c['t'];
        $v = (string)$c->v;
        if ($t === 's') $v = $strings[(int)$v] ?? '';
        if ($v !== '') $parts[] = "$ref=$v";
    }
    if ($parts) echo "R$rnum: " . implode(' | ', $parts) . "\n";
}
