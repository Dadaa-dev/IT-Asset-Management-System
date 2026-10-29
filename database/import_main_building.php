<?php
/**
 * Import Main and Melloville Building inventories from inventory/Inventory.xlsx
 * (parsed from the unzipped OOXML in database/_xlsx).
 *
 * Usage: php database/import_main_building.php
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli('localhost', 'root', '', 'capstone_system');
$conn->set_charset('utf8mb4');

$xlsxDir = __DIR__ . '/_xlsx/xl';
if (!is_file($xlsxDir . '/sharedStrings.xml')) {
    fwrite(STDERR, "Unzip inventory/Inventory.xlsx into database/_xlsx first.\n");
    exit(1);
}

$conn->query("ALTER TABLE items MODIFY COLUMN asset_code VARCHAR(40) NOT NULL");
$conn->query("CREATE TABLE IF NOT EXISTS app_settings (
    k VARCHAR(80) PRIMARY KEY,
    v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function load_strings($path) {
    $xml = simplexml_load_file($path);
    $out = [];
    foreach ($xml->si as $si) {
        $t = '';
        if (isset($si->t)) $t = (string)$si->t;
        else foreach ($si->r as $r) $t .= (string)$r->t;
        $out[] = trim(preg_replace('/\s+/', ' ', $t));
    }
    return $out;
}

function sheet_rows($path, $strings) {
    $xml = simplexml_load_file($path);
    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rnum = (int)$row['r'];
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) continue;
            $col = $m[1];
            $t = (string)$c['t'];
            $v = (string)$c->v;
            if ($t === 's') $v = $strings[(int)$v] ?? '';
            $v = trim($v);
            if ($v !== '') $rows[$rnum][$col] = $v;
        }
    }
    ksort($rows);
    return $rows;
}

function cell($row, $col) {
    return isset($row[$col]) ? trim($row[$col]) : '';
}

function normalize_tag($tag) {
    $tag = strtoupper(trim($tag));
    $tag = preg_replace('/\s+/', '-', $tag);
    $tag = preg_replace('/[^A-Z0-9\/\-]/', '', $tag);
    $tag = trim($tag, '-/');
    return $tag;
}

function map_condition($raw) {
    $s = strtoupper($raw);
    if ($s === '' || preg_match('/GOOD|WORKING|GOODS/', $s)) return 'good';
    if (preg_match('/FAIR|LAGGY|NEED|UPGRADE|FIX|CHANGE|SWAP/', $s)) return 'fair';
    if (preg_match('/POOR|BROKEN|NOT WORKING|DEAD/', $s)) return 'poor';
    return 'good';
}

function map_status($raw) {
    $s = strtoupper($raw);
    if (preg_match('/NOT WORKING|BROKEN|NEED TO FIX/', $s)) return 'under_repair';
    return 'active';
}

function map_category($conn, $name) {
    $n = strtoupper($name);
    $map = [
        'Computer / Laptop' => ['SYSTEM UNIT','PC ','DESKTOP','CPU','WORKSTATION'],
        'Monitor' => ['MONITOR'],
        'Printer' => ['PRINTER','EPSON','BROTHER','RISO','PHOTOCOPY','ID MAKER'],
        'Keyboard' => ['KEYBOARD'],
        'Mouse' => ['MOUSE'],
        'Networking Equipment' => ['ROUTER','SWITCH','CABINET','CAT5','CAT6'],
        'Projector' => ['PROJECTOR'],
        'Furniture' => ['SHELV','CABINET','PIANO'],
        'Others' => [],
    ];
    $chosen = 'Others';
    foreach ($map as $cat => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($n, $needle)) { $chosen = $cat; break 2; }
        }
    }
    static $cache = [];
    if (isset($cache[$chosen])) return $cache[$chosen];
    $stmt = $conn->prepare('SELECT id FROM categories WHERE name=?');
    $stmt->bind_param('s', $chosen);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        $ins = $conn->prepare('INSERT INTO categories (name) VALUES (?)');
        $ins->bind_param('s', $chosen);
        $ins->execute();
        $cache[$chosen] = (int)$conn->insert_id;
    } else {
        $cache[$chosen] = (int)$row['id'];
    }
    return $cache[$chosen];
}

function ensure_room($conn, $buildingId, $floorNumber, $roomNumber, $label) {
    $floor = $conn->query("SELECT id FROM floors WHERE building_id=$buildingId AND floor_number=$floorNumber")->fetch_assoc();
    if (!$floor) {
        $conn->query("INSERT INTO floors (building_id, floor_number, label) VALUES ($buildingId, $floorNumber, 'Floor $floorNumber')");
        $floorId = (int)$conn->insert_id;
    } else {
        $floorId = (int)$floor['id'];
    }
    $stmt = $conn->prepare('SELECT id FROM rooms WHERE floor_id=? AND room_number=?');
    $stmt->bind_param('is', $floorId, $roomNumber);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    if ($room) {
        $upd = $conn->prepare('UPDATE rooms SET label=? WHERE id=?');
        $upd->bind_param('si', $label, $room['id']);
        $upd->execute();
        return (int)$room['id'];
    }
    $ins = $conn->prepare('INSERT INTO rooms (floor_id, room_number, label) VALUES (?,?,?)');
    $ins->bind_param('iss', $floorId, $roomNumber, $label);
    $ins->execute();
    return (int)$conn->insert_id;
}

$usedTags = [];
$res = $conn->query('SELECT id, asset_code FROM items');
while ($row = $res->fetch_assoc()) $usedTags[strtoupper($row['asset_code'])] = (int)$row['id'];

function upsert_item($conn, &$usedTags, $roomId, $name, $tag, $desc, $serial, $prop, $qty, $condition, $status, $categoryName) {
    $tag = normalize_tag($tag);
    if ($tag === '') $tag = 'MB-ASSET';
    $catId = map_category($conn, $categoryName ?: $name);
    $qty = max(1, (int)$qty);
    $esc = $conn->real_escape_string($tag);
    $existing = $conn->query("SELECT id FROM items WHERE asset_code='$esc'")->fetch_assoc();
    $uid = 1;
    if ($existing) {
        $stmt = $conn->prepare('UPDATE items SET room_id=?, category_id=?, name=?, description=?, serial_no=?, property_no=?, quantity=?, `condition`=?, status=?, updated_by=? WHERE id=?');
        $stmt->bind_param('iissssissii', $roomId, $catId, $name, $desc, $serial, $prop, $qty, $condition, $status, $uid, $existing['id']);
        $stmt->execute();
        $usedTags[$tag] = (int)$existing['id'];
        return (int)$existing['id'];
    }
    $base = $tag;
    $n = 2;
    while (isset($usedTags[$tag])) {
        $tag = $base . '-' . $n;
        $n++;
    }
    $stmt = $conn->prepare('INSERT INTO items (room_id,category_id,name,description,serial_no,property_no,asset_code,quantity,`condition`,status,added_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->bind_param('iisssssissi', $roomId, $catId, $name, $desc, $serial, $prop, $tag, $qty, $condition, $status, $uid);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $usedTags[$tag] = $id;
    $log = $conn->prepare('INSERT INTO item_logs (item_id,user_id,action,note) VALUES (?,?,?,?)');
    $act = 'add';
    $note = 'Imported from Main Building inventory: ' . $name;
    $log->bind_param('iiss', $id, $uid, $act, $note);
    $log->execute();
    return $id;
}

$guest = 'reports@spist.edu.ph';
$exists = $conn->query("SELECT id FROM users WHERE gmail='$guest'")->fetch_assoc();
if (!$exists) {
    $hash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $ins = $conn->prepare("INSERT INTO users (gmail,password,full_name,auth_provider,role) VALUES (?,?,?,'local','staff')");
    $name = 'QR / Google Form Reports';
    $ins->bind_param('sss', $guest, $hash, $name);
    $ins->execute();
}

$strings = load_strings($xlsxDir . '/sharedStrings.xml');
$buildingIds = [];
$result = $conn->query("SELECT id, name FROM buildings WHERE name IN ('Main Bldg','Melloville Bldg')");
while ($building = $result->fetch_assoc()) {
    $buildingIds[$building['name']] = (int)$building['id'];
}
if (!isset($buildingIds['Main Bldg'], $buildingIds['Melloville Bldg'])) {
    fwrite(STDERR, "Main Bldg and Melloville Bldg must exist in the buildings table.\n");
    exit(1);
}
$mainId = $buildingIds['Main Bldg'];
$mellovilleId = $buildingIds['Melloville Bldg'];

$labRoom       = ensure_room($conn, $mainId, 3, 'CLAB', 'Computer Laboratory');
$elecRoom      = ensure_room($conn, $mainId, 3, 'ELAB', 'Electronics Laboratory');
$mellovilleLab = ensure_room($conn, $mellovilleId, 3, 'CLAB', 'Computer Laboratory');
ensure_room($conn, $mellovilleId, 3, 'ELAB', 'Electronics Laboratory');
$toolRoom  = ensure_room($conn, $mainId, 1, 'TOOL', 'Tool Room');

$count = 0;

foreach ([
    [1, $labRoom, 'MAIN-CL-'],
    [2, $mellovilleLab, 'MELLO-CL-'],
] as [$sheetNumber, $roomId, $tagPrefix]) {
    $rows = sheet_rows($xlsxDir . "/worksheets/sheet$sheetNumber.xml", $strings);
    foreach ($rows as $rnum => $row) {
        if ($rnum < 14) continue;
        $id = cell($row, 'B');
        if ($id === '' || stripos($id, 'DATE') !== false || stripos($id, 'Noted') !== false || stripos($id, 'Prepair') !== false) continue;
        if (!preg_match('/^PC/i', $id) && strcasecmp($id, 'PC TEACHER') !== 0) continue;
        $mb = cell($row, 'C');
        $cpu = cell($row, 'D');
        $ram = cell($row, 'E');
        $ssd = cell($row, 'F');
        $hdd = cell($row, 'G');
        $kb  = cell($row, 'H');
        $mon = cell($row, 'I');
        $st  = cell($row, 'J');
        $tag = $tagPrefix . normalize_tag($id);
        $desc = "Motherboard: $mb\nProcessor: $cpu\nRAM: $ram\nSSD: $ssd\nHDD: $hdd\nKeyboard/Mouse: $kb\nMonitor: $mon\nRemarks: $st";
        upsert_item($conn, $usedTags, $roomId, 'Computer Lab ' . $id, $tag, $desc, '', $id, 1, map_condition($st), map_status($st), 'Computer / Laptop');
        $count++;
    }
}

// Sheet 3: Electronics Laboratory
$rows = sheet_rows($xlsxDir . '/worksheets/sheet3.xml', $strings);
$mode = 'pc';
foreach ($rows as $rnum => $row) {
    if ($rnum < 14) continue;
    $inv = cell($row, 'B');
    $item = cell($row, 'C');
    if (strcasecmp($inv, 'Inventory ID') === 0 || strcasecmp($item, 'Item') === 0) {
        $mode = (isset($row['H']) && strcasecmp($row['H'], 'Asset Tag') === 0) ? 'pc' : 'gear';
        continue;
    }
    if ($item === '' && $inv === '') continue;
    if (stripos($inv, 'DATE') !== false) continue;
    if ($mode === 'pc' && $item !== '') {
        $tag = cell($row, 'H') ?: cell($row, 'D') ?: $inv;
        $spec = cell($row, 'E');
        $mon = cell($row, 'F');
        $kb = cell($row, 'G');
        $cond = cell($row, 'I');
        $qty = cell($row, 'J') ?: 1;
        $desc = "ID Name: " . cell($row, 'D') . "\nSpec: $spec\nMonitor: $mon\nKeyboard/Mouse: $kb";
        upsert_item($conn, $usedTags, $elecRoom, $item . ' ' . (cell($row, 'D') ?: $inv), $tag, $desc, '', $inv, $qty, map_condition($cond), map_status($cond), $item);
        $count++;
    } elseif ($item !== '') {
        $tag = cell($row, 'F') ?: $inv;
        $serial = cell($row, 'E');
        $brand = cell($row, 'G');
        $cond = cell($row, 'H');
        $qty = cell($row, 'I') ?: 1;
        $remarks = cell($row, 'J');
        $loc = cell($row, 'D');
        $desc = "Location: $loc\nBrand: $brand\nRemarks: $remarks";
        $name = trim($item . ($brand ? ' (' . $brand . ')' : ''));
        upsert_item($conn, $usedTags, $elecRoom, $name, $tag ?: $inv, $desc, $serial, $inv, $qty, map_condition($cond . ' ' . $remarks), map_status($cond . ' ' . $remarks), $item);
        $count++;
    }
}

// Sheet 4: Office computers (Main Building)
$rows = sheet_rows($xlsxDir . '/worksheets/sheet4.xml', $strings);
$deptFloor = [
    'Information Technology' => [2, 'IT'],
    'College Department' => [2, 'COL'],
    'DEAN' => [2, 'DEAN'],
    'VP OFFICE / GENSERV OFFICE' => [2, 'VPGO'],
    'Board of Trustee' => [3, 'BOT'],
    'Marketing Office' => [1, 'MKT'],
    'Clinic Department' => [1, 'CLIN'],
    'Guidance Department' => [1, 'GUID'],
    'Beed Faculty' => [2, 'BEED'],
    'Library Department' => [1, 'LIB'],
    'Registrar Department' => [1, 'REG'],
    'Admission' => [1, 'ADM'],
    'Treasurey Department' => [1, 'TRES'],
    'SCEA Department' => [2, 'SCEA'],
    'Human Resource Department' => [2, 'HRD'],
    'Account Management office' => [2, 'AMO'],
    'Apparel Office' => [1, 'APP'],
    'Appreal Office' => [1, 'APP'],
    'Senior High School Department' => [2, 'SHS'],
    'Procurement Department' => [1, 'PRO'],
    'STUDENT BODY ORGANIZATION' => [1, 'SBO'],
];
foreach ($rows as $rnum => $row) {
    if ($rnum < 14) continue;
    $inv = cell($row, 'B');
    $dept = cell($row, 'C');
    if ($inv === '' || strcasecmp($inv, 'Inventory ID') === 0 || !preg_match('/[A-Z]/i', $inv)) continue;
    if (stripos($inv, 'Prepair') !== false) continue;
    $tag = cell($row, 'D') ?: $inv;
    $cpu = cell($row, 'E');
    $mb = cell($row, 'F');
    $ram = cell($row, 'G');
    $stor = cell($row, 'H');
    $prn = cell($row, 'I');
    $kb = cell($row, 'J');
    $mon = cell($row, 'L');
    $user = cell($row, 'O');
    $cond = cell($row, 'R');
    $remarks = cell($row, 'U');
    $map = $deptFloor[$dept] ?? [1, strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $dept) ?: 'OFF', 0, 6))];
    $roomId = ensure_room($conn, $mainId, $map[0], $map[1], $dept ?: 'Office');
    $name = trim(($user ? $user . ' workstation' : 'Office computer') . ($dept ? ' (' . $dept . ')' : ''));
    $desc = "Department: $dept\nUser: $user\nCPU/GPU: $cpu\nMotherboard: $mb\nRAM: $ram\nStorage: $stor\nKeyboard/Mouse: $kb\nMonitor: $mon\nCondition: $cond\nRemarks: $remarks";
    upsert_item($conn, $usedTags, $roomId, $name, $tag, $desc, '', $inv, 1, map_condition($cond . ' ' . $remarks), map_status($cond . ' ' . $remarks), $cpu ? 'Computer / Laptop' : 'Others');
    $count++;
    if ($prn !== '' && strcasecmp($prn, 'NONE') !== 0) {
        upsert_item($conn, $usedTags, $roomId, $prn . ' (' . ($dept ?: 'Office') . ')', $inv . '-PRN', "Assigned printer for $user / $dept\nLinked workstation: $tag", '', $inv, 1, map_condition($cond), 'active', 'Printer');
        $count++;
    }
}

// Sheet 5: Tool Room
$rows = sheet_rows($xlsxDir . '/worksheets/sheet5.xml', $strings);
$skip = ['Inventory ID','Column1','Column2','Column3','Column4','Column5','Column6','Column7','Column8','Column9','Layer 1','Layer 2','Layer 3','Layer 4','Layer 5','Layer 6','Layer 7','Remarks','DATE CHECKED','Shelves 1','Shelves 2','Shelves 3','Shelves 4','Shelves 5','Outside of Tool Room'];
foreach ($rows as $rnum => $row) {
    if ($rnum < 15) continue;
    $inv = cell($row, 'B');
    if (stripos($inv, 'DATE') !== false || stripos($inv, 'Prepair') !== false || stripos($inv, 'Noted') !== false) continue;
    if (in_array($inv, $skip, true) || preg_match('/^Shelves/i', $inv)) continue;
    $parts = [];
    foreach ($row as $col => $val) {
        if ($col === 'B') continue;
        if (in_array($val, $skip, true) || preg_match('/^Shelves|^Column/i', $val)) continue;
        if (preg_match('/^\d{5,}$/', $val)) continue;
        $parts[] = $val;
    }
    $tag = preg_match('/^TR-/i', $inv) ? $inv : '';
    if ($tag && $parts) {
        upsert_item($conn, $usedTags, $toolRoom, $parts[0], $tag, implode("\n", $parts), '', $tag, 1, 'good', 'active', $parts[0]);
        $count++;
        array_shift($parts);
        $i = 2;
        foreach ($parts as $extra) {
            upsert_item($conn, $usedTags, $toolRoom, $extra, $tag . '-' . $i, 'Tool Room (with ' . $tag . ')', '', $tag, 1, 'good', 'active', $extra);
            $count++;
            $i++;
        }
    } elseif ($tag) {
        upsert_item($conn, $usedTags, $toolRoom, $tag, $tag, 'Tool Room tagged item', '', $tag, 1, 'good', 'active', $tag);
        $count++;
    } else {
        foreach ($parts as $idx => $extra) {
            upsert_item($conn, $usedTags, $toolRoom, $extra, 'TR-R' . $rnum . '-' . ($idx + 1), 'Tool Room inventory, row ' . $rnum, '', '', 1, 'good', 'active', $extra);
            $count++;
        }
    }
}

echo "Imported/updated $count Main and Melloville Building inventory records.\n";
echo "Assets now: " . $conn->query('SELECT COUNT(*) FROM items')->fetch_row()[0] . "\n";
