<?php
/**
 * Unified Search Endpoint
 *
 * Location : applications/tribe/custom/search.php
 * URL      : http://localhost:12000/custom/search.php
 *
 * ─── Why this file doesn't go through Core::searchObjects ────────────────────
 *
 *  1. TYPESENSE_ENABLED=false in .env disables ALL Typesense syncing inside
 *     Core (pushObject, deleteObject, etc.) — so tribe_{type} collections are
 *     never populated. Searching through Core::searchObjects → Typesense would
 *     always return empty.  We query MySQL directly instead, which is always
 *     current.
 *
 *  2. Core::searchObjectsDatabase() passes type=null to getIDs(), which
 *     generates WHERE type='null' — a literal string that never matches.
 *     We build the query ourselves to avoid that bug.
 *
 *  3. The file/uploads index is maintained by index_files.php via the inotify
 *     watcher — completely outside Core. We query that Typesense collection
 *     directly over HTTP, same as status.php does.
 *
 * ─── Stale results / OPcache ─────────────────────────────────────────────────
 *
 *  php-fpm caches compiled opcodes. File changes on disk are NOT seen until
 *  either the TTL expires or OPcache is explicitly cleared.
 *  This file calls opcache_invalidate() on itself at startup so any edit to
 *  search.php is live immediately without restarting php-fpm.
 *  DB queries always go to MySQL (never cached here), and the Typesense HTTP
 *  call is a live network request — so both sources are always fresh.
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
$initFile = null;
$dir = __DIR__;
for ($i = 0; $i < 5; $i++) {
    if (file_exists($dir . '/_init.php')) { $initFile = $dir . '/_init.php'; break; }
    $dir = dirname($dir);
}
if (!$initFile) {
    http_response_code(500);
    echo json_encode(['error' => '_init.php not found']);
    exit;
}
require $initFile;

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
// $publicOnly is resolved after env flags are read below

// ── Env / config helpers ──────────────────────────────────────────────────────
function search_parse_env(string $key): ?string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];

    $val = getenv($key);
    if ($val !== false) { $cache[$key] = $val; return $val; }

    $envFile = __DIR__ . '/../.env';
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

$TS_KEY        = search_parse_env('TYPESENSE_API_KEY') ?: 'xyz';
$TS_HOST       = search_parse_env('TYPESENSE_HOST')
                    ?: ((search_parse_env('PROJECT_NAME') ?: 'tribe') . '_typesense');
$TS_PORT       = search_parse_env('TYPESENSE_PORT') ?: '8108';
$TS_FILES_COLL = search_parse_env('COLLECTION_NAME') ?: 'files';

// ── Read the three controlling .env flags ─────────────────────────────────────
// TYPESENSE_ENABLED                    – whether Typesense is used for DB objects
// TYPESENSE_SHOW_PUBLIC_OBJECTS_ONLY   – hide non-public DB objects when true
// TYPESENSE_HIDE_UPLOADS_FOLDER_RESULTS – suppress files/uploads results when true
$envTypesenseEnabled = (strtolower(trim(search_parse_env('TYPESENSE_ENABLED')                    ?: 'true')) !== 'false');
$envShowPublicOnly   = (strtolower(trim(search_parse_env('TYPESENSE_SHOW_PUBLIC_OBJECTS_ONLY')   ?: 'true')) !== 'false');
$envHideUploads      = (strtolower(trim(search_parse_env('TYPESENSE_HIDE_UPLOADS_FOLDER_RESULTS') ?: 'true')) !== 'false');

// Resolve effective public-only flag.
// The ?public_only param can tighten visibility beyond the env default,
// but cannot loosen it when the env mandates public-only.
//
//   env=true,  param=true  → true   (env: public-only, param agrees)
//   env=true,  param=false → true   (env overrides – cannot be loosened by request)
//   env=false, param=true  → true   (caller explicitly wants public-only)
//   env=false, param=false → false  (both agree: show all content)
$paramPublicOnly = (($_GET['public_only'] ?? 'true') !== 'false');
$publicOnly      = $envShowPublicOnly || $paramPublicOnly;

// ── Typesense HTTP helper ─────────────────────────────────────────────────────
function ts_get(string $path, array $params, string $host, string $port, string $key): array
{
    $url = "http://{$host}:{$port}{$path}?" . http_build_query($params);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ["X-TYPESENSE-API-KEY: {$key}"],
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
// 1a. DATABASE SEARCH — Typesense (when TYPESENSE_ENABLED=true)
// ─────────────────────────────────────────────────────────────────────────────
$dbResults = [];
$dbTotal   = 0;
$dbTimeMs  = 0;

if ($envTypesenseEnabled && in_array($source, ['db', 'all'])) {
    $t0 = microtime(true);
    try {
        $ts = new \Tribe\Typesense();

        $searchOptions = [
            'per_page'        => $perPage,
            'page'            => $page,
            'show_public_only'=> $publicOnly,
            'query_by'        => 'search_content',
        ];
        if ($type) {
            $searchOptions['type'] = $type;
        }

        $result = $ts->search($query, $searchOptions);

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
                    '_source'    => 'typesense',
                    '_highlights'=> $hit['highlights'] ?? [],
                ];
            }
        }
    } catch (\Throwable $e) {
        error_log("search.php Typesense DB search error: " . $e->getMessage());
    }

    $dbTimeMs = (int)round((microtime(true) - $t0) * 1000);
}

// ─────────────────────────────────────────────────────────────────────────────
// 1b. DATABASE SEARCH — Direct MySQL (when TYPESENSE_ENABLED=false)
// ─────────────────────────────────────────────────────────────────────────────
// Uses the `type` and `content_privacy` real columns (not JSON path) for the
// type/privacy filter — these are indexed and fast. The LOWER(content) LIKE
// scan covers all JSON field values in one pass.
// ─────────────────────────────────────────────────────────────────────────────
if (!$envTypesenseEnabled && in_array($source, ['db','all'])) {
    $t0 = microtime(true);

    try {
        $mysql = new \Tribe\MySQL();
        $link  = $mysql->databaseLink;

        // Sanitise query for LIKE — search the entire JSON blob.
        // Lower-case both sides so the match is always case-insensitive,
        // regardless of the column's collation.
        $escaped = mysqli_real_escape_string($link, strtolower($query));
        $like    = '%' . $escaped . '%';

        // ── WHERE clauses ──────────────────────────────────────────────────
        $where = [];

        // Type filter
        if ($type) {
            $st      = mysqli_real_escape_string($link, $type);
            $where[] = "`type` = '{$st}'";
        } else {
            // Exclude internal/system types that should never surface in search
            $where[] = "`type` NOT IN ('webapp','deleted_record','search_sync_failed','apikey_record')";
        }

        // Public-only (skip for user type — Core does the same)
        if ($publicOnly && $type !== 'user') {
            $where[] = "`content_privacy` = 'public'";
        }

        // Full content search — matches any JSON field value (case-insensitive)
        $where[] = "LOWER(`content`) LIKE '{$like}'";

        $whereStr = implode(' AND ', $where);

        // ── Count ──────────────────────────────────────────────────────────
        $countRow = $mysql->executeSQL("SELECT COUNT(`id`) AS `cnt` FROM `data` WHERE {$whereStr}");
        $dbTotal  = (int)($countRow[0]['cnt'] ?? 0);

        // ── Paginated fetch ────────────────────────────────────────────────
        if ($dbTotal > 0) {
            $offset  = ($page - 1) * $perPage;
            $idsRows = $mysql->executeSQL(
                "SELECT `id` FROM `data` WHERE {$whereStr} ORDER BY `updated_on` DESC LIMIT {$offset}, {$perPage}"
            );

            if ($idsRows) {
                $core    = new \Tribe\Core();
                $objects = $core->getObjects($idsRows);

                if ($objects) {
                    // Preserve ORDER BY updated_on from the ID query
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
// 2. FILE SEARCH — direct Typesense HTTP, always live
// ─────────────────────────────────────────────────────────────────────────────
// TYPESENSE_HIDE_UPLOADS_FOLDER_RESULTS=true  → skip this section entirely.
// The inotify watcher maintains the `files` collection independently of
// TYPESENSE_ENABLED, so the HTTP query always reflects the current state of
// the uploads folder.
// ─────────────────────────────────────────────────────────────────────────────
$fileResults = [];
$fileTotal   = 0;
$fileTimeMs  = 0;
$facets      = [];

if (!$envHideUploads && in_array($source, ['files','all'])) {
    $fileFilters = [];
    if ($category)  $fileFilters[] = "category:={$category}";
    if ($extension) $fileFilters[] = "extension:={$extension}";

    $fileParams = [
        'q'                           => $query,
        'query_by'                    => 'filename,content,title,description,author,tags',
        'per_page'                    => $perPage,
        'page'                        => $page,
        'num_typos'                   => 2,
        'highlight_full_fields'       => 'filename,content,title,description',
        'highlight_affix_num_tokens'  => 4,
        'snippet_threshold'           => 30,
        'facet_by'                    => 'category,extension',
    ];

    if (!empty($fileFilters)) {
        $fileParams['filter_by'] = implode(' && ', $fileFilters);
    }

    $t0       = microtime(true);
    $fileResp = ts_get(
        "/collections/{$TS_FILES_COLL}/documents/search",
        $fileParams,
        $TS_HOST, $TS_PORT, $TS_KEY
    );
    $fileTimeMs = (int)round((microtime(true) - $t0) * 1000);

    if (empty($fileResp['_error']) && ($fileResp['_code'] ?? 0) < 400) {
        $fileTotal = (int)($fileResp['found'] ?? 0);

        foreach ($fileResp['hits'] ?? [] as $hit) {
            $doc = $hit['document'] ?? [];
            $doc['_highlights'] = $hit['highlights'] ?? [];
            $doc['_source']     = 'files';
            $fileResults[]      = $doc;
        }

        foreach ($fileResp['facet_counts'] ?? [] as $fc) {
            $facets['files_' . ($fc['field_name'] ?? 'unknown')] = $fc['counts'] ?? [];
        }
    } else {
        $errMsg = $fileResp['_error'] ?? ('HTTP ' . ($fileResp['_code'] ?? '?'));
        error_log("search.php files search error: " . $errMsg);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Response
// ─────────────────────────────────────────────────────────────────────────────
// Determine which backend actually served DB results (for meta transparency)
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
        'db_backend'     => $dbBackend,  // "typesense" | "mysql" | "none"
        'generated_at'   => date('Y-m-d H:i:s'),
        // Active env-level visibility flags (for debugging / transparency)
        'env' => [
            'typesense_enabled'              => $envTypesenseEnabled,
            'show_public_objects_only'        => $envShowPublicOnly,
            'hide_uploads_folder_results'     => $envHideUploads,
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