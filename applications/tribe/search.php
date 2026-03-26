<?php
/**
 * Unified Search Endpoint (Deepsearch / Meilisearch)
 *
 * Location : applications/tribe/custom/search.php
 * URL      : http://localhost:12000/custom/search.php
 *
 * ─── Query parameters ────────────────────────────────────────────────────────
 *
 *  q            REQUIRED  Search string.
 *  source       optional  "db" | "files" | "all"  (default: all)
 *  type         optional  Filter DB results to one Tribe type slug.
 *  category     optional  Filter file results: pdf|image|video|text|other
 *  extension    optional  Filter file results by extension, e.g. "pdf"
 *  page         optional  1-based page number (default: 1)
 *  per_page     optional  Results per source, 1-100 (default: 25)
 *  public_only  optional  "true"|"false"  (default: true)
 *
 * ─── Response ────────────────────────────────────────────────────────────────
 *
 *  {
 *    "meta": { "query", "source", "page", "per_page", "total_db",
 *              "total_files", "total", "search_time_ms", "generated_at" },
 *    "db_results":   [ { id, type, slug, updated_on, created_on, modules:{…} } ],
 *    "file_results": [ { filename, path, category, content, _highlights:[…] } ],
 *    "facets":       { "files_category":[…], "files_extension":[…] }
 *  }
 */

// ── Invalidate this file's opcode cache so edits are live without php-fpm restart
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
require  __DIR__ . '/_init.php';

// ── Headers ───────────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Use GET.']);
    exit;
}

// ── Input ─────────────────────────────────────────────────────────────────────
$query = trim($_GET['q'] ?? '');
if ($query === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Parameter "q" is required.']);
    exit;
}

$source    = in_array($_GET['source'] ?? 'all', ['db','files','all'], true)
                 ? ($_GET['source'] ?? 'all') : 'all';
$type      = preg_replace('/[^a-z0-9_\-]/i', '', $_GET['type']      ?? '');
$category  = preg_replace('/[^a-z]/i',       '', $_GET['category']  ?? '');
$extension = preg_replace('/[^a-z0-9]/i',    '', $_GET['extension'] ?? '');
$page      = max(1,   (int)($_GET['page']     ?? 1));
$perPage   = min(100, max(1, (int)($_GET['per_page'] ?? 25)));

// ── Env / config helpers ──────────────────────────────────────────────────────
function search_parse_env(string $key): ?string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];

    $val = getenv($key);
    if ($val !== false) { $cache[$key] = $val; return $val; }

    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) { $cache[$key] = null; return null; }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $eq = strpos($line, '=');
        if ($eq === false) continue;
        $k = trim(substr($line, 0, $eq));
        $v = trim(substr($line, $eq + 1));
        if ($k !== $key) continue;

        if (strlen($v) >= 2 &&
            (($v[0] === '"'  && ($cp = strpos($v, '"', 1))  !== false) ||
             ($v[0] === "'"  && ($cp = strpos($v, "'", 1)) !== false))) {
            $cache[$key] = substr($v, 1, $cp - 1);
        } else {
            if (($cp = strpos($v, ' #')) !== false) $v = substr($v, 0, $cp);
            $cache[$key] = trim($v);
        }
        return $cache[$key];
    }
    $cache[$key] = null;
    return null;
}

$DS_KEY        = search_parse_env('DEEPSEARCH_API_KEY') ?: 'xyz';
$DS_HOST       = search_parse_env('DEEPSEARCH_HOST')
                    ?: ((search_parse_env('PROJECT_NAME') ?: 'tribe') . '_deepsearch');
$DS_PORT       = search_parse_env('DEEPSEARCH_PORT') ?: '8108';
$DS_FILES_COLL = search_parse_env('COLLECTION_NAME') ?: 'files';

// ── Read the three controlling .env flags ────────────────────────────────────
$envDeepsearchEnabled = (strtolower(trim(search_parse_env('DEEPSEARCH_ENABLED')                    ?: 'true')) !== 'false');
$envShowPublicOnly    = (strtolower(trim(search_parse_env('DEEPSEARCH_SHOW_PUBLIC_OBJECTS_ONLY')   ?: 'true')) !== 'false');
$envHideUploads       = !(strtolower(trim(search_parse_env('DEEPSEARCH_SHOW_UPLOADS_FOLDER_RESULTS') ?: 'true')) !== 'false');

