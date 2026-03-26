#!/usr/bin/env php
<?php
/**
 * Tribe Deepsearch Indexer
 *
 * Indexes both MySQL DB objects and filesystem files into Meilisearch.
 * Designed to run on a cron schedule (e.g. every 60s). Stores a cursor
 * timestamp after each run so only new/modified items are processed on
 * subsequent runs — the first run is always a full backfill.
 *
 * Usage:
 *   php indexer.php           # delta run (or full backfill if no cursor exists)
 *   php indexer.php --full    # force full re-index regardless of cursor
 *   php indexer.php --db      # only index DB objects
 *   php indexer.php --files   # only index files
 *
 * Environment variables (inherited from docker-compose / .env):
 *   DEEPSEARCH_HOST, DEEPSEARCH_PORT, DEEPSEARCH_API_KEY
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_ROOT_PASSWORD
 *   PROJECT_NAME, INDEX_FOLDER, COLLECTION_NAME
 */

// ─── Config ───────────────────────────────────────────────────────────────────
$DS_HOST    = getenv('DEEPSEARCH_HOST')    ?: 'deepsearch';
$DS_PORT    = getenv('DEEPSEARCH_PORT')    ?: '8108';
$DS_KEY     = getenv('DEEPSEARCH_API_KEY') ?: 'xyz';
$FILES_COLL = getenv('COLLECTION_NAME')    ?: 'files';
$INDEX_DIR  = getenv('INDEX_FOLDER')       ?: '/var/www/html/uploads';
$CURSOR_DIR = '/tmp/tribe_indexer';

const DB_BATCH       = 500;
const MS_BATCH       = 100;
const FILE_BATCH     = 50;
const MAX_FILE_CHARS = 100000;

const SKIP_TYPES = ['webapp', 'deleted_record', 'search_sync_failed', 'apikey_record'];
const STRIP_KEYS = ['password_md5', 'mysql_access_log', 'mysql_activity_log'];

const SKIP_DIRS = [
    'filebrowser', 'backups', 'typesense', 'deepsearch',
    'sites', 'mysql', '.git', 'node_modules', 'vendor',
];

// ─── Logging ──────────────────────────────────────────────────────────────────
function log_msg(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

// ─── Meilisearch HTTP helpers ─────────────────────────────────────────────────
function meili(string $method, string $path, $body = null, int $timeout = 30): array
{
    global $DS_HOST, $DS_PORT, $DS_KEY;

    $ch = curl_init("http://{$DS_HOST}:{$DS_PORT}{$path}");
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$DS_KEY}",
            'Content-Type: application/json',
        ],
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $err) return ['_error' => $err, '_code' => 0];
    $data = json_decode($raw, true) ?? [];
    $data['_code'] = $code;
    return $data;
}

// ─── Cursor helpers ───────────────────────────────────────────────────────────
function read_cursor(string $name): int
{
    global $CURSOR_DIR;
    $file = "{$CURSOR_DIR}/{$name}.cursor";
    return file_exists($file) ? (int)file_get_contents($file) : 0;
}

function write_cursor(string $name, int $ts): void
{
    global $CURSOR_DIR;
    @mkdir($CURSOR_DIR, 0755, true);
    file_put_contents("{$CURSOR_DIR}/{$name}.cursor", (string)$ts);
}

// ─── Wait for Meilisearch ─────────────────────────────────────────────────────
function wait_for_deepsearch(int $maxWait = 90): void
{
    $waited = 0;
    while ($waited < $maxWait) {
        if ((meili('GET', '/health')['status'] ?? '') === 'available') {
            log_msg('Deepsearch ready.');
            return;
        }
        log_msg("Deepsearch not ready ({$waited}s)…");
        sleep(3);
        $waited += 3;
    }
    throw new \RuntimeException("Deepsearch did not become ready after {$maxWait}s");
}

// ─── Index management ─────────────────────────────────────────────────────────
function ensure_db_index(string $type): void
{
    $name = "tribe_{$type}";
    $info = meili('GET', "/indexes/{$name}");
    if (($info['_code'] ?? 0) === 404) {
        meili('POST', '/indexes', ['uid' => $name, 'primaryKey' => 'id']);
        usleep(500000);
    }
    meili('PUT', "/indexes/{$name}/settings", [
        'searchableAttributes' => ['search_content', 'slug', 'type'],
        'filterableAttributes' => ['type', 'content_privacy', 'user_id', 'updated_on', 'created_on'],
        'sortableAttributes'   => ['updated_on', 'created_on'],
    ]);
}

