<?php
/**
 * Database Connection
 * PrintFlow - Printing Shop PWA
 */

/**
 * Read env from getenv / $_ENV / $_SERVER (Apache on Windows often omits getenv).
 */
function printflow_env(string $name): string|false {
    $v = getenv($name);
    if ($v !== false) {
        return $v;
    }
    if (isset($_ENV[$name])) {
        return (string) $_ENV[$name];
    }
    if (isset($_SERVER[$name])) {
        return (string) $_SERVER[$name];
    }
    return false;
}

/** Minimal .env loader (project root .env — see .env.example). */
function printflow_load_dotenv(string $path): array {
    if (!is_readable($path)) {
        return [];
    }
    $raw = @file($path, FILE_IGNORE_NEW_LINES);
    if ($raw === false) {
        return [];
    }
    if (isset($raw[0]) && strncmp($raw[0], "\xEF\xBB\xBF", 3) === 0) {
        $raw[0] = substr($raw[0], 3);
    }
    $out = [];
    foreach ($raw as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\"'");
        if ($k !== '') {
            $out[$k] = $v;
        }
    }
    return $out;
}

/** Obvious .env placeholders — never send these to MySQL as real passwords. */
function printflow_is_placeholder_db_pass(string $pass): bool {
    $p = strtolower(trim($pass));
    $bad = [
        'your_mysql_password_here',
        'your_password_here',
        'changeme',
        'password',
        'secret',
        'example',
    ];
    return in_array($p, $bad, true);
}

// Merge order: defaults → .env file → OS env → includes/db.local.php (local wins).
$db_config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '122704',
    'name' => 'printflow_3',
    'port' => 3306,
    'socket' => '',
];
$envKeys = [
    'host' => 'PRINTFLOW_DB_HOST',
    'user' => 'PRINTFLOW_DB_USER',
    'pass' => 'PRINTFLOW_DB_PASS',
    'name' => 'PRINTFLOW_DB_NAME',
    'port' => 'PRINTFLOW_DB_PORT',
    'socket' => 'PRINTFLOW_DB_SOCKET',
];
$root = dirname(__DIR__);
$dot = printflow_load_dotenv($root . DIRECTORY_SEPARATOR . '.env');
foreach ($envKeys as $key => $envName) {
    if (isset($dot[$envName])) {
        $db_config[$key] = $dot[$envName];
    }
}
foreach ($envKeys as $key => $envName) {
    $v = printflow_env($envName);
    if ($v !== false) {
        $db_config[$key] = $v;
    }
}
$__db_local = __DIR__ . '/db.local.php';
if (is_readable($__db_local)) {
    $__local = require $__db_local;
    if (is_array($__local)) {
        foreach ($__local as $k => $v) {
            if (array_key_exists($k, $db_config)) {
                $db_config[$k] = $v;
            }
        }
    }
}

if (printflow_is_placeholder_db_pass((string) $db_config['pass'])) {
    die(
        '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:2rem auto;padding:1rem;">'
        . '<h1 style="font-size:1.1rem;">Database password not configured</h1>'
        . '<p><code>PRINTFLOW_DB_PASS</code> in <code>.env</code> is still a <strong>placeholder</strong> (e.g. <code>your_mysql_password_here</code>). '
        . 'Replace it with the real password you use for MySQL in phpMyAdmin, or remove that line to use an empty password (XAMPP default).</p>'
        . '<p>File: <code>' . htmlspecialchars($root . DIRECTORY_SEPARATOR . '.env', ENT_QUOTES, 'UTF-8') . '</code></p>'
        . '</div>'
    );
}

define('DB_HOST', $db_config['host']);
define('DB_USER', $db_config['user']);
define('DB_PASS', $db_config['pass']);
define('DB_NAME', $db_config['name']);
define('DB_PORT', (int) $db_config['port']);
define('DB_SOCKET', (string) $db_config['socket']);

/**
 * Create a mysqli connection and gracefully handle mysqli_sql_exception.
 * Returns null on failure and sets $lastError.
 */
function printflow_try_connect(
    string $host,
    string $user,
    string $pass,
    string $name,
    int $port,
    string $socket,
    string &$lastError
): ?mysqli {
    try {
        $sock = $socket !== '' ? $socket : null;
        return new mysqli($host, $user, $pass, $name, $port, $sock);
    } catch (mysqli_sql_exception $e) {
        $lastError = $e->getMessage();
        return null;
    }
}

$connectError = '';
$conn = printflow_try_connect(
    DB_HOST,
    DB_USER,
    DB_PASS,
    DB_NAME,
    DB_PORT,
    DB_SOCKET,
    $connectError
);

// Windows/XAMPP fallback: localhost can resolve to IPv6 (::1) and be refused.
if (!$conn && strtolower(DB_HOST) === 'localhost') {
    $conn = printflow_try_connect(
        '127.0.0.1',
        DB_USER,
        DB_PASS,
        DB_NAME,
        DB_PORT,
        DB_SOCKET,
        $connectError
    );
}

