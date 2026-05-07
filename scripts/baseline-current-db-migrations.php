<?php

declare(strict_types=1);

$dryRun = in_array('--dry-run', $argv, true);

$root = dirname(__DIR__);
$envPath = $root . DIRECTORY_SEPARATOR . '.env';

if (! is_file($envPath)) {
    fwrite(STDERR, ".env not found at {$envPath}" . PHP_EOL);
    exit(1);
}

$env = file_get_contents($envPath);
if ($env === false) {
    fwrite(STDERR, "Unable to read .env" . PHP_EOL);
    exit(1);
}

function envValue(string $env, string $key, ?string $default = null): ?string
{
    $pattern = '/^[ \t]*' . preg_quote($key, '/') . '[ \t]*=[ \t]*([^\r\n]*)$/m';
    if (! preg_match($pattern, $env, $matches)) {
        return $default;
    }

    $value = trim($matches[1]);
    $value = trim($value, "\"'");

    return $value;
}

$host = envValue($env, 'database.default.hostname', 'localhost');
$database = envValue($env, 'database.default.database');
$username = envValue($env, 'database.default.username', 'root');
$password = envValue($env, 'database.default.password', '');
$port = (int) envValue($env, 'database.default.port', '3306');

if ($database === null || $database === '') {
    fwrite(STDERR, "database.default.database is missing in .env" . PHP_EOL);
    exit(1);
}

$targets = [
    ['version' => '2026-04-28-080000', 'class' => 'App\Database\Migrations\CreateCustomersTable', 'table' => 'customers'],
    ['version' => '2026-04-28-080100', 'class' => 'App\Database\Migrations\CreateCustomOrdersTable', 'table' => 'customorders'],
    ['version' => '2026-04-28-080200', 'class' => 'App\Database\Migrations\CreateDailyEmployeesRegisterTable', 'table' => 'daily_employees_register'],
    ['version' => '2026-04-28-080300', 'class' => 'App\Database\Migrations\CreateDailyExpenseRegisterTable', 'table' => 'daily_expense_register'],
    ['version' => '2026-04-28-080400', 'class' => 'App\Database\Migrations\CreateDailyProductsRegisterTable', 'table' => 'daily_products_register'],
    ['version' => '2026-04-28-080500', 'class' => 'App\Database\Migrations\CreateDailyRawmaterialsRegisterTable', 'table' => 'daily_rawmaterials_register'],
    ['version' => '2026-04-28-080600', 'class' => 'App\Database\Migrations\CreateEmployeesTable', 'table' => 'employees'],
    ['version' => '2026-04-28-080700', 'class' => 'App\Database\Migrations\CreateExpensesTable', 'table' => 'expenses'],
    ['version' => '2026-04-28-080800', 'class' => 'App\Database\Migrations\CreateInvoiceTable', 'table' => 'invoice'],
    ['version' => '2026-04-28-080900', 'class' => 'App\Database\Migrations\CreateInvoiceProductsTable', 'table' => 'invoiceproducts'],
    ['version' => '2026-04-28-081000', 'class' => 'App\Database\Migrations\CreateNotificationsTable', 'table' => 'notifications'],
    ['version' => '2026-04-28-081100', 'class' => 'App\Database\Migrations\CreateOrdersTable', 'table' => 'orders'],
    ['version' => '2026-04-28-081200', 'class' => 'App\Database\Migrations\CreateQuotationTable', 'table' => 'quotation'],
    ['version' => '2026-04-28-081300', 'class' => 'App\Database\Migrations\CreateQoutationProductsTable', 'table' => 'qoutationproducts'],
    ['version' => '2026-04-28-081400', 'class' => 'App\Database\Migrations\CreateRawMaterialsTable', 'table' => 'raw_materials'],
    ['version' => '2026-04-28-081500', 'class' => 'App\Database\Migrations\CreateReceiptTable', 'table' => 'receipt'],
    ['version' => '2026-04-28-081600', 'class' => 'App\Database\Migrations\CreateTransactionIdsTable', 'table' => 'transaction_ids'],
];