$paramPublicOnly = (($_GET['public_only'] ?? 'true') !== 'false');
$publicOnly      = $envShowPublicOnly || $paramPublicOnly;

// ── Meilisearch HTTP helper ──────────────────────────────────────────────────
function ds_search(string $indexUid, array $body, string $host, string $port, string $key): array
{
    $url = "http://{$host}:{$port}/indexes/{$indexUid}/search";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$key}",
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body),
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $err) return ['_error' => $err, '_code' => 0];
    $d = json_decode($raw, true);
    if ($d === null) return ['_error' => 'Invalid JSON', '_code' => $code];
    $d['_code'] = $code;
    return $d;
}

function ds_multi_search(array $queries, string $host, string $port, string $key): array
{
    $url = "http://{$host}:{$port}/multi-search";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$key}",
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode(['queries' => $queries]),
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $err) return ['_error' => $err, '_code' => 0];
    $d = json_decode($raw, true);
    if ($d === null) return ['_error' => 'Invalid JSON', '_code' => $code];
    $d['_code'] = $code;
    return $d;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1a. DATABASE SEARCH — Deepsearch/Meilisearch (when DEEPSEARCH_ENABLED=true)
// ─────────────────────────────────────────────────────────────────────────────
$dbResults = [];
$dbTotal   = 0;
$dbTimeMs  = 0;

if ($envDeepsearchEnabled && in_array($source, ['db', 'all'])) {
    $t0 = microtime(true);
    try {
        $ds = new \Tribe\Deepsearch();

        $searchOptions = [
            'per_page'        => $perPage,
            'page'            => $page,
            'show_public_only'=> $publicOnly,
            'query_by'        => 'search_content',
        ];
        if ($type) {
            $searchOptions['type'] = $type;
        }

        $result = $ds->search($query, $searchOptions);

        if ($result && isset($result['found'])) {
            $dbTotal = (int)$result['found'];

            foreach ($result['hits'] ?? [] as $hit) {
                $doc = $hit['document'] ?? [];
                // Strip sensitive keys
                foreach (['password_md5','mysql_access_log','mysql_activity_log'] as $sk) {
                    unset($doc[$sk]);
                }
                $dbResults[] = [
                    'id'         => $doc['id'],
                    'type'       => $doc['type']       ?? null,
                    'slug'       => $doc['slug']       ?? null,
                    'updated_on' => $doc['updated_on'] ?? null,
                    'created_on' => $doc['created_on'] ?? null,
                    'modules'    => $doc,
                    '_source'    => 'deepsearch',
                    '_highlights'=> [],
                ];
            }
        }
    } catch (\Throwable $e) {
        error_log("search.php Deepsearch DB search error: " . $e->getMessage());
    }

    $dbTimeMs = (int)round((microtime(true) - $t0) * 1000);
}

