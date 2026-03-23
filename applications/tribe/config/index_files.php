#!/usr/bin/env php
<?php
/**
 * Typesense File Indexer
 * Indexes PDFs, images, videos, text, HTML, and markdown from a folder.
 * Runs as a full re-index or single-file update (pass file path as argument).
 */

require '/var/www/html/vendor/autoload.php';

use Typesense\Client;

// ─── Config ──────────────────────────────────────────────────────────────────
$TYPESENSE_HOST    = getenv('TYPESENSE_HOST')    ?: 'typesense';
$TYPESENSE_PORT    = getenv('TYPESENSE_PORT')    ?: '8108';
$TYPESENSE_KEY     = getenv('TYPESENSE_API_KEY') ?: 'xyz';
$COLLECTION_NAME   = getenv('COLLECTION_NAME')   ?: 'files';
$INDEX_FOLDER      = getenv('INDEX_FOLDER')      ?: '/uploads';
$MAX_CONTENT_CHARS = 100000; // cap per file to avoid oversized docs

// ─── Typesense Client ────────────────────────────────────────────────────────
$client = new Client([
    'api_key' => $TYPESENSE_KEY,
    'nodes'   => [[
        'host'     => $TYPESENSE_HOST,
        'port'     => $TYPESENSE_PORT,
        'protocol' => 'http',
    ]],
    'connection_timeout_seconds' => 10,
]);

// ─── Ensure Collection Exists ────────────────────────────────────────────────
function waitForTypesense(Client $client, int $maxWaitSeconds = 60): void {
    $waited = 0;
    while ($waited < $maxWaitSeconds) {
        try {
            $client->health->retrieve(3);
            log_msg("Typesense is ready.");
            return;
        } catch (\Exception $e) {
            log_msg("Typesense not ready yet, waiting... ({$waited}s)");
            sleep(3);
            $waited += 3;
        }
    }
    throw new \RuntimeException("Typesense did not become ready after {$maxWaitSeconds}s");
}

function ensureCollection(Client $client, string $name): void {
    try {
        $client->collections[$name]->retrieve();
        log_msg("Collection '$name' already exists.");
    } catch (\Exception $e) {
        log_msg("Creating collection '$name'...");
        $client->collections->create([
            'name'   => $name,
            'fields' => [
                ['name' => 'id',           'type' => 'string'],
                ['name' => 'filename',     'type' => 'string'],
                ['name' => 'path',         'type' => 'string',  'facet' => true],
                ['name' => 'extension',    'type' => 'string',  'facet' => true],
                ['name' => 'mime_type',    'type' => 'string',  'facet' => true],
                ['name' => 'category',     'type' => 'string',  'facet' => true], // pdf/image/video/text/other
                ['name' => 'content',      'type' => 'string'],
                ['name' => 'title',        'type' => 'string',  'optional' => true],
                ['name' => 'description',  'type' => 'string',  'optional' => true],
                ['name' => 'author',       'type' => 'string',  'optional' => true, 'facet' => true],
                ['name' => 'tags',         'type' => 'string[]','optional' => true, 'facet' => true],
                ['name' => 'width',        'type' => 'int32',   'optional' => true],
                ['name' => 'height',       'type' => 'int32',   'optional' => true],
                ['name' => 'duration_sec', 'type' => 'float',   'optional' => true],
                ['name' => 'size_bytes',   'type' => 'int64'],
                ['name' => 'modified_at',  'type' => 'int64'],
                ['name' => 'created_at',   'type' => 'int64'],
            ],
            'default_sorting_field' => 'modified_at',
        ]);
        log_msg("Collection created.");
    }
}

