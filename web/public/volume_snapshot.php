<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

const CBOE_TRADABLE_PRODUCTS_URL = 'https://www-api.cboe.com/tradable_products/data/';
const CBOE_MARKET_SHARE_URL = 'https://www-api.cboe.com/us/options/market_share/market/data/?bias=Volume&limit=6&dt=';

/**
 * Return a JSON response and exit.
 */
function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Best-effort GET JSON helper with small timeout.
 */
function fetch_json(string $url, int $timeoutSeconds = 12): ?array
{
    try {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: cboemarkets-volume-snapshot/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    } catch (Throwable) {
        return null;
    }
}

function fmt_volume(float $n): string
{
    if ($n >= 1_000_000_000) {
        return number_format($n / 1_000_000_000, 2) . 'B';
    }
    if ($n >= 1_000_000) {
        return number_format($n / 1_000_000, 2) . 'M';
    }
    if ($n >= 1_000) {
        return number_format($n / 1_000, 2) . 'K';
    }
    return number_format($n, 0);
}

function find_volume_by_root(array $items, string $root): ?float
{
    foreach ($items as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (($row['underlying_root'] ?? null) === $root) {
            return isset($row['volume']) ? (float) $row['volume'] : null;
        }
    }
    return null;
}

function normalize_date_label(string $ymd): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $ymd, new DateTimeZone('UTC'));
    if (!$dt) {
        return $ymd;
    }
    return $dt->format('F j, Y');
}

$cacheDir = dirname(__DIR__) . '/logs/cache';
$cacheFile = $cacheDir . '/volume_snapshot.json';
$today = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d');

if (is_file($cacheFile)) {
    $rawCache = @file_get_contents($cacheFile);
    $cached = $rawCache ? json_decode($rawCache, true) : null;
    if (is_array($cached) && ($cached['cache_date'] ?? null) === $today) {
        respond($cached);
    }
}

$tradable = fetch_json(CBOE_TRADABLE_PRODUCTS_URL);
if (!is_array($tradable) || !isset($tradable['data']) || !is_array($tradable['data'])) {
    if (is_file($cacheFile)) {
        $rawCache = @file_get_contents($cacheFile);
        $cached = $rawCache ? json_decode($rawCache, true) : null;
        if (is_array($cached)) {
            $cached['stale'] = true;
            $cached['stale_reason'] = 'tradable_products_unavailable';
            respond($cached);
        }
    }
    respond(['success' => false, 'error' => 'Unable to fetch snapshot data.'], 502);
}

$equity = is_array($tradable['data']['equity'] ?? null) ? $tradable['data']['equity'] : [];
$volatility = is_array($tradable['data']['volatility'] ?? null) ? $tradable['data']['volatility'] : [];

$spxOptionsVolume = find_volume_by_root($equity, 'SPX');
$vixOptionsVolume = find_volume_by_root($volatility, 'VIX');
$vixFuturesVolume = find_volume_by_root($volatility, 'VX');

$tradingDate = null;
foreach ([$equity, $volatility] as $group) {
    foreach ($group as $row) {
        if (is_array($row) && !empty($row['trading_dt']) && is_string($row['trading_dt'])) {
            $tradingDate = $row['trading_dt'];
            break 2;
        }
    }
}

if ($spxOptionsVolume === null || $vixOptionsVolume === null || $vixFuturesVolume === null || !$tradingDate) {
    if (is_file($cacheFile)) {
        $rawCache = @file_get_contents($cacheFile);
        $cached = $rawCache ? json_decode($rawCache, true) : null;
        if (is_array($cached)) {
            $cached['stale'] = true;
            $cached['stale_reason'] = 'missing_required_fields';
            respond($cached);
        }
    }
    respond(['success' => false, 'error' => 'Missing required snapshot fields.'], 502);
}

$marketShare = fetch_json(CBOE_MARKET_SHARE_URL . rawurlencode($tradingDate));
$industryVolume = null;
if (is_array($marketShare)) {
    $industryVolume = isset($marketShare['data']['total']['integrated'][0])
        ? (float) $marketShare['data']['total']['integrated'][0]
        : null;
}

if ($industryVolume === null) {
    if (is_file($cacheFile)) {
        $rawCache = @file_get_contents($cacheFile);
        $cached = $rawCache ? json_decode($rawCache, true) : null;
        if (is_array($cached)) {
            $cached['stale'] = true;
            $cached['stale_reason'] = 'market_share_unavailable';
            respond($cached);
        }
    }
    respond(['success' => false, 'error' => 'Unable to fetch industry volume.'], 502);
}

$payload = [
    'success' => true,
    'cache_date' => $today,
    'trading_date' => $tradingDate,
    'trading_date_label' => normalize_date_label($tradingDate),
    'updated_at_utc' => gmdate('c'),
    'values' => [
        'spx_options' => (int) round($spxOptionsVolume),
        'vix_options' => (int) round($vixOptionsVolume),
        'vix_futures' => (int) round($vixFuturesVolume),
        'industry_volume' => (int) round($industryVolume),
    ],
    'display' => [
        'spx_options' => fmt_volume($spxOptionsVolume),
        'vix_options' => fmt_volume($vixOptionsVolume),
        'vix_futures' => fmt_volume($vixFuturesVolume),
        'industry_volume' => fmt_volume($industryVolume),
    ],
];

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
@file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_SLASHES));

respond($payload);