$mysqli = @new mysqli($host, $username, $password, $database, $port);
if ($mysqli->connect_error) {
    fwrite(STDERR, "Database connection failed: {$mysqli->connect_error}" . PHP_EOL);
    exit(1);
}

$missingTables = [];
foreach ($targets as $target) {
    $tableName = $mysqli->real_escape_string($target['table']);
    $result = $mysqli->query("SHOW TABLES LIKE '{$tableName}'");
    if ($result->num_rows === 0) {
        $missingTables[] = $target['table'];
    }
}

if ($missingTables !== []) {
    fwrite(STDERR, 'Cannot baseline because these tables are missing: ' . implode(', ', $missingTables) . PHP_EOL);
    exit(1);
}

$existingVersions = [];
$result = $mysqli->query("SELECT version FROM migrations WHERE namespace = 'App'");
while ($row = $result->fetch_assoc()) {
    $existingVersions[$row['version']] = true;
}

$pending = array_values(array_filter(
    $targets,
    static fn(array $target): bool => ! isset($existingVersions[$target['version']])
));

if ($pending === []) {
    echo "No baseline changes needed. All target migrations are already recorded." . PHP_EOL;
    exit(0);
}

$backupDir = $root . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'migration-baseline-backups';
if (! is_dir($backupDir) && ! mkdir($backupDir, 0777, true) && ! is_dir($backupDir)) {
    fwrite(STDERR, "Unable to create backup directory: {$backupDir}" . PHP_EOL);
    exit(1);
}

$backupRows = [];
$result = $mysqli->query('SELECT id, version, class, `group`, namespace, time, batch FROM migrations ORDER BY id');
while ($row = $result->fetch_assoc()) {
    $backupRows[] = $row;
}

$backupPath = $backupDir . DIRECTORY_SEPARATOR . 'migrations-before-baseline-' . date('Ymd_His') . '.json';
file_put_contents($backupPath, json_encode($backupRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo 'Database: ' . $database . PHP_EOL;
echo 'Backup saved to: ' . $backupPath . PHP_EOL;
echo 'Pending baseline migrations:' . PHP_EOL;
foreach ($pending as $target) {
    echo ' - ' . $target['version'] . ' => ' . $target['table'] . PHP_EOL;
}

if ($dryRun) {
    echo PHP_EOL . 'Dry run only. No changes were written.' . PHP_EOL;
    exit(0);
}

$baselineBatch = 1;
$baselineTime = time();
$nextId = 1;

$result = $mysqli->query('SELECT COALESCE(MAX(id), 0) AS max_id FROM migrations');
$row = $result->fetch_assoc();
if ($row !== null) {
    $nextId = ((int) $row['max_id']) + 1;
}

$insertStmt = $mysqli->prepare(
    'INSERT INTO migrations (id, version, class, `group`, namespace, time, batch) VALUES (?, ?, ?, ?, ?, ?, ?)'
);

$mysqli->begin_transaction();

try {
    foreach ($pending as $target) {
        $group = 'default';
        $namespace = 'App';
        $version = $target['version'];
        $class = $target['class'];
        $currentId = $nextId++;
        $insertStmt->bind_param('issssii', $currentId, $version, $class, $group, $namespace, $baselineTime, $baselineBatch);
        $insertStmt->execute();
    }

    $mysqli->commit();
} catch (Throwable $e) {
    $mysqli->rollback();
    fwrite(STDERR, 'Baseline failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo PHP_EOL . 'Baseline complete. Added ' . count($pending) . ' migration record(s) to batch ' . $baselineBatch . '.' . PHP_EOL;
echo 'Recommendation: avoid migrate:rollback on this imported database unless you intend to remove baseline tables.' . PHP_EOL;