// ─── File Type Handlers ───────────────────────────────────────────────────────
function extractText(SplFileInfo $file, int $maxChars): array {
    $ext  = strtolower($file->getExtension());
    $path = $file->getPathname();
    $doc  = ['category' => 'other', 'content' => '', 'mime_type' => mime_content_type($path) ?: 'application/octet-stream'];

    // TEXT / MARKDOWN / CODE
    if (in_array($ext, ['txt','md','markdown','csv','json','xml','yaml','yml','html','htm','php','js','ts','css','sh','env','log','ini','conf'])) {
        $doc['category'] = in_array($ext, ['html','htm']) ? 'text' : 'text';
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            // Strip HTML tags for html files
            $doc['content'] = mb_substr(
                in_array($ext, ['html','htm']) ? strip_tags($raw) : $raw,
                0, $maxChars
            );
        }
        return $doc;
    }

    // In extractText(), replace the PDF block:
    if ($ext === 'pdf') {
        $doc['category'] = 'pdf';
        $doc['mime_type'] = 'application/pdf';
        $safePath = escapeshellarg($path);

        // First try native text extraction
        $text = shell_exec("pdftotext -layout $safePath - 2>/dev/null");

        // If pdftotext returned nothing (scanned PDF), OCR with Tesseract via ImageMagick
        if (empty(trim($text ?? ''))) {
            $tmpDir = sys_get_temp_dir() . '/ocr_' . md5($path);
            @mkdir($tmpDir);
            // Convert PDF pages to images, then OCR
            shell_exec("gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r150 -sOutputFile={$tmpDir}/page-%03d.png $safePath 2>/dev/null");
            $ocrParts = [];
            foreach (glob("{$tmpDir}/page-*.png") as $page) {
                $safePage = escapeshellarg($page);
                $ocrParts[] = shell_exec("tesseract $safePage stdout -l eng 2>/dev/null") ?? '';
            }
            $text = implode("\n", $ocrParts);
            array_map('unlink', glob("{$tmpDir}/page-*.png"));
            @rmdir($tmpDir);
        }

        $doc['content'] = mb_substr($text ?? '', 0, $maxChars);
        // ... pdfinfo block stays the same
    }

    // In extractText(), update the IMAGE block to add OCR:
    if (in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','tiff','tif','heic','avif'])) {
        $doc['category'] = 'image';
        $safePath = escapeshellarg($path);

        $exifJson = shell_exec("exiftool -json -ImageWidth -ImageHeight -Title -Description -Artist -Keywords -Comment -DateTimeOriginal $safePath 2>/dev/null");
        if ($exifJson) {
            $exif = json_decode($exifJson, true)[0] ?? [];
            $doc['width']       = (int)($exif['ImageWidth']  ?? 0) ?: null;
            $doc['height']      = (int)($exif['ImageHeight'] ?? 0) ?: null;
            $doc['title']       = $exif['Title']       ?? null;
            $doc['description'] = $exif['Description'] ?? $exif['Comment'] ?? null;
            $doc['author']      = $exif['Artist']      ?? null;

            $keywords = $exif['Keywords'] ?? null;
            if ($keywords) {
                $doc['tags'] = is_array($keywords) ? $keywords : array_map('trim', explode(',', $keywords));
            }

            // Build searchable content from EXIF fields
            $parts = array_filter([
                $doc['title'],
                $doc['description'],
                $doc['author'],
                is_array($doc['tags'] ?? null) ? implode(' ', $doc['tags']) : null,
                $exif['DateTimeOriginal'] ?? null,
            ]);
            $doc['content'] = implode(' ', $parts);
        }

        // OCR the image with Tesseract if content is still thin
        if (mb_strlen(trim($doc['content'])) < 20 && in_array($ext, ['jpg','jpeg','png','bmp','tiff','tif','webp'])) {
            $ocrText = shell_exec("tesseract $safePath stdout -l eng 2>/dev/null") ?? '';
            $doc['content'] = trim($ocrText) ?: $doc['content'];
        }
        
        // Fallback: filename is searchable even without metadata
        if (empty(trim($doc['content']))) {
            $doc['content'] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }
        return $doc;
    }

    // VIDEOS — metadata via ffprobe
    if (in_array($ext, ['mp4','mov','avi','mkv','webm','flv','wmv','m4v','mpeg','mpg','3gp'])) {
        $doc['category'] = 'video';
        $safePath = escapeshellarg($path);

        // Duration & dimensions via ffprobe
        $ffJson = shell_exec("ffprobe -v quiet -print_format json -show_streams -show_format $safePath 2>/dev/null");
        if ($ffJson) {
            $ff = json_decode($ffJson, true) ?? [];
            $format  = $ff['format']  ?? [];
            $streams = $ff['streams'] ?? [];

            $doc['duration_sec'] = isset($format['duration']) ? (float)$format['duration'] : null;

            // Title / comment from format tags
            $tags = $format['tags'] ?? [];
            $doc['title']       = $tags['title']   ?? $tags['TITLE']   ?? null;
            $doc['description'] = $tags['comment'] ?? $tags['COMMENT'] ?? null;
            $doc['author']      = $tags['artist']  ?? $tags['ARTIST']  ?? $tags['author'] ?? null;

            // Width/height from video stream
            foreach ($streams as $stream) {
                if (($stream['codec_type'] ?? '') === 'video') {
                    $doc['width']  = (int)($stream['width']  ?? 0) ?: null;
                    $doc['height'] = (int)($stream['height'] ?? 0) ?: null;
                    break;
                }
            }
        }

        // Also try exiftool for richer video metadata
        $exifJson = shell_exec("exiftool -json -Title -Description -Comment -Artist -Keywords $safePath 2>/dev/null");
        if ($exifJson) {
            $exif = json_decode($exifJson, true)[0] ?? [];
            $doc['title']       = $doc['title']       ?? ($exif['Title']       ?? null);
            $doc['description'] = $doc['description'] ?? ($exif['Description'] ?? $exif['Comment'] ?? null);
            $doc['author']      = $doc['author']       ?? ($exif['Artist']      ?? null);
        }

        $parts = array_filter([
            $doc['title'],
            $doc['description'],
            $doc['author'],
            $doc['duration_sec'] ? round($doc['duration_sec']).'s' : null,
        ]);
        $doc['content'] = implode(' ', $parts);
        if (empty(trim($doc['content']))) {
            $doc['content'] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }
        return $doc;
    }

    // Everything else — just filename as content
    $doc['content'] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
    return $doc;
}

