<?php
$base = __DIR__ . '/_xlsx/xl';
$ssXml = simplexml_load_file($base . '/sharedStrings.xml');
$ssXml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
$strings = [];
foreach ($ssXml->si as $si) {
    $t = '';
    if (isset($si->t)) {
        $t = (string)$si->t;
    } else {
        foreach ($si->r as $r) {
            $t .= (string)$r->t;
        }
    }
    $strings[] = trim($t);
}

function col_row($cell) {
    preg_match('/^([A-Z]+)(\d+)$/', $cell, $m);
    return [$m[1], (int)$m[2]];
}

function dump_sheet($path, $strings, $maxRows = 60) {
    echo "\n======== $path ========\n";
    $xml = simplexml_load_file($path);
    $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rnum = (int)$row['r'];
        if ($rnum > $maxRows) continue;
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            [$col, $rn] = col_row($ref);
            $t = (string)$c['t'];
            $v = (string)$c->v;
            if ($t === 's') $v = $strings[(int)$v] ?? '';
            $rows[$rn][$col] = $v;
        }
    }
    ksort($rows);
    foreach ($rows as $rn => $cols) {
        ksort($cols);
        $parts = [];
        foreach ($cols as $col => $v) {
            if ($v === '' || $v === null) continue;
            $parts[] = "$col=$v";
        }
        if ($parts) echo "R$rn: " . implode(' | ', $parts) . "\n";
    }
}

dump_sheet($base . '/worksheets/sheet1.xml', $strings, 55);
dump_sheet($base . '/worksheets/sheet3.xml', $strings, 80);
dump_sheet($base . '/worksheets/sheet4.xml', $strings, 80);
dump_sheet($base . '/worksheets/sheet5.xml', $strings, 80);