// ─────────────────────────────────────────────────────────────────────────────
// 1b. DATABASE SEARCH — Direct MySQL (when DEEPSEARCH_ENABLED=false)
// ─────────────────────────────────────────────────────────────────────────────
if (!$envDeepsearchEnabled && in_array($source, ['db','all'])) {
    $t0 = microtime(true);

    try {
        $mysql = new \Tribe\MySQL();
        $link  = $mysql->databaseLink;

        $escaped = mysqli_real_escape_string($link, strtolower($query));
        $like    = '%' . $escaped . '%';

        $where = [];

        if ($type) {
            $st      = mysqli_real_escape_string($link, $type);
            $where[] = "`type` = '{$st}'";
        } else {
            $where[] = "`type` NOT IN ('webapp','deleted_record','search_sync_failed','apikey_record')";
        }

        if ($publicOnly && $type !== 'user') {
            $where[] = "`content_privacy` = 'public'";
        }

        $where[] = "LOWER(`content`) LIKE '{$like}'";

        $whereStr = implode(' AND ', $where);

        $countRow = $mysql->executeSQL("SELECT COUNT(`id`) AS `cnt` FROM `data` WHERE {$whereStr}");
        $dbTotal  = (int)($countRow[0]['cnt'] ?? 0);

        if ($dbTotal > 0) {
            $offset  = ($page - 1) * $perPage;
            $idsRows = $mysql->executeSQL(
                "SELECT `id` FROM `data` WHERE {$whereStr} ORDER BY `updated_on` DESC LIMIT {$offset}, {$perPage}"
            );

            if ($idsRows) {
                $core    = new \Tribe\Core();
                $objects = $core->getObjects($idsRows);

                if ($objects) {
                    foreach ($idsRows as $row) {
                        $obj = $objects[$row['id']] ?? null;
                        if (!$obj) continue;

                        foreach (['password_md5','mysql_access_log','mysql_activity_log'] as $sk) {
                            unset($obj[$sk]);
                        }

                        $dbResults[] = [
                            'id'         => $obj['id'],
                            'type'       => $obj['type']       ?? null,
                            'slug'       => $obj['slug']       ?? null,
                            'updated_on' => $obj['updated_on'] ?? null,
                            'created_on' => $obj['created_on'] ?? null,
                            'modules'    => $obj,
                            '_source'    => 'db',
                        ];
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        error_log("search.php MySQL DB search error: " . $e->getMessage());
    }

    $dbTimeMs = (int)round((microtime(true) - $t0) * 1000);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. FILE SEARCH — direct Meilisearch HTTP, always live
// ─────────────────────────────────────────────────────────────────────────────
$fileResults = [];
$fileTotal   = 0;
$fileTimeMs  = 0;
$facets      = [];

if (!$envHideUploads && $envDeepsearchEnabled && in_array($source, ['files','all'])) {
    $fileFilters = [];
    if ($category)  $fileFilters[] = "category = \"{$category}\"";
    if ($extension) $fileFilters[] = "extension = \"{$extension}\"";

    $fileBody = [
        'q'      => $query,
        'limit'  => $perPage,
        'offset' => ($page - 1) * $perPage,
        'attributesToHighlight' => ['filename', 'content', 'title', 'description'],
    ];

    if (!empty($fileFilters)) {
        $fileBody['filter'] = implode(' AND ', $fileFilters);
    }

    $t0       = microtime(true);
    $fileResp = ds_search($DS_FILES_COLL, $fileBody, $DS_HOST, $DS_PORT, $DS_KEY);
    $fileTimeMs = (int)round((microtime(true) - $t0) * 1000);

    if (empty($fileResp['_error']) && ($fileResp['_code'] ?? 0) < 400) {
        $fileTotal = (int)($fileResp['estimatedTotalHits'] ?? $fileResp['totalHits'] ?? 0);

        foreach ($fileResp['hits'] ?? [] as $hit) {
            $doc = $hit;
            $doc['_highlights'] = $hit['_formatted'] ?? [];
            $doc['_source']     = 'files';
            unset($doc['_formatted']);
            $fileResults[]      = $doc;
        }
    } else {
        $errMsg = $fileResp['_error'] ?? ('HTTP ' . ($fileResp['_code'] ?? '?'));
        error_log("search.php files search error: " . $errMsg);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Response
// ─────────────────────────────────────────────────────────────────────────────
$dbBackend = 'none';
if (!empty($dbResults)) {
    $dbBackend = $dbResults[0]['_source'] ?? 'unknown';
}

$response = [
    'meta' => [
        'query'          => $query,
        'source'         => $source,
        'page'           => $page,
        'per_page'       => $perPage,
        'public_only'    => $publicOnly,
        'total_db'       => $dbTotal,
        'total_files'    => $fileTotal,
        'total'          => $dbTotal + $fileTotal,
        'search_time_ms' => max($dbTimeMs, $fileTimeMs),
        'db_backend'     => $dbBackend,  // "deepsearch" | "mysql" | "none"
        'generated_at'   => date('Y-m-d H:i:s'),
        'env' => [
            'deepsearch_enabled'             => $envDeepsearchEnabled,
            'show_public_objects_only'       => $envShowPublicOnly,
            'show_uploads_folder_results'    => !$envHideUploads,
        ],
    ],
    'db_results'   => $dbResults,
    'file_results' => $fileResults,
];

if (!empty($facets)) {
    $response['facets'] = $facets;
}

$activeFilters = array_filter([
    'type'      => $type      ?: null,
    'category'  => $category  ?: null,
    'extension' => $extension ?: null,
]);
if (!empty($activeFilters)) {
    $response['meta']['filters'] = $activeFilters;
}

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
