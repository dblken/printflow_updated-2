<?php
/**
 * Philippine Address API (PSGC)
 * Shared endpoint for province/city/barangay cascading (used by profile, branches, etc.)
 */
require_once __DIR__ . '/../includes/api_header.php';
require_once __DIR__ . '/../includes/auth.php';

require_role(['Admin', 'Manager', 'Owner']);

$action = $_GET['address_action'] ?? '';
if ($action === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

$fetchJson = static function (string $url): array {
    $cachePath = __DIR__ . '/../tmp/psgc_cache_' . md5($url) . '.json';
    if (file_exists($cachePath) && (time() - filemtime($cachePath) < 86400)) {
        return json_decode(file_get_contents($cachePath), true);
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $httpCode >= 400) {
            throw new RuntimeException($err ?: ('Address data request failed (' . $httpCode . ')'));
        }
    } else {
        $context = stream_context_create([
            'http' => ['method' => 'GET', 'header' => "Accept: application/json\r\n", 'timeout' => 20]
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) throw new RuntimeException('Unable to fetch address data.');
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) throw new RuntimeException('Invalid address dataset response.');

    if (!is_dir(__DIR__ . '/../tmp')) mkdir(__DIR__ . '/../tmp', 0777, true);
    file_put_contents($cachePath, $body);

    return $decoded;
};

try {
    $base = 'https://psgc.gitlab.io/api';
    if ($action === 'provinces') {
        $rows = $fetchJson($base . '/provinces/');
        $data = array_map(static fn($r) => ['code' => (string)($r['code'] ?? ''), 'name' => (string)($r['name'] ?? '')], $rows);
        usort($data, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    if ($action === 'cities') {
        $provinceCode = preg_replace('/[^0-9]/', '', (string)($_GET['province_code'] ?? ''));
        if ($provinceCode === '') throw new RuntimeException('Province code is required.');
        $rows = $fetchJson($base . '/provinces/' . rawurlencode($provinceCode) . '/cities-municipalities/');
        $data = array_map(static fn($r) => ['code' => (string)($r['code'] ?? ''), 'name' => (string)($r['name'] ?? '')], $rows);
        usort($data, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    if ($action === 'barangays') {
        $cityCode = preg_replace('/[^0-9]/', '', (string)($_GET['city_code'] ?? ''));
        if ($cityCode === '') throw new RuntimeException('City/Municipality code is required.');
        $rows = $fetchJson($base . '/cities-municipalities/' . rawurlencode($cityCode) . '/barangays/');
        $data = array_map(static fn($r) => ['code' => (string)($r['code'] ?? ''), 'name' => (string)($r['name'] ?? '')], $rows);
        usort($data, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
    throw new RuntimeException('Invalid address action.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
