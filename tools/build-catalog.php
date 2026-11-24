<?php
/**
 * Rebuilds Data/vn-admin-units.json from the official list (maintainers only, command line).
 *
 * Source: https://danhmuchanhchinh.nso.gov.vn/ — choose level "Xã", set the date, press
 * "Xuất Excel". The exported .xls has one row per ward / commune / special zone with its
 * province code and name.
 *
 * Usage, from the website root (PhpSpreadsheet ships with gp247/core):
 *   php app/GP247/Plugins/AddressVN/tools/build-catalog.php <export.xls> <YYYY-MM-DD>
 *
 * Reads the .xls, writes only Data/vn-admin-units.json, and stops without writing anything
 * when a row cannot be trusted (unexpected header, malformed code, duplicate code, a
 * province with two names).
 *
 * @aidlc-unit plugin-address-vn
 * @aidlc-story US-address-vn-catalog
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

[, $input, $asOf] = $argv + [null, null, null];
if (!$input || !is_file($input) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $asOf)) {
    fwrite(STDERR, "usage: php build-catalog.php <export.xls> <YYYY-MM-DD>\n");
    exit(2);
}
require getcwd() . '/vendor/autoload.php';

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xls');
$reader->setReadDataOnly(true);
$rows = $reader->load($input)->getActiveSheet()->toArray(null, false, false, false);

// WHY: cells of the export contain line breaks inside names ("Phường \nHoàn Kiếm").
$clean = static fn ($value): string => trim((string) preg_replace('/\s+/u', ' ', (string) $value));
$header = array_map($clean, array_shift($rows) ?? []);
if ($header !== ['Mã', 'Tên', 'Tên Tiếng Anh', 'Cấp', 'Nghị định', 'Mã TP', 'Tỉnh / Thành Phố']) {
    fwrite(STDERR, 'Unexpected header: ' . json_encode($header, JSON_UNESCAPED_UNICODE) . "\n");
    exit(1);
}

$provinces = [];
$wardCodes = [];
$errors = [];
foreach ($rows as $i => $row) {
    [$code, $name, , , , $provinceCode, $provinceName] = array_map($clean, $row);
    if ($code === '' && $name === '') {
        continue;
    }
    $line = $i + 2;
    if (!preg_match('/^\d{5}$/', $code) || $name === '' || !preg_match('/^\d{2}$/', $provinceCode) || $provinceName === '') {
        $errors[] = "row $line: " . json_encode($row, JSON_UNESCAPED_UNICODE);
        continue;
    }
    if (isset($wardCodes[$code])) {
        $errors[] = "row $line: duplicate code $code";
        continue;
    }
    $wardCodes[$code] = true;
    $provinces[$provinceCode] ??= ['code' => $provinceCode, 'name' => $provinceName, 'wards' => []];
    if ($provinces[$provinceCode]['name'] !== $provinceName) {
        $errors[] = "row $line: province $provinceCode has two names";
    }
    $provinces[$provinceCode]['wards'][] = ['code' => $code, 'name' => $name];
}
if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
ksort($provinces, SORT_STRING);

$output = dirname(__DIR__) . '/Data/vn-admin-units.json';
file_put_contents($output, json_encode([
    'version' => $asOf,
    'source' => 'Cục Thống kê (General Statistics Office of Viet Nam) — https://danhmuchanhchinh.nso.gov.vn/, commune-level list in force on ' . $asOf,
    'provinces' => array_values($provinces),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");

echo count($provinces), ' provinces, ', count($wardCodes), ' wards → ', $output, PHP_EOL;
