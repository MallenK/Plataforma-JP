<?php
/**
 * Executes seed_data.sql against TiDB Cloud.
 * Usage: docker exec jp_app php /var/www/html/app/Database/Seeds/run_seed.php
 */

$sqlFile = __DIR__ . '/seed_data.sql';
if (!file_exists($sqlFile)) {
    echo "ERROR: seed_data.sql not found. Run generate_seed.php first.\n";
    exit(1);
}

// Load credentials from .env
$envFile = '/var/www/html/.env';
$env = [];
foreach (file($envFile) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    if (strpos($line, '=') === false) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v);
}

$host = $env['database.default.hostname'] ?? '';
$db   = $env['database.default.database'] ?? '';
$user = $env['database.default.username'] ?? '';
$pass = $env['database.default.password'] ?? '';
$port = (int)($env['database.default.port'] ?? 3306);

echo "Connecting to {$host}:{$port}/{$db}...\n";

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
try {
    $sslCa = '/etc/ssl/certs/ca-certificates.crt';
    $opts = [
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 30,
    ];
    if (file_exists($sslCa)) {
        $opts[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
    }
    $pdo = new PDO($dsn, $user, $pass, $opts);
} catch (Exception $e) {
    echo "ERROR connecting: " . $e->getMessage() . "\n";
    exit(1);
}
echo "Connected.\n";

$sql = file_get_contents($sqlFile);

// Split on semicolons. After split, strip leading -- comment lines from each chunk.
$rawParts = explode(";\n", $sql);
$statements = [];
foreach ($rawParts as $part) {
    // Remove comment-only lines, then trim
    $clean = preg_replace('/^[ \t]*--[^\n]*\n?/m', '', $part);
    $clean = trim($clean);
    if ($clean !== '') {
        $statements[] = $clean;
    }
}

$total = count($statements);
echo "Executing {$total} statements...\n";

$errors = 0;
$ok = 0;
foreach ($statements as $i => $stmt) {
    if ($stmt === '') continue;
    try {
        $pdo->exec($stmt);
        $ok++;
    } catch (Exception $e) {
        $errors++;
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate entry') !== false) {
            echo "  SKIP duplicate at stmt " . ($i+1) . "\n";
        } else {
            echo "  ERROR at stmt " . ($i+1) . ": " . substr($msg, 0, 120) . "\n";
            echo "  SQL: " . substr($stmt, 0, 100) . "...\n";
        }
    }
    if (($i + 1) % 10 === 0) {
        echo "  ... " . ($i+1) . "/{$total} done\n";
    }
}

echo "\nDone! OK: {$ok}, Errors/Skipped: {$errors}\n";

// Quick verification
echo "\n--- Verification ---\n";
foreach (['users','locations','bono_types','player_profiles','player_bonos','classes','class_sessions','class_session_players','conversations','messages','notifications','purchase_requests','player_annotations','player_metrics'] as $t) {
    $c = $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
    echo sprintf("  %-30s %d rows\n", $t, $c);
}
