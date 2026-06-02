<?php
/**
 * One-time fix:
 *  1. Add class_format column if missing
 *  2. Delete excess players (keep max 2 per session, oldest rows first)
 *  3. Set class_format = 'pareja' where 2 players remain, 'individual' where 1
 * Usage: docker exec jp_app php /var/www/html/app/Database/Seeds/fix_class_format.php
 */

$envFile = '/var/www/html/.env';
$env = [];
foreach (file($envFile) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v);
}

$pdo = new PDO(
    "mysql:host={$env['database.default.hostname']};port={$env['database.default.port']};dbname={$env['database.default.database']};charset=utf8mb4",
    $env['database.default.username'],
    $env['database.default.password'],
    [
        PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]
);

$user = $pdo->query('SELECT USER()')->fetchColumn();
echo "Connected as: {$user}\n";

if (strpos($user, '3CfRvWaoPimkrS9') !== false) {
    echo "ERROR: Connected to MAIN cluster (production). Aborting.\n";
    exit(1);
}

// ── Step 1: Add class_format column if not exists ────────────────
$col = $pdo->query("
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'class_sessions'
      AND COLUMN_NAME  = 'class_format'
")->fetchColumn();

if (!$col) {
    $pdo->exec("ALTER TABLE class_sessions ADD COLUMN class_format ENUM('individual','pareja') NOT NULL DEFAULT 'individual'");
    $pdo->exec("ALTER TABLE classes       ADD COLUMN class_format ENUM('individual','pareja') NOT NULL DEFAULT 'individual'");
    echo "Step 1: class_format column added.\n";
} else {
    echo "Step 1: class_format column already exists.\n";
}

// ── Step 2: Delete excess players (keep max 2 per session) ───────
// Get all sessions with more than 2 players
$sessions = $pdo->query("
    SELECT session_id, COUNT(*) AS cnt
    FROM class_session_players
    GROUP BY session_id
    HAVING cnt > 2
")->fetchAll(PDO::FETCH_ASSOC);

$totalDeleted = 0;
foreach ($sessions as $s) {
    $sid  = (int)$s['session_id'];
    $keep = 2; // max players per session

    // Get the IDs to keep (first 2 by id ASC)
    $keep_ids = $pdo->query("
        SELECT id FROM class_session_players
        WHERE session_id = {$sid}
        ORDER BY id ASC
        LIMIT {$keep}
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($keep_ids)) continue;

    $in = implode(',', array_map('intval', $keep_ids));
    $deleted = $pdo->exec("
        DELETE FROM class_session_players
        WHERE session_id = {$sid}
          AND id NOT IN ({$in})
    ");
    $totalDeleted += $deleted;
}
echo "Step 2: {$totalDeleted} excess players deleted.\n";

// ── Step 3: Set class_format — pareja only when title says so ───
// Individual by default; pareja only if title explicitly contains
// 'pareja' or 'doble' (case-insensitive).
$pdo->exec("
    UPDATE class_sessions cs
    LEFT JOIN classes c ON c.id = cs.class_id
    SET cs.class_format = CASE
        WHEN (LOWER(cs.title)  LIKE '%pareja%' OR LOWER(cs.title)  LIKE '%doble%'
           OR LOWER(c.title)   LIKE '%pareja%' OR LOWER(c.title)   LIKE '%doble%')
        THEN 'pareja'
        ELSE 'individual'
    END
");
echo "Step 3: class_format set on all sessions (title-based).\n";

// Same for class templates
$pdo->exec("
    UPDATE classes
    SET class_format = CASE
        WHEN (LOWER(title) LIKE '%pareja%' OR LOWER(title) LIKE '%doble%')
        THEN 'pareja'
        ELSE 'individual'
    END
");
echo "Step 3: class_format set on all class templates (title-based).\n";

// ── Summary ──────────────────────────────────────────────────────
$rows = $pdo->query("SELECT class_format, COUNT(*) AS total FROM class_sessions GROUP BY class_format")->fetchAll(PDO::FETCH_ASSOC);
echo "\nclass_sessions summary:\n";
foreach ($rows as $r) echo "  {$r['class_format']}: {$r['total']}\n";

$sample = $pdo->query("
    SELECT cs.id, cs.class_format, COUNT(csp.id) AS players
    FROM class_sessions cs
    LEFT JOIN class_session_players csp ON csp.session_id = cs.id
    GROUP BY cs.id, cs.class_format
    ORDER BY cs.id
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
echo "\nFirst 10 sessions:\n";
foreach ($sample as $r) echo "  session {$r['id']}: {$r['players']} players → {$r['class_format']}\n";