function ensure_files_index(): void
{
    global $FILES_COLL;
    $info = meili('GET', "/indexes/{$FILES_COLL}");
    if (($info['_code'] ?? 0) === 404) {
        meili('POST', '/indexes', ['uid' => $FILES_COLL, 'primaryKey' => 'id']);
        usleep(500000);
    }
    meili('PUT', "/indexes/{$FILES_COLL}/settings", [
        'searchableAttributes' => ['filename', 'content', 'title', 'description', 'author', 'tags'],
        'filterableAttributes' => ['category', 'extension', 'mime_type', 'path', 'author'],
        'sortableAttributes'   => ['modified_at', 'created_at', 'size_bytes'],
    ]);
}

// ─── MySQL connection ─────────────────────────────────────────────────────────
function make_pdo(): PDO
{
    $host = getenv('DB_HOST') ?: 'mysql';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: getenv('PROJECT_NAME') ?: 'tribe';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: getenv('DB_ROOT_PASSWORD') ?: '';

    return new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]
    );
}

// ─── DB document transform ────────────────────────────────────────────────────
function db_row_to_doc(array $row): array
{
    $obj = json_decode($row['content'] ?? '{}', true) ?: [];
    $obj['id']         = (int)$row['id'];
    $obj['updated_on'] = (int)$row['updated_on'];
    $obj['created_on'] = (int)$row['created_on'];
    if (!empty($row['type'])) $obj['type'] = $row['type'];

    foreach (STRIP_KEYS as $k) unset($obj[$k]);

    $doc = [
        'id'              => (string)$obj['id'],
        'type'            => (string)($obj['type']            ?? ''),
        'slug'            => (string)($obj['slug']            ?? ''),
        'content_privacy' => (string)($obj['content_privacy'] ?? 'public'),
        'updated_on'      => (int)$obj['updated_on'],
        'created_on'      => (int)$obj['created_on'],
        'search_content'  => '',
    ];

    if (isset($obj['user_id'])) $doc['user_id'] = (int)$obj['user_id'];

    $textParts = [];
    foreach ($obj as $k => $v) {
        if (array_key_exists($k, $doc) || $v === null || $v === '') continue;
        if (is_array($v)) {
            $flat = implode(', ', array_filter($v, 'is_scalar'));
            $doc[$k] = $flat;
            $textParts[] = $flat;
        } else {
            $doc[$k] = $v;
            if (is_string($v)) $textParts[] = $v;
        }
    }

    $doc['search_content'] = implode(' ', array_filter($textParts));
    return $doc;
}

// ─── DB indexer ───────────────────────────────────────────────────────────────
function index_db(PDO $pdo, int $since, bool $full): array
{
    $skipList = implode("','", SKIP_TYPES);
    $stats    = ['indexed' => 0, 'failed' => 0];
    $offset   = 0;

    $whereTime = $full ? '' : "AND `updated_on` > {$since}";

    log_msg($full ? 'DB: full re-index…' : "DB: delta since " . date('Y-m-d H:i:s', $since) . "…");

    do {
        $stmt = $pdo->query(
            "SELECT `id`, `type`, `content`, `updated_on`, `created_on` FROM `data`
             WHERE `type` NOT IN ('{$skipList}') AND `type` IS NOT NULL AND `type` != ''
             {$whereTime}
             ORDER BY `updated_on` ASC
             LIMIT {$offset}, " . DB_BATCH
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) break;

        // Group docs by type so each batch targets a single index
        $byType = [];
        foreach ($rows as $row) {
            $type = $row['type'];
            if (!$type || in_array($type, SKIP_TYPES)) continue;
            $byType[$type][] = db_row_to_doc($row);
        }

        foreach ($byType as $type => $docs) {
            ensure_db_index($type);
            foreach (array_chunk($docs, MS_BATCH) as $batch) {
                $res = meili('POST', "/indexes/tribe_{$type}/documents", $batch);
                if (($res['_code'] ?? 0) < 400 || ($res['_code'] ?? 0) === 202) {
                    $stats['indexed'] += count($batch);
                } else {
                    $stats['failed'] += count($batch);
                    log_msg("DB ERROR on tribe_{$type}: " . json_encode($res));
                }
            }
        }

        $offset += DB_BATCH;
    } while (count($rows) === DB_BATCH);

    return $stats;
}

