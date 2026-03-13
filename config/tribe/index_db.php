#!/usr/bin/env php
<?php
/**
 * Typesense DB Indexer
 *
 * Backfills every row in the MySQL `data` table into Typesense.
 * Mirrors the style of index_files.php so the two scripts feel like a pair.
 *
 * Modes
 * ─────
 *   Full re-index (default)
 *     php index_db.php
 *
 *   Single-object upsert (called by Core after a push/update)
 *     php index_db.php <id>
 *
 *   Single-object delete (called by Core after a delete)
 *     php index_db.php <id> delete
 *
 * Environment variables (all inherited from docker-compose / .env)
 * ────────────────────────────────────────────────────────────────
 *   TYPESENSE_HOST     container name, default "{PROJECT_NAME}_typesense"
 *   TYPESENSE_PORT     internal port,  default 8108
 *   TYPESENSE_API_KEY  default "xyz"
 *   DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS
 *   PROJECT_NAME       used to derive container names
 *
 * Skip list (identical to reindex_db.php)
 * ────────────────────────────────────────
 *   Types in SKIP_TYPES are never indexed.
 *   Fields in STRIP_KEYS are removed before indexing.
 */

// ─── Bootstrap ────────────────────────────────────────────────────────────────
// Script lives at /var/www/html/index_db.php — _init.php is in the same dir.
// Composer vendor/ is also at /var/www/html/vendor/autoload.php.
// Both paths are therefore always predictable and never need a directory crawl.

$webRoot = '/var/www/html';

$tribeBootstrapped = false;
$initFile = $webRoot . '/_init.php';
if (file_exists($initFile)) {
    require $initFile;
    $tribeBootstrapped = true;
} else {
    echo '[' . date('Y-m-d H:i:s') . '] WARN: _init.php not found at ' . $initFile . ' — running in standalone PDO mode.' . PHP_EOL;
}

// ─── Composer autoload (for Typesense PHP SDK) ───────────────────────────────
$autoload = $webRoot . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    echo '[' . date('Y-m-d H:i:s') . '] FATAL: vendor/autoload.php not found at ' . $autoload . PHP_EOL;
    exit(1);
}

// ─── Config ───────────────────────────────────────────────────────────────────
const MYSQL_BATCH = 500;   // rows fetched per SELECT
const TS_BATCH    = 100;   // documents per Typesense import call

const SKIP_TYPES = [
    'webapp', 'deleted_record', 'search_sync_failed', 'apikey_record',
];
const STRIP_KEYS = [
    'password_md5', 'mysql_access_log', 'mysql_activity_log',
];

// ─── Typesense connection ─────────────────────────────────────────────────────
$TS_KEY  = getenv('TYPESENSE_API_KEY') ?: ($_ENV['TYPESENSE_API_KEY'] ?? 'xyz');
$TS_PORT = getenv('TYPESENSE_PORT')    ?: ($_ENV['TYPESENSE_PORT']    ?? '8108');
$TS_HOST = getenv('TYPESENSE_HOST')
    ?: ($_ENV['TYPESENSE_HOST']
        ?? ((getenv('PROJECT_NAME') ?: ($_ENV['PROJECT_NAME'] ?? 'tribe')) . '_typesense'));

use Typesense\Client as TsClient;

$tsClient = new TsClient([
    'api_key' => $TS_KEY,
    'nodes'   => [[
        'host'     => $TS_HOST,
        'port'     => $TS_PORT,
        'protocol' => 'http',
    ]],
    'connection_timeout_seconds'   => 10,
    'healthcheck_interval_seconds' => 60,
]);