// ─── Build a Typesense Document ───────────────────────────────────────────────
function buildDocument(SplFileInfo $file, string $indexFolder, int $maxChars): array {
    $extracted = extractText($file, $maxChars);

    $relativePath = str_replace($indexFolder, '', $file->getPath());
    $relativePath = '/' . ltrim($relativePath, '/');

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

    // Optional metadata fields — only include if non-null/non-empty
    foreach (['title','description','author','tags','width','height','duration_sec'] as $field) {
        if (!empty($extracted[$field])) {
            $doc[$field] = $extracted[$field];
        }
    }

    return $doc;
}

// ─── Skip List ────────────────────────────────────────────────────────────────
function shouldSkip(SplFileInfo $file): bool {
    $skip_dirs = ['filebrowser', 'backups', 'typesense', 'types', 'sites', '.git', 'node_modules', 'vendor'];
    $path_parts = explode(DIRECTORY_SEPARATOR, $file->getPathname());
    foreach ($skip_dirs as $dir) {
        if (in_array($dir, $path_parts)) return true;
    }
    // Skip hidden files
    if (str_starts_with($file->getFilename(), '.')) return true;
    // Skip tiny lock/temp files
    if ($file->getSize() === 0) return true;
    return false;
}

// ─── Logging ─────────────────────────────────────────────────────────────────
function log_msg(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

// ─── Main ─────────────────────────────────────────────────────────────────────
waitForTypesense($client);
ensureCollection($client, $COLLECTION_NAME);

$singleFile = $argv[1] ?? null;

if ($singleFile) {
    // Single file mode — triggered by inotify watcher
    if (!file_exists($singleFile)) {
        // File was deleted — remove from index
        $id = md5($singleFile);
        try {
            $client->collections[$COLLECTION_NAME]->documents[$id]->delete();
            log_msg("Deleted from index: $singleFile");
        } catch (\Exception $e) {
            log_msg("Not found in index (already removed?): $singleFile");
        }
        exit(0);
    }
    $file = new SplFileInfo($singleFile);
    if (!$file->isFile() || shouldSkip($file)) {
        log_msg("Skipping: $singleFile");
        exit(0);
    }
    try {
        $doc = buildDocument($file, $INDEX_FOLDER, $MAX_CONTENT_CHARS);
        $client->collections[$COLLECTION_NAME]->documents->upsert($doc);
        log_msg("Indexed: {$file->getFilename()} [{$doc['category']}]");
    } catch (\Exception $e) {
        log_msg("ERROR indexing $singleFile: " . $e->getMessage());
    }
    exit(0);
}

// Full re-index mode
log_msg("Starting full index of: $INDEX_FOLDER");
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($INDEX_FOLDER, RecursiveDirectoryIterator::SKIP_DOTS)
);

$batch   = [];
$total   = 0;
$skipped = 0;
$errors  = 0;

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    if (shouldSkip($file)) { $skipped++; continue; }

    try {
        $batch[] = buildDocument($file, $INDEX_FOLDER, $MAX_CONTENT_CHARS);
        $total++;
    } catch (\Exception $e) {
        log_msg("ERROR reading {$file->getPathname()}: " . $e->getMessage());
        $errors++;
        continue;
    }

    if (count($batch) >= 50) {
        $client->collections[$COLLECTION_NAME]->documents->import($batch, ['action' => 'upsert']);
        log_msg("Imported batch of 50 (total so far: $total)");
        $batch = [];
    }
}

if (!empty($batch)) {
    $client->collections[$COLLECTION_NAME]->documents->import($batch, ['action' => 'upsert']);
}

log_msg("Done! Indexed: $total | Skipped: $skipped | Errors: $errors");