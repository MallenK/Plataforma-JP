<?php
/**
 * Cleans up partially inserted seed data (IDs >= 1001).
 * Usage: docker exec jp_app php /var/www/html/app/Database/Seeds/cleanup_seed.php
 */

$envFile = '/var/www/html/.env';
$env = [];
foreach (file($envFile) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v);
}

$dsn = "mysql:host={$env['database.default.hostname']};port={$env['database.default.port']};dbname={$env['database.default.database']};charset=utf8mb4";
$pdo = new PDO($dsn, $env['database.default.username'], $env['database.default.password'], [
    PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
echo "Connected.\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

$tables = [
    'class_session_players'  => 'id',
    'class_session_coaches'  => 'session_id',  // no id col yet - delete by session_id
    'class_sessions'         => 'id',
    'classes'                => 'id',
    'player_bonos'           => 'id',
    'bono_types'             => 'id',
    'player_metrics'         => 'id',
    'player_annotations'     => 'id',
    'player_profiles'        => 'id',
    'purchase_requests'      => 'id',
    'notification_recipients'=> 'notification_id',
    'notifications'          => 'id',
    'messages'               => 'id',
    'conversations'          => 'id',
    'locations'              => 'id',
    'users'                  => 'id',
];

foreach ($tables as $table => $col) {
    try {
        $stmt = $pdo->exec("DELETE FROM {$table} WHERE {$col} >= 1000");
        echo "  {$table}: deleted {$stmt} rows\n";
    } catch (Exception $e) {
        // class_session_coaches might need different handling
        try {
            // Try via session_id join
            $stmt2 = $pdo->exec("DELETE FROM {$table} WHERE session_id >= 1001");
            echo "  {$table}: deleted {$stmt2} rows (via session_id)\n";
        } catch (Exception $e2) {
            echo "  {$table}: skip ({$e2->getMessage()})\n";
        }
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "\nDone. Verification:\n";
foreach (array_keys($tables) as $t) {
    $c = $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
    echo "  {$t}: {$c} rows\n";
}