// ─── MySQL connection ─────────────────────────────────────────────────────────
// Prefer Tribe's MySQL class; fall back to a raw PDO connection.
function db_query(string $sql, $tribeDb = null): array
{
    if ($tribeDb) {
        $result = $tribeDb->executeSQL($sql);
        return is_array($result) ? $result : [];
    }

    static $pdo = null;
    if (!$pdo) {
        $host = getenv('DB_HOST') ?: 'mysql';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: getenv('PROJECT_NAME') ?: 'tribe';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: getenv('DB_ROOT_PASSWORD') ?: '';
        $pdo  = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                        $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    $stmt = $pdo->query($sql);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

$tribeDb   = $tribeBootstrapped ? new \Tribe\MySQL()    : null;
$tribeConf = $tribeBootstrapped ? new \Tribe\Config()   : null;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function log_msg(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

/**
 * Collection name convention: tribe_{type}
 * Mirrors Typesense::getCollectionName() so the two stay in sync.
 */
function collection_name(string $type): string
{
    return "tribe_{$type}";
}

/**
 * Generic auto-schema for types not declared in Config::getTypes().
 * Uses Typesense's wildcard `.*` so every field is auto-indexed.
 */
function generic_schema(string $collName): array
{
    return [
        'name'   => $collName,
        'fields' => [
            ['name' => '.*',              'type' => 'auto'],
            ['name' => 'id',              'type' => 'string'],
            ['name' => 'type',            'type' => 'string', 'facet' => true],
            ['name' => 'content_privacy', 'type' => 'string', 'facet' => true,  'optional' => true],
            ['name' => 'updated_on',      'type' => 'int64',  'optional' => true],
            ['name' => 'created_on',      'type' => 'int64',  'optional' => true],
            ['name' => 'search_content',  'type' => 'string', 'optional' => true],
        ],
        'default_sorting_field' => 'updated_on',
        'token_separators'      => ['-', '_', '.', '@'],
    ];
}

/**
 * Ensure a Typesense collection exists, creating it when absent.
 * Uses typed schema for known Tribe types; generic auto-schema otherwise.
 */
function ensure_collection(
    TsClient $ts,
    string $type,
    string $collName,
    ?object $tribeTs  // \Tribe\Typesense instance, or null
): void {
    try {
        $ts->collections[$collName]->retrieve();
        return; // already exists
    } catch (\Exception $e) {
        // does not exist — fall through to creation
    }

    if ($tribeTs) {
        // Let Tribe create the properly-typed schema
        try {
            $tribeTs->createOrUpdateCollection($type);
            log_msg("  Created typed collection '{$collName}' via Tribe.");
            return;
        } catch (\Exception $e) {
            log_msg("  WARN: Tribe schema creation failed ({$e->getMessage()}), using generic schema.");
        }
    }

    try {
        $ts->collections->create(generic_schema($collName));
        log_msg("  Created generic collection '{$collName}'.");
    } catch (\Exception $e2) {
        log_msg("  ERROR creating collection '{$collName}': " . $e2->getMessage());
    }
}

/**
 * Transform a raw Tribe DB row into a Typesense document.
 * id is always cast to string (Typesense requirement).
 */
function to_document(array $obj): array
{
    foreach (STRIP_KEYS as $k) {
        unset($obj[$k]);
    }

    $doc = [
        'id'              => (string)($obj['id']),
        'type'            => (string)($obj['type']            ?? ''),
        'slug'            => (string)($obj['slug']            ?? ''),
        'content_privacy' => (string)($obj['content_privacy'] ?? 'public'),
        'updated_on'      => (int)($obj['updated_on']         ?? time()),
        'created_on'      => (int)($obj['created_on']         ?? time()),
        'search_content'  => '',
    ];

    if (isset($obj['user_id'])) {
        $doc['user_id'] = (int)$obj['user_id'];
    }

    $textParts = [];
    foreach ($obj as $k => $v) {
        if (array_key_exists($k, $doc)) continue;   // already handled
        if ($v === null || $v === '')   continue;

        if (is_bool($v))      { $doc[$k] = $v; }
        elseif (is_int($v))   { $doc[$k] = $v; }
        elseif (is_float($v)) { $doc[$k] = $v; }
        elseif (is_array($v)) {
            $flat      = implode(', ', array_filter($v, 'is_scalar'));
            $doc[$k]   = $flat;
            $textParts[] = $flat;
        } else {
            $doc[$k]     = (string)$v;
            $textParts[] = (string)$v;
        }
    }

    $doc['search_content'] = implode(' ', array_filter($textParts));
    return $doc;
}

/**
 * Parse Typesense's JSONL import response (same logic as reindex_db.php).
 */
function parse_import_response($raw): array
{
    $ok = 0; $fail = 0; $failMsgs = [];

    if (is_array($raw)) {
        foreach ($raw as $line) {
            if ($line['success'] ?? false) {
                $ok++;
            } else {
                $fail++;
                $failMsgs[] = $line['error'] ?? '?';
            }
        }
    } else {
        foreach (explode("\n", trim((string)$raw)) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $d = json_decode($line, true);
            if ($d === null) { $fail++; $failMsgs[] = "unparseable: $line"; continue; }
            if ($d['success'] ?? false) $ok++;
            else { $fail++; $failMsgs[] = $d['error'] ?? '?'; }
        }
    }

    return ['ok' => $ok, 'fail' => $fail, 'errors' => array_unique($failMsgs)];
}

// ─── Wait for Typesense ───────────────────────────────────────────────────────
function wait_for_typesense(TsClient $ts, int $maxWait = 60): void
{
    $waited = 0;
    while ($waited < $maxWait) {
        try {
            $ts->health->retrieve(3);
            log_msg("Typesense is ready.");
            return;
        } catch (\Exception $e) {
            log_msg("Typesense not ready ({$waited}s)…");
            sleep(3);
            $waited += 3;
        }
    }
    throw new \RuntimeException("Typesense did not become ready after {$maxWait}s");
}

// ─── Hydrate full objects from raw DB rows ────────────────────────────────────
function hydrate_rows(array $rows): array
{
    $objects = [];
    foreach ($rows as $row) {
        $obj = json_decode($row['content'] ?? '{}', true) ?: [];
        $obj['id']         = (int)$row['id'];
        $obj['updated_on'] = (int)$row['updated_on'];
        $obj['created_on'] = (int)$row['created_on'];
        // Prefer the real indexed `type` column over the JSON-decoded value
        // so the type is always available even if content JSON is malformed.
        if (!empty($row['type'])) {
            $obj['type'] = $row['type'];
        }
        $objects[] = $obj;
    }
    return $objects;
}

// ─── Tribe helpers (only used when bootstrapped) ──────────────────────────────
$tribeTs = null;
if ($tribeBootstrapped) {
    try {
        $tribeTs = new \Tribe\Typesense();
    } catch (\Exception $e) {
        log_msg("WARN: Could not instantiate Tribe\\Typesense: " . $e->getMessage());
    }
}

// ─── Main ─────────────────────────────────────────────────────────────────────
wait_for_typesense($tsClient);

$singleId = $argv[1] ?? null;
$mode     = strtolower($argv[2] ?? '');

// ── Single-object mode ────────────────────────────────────────────────────────
if ($singleId !== null) {
    $id = (int)$singleId;

    if ($mode === 'delete') {
        // Determine type from DB before we lose the row
        $rows = db_query("SELECT `id`, `type`, `content`, `updated_on`, `created_on` FROM `data` WHERE `id`={$id} LIMIT 1", $tribeDb);
        if (empty($rows)) {
            log_msg("Object {$id} not found in DB (already deleted?).");
            exit(0);
        }
        $obj  = hydrate_rows($rows)[0];
        $type = $obj['type'] ?? '';

        if (!$type || in_array($type, SKIP_TYPES)) {
            log_msg("Skipping delete for type '{$type}': {$id}");
            exit(0);
        }

        $collName = collection_name($type);
        try {
            $tsClient->collections[$collName]->documents[(string)$id]->delete();
            log_msg("Deleted from Typesense [{$type}]: {$id}");
        } catch (\Exception $e) {
            log_msg("Not in Typesense (already removed?): {$id} — " . $e->getMessage());
        }
        exit(0);
    }

    // Upsert mode (create or update)
    $rows = db_query("SELECT `id`, `type`, `content`, `updated_on`, `created_on` FROM `data` WHERE `id`={$id} LIMIT 1", $tribeDb);
    if (empty($rows)) {
        log_msg("Object {$id} not found in DB.");
        exit(1);
    }

    $obj  = hydrate_rows($rows)[0];
    $type = $obj['type'] ?? '';

    if (!$type || in_array($type, SKIP_TYPES)) {
        log_msg("Skipping type '{$type}': {$id}");
        exit(0);
    }

    $collName = collection_name($type);
    ensure_collection($tsClient, $type, $collName, $tribeTs);

    try {
        $doc = to_document($obj);
        $tsClient->collections[$collName]->documents->upsert($doc);
        log_msg("Upserted [{$type}]: {$id}");
    } catch (\Exception $e) {
        log_msg("ERROR upserting {$id}: " . $e->getMessage());
        exit(1);
    }
    exit(0);
}

// ── Full re-index mode ────────────────────────────────────────────────────────
$skipList = implode("','", SKIP_TYPES);

log_msg("Starting full DB re-index…");

$stats  = [];
$offset = 0;

do {
    // Fetch a batch of raw rows directly (content column is JSON)
    $rows = db_query(
        "SELECT `id`, `type`, `content`, `updated_on`, `created_on` FROM `data`
         WHERE `type` NOT IN ('{$skipList}')
           AND `type` IS NOT NULL
           AND `type` != ''
         ORDER BY `id` ASC
         LIMIT {$offset}, " . MYSQL_BATCH,
        $tribeDb
    );

    if (empty($rows)) break;

    $objects = hydrate_rows($rows);

    // Group by type for batch import
    $byType = [];
    foreach ($objects as $obj) {
        $t = $obj['type'] ?? null;
        if (!$t || in_array($t, SKIP_TYPES)) continue;
        $byType[$t][] = to_document($obj);
    }

    foreach ($byType as $type => $docs) {
        $collName = collection_name($type);
        ensure_collection($tsClient, $type, $collName, $tribeTs);

        foreach (array_chunk($docs, TS_BATCH) as $batch) {
            try {
                $raw    = $tsClient->collections[$collName]->documents->import(
                    $batch, ['action' => 'upsert']
                );
                $parsed = parse_import_response($raw);

                $stats[$type]['ok']   = ($stats[$type]['ok']   ?? 0) + $parsed['ok'];
                $stats[$type]['fail'] = ($stats[$type]['fail'] ?? 0) + $parsed['fail'];

                foreach ($parsed['errors'] as $em) {
                    log_msg("  WARN [{$type}] {$em}");
                }
            } catch (\Exception $e) {
                log_msg("  ERROR importing to '{$collName}': " . $e->getMessage());
                $stats[$type]['fail'] = ($stats[$type]['fail'] ?? 0) + count($batch);
            }
        }
    }

    $offset += MYSQL_BATCH;
    log_msg("Processed offset {$offset}…");

} while (count($rows) === MYSQL_BATCH);

// ── Summary ───────────────────────────────────────────────────────────────────
log_msg("\n── Results ─────────────────────────────────────────────────────────");
$totalOk = $totalFail = 0;

ksort($stats);
foreach ($stats as $type => $s) {
    $ok   = $s['ok']   ?? 0;
    $fail = $s['fail'] ?? 0;
    $totalOk   += $ok;
    $totalFail += $fail;
    $icon = $fail > 0 ? '⚠' : '✓';
    log_msg("  {$icon}  " . collection_name($type) . ": {$ok} indexed, {$fail} failed");
}

log_msg("\nTotal indexed: {$totalOk}  |  Total failed: {$totalFail}");
log_msg("Done.");
