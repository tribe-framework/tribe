<?php
/**
 * Indexer Status Endpoint
 * Reports on both:
 *   - DB objects  : rows in MySQL `data` table vs. tribe_* Typesense collections
 *   - Files       : files on disk vs. the `files` Typesense collection
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ─── .env parser ──────────────────────────────────────────────────────────────
function parse_dotenv(string $key): ?string {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) return null;

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;

        $eqPos = strpos($line, '=');
        if ($eqPos === false) continue;

        $k = trim(substr($line, 0, $eqPos));
        $v = trim(substr($line, $eqPos + 1));

        if ($k !== $key) continue;

        if (strlen($v) >= 2 && (
            ($v[0] === '"'  && str_contains(substr($v, 1), '"'))  ||
            ($v[0] === "'"  && str_contains(substr($v, 1), "'"))
        )) {
            $quote    = $v[0];
            $closePos = strpos($v, $quote, 1);
            return substr($v, 1, $closePos - 1);
        }

        if (($commentPos = strpos($v, ' #')) !== false) {
            $v = substr($v, 0, $commentPos);
        }
        return trim($v);
    }
    return null;
}

// ─── Config ───────────────────────────────────────────────────────────────────
$PROJECT_NAME   = getenv('PROJECT_NAME')      ?: parse_dotenv('PROJECT_NAME')      ?: 'tribe';
$TYPESENSE_KEY  = getenv('TYPESENSE_API_KEY') ?: parse_dotenv('TYPESENSE_API_KEY') ?: 'xyz';
$TYPESENSE_HOST = getenv('TYPESENSE_HOST')    ?: ($PROJECT_NAME . '_typesense');
$TYPESENSE_PORT = '8108';

$FILES_COLLECTION = 'files';
$INDEX_FOLDER     = '/var/www/html/uploads';
$SKIP_DIRS        = ['filebrowser', 'backups', 'typesense', '.git', 'node_modules', 'vendor'];

// Types that index_db.php explicitly skips — mirror SKIP_TYPES exactly.
$DB_SKIP_TYPES = ['webapp', 'deleted_record', 'search_sync_failed', 'apikey_record'];

// ─── Helpers ──────────────────────────────────────────────────────────────────
function typesense_get(string $path, string $host, string $port, string $key): array {
    $url = "http://{$host}:{$port}{$path}";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => ["X-TYPESENSE-API-KEY: {$key}"],
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $err) return ['_error' => "curl failed: {$err}"];
    if ($code >= 400)           return ['_error' => "HTTP {$code} — {$raw}"];
    return json_decode($raw, true) ?? ['_error' => 'Invalid JSON'];
}

function should_skip_file(SplFileInfo $file, array $skipDirs): bool {
    if (str_starts_with($file->getFilename(), '.')) return true;
    if ($file->getSize() === 0) return true;
    foreach (explode(DIRECTORY_SEPARATOR, $file->getPathname()) as $part) {
        if (in_array($part, $skipDirs)) return true;
    }
    return false;
}

function get_file_category(string $ext): string {
    if ($ext === 'pdf')                                                                return 'pdf';
    if (in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','tiff','heic','avif']))  return 'image';
    if (in_array($ext, ['mp4','mov','avi','mkv','webm','flv','wmv','m4v','mpeg']))     return 'video';
    if (in_array($ext, ['txt','md','html','htm','csv','json','xml','yaml','yml',
                        'php','js','ts','css','sh','log','ini','conf']))               return 'text';
    return 'other';
}

function pdo_connect(): ?PDO {
    try {
        $host = getenv('DB_HOST') ?: 'mysql';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: getenv('PROJECT_NAME') ?: 'tribe';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: getenv('DB_ROOT_PASSWORD') ?: '';
        return new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
    } catch (\Exception $e) {
        return null;
    }
}

// ─── 1. Typesense health ──────────────────────────────────────────────────────
$tsHealth     = typesense_get('/health', $TYPESENSE_HOST, $TYPESENSE_PORT, $TYPESENSE_KEY);
$typesense_ok = !isset($tsHealth['_error']) && ($tsHealth['ok'] ?? false);

// ─── 2. DB objects ────────────────────────────────────────────────────────────
$db_ok             = false;
$db_total          = 0;
$db_by_type        = [];   // [ type => count ]  — from MySQL
$ts_objects_total  = 0;    // sum of num_documents across tribe_* collections
$ts_by_type        = [];   // [ type => count ]  — from Typesense

$pdo = pdo_connect();
if ($pdo) {
    $db_ok = true;
    try {
        // Total indexable rows (excluding skip types)
        $skipList = implode("','", array_map(fn($t) => addslashes($t), $DB_SKIP_TYPES));
        $stmt = $pdo->query(
            "SELECT `type`, COUNT(*) AS `cnt`
               FROM `data`
              WHERE `type` IS NOT NULL
                AND `type` != ''
                AND `type` NOT IN ('{$skipList}')
              GROUP BY `type`
              ORDER BY `cnt` DESC"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $db_by_type[$row['type']] = (int)$row['cnt'];
            $db_total += (int)$row['cnt'];
        }
    } catch (\Exception $e) {
        $db_ok = false;
    }
}

// Fetch tribe_* collections from Typesense to count indexed objects
$allCollections = typesense_get('/collections', $TYPESENSE_HOST, $TYPESENSE_PORT, $TYPESENSE_KEY);
$ts_collections = [];

if (!isset($allCollections['_error']) && is_array($allCollections)) {
    foreach ($allCollections as $coll) {
        $name = $coll['name'] ?? '';
        $ndoc = (int)($coll['num_documents'] ?? 0);

        if (str_starts_with($name, 'tribe_')) {
            $type = substr($name, 6);   // strip "tribe_" prefix
            $ts_by_type[$type]    = $ndoc;
            $ts_objects_total    += $ndoc;
            $ts_collections[]     = ['name' => $name, 'count' => $ndoc];
        }
    }
}

// Per-type breakdown combining DB + TS counts
$objects_remaining    = max(0, $db_total - $ts_objects_total);
$objects_pct_indexed  = $db_total > 0 ? round(($ts_objects_total / $db_total) * 100, 1) : 100.0;

$all_types = array_unique(array_merge(array_keys($db_by_type), array_keys($ts_by_type)));
sort($all_types);
$objects_by_type = [];
foreach ($all_types as $t) {
    $on_db  = $db_by_type[$t] ?? 0;
    $in_ts  = $ts_by_type[$t] ?? 0;
    $objects_by_type[$t] = [
        'in_database' => $on_db,
        'indexed'     => $in_ts,
        'remaining'   => max(0, $on_db - $in_ts),
    ];
}

// ─── 3. Files ─────────────────────────────────────────────────────────────────
$files_collection = typesense_get(
    "/collections/{$FILES_COLLECTION}",
    $TYPESENSE_HOST, $TYPESENSE_PORT, $TYPESENSE_KEY
);
$files_ts_ok    = !isset($files_collection['_error']);
$files_indexed  = $files_collection['num_documents'] ?? 0;

$files_by_category_indexed  = [];
$files_by_extension_indexed = [];

if ($files_ts_ok) {
    $facet = typesense_get(
        "/collections/{$FILES_COLLECTION}/documents/search"
        . "?q=*&query_by=filename&facet_by=category,extension&per_page=0",
        $TYPESENSE_HOST, $TYPESENSE_PORT, $TYPESENSE_KEY
    );
    if (isset($facet['facet_counts'])) {
        foreach ($facet['facet_counts'] as $f) {
            foreach ($f['counts'] as $c) {
                if ($f['field_name'] === 'category') {
                    $files_by_category_indexed[$c['value']] = $c['count'];
                } else {
                    $files_by_extension_indexed[$c['value']] = $c['count'];
                }
            }
        }
    }
}

// Filesystem scan
$fs_total        = 0;
$fs_by_category  = [];
$fs_by_extension = [];

if (is_dir($INDEX_FOLDER)) {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($INDEX_FOLDER, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if (!$file->isFile() || should_skip_file($file, $SKIP_DIRS)) continue;
        $ext = strtolower($file->getExtension());
        $cat = get_file_category($ext);
        $fs_total++;
        $fs_by_category[$cat]  = ($fs_by_category[$cat]  ?? 0) + 1;
        $fs_by_extension[$ext] = ($fs_by_extension[$ext] ?? 0) + 1;
    }
}

$files_remaining   = max(0, $fs_total - $files_indexed);
$files_pct_indexed = $fs_total > 0 ? round(($files_indexed / $fs_total) * 100, 1) : 100.0;

$all_cats = array_unique(array_merge(
    array_keys($fs_by_category),
    array_keys($files_by_category_indexed)
));
sort($all_cats);
$files_by_category = [];
foreach ($all_cats as $cat) {
    $on_disk = $fs_by_category[$cat]              ?? 0;
    $indexed = $files_by_category_indexed[$cat]   ?? 0;
    $files_by_category[$cat] = [
        'on_disk'   => $on_disk,
        'indexed'   => $indexed,
        'remaining' => max(0, $on_disk - $indexed),
    ];
}

arsort($fs_by_extension);
$top_extensions = array_slice($fs_by_extension, 0, 15, true);

// ─── 4. Overall status ────────────────────────────────────────────────────────
$has_error    = !$typesense_ok || (!$db_ok && $pdo === null);
$fully_synced = ($objects_remaining === 0 && $files_remaining === 0);

if ($has_error) {
    $overall_status = 'error';
} elseif ($fully_synced) {
    $overall_status = 'complete';
} else {
    $overall_status = 'indexing';
}

// ─── 5. Response ─────────────────────────────────────────────────────────────
$response = [
    'status'       => $overall_status,
    'generated_at' => date('Y-m-d H:i:s'),

    'services' => [
        'typesense' => $typesense_ok,
        'database'  => $db_ok,
    ],

    // ── DB objects ────────────────────────────────────────────────────────────
    'objects' => [
        'summary' => [
            'total_in_database' => $db_total,
            'total_indexed'     => $ts_objects_total,
            'total_remaining'   => $objects_remaining,
            'percent_indexed'   => $objects_pct_indexed,
        ],
        'by_type'     => $objects_by_type,
        'collections' => $ts_collections,    // raw tribe_* collection list
    ],

    // ── Files ─────────────────────────────────────────────────────────────────
    'files' => [
        'summary' => [
            'total_on_disk'   => $fs_total,
            'total_indexed'   => $files_indexed,
            'total_remaining' => $files_remaining,
            'percent_indexed' => $files_pct_indexed,
        ],
        'by_category'    => $files_by_category,
        'top_extensions' => $top_extensions,
        'collection'     => [
            'name'       => $FILES_COLLECTION,
            'created_at' => isset($files_collection['created_at'])
                ? date('Y-m-d H:i:s', $files_collection['created_at'])
                : null,
        ],
    ],

    'debug' => [
        'typesense_host' => $TYPESENSE_HOST,
        'typesense_port' => $TYPESENSE_PORT,
        'project_name'   => $PROJECT_NAME,
        'index_folder'   => $INDEX_FOLDER,
    ],
];

if ($has_error) {
    $errors = [];
    if (!$typesense_ok) {
        $errors[] = $tsHealth['_error'] ?? 'Typesense unreachable';
    }
    if (!$db_ok) {
        $errors[] = 'MySQL unreachable or query failed';
    }
    $response['errors'] = $errors;
    http_response_code(503);
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);