// ─── File text extraction ─────────────────────────────────────────────────────
function extract_file(SplFileInfo $file): array
{
    $ext  = strtolower($file->getExtension());
    $path = $file->getPathname();
    $doc  = [
        'category' => 'other',
        'content'  => '',
        'mime_type' => @mime_content_type($path) ?: 'application/octet-stream',
    ];

    // Text / code / markup
    if (in_array($ext, ['txt','md','markdown','csv','json','xml','yaml','yml',
                         'html','htm','php','js','ts','css','sh','env','log','ini','conf'])) {
        $doc['category'] = 'text';
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            $doc['content'] = mb_substr(
                in_array($ext, ['html','htm']) ? strip_tags($raw) : $raw,
                0, MAX_FILE_CHARS
            );
        }
        return $doc;
    }

    // PDF
    if ($ext === 'pdf') {
        $doc['category'] = 'pdf';
        $doc['mime_type'] = 'application/pdf';
        $safe = escapeshellarg($path);
        $text = shell_exec("pdftotext -layout {$safe} - 2>/dev/null");

        if (empty(trim($text ?? ''))) {
            $tmpDir = sys_get_temp_dir() . '/ocr_' . md5($path);
            @mkdir($tmpDir);
            shell_exec("gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r150 -sOutputFile={$tmpDir}/page-%03d.png {$safe} 2>/dev/null");
            $parts = [];
            foreach (glob("{$tmpDir}/page-*.png") as $page) {
                $parts[] = shell_exec("tesseract " . escapeshellarg($page) . " stdout -l eng 2>/dev/null") ?? '';
            }
            $text = implode("\n", $parts);
            array_map('unlink', glob("{$tmpDir}/page-*.png"));
            @rmdir($tmpDir);
        }

        $doc['content'] = mb_substr($text ?? '', 0, MAX_FILE_CHARS);
        return $doc;
    }

    // Images
    if (in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','tiff','tif','heic','avif'])) {
        $doc['category'] = 'image';
        $safe = escapeshellarg($path);
        $exifJson = shell_exec("exiftool -json -ImageWidth -ImageHeight -Title -Description -Artist -Keywords -Comment -DateTimeOriginal {$safe} 2>/dev/null");
        if ($exifJson) {
            $exif = json_decode($exifJson, true)[0] ?? [];
            $doc['width']       = (int)($exif['ImageWidth']  ?? 0) ?: null;
            $doc['height']      = (int)($exif['ImageHeight'] ?? 0) ?: null;
            $doc['title']       = $exif['Title']       ?? null;
            $doc['description'] = $exif['Description'] ?? $exif['Comment'] ?? null;
            $doc['author']      = $exif['Artist']      ?? null;
            $kw = $exif['Keywords'] ?? null;
            if ($kw) $doc['tags'] = is_array($kw) ? $kw : array_map('trim', explode(',', $kw));
            $doc['content'] = implode(' ', array_filter([
                $doc['title'], $doc['description'], $doc['author'],
                is_array($doc['tags'] ?? null) ? implode(' ', $doc['tags']) : null,
                $exif['DateTimeOriginal'] ?? null,
            ]));
        }
        if (mb_strlen(trim($doc['content'])) < 20 && in_array($ext, ['jpg','jpeg','png','bmp','tiff','tif','webp'])) {
            $ocr = shell_exec("tesseract {$safe} stdout -l eng 2>/dev/null") ?? '';
            $doc['content'] = trim($ocr) ?: $doc['content'];
        }
        if (empty(trim($doc['content']))) {
            $doc['content'] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }
        return $doc;
    }

    // Videos
    if (in_array($ext, ['mp4','mov','avi','mkv','webm','flv','wmv','m4v','mpeg','mpg','3gp'])) {
        $doc['category'] = 'video';
        $safe = escapeshellarg($path);
        $ffJson = shell_exec("ffprobe -v quiet -print_format json -show_streams -show_format {$safe} 2>/dev/null");
        if ($ffJson) {
            $ff     = json_decode($ffJson, true) ?? [];
            $fmt    = $ff['format']['tags']  ?? [];
            $streams = $ff['streams'] ?? [];
            $doc['duration_sec'] = isset($ff['format']['duration']) ? (float)$ff['format']['duration'] : null;
            $doc['title']        = $fmt['title']   ?? $fmt['TITLE']   ?? null;
            $doc['description']  = $fmt['comment'] ?? $fmt['COMMENT'] ?? null;
            $doc['author']       = $fmt['artist']  ?? $fmt['ARTIST']  ?? $fmt['author'] ?? null;
            foreach ($streams as $s) {
                if (($s['codec_type'] ?? '') === 'video') {
                    $doc['width']  = (int)($s['width']  ?? 0) ?: null;
                    $doc['height'] = (int)($s['height'] ?? 0) ?: null;
                    break;
                }
            }
        }
        $doc['content'] = implode(' ', array_filter([
            $doc['title'], $doc['description'], $doc['author'],
            $doc['duration_sec'] ? round($doc['duration_sec']) . 's' : null,
        ])) ?: pathinfo($file->getFilename(), PATHINFO_FILENAME);
        return $doc;
    }

    $doc['content'] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
    return $doc;
}