// Check connection
if (!$conn || $conn->connect_error) {
    $rawError = $conn ? $conn->connect_error : $connectError;
    $msg = htmlspecialchars($rawError, ENT_QUOTES, 'UTF-8');
    $hint = '';
    if (stripos($rawError, 'No connection could be made') !== false || stripos($rawError, 'Connection refused') !== false) {
        $hint = '<p><strong>MySQL service is not reachable.</strong> This usually means MySQL is not running, host/port is wrong, or localhost resolves to an unavailable interface.</p>'
            . '<ul>'
            . '<li>Start <strong>MySQL</strong> from the XAMPP Control Panel.</li>'
            . '<li>Check host/port in <code>.env</code>: '
            . '<code>PRINTFLOW_DB_HOST=127.0.0.1</code>, <code>PRINTFLOW_DB_PORT=3306</code> (or your real port).</li>'
            . '<li>If your MySQL uses a custom port, update <code>PRINTFLOW_DB_PORT</code> to match.</li>'
            . '</ul>';
    } elseif (stripos($rawError, 'Access denied') !== false) {
        if (DB_PASS === '') {
            $hint = '<p><strong>MySQL rejected <code>root</code> with no password</strong> (that is what “using password: NO” means). '
                . 'Set your real password in one place:</p><ul>'
                . '<li>Create <code>' . htmlspecialchars($root, ENT_QUOTES, 'UTF-8') . DIRECTORY_SEPARATOR . '.env</code> '
                . '(copy from <code>.env.example</code>) and set <code>PRINTFLOW_DB_PASS=...</code>, <strong>or</strong></li>'
                . '<li>Edit <code>includes/db.local.php</code> and set <code>\'pass\' => \'your_mysql_password\'</code> (remove any line that sets pass to an empty string).</li>'
                . '</ul>';
        } else {
            $hint = '<p><strong>Wrong password or user.</strong> “using password: YES” means a password was sent but MySQL did not accept it. '
                . 'Open phpMyAdmin and confirm the password for <code>' . htmlspecialchars(DB_USER, ENT_QUOTES, 'UTF-8') . '</code>, then set the same value in '
                . '<code>.env</code> as <code>PRINTFLOW_DB_PASS=...</code> (no quotes) or <code>\'pass\' => \'...\'</code> in <code>includes/db.local.php</code>.</p>'
                . '<p>Also confirm <code>PRINTFLOW_DB_NAME=' . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . '</code> exists in MySQL.</p>';
        }
    }
    die(
        '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:2rem auto;padding:1rem;">'
        . '<h1 style="font-size:1.1rem;">Database connection failed</h1>'
        . '<p>' . $msg . '</p>'
        . '<p><strong>Connection target:</strong> <code>'
        . htmlspecialchars(DB_HOST, ENT_QUOTES, 'UTF-8') . ':' . htmlspecialchars((string) DB_PORT, ENT_QUOTES, 'UTF-8')
        . '</code> / DB <code>' . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . '</code></p>'
        . $hint
        . '</div>'
    );
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

require_once __DIR__ . '/ensure_products_schema.php';
printflow_ensure_products_product_type_column();

require_once __DIR__ . '/ensure_services_table.php';
ensure_services_table();

require_once __DIR__ . '/ensure_order_messages_schema.php';
printflow_ensure_order_messages_schema();

require_once __DIR__ . '/ensure_orders_status_schema.php';
printflow_ensure_orders_status_schema();

/**
 * Prepare and execute a SQL statement safely
 * @param string $sql SQL query with placeholders
 * @param string $types Parameter types (e.g., 'ssi' for string, string, integer)
 * @param array $params Array of parameters
 * @return mysqli_stmt|false
 */
function db_prepare($sql, $types = '', $params = []) {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Database prepare error: " . $conn->error);
        return false;
    }
    
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    return $stmt;
}

/**
 * Execute a query and return results as associative array
 * @param string $sql SQL query
 * @param string $types Parameter types
 * @param array $params Parameters
 * @return array|false
 */
function db_query($sql, $types = '', $params = []) {
    global $conn;

    // No params: use query() directly (avoids get_result() which requires mysqlnd)
    if (empty($types) && empty($params)) {
        $result = $conn->query($sql);
        if (!$result) {
            error_log("Database query error: " . $conn->error);
            return false;
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
        return $rows;
    }

    $stmt = db_prepare($sql, $types, $params);
    if (!$stmt) return false;
    
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        return false;
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        error_log("Database get_result failed (mysqlnd may be missing): " . $stmt->error);
        return false;
    }
    
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    
    $stmt->close();
    return $rows;
}

/**
 * Execute an INSERT/UPDATE/DELETE query
 * @param string $sql SQL query
 * @param string $types Parameter types
 * @param array $params Parameters
 * @return bool|int Returns true for success, false for failure, or last insert ID
 */
function db_execute($sql, $types = '', $params = []) {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Database prepare error: " . $conn->error);
        return false;
    }
    
    if (!empty($types) && !empty($params)) {
        if (strpos($types, 'b') !== false) {
            // Handle binary data using send_long_data
            $null = null;
            $bind_params = [];
            $type_arr = str_split($types);
            
            $bind_refs = [$types];
            foreach ($type_arr as $i => $t) {
                if ($t === 'b') {
                    $bind_params[$i] = $null;
                } else {
                    $bind_params[$i] = $params[$i];
                }
            }
            $stmt->bind_param($types, ...$bind_params);
            
            foreach ($type_arr as $i => $t) {
                if ($t === 'b' && $params[$i] !== null) {
                    $stmt->send_long_data($i, $params[$i]);
                }
            }
        } else {
            $stmt->bind_param($types, ...$params);
        }
    }
    
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $insert_id = $stmt->insert_id;
    $stmt->close();
    
    return $insert_id > 0 ? $insert_id : true;
}

/**
 * Escape string for SQL queries (use prepared statements instead when possible)
 * @param string $str
 * @return string
 */
function db_escape($str) {
    global $conn;
    return $conn->real_escape_string($str);
}

/**
 * Close database connection
 */
function db_close() {
    global $conn;
    if ($conn) {
        $conn->close();
    }
}
