<?php
session_start();
if (!isset($_SESSION['email']) || trim($_SESSION['role']) !== 'planning_officer') {
    header("Location: ../../auth/login.php");
    exit();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/Prototype/connect.php';

$action  = $_GET['action'] ?? 'download';
$month   = trim($_GET['month'] ?? '');
$year    = trim($_GET['year'] ?? '');
$quarter = trim($_GET['quarter'] ?? '');

if ($month !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = '';
}
if ($year !== '' && !preg_match('/^\d{4}$/', $year)) {
    $year = '';
}
if ($quarter !== '' && !preg_match('/^[1-4]$/', $quarter)) {
    $quarter = '';
}

$where = " WHERE status = 'approved'";
if ($month !== '') {
    $where .= " AND DATE_FORMAT(travel_date, '%Y-%m') = '" . $conn->real_escape_string($month) . "'";
}
if ($quarter !== '') {
    $where .= " AND QUARTER(travel_date) = " . (int)$quarter;
}
if ($year !== '') {
    $where .= " AND YEAR(travel_date) = " . (int)$year;
}

// driver is the name the officer picked on the travel order form when they
// approved/printed, and is what the DRIVER column reports.
$rows = [];
$result = $conn->query("SELECT id, ticket_no, requested_by, driver, passengers, travel_date, destination, purpose
                        FROM vehicle_requests$where
                        ORDER BY travel_date ASC, id ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

if ($action === 'months') {
    $years = [];
    $yResult = $conn->query("SELECT YEAR(travel_date) AS y, COUNT(*) AS total
                             FROM vehicle_requests
                             WHERE status = 'approved' AND travel_date IS NOT NULL
                             GROUP BY y
                             ORDER BY y DESC");
    if ($yResult) {
        while ($y = $yResult->fetch_assoc()) {
            $years[] = [
                'value' => (string)$y['y'],
                'label' => (string)$y['y'],
                'count' => (int)$y['total']
            ];
        }
    }

    $yearFilter = $year !== '' ? ' AND YEAR(travel_date) = ' . (int)$year : '';
    $quarterFilter = $quarter !== '' ? ' AND QUARTER(travel_date) = ' . (int)$quarter : '';

    $quarterNames = [
        1 => 'Q1 (Jan - Mar)',
        2 => 'Q2 (Apr - Jun)',
        3 => 'Q3 (Jul - Sep)',
        4 => 'Q4 (Oct - Dec)'
    ];
    $quarters = [];
    $qResult = $conn->query("SELECT QUARTER(travel_date) AS q, COUNT(*) AS total
                             FROM vehicle_requests
                             WHERE status = 'approved' AND travel_date IS NOT NULL$yearFilter
                             GROUP BY q
                             ORDER BY q ASC");
    if ($qResult) {
        while ($q = $qResult->fetch_assoc()) {
            $qNum = (int)$q['q'];
            if (!isset($quarterNames[$qNum])) {
                continue;
            }
            $quarters[] = [
                'value' => (string)$qNum,
                'label' => $quarterNames[$qNum],
                'count' => (int)$q['total']
            ];
        }
    }

    $months = [];
    $mResult = $conn->query("SELECT DATE_FORMAT(travel_date, '%Y-%m') AS ym, COUNT(*) AS total
                             FROM vehicle_requests
                             WHERE status = 'approved' AND travel_date IS NOT NULL$yearFilter$quarterFilter
                             GROUP BY ym
                             ORDER BY ym DESC");
    if ($mResult) {
        while ($m = $mResult->fetch_assoc()) {
            $months[] = [
                'value' => $m['ym'],
                'label' => date('F Y', strtotime($m['ym'] . '-01')),
                'count' => (int)$m['total']
            ];
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'years' => $years, 'quarters' => $quarters, 'months' => $months, 'total' => count($rows)]);
    $conn->close();
    exit;
}

if ($action === 'preview') {
    $label = 'All Months';
    if ($month !== '') {
        $label = date('F Y', strtotime($month . '-01'));
    } elseif ($quarter !== '') {
        $label = 'Q' . $quarter . ($year !== '' ? ' ' . $year : '');
    } elseif ($year !== '') {
        $label = 'Year ' . $year;
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'month' => $month,
        'quarter' => $quarter,
        'year' => $year,
        'label' => $label,
        'rows' => array_map(function ($r) {
            $r['travel_month'] = !empty($r['travel_date']) ? date('Y-m', strtotime($r['travel_date'])) : 'undated';
            $r['travel_date'] = !empty($r['travel_date']) ? date('M j, Y', strtotime($r['travel_date'])) : '';
            return $r;
        }, $rows)
    ]);
    $conn->close();
    exit;
}

if (!class_exists('ZipArchive')) {
    header('Content-Type: text/plain');
    echo 'The PHP zip extension is required to export the summary.';
    $conn->close();
    exit;
}

$months = [];
foreach ($rows as $r) {
    $key = $r['travel_date'] ? date('Y-m', strtotime($r['travel_date'])) : 'undated';
    $months[$key][] = $r;
}
ksort($months);

$sheetRows = '';
$rowNum = 1;

$sheetRows .= '<row r="1" ht="22" customHeight="1">'
    . '<c r="A1" s="4" t="inlineStr"><is><t>VEHICLE REQUEST SUMMARY</t></is></c></row>';
$rowNum++;

$periodLabel = 'All Months';
if ($month !== '') {
    $periodLabel = date('F Y', strtotime($month . '-01'));
} elseif ($quarter !== '') {
    $periodLabel = 'Q' . $quarter . ($year !== '' ? ' ' . $year : '');
} elseif ($year !== '') {
    $periodLabel = 'Year ' . $year;
}
$sheetRows .= '<row r="2">'
    . '<c r="A2" s="0" t="inlineStr"><is><t>' . xlsxEscape('Period: ' . $periodLabel) . '</t></is></c>'
    . '<c r="B2" s="0" t="inlineStr"><is><t>' . xlsxEscape('Approved requests: ' . count($rows)) . '</t></is></c></row>';
$rowNum++;

$sheetRows .= '<row r="3" ht="20" customHeight="1">';
// Must stay in sync with SUMMARY_HEADERS in vehicle_requested.php - the downloaded
// sheet and the on-screen summary table are the same report.
$headers = ['TICKET NUMBER', 'REQUESTED BY', 'DATE OF TRAVEL', 'DRIVER', 'PASSENGER', 'DESTINATION', 'PURPOSE'];
foreach ($headers as $i => $header) {
    $col = xlsxColName($i);
    $sheetRows .= '<c r="' . $col . $rowNum . '" s="1" t="inlineStr"><is><t>' . xlsxEscape($header) . '</t></is></c>';
}
$sheetRows .= '</row>';
$rowNum++;

foreach ($months as $key => $monthRows) {
    $label = $key === 'undated' ? 'Undated' : date('F Y', strtotime($key . '-01'));
    $sheetRows .= '<row r="' . $rowNum . '" ht="18" customHeight="1">';
    $sheetRows .= '<c r="A' . $rowNum . '" s="2" t="inlineStr"><is><t>'
        . xlsxEscape($label . '  (' . count($monthRows) . ')') . '</t></is></c>';
    for ($i = 1; $i < count($headers); $i++) {
        $sheetRows .= '<c r="' . xlsxColName($i) . $rowNum . '" s="2"/>';
    }
    $sheetRows .= '</row>';
    $rowNum++;

    foreach ($monthRows as $r) {
        $dateValue = $r['travel_date'] ? date('Y-m-d', strtotime($r['travel_date'])) : '';
        // Trip Ticket is the per-travel-year sequence, zero-padded to 001, 002 ...
        $ticketNo = ($r['ticket_no'] === null || $r['ticket_no'] === '')
            ? '---'
            : sprintf('%03d', (int)$r['ticket_no']);
        $sheetRows .= '<row r="' . $rowNum . '">';
        $sheetRows .= '<c r="A' . $rowNum . '" s="0" t="inlineStr"><is><t>' . xlsxEscape($ticketNo) . '</t></is></c>';
        $sheetRows .= '<c r="B' . $rowNum . '" s="0" t="inlineStr"><is><t>' . xlsxEscape($r['requested_by']) . '</t></is></c>';
        if ($dateValue !== '') {
            // Numeric cells must hold an Excel serial (days since 1899-12-30), not an ISO string.
            $serial = (int)floor(strtotime($dateValue . ' 00:00:00 UTC') / 86400) + 25569;
            $sheetRows .= '<c r="C' . $rowNum . '" s="3"><v>' . $serial . '</v></c>';
        } else {
            $sheetRows .= '<c r="C' . $rowNum . '" s="0"/>';
        }
        // An unassigned driver shows as --- rather than a blank, so a missing name
        // is never mistaken for an empty cell in the report.
        $driverValue = ($r['driver'] === null || $r['driver'] === '') ? '---' : $r['driver'];
        $sheetRows .= '<c r="D' . $rowNum . '" s="0" t="inlineStr"><is><t>' . xlsxEscape($driverValue) . '</t></is></c>';
        $sheetRows .= '<c r="E' . $rowNum . '" s="0" t="inlineStr"><is><t>' . xlsxEscape($r['passengers']) . '</t></is></c>';
        $sheetRows .= '<c r="F' . $rowNum . '" s="0" t="inlineStr"><is><t>' . xlsxEscape($r['destination']) . '</t></is></c>';
        $sheetRows .= '<c r="G' . $rowNum . '" s="0" t="inlineStr"><is><t>' . xlsxEscape($r['purpose']) . '</t></is></c>';
        $sheetRows .= '</row>';
        $rowNum++;
    }
}

$sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<sheetPr><outlinePr summaryBelow="1" summaryRight="1"/></sheetPr>'
    . '<dimension ref="A1:G' . max(1, $rowNum - 1) . '"/>'
    . '<sheetViews><sheetView workbookViewId="0">'
    . '<pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/>'
    . '</sheetView></sheetViews>'
    . '<sheetFormatPr defaultRowHeight="15"/>'
    . '<cols>'
    . '<col min="1" max="1" width="14" customWidth="1"/>'
    . '<col min="2" max="2" width="24" customWidth="1"/>'
    . '<col min="3" max="3" width="16" customWidth="1"/>'
    . '<col min="4" max="4" width="20" customWidth="1"/>'
    . '<col min="5" max="5" width="16" customWidth="1"/>'
    . '<col min="6" max="6" width="28" customWidth="1"/>'
    . '<col min="7" max="7" width="40" customWidth="1"/>'
    . '</cols>'
    . '<sheetData>' . $sheetRows . '</sheetData>'
    . '<autoFilter ref="A3:G3"/>'
    . '</worksheet>';

$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<numFmts count="1"><numFmt numFmtId="164" formatCode="mmm d, yyyy"/></numFmts>'
    . '<fonts count="3">'
    . '<font><sz val="11"/><name val="Calibri"/></font>'
    . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
    . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
    . '</fonts>'
    . '<fills count="3">'
    . '<fill><patternFill patternType="none"/></fill>'
    . '<fill><patternFill patternType="gray125"/></fill>'
    . '<fill><patternFill patternType="solid"><fgColor rgb="FF1A4D2E"/><bgColor indexed="64"/></patternFill></fill>'
    . '</fills>'
    . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
    . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="5">'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
    . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
    . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
    . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
    . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
    . '</cellXfs>'
    . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
    . '</styleSheet>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . '</Types>';

$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '</Relationships>';

$workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets><sheet name="Vehicle Request Summary" sheetId="1" r:id="rId1"/></sheets>'
    . '</workbook>';

$workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>';

$filename = 'vehicle_request_summary';
if ($month !== '') {
    $filename .= '_' . $month;
} elseif ($quarter !== '') {
    $filename .= '_Q' . $quarter . ($year !== '' ? '_' . $year : '');
} elseif ($year !== '') {
    $filename .= '_' . $year;
}
$filename .= '_' . date('Ymd_His') . '.xlsx';

$tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
    header('Content-Type: text/plain');
    echo 'Failed to create the Excel file.';
    $conn->close();
    exit;
}
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rootRels);
$zip->addFromString('xl/workbook.xml', $workbookXml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
$zip->addFromString('xl/styles.xml', $stylesXml);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
$zip->close();

$conn->close();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-store');
readfile($tmpFile);
unlink($tmpFile);
exit;

function xlsxEscape($value) {
    $value = (string)$value;
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function xlsxColName($index) {
    $name = '';
    $index++;
    while ($index > 0) {
        $rem = ($index - 1) % 26;
        $name = chr(65 + $rem) . $name;
        $index = (int)(($index - $rem) / 26);
    }
    return $name;
}