function build_file_doc(SplFileInfo $file, string $indexDir): array
{
    $extracted    = extract_file($file);
    $relativePath = '/' . ltrim(str_replace($indexDir, '', $file->getPath()), '/');

    $doc = [
        'id'          => md5($file->getPathname()),
        'filename'    => $file->getFilename(),
        'path'        => $relativePath,
        'extension'   => strtolower($file->getExtension()),
        'mime_type'   => $extracted['mime_type'],
        'category'    => $extracted['category'],
        'content'     => $extracted['content'] ?? '',
        'size_bytes'  => $file->getSize(),
        'modified_at' => $file->getMTime(),
        'created_at'  => filectime($file->getPathname()),
    ];

    foreach (['title','description','author','tags','width','height','duration_sec'] as $f) {
        if (!empty($extracted[$f])) $doc[$f] = $extracted[$f];
    }

    return $doc;
}

function should_skip_file(SplFileInfo $file): bool
{
    if (str_starts_with($file->getFilename(), '.')) return true;
    if ($file->getSize() === 0) return true;
    foreach (explode(DIRECTORY_SEPARATOR, $file->getPathname()) as $part) {
        if (in_array($part, SKIP_DIRS)) return true;
    }
    return false;
}

// ─── File indexer ─────────────────────────────────────────────────────────────
function index_files(string $indexDir, int $since, bool $full): array
{
    global $FILES_COLL;

    ensure_files_index();
    $stats = ['indexed' => 0, 'skipped' => 0, 'failed' => 0];

    log_msg($full ? 'Files: full re-index…' : "Files: delta since " . date('Y-m-d H:i:s', $since) . "…");

    if (!is_dir($indexDir)) {
        log_msg("Files: index folder not found: {$indexDir}");
        return $stats;
    }

    $iter  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($indexDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    $batch = [];

    foreach ($iter as $file) {
        if (!$file->isFile()) continue;
        if (should_skip_file($file)) { $stats['skipped']++; continue; }

        // Delta: skip files not modified since last run
        if (!$full && $file->getMTime() <= $since) { $stats['skipped']++; continue; }

        try {
            $batch[] = build_file_doc($file, $indexDir);
        } catch (\Exception $e) {
            log_msg("Files ERROR reading {$file->getPathname()}: " . $e->getMessage());
            $stats['failed']++;
            continue;
        }

        if (count($batch) >= FILE_BATCH) {
            $res = meili('POST', "/indexes/{$FILES_COLL}/documents", $batch);
            if (($res['_code'] ?? 0) < 400 || ($res['_code'] ?? 0) === 202) {
                $stats['indexed'] += count($batch);
            } else {
                $stats['failed'] += count($batch);
                log_msg("Files ERROR batch: " . json_encode($res));
            }
            $batch = [];
        }
    }

    if (!empty($batch)) {
        $res = meili('POST', "/indexes/{$FILES_COLL}/documents", $batch);
        if (($res['_code'] ?? 0) < 400 || ($res['_code'] ?? 0) === 202) {
            $stats['indexed'] += count($batch);
        } else {
            $stats['failed'] += count($batch);
        }
    }

    return $stats;
}

// ─── Main ─────────────────────────────────────────────────────────────────────
$args     = array_slice($argv, 1);
$forceDb  = in_array('--db',   $args);
$forceFiles = in_array('--files', $args);
$fullMode = in_array('--full', $args);

// If neither flag given, run both
$runDb    = $forceDb    || (!$forceDb && !$forceFiles);
$runFiles = $forceFiles || (!$forceDb && !$forceFiles);

wait_for_deepsearch();

$now = time();

// ── DB ────────────────────────────────────────────────────────────────────────
if ($runDb) {
    try {
        $pdo    = make_pdo();
        $cursor = read_cursor('db');
        $full   = $fullMode || $cursor === 0;
        $stats  = index_db($pdo, $cursor, $full);
        write_cursor('db', $now);
        log_msg("DB done — indexed: {$stats['indexed']}, failed: {$stats['failed']}");
    } catch (\Throwable $e) {
        log_msg("DB ERROR: " . $e->getMessage());
    }
}

// ── Files ─────────────────────────────────────────────────────────────────────
if ($runFiles) {
    $cursor = read_cursor('files');
    $full   = $fullMode || $cursor === 0;
    $stats  = index_files($INDEX_DIR, $cursor, $full);
    write_cursor('files', $now);
    log_msg("Files done — indexed: {$stats['indexed']}, skipped: {$stats['skipped']}, failed: {$stats['failed']}");
}
