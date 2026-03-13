<?php
/**
 * Indexer Status Endpoint
 * Place at: applications/tribe/status.php
 * Access at: http://localhost:12000/status.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ─── .env parser ──────────────────────────────────────────────────────────────
// Used as fallback when a key isn't in the process environment.
// Handles: quoted values, unquoted values, inline comments.
//
// BUG FIXED: original trim() only stripped quotes from the edges but did NOT
// strip inline comments (e.g. KEY="value" # comment → returned 'value" # comment').
// The fix: strip inline comments before trimming quote chars.
function parse_dotenv(string $key): ?string {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) return null;

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;

        // Split on first '=' only
        $eqPos = strpos($line, '=');
        if ($eqPos === false) continue;

        $k = trim(substr($line, 0, $eqPos));
        $v = trim(substr($line, $eqPos + 1));

        if ($k !== $key) continue;

        // If value is quoted, extract content between the outermost quote pair.
        // This correctly handles inline comments after closing quote.
        if (strlen($v) >= 2 && (
            ($v[0] === '"'  && str_contains(substr($v, 1), '"'))  ||
            ($v[0] === "'"  && str_contains(substr($v, 1), "'"))
        )) {
            $quote = $v[0];
            $closePos = strpos($v, $quote, 1);
            return substr($v, 1, $closePos - 1);
        }

        // Unquoted value — strip inline comment and surrounding whitespace
        if (($commentPos = strpos($v, ' #')) !== false) {
            $v = substr($v, 0, $commentPos);
        }
        return trim($v);
    }
    return null;
}

// ─── Config ───────────────────────────────────────────────────────────────────
// NOTE: php_tribe must have these in its `environment:` block in docker-compose
// so that getenv() can read them directly. The parse_dotenv() calls are a
// belt-and-suspenders fallback for local dev / edge cases.
$PROJECT_NAME   = getenv('PROJECT_NAME')      ?: parse_dotenv('PROJECT_NAME')      ?: 'tribe';
$TYPESENSE_KEY  = getenv('TYPESENSE_API_KEY') ?: parse_dotenv('TYPESENSE_API_KEY') ?: 'xyz';

// php_tribe cannot use localhost:12006 — that resolves to itself, not Typesense.
// It must reach Typesense via Docker's internal network using the container name,
// which is always {PROJECT_NAME}_typesense per the compose file.
// If TYPESENSE_HOST is already set in the environment (as it is in docker-compose),
// use it directly — otherwise derive it from PROJECT_NAME.
$TYPESENSE_HOST = getenv('TYPESENSE_HOST') ?: ($PROJECT_NAME . '_typesense');
$TYPESENSE_PORT = '8108'; // internal port, NOT the host-mapped port (12006)
$COLLECTION     = 'files';
$INDEX_FOLDER   = '/var/www/html/uploads';

$SKIP_DIRS = ['filebrowser', 'backups', 'typesense', '.git', 'node_modules', 'vendor'];

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
    if ($raw === false || $err) return ['error' => "curl failed: {$err} — url: $url"];
    if ($code >= 400)           return ['error' => "HTTP {$code} from Typesense — url: $url response: $raw"];
    return json_decode($raw, true) ?? ['error' => 'Invalid JSON from Typesense'];
}

function should_skip(SplFileInfo $file, array $skipDirs): bool {
    if (str_starts_with($file->getFilename(), '.')) return true;
    if ($file->getSize() === 0) return true;
    $parts = explode(DIRECTORY_SEPARATOR, $file->getPathname());
    foreach ($skipDirs as $dir) {
        if (in_array($dir, $parts)) return true;
    }
    return false;
}

function get_category(string $ext): string {
    if ($ext === 'pdf')                                                               return 'pdf';
    if (in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','tiff','heic','avif'])) return 'image';
    if (in_array($ext, ['mp4','mov','avi','mkv','webm','flv','wmv','m4v','mpeg']))    return 'video';
    if (in_array($ext, ['txt','md','html','htm','csv','json','xml','yaml','yml',
                        'php','js','ts','css','sh','log','ini','conf']))              return 'text';
    return 'other';
}

// ─── 1. Typesense stats ───────────────────────────────────────────────────────
$collection   = typesense_get("/collections/{$COLLECTION}", $TYPESENSE_HOST, $TYPESENSE_PORT, $TYPESENSE_KEY);
$typesense_ok = !isset($collection['error']);
$indexed_total = $collection['num_documents'] ?? 0;

$indexed_by_category  = [];
$indexed_by_extension = [];

if ($typesense_ok) {
    $facet_resp = typesense_get(
        "/collections/{$COLLECTION}/documents/search?q=*&query_by=filename&facet_by=category,extension&per_page=0",
        $TYPESENSE_HOST, $TYPESENSE_PORT, $TYPESENSE_KEY
    );
    if (isset($facet_resp['facet_counts'])) {
        foreach ($facet_resp['facet_counts'] as $facet) {
            foreach ($facet['counts'] as $count) {
                if ($facet['field_name'] === 'category') {
                    $indexed_by_category[$count['value']] = $count['count'];
                } else {
                    $indexed_by_extension[$count['value']] = $count['count'];
                }
            }
        }
    }
}

// ─── 2. Filesystem scan ───────────────────────────────────────────────────────
$fs_total        = 0;
$fs_by_category  = [];
$fs_by_extension = [];

if (is_dir($INDEX_FOLDER)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($INDEX_FOLDER, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || should_skip($file, $SKIP_DIRS)) continue;
        $ext = strtolower($file->getExtension());
        $cat = get_category($ext);
        $fs_total++;
        $fs_by_category[$cat]  = ($fs_by_category[$cat]  ?? 0) + 1;
        $fs_by_extension[$ext] = ($fs_by_extension[$ext] ?? 0) + 1;
    }
}

// ─── 3. Build output ──────────────────────────────────────────────────────────
$remaining   = max(0, $fs_total - $indexed_total);
$pct_indexed = $fs_total > 0 ? round(($indexed_total / $fs_total) * 100, 1) : 100.0;

$category_breakdown = [];
$all_cats = array_unique(array_merge(array_keys($fs_by_category), array_keys($indexed_by_category)));
sort($all_cats);
foreach ($all_cats as $cat) {
    $fs  = $fs_by_category[$cat]      ?? 0;
    $idx = $indexed_by_category[$cat] ?? 0;
    $category_breakdown[$cat] = [
        'on_disk'   => $fs,
        'indexed'   => $idx,
        'remaining' => max(0, $fs - $idx),
    ];
}

arsort($fs_by_extension);
$top_extensions = array_slice($fs_by_extension, 0, 15, true);

$status = [
    'status'       => !$typesense_ok ? 'error' : ($remaining === 0 ? 'complete' : 'indexing'),
    'typesense_ok' => $typesense_ok,
    'debug' => [
        'typesense_host' => $TYPESENSE_HOST,
        'typesense_port' => $TYPESENSE_PORT,
        'project_name'   => $PROJECT_NAME,
    ],
    'summary' => [
        'total_on_disk'   => $fs_total,
        'total_indexed'   => $indexed_total,
        'total_remaining' => $remaining,
        'percent_indexed' => $pct_indexed,
    ],
    'by_category'    => $category_breakdown,
    'top_extensions' => $top_extensions,
    'typesense' => [
        'collection' => $COLLECTION,
        'created_at' => isset($collection['created_at'])
            ? date('Y-m-d H:i:s', $collection['created_at'])
            : null,
    ],
    'generated_at' => date('Y-m-d H:i:s'),
];

if (!$typesense_ok) {
    $status['error'] = $collection['error'] ?? 'Unknown Typesense error';
    http_response_code(503);
}

echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
