<?php
// Database settings. Values are loaded from the process environment first, then
// from a local .env file, and finally from safe fallback values that are suitable
// for shared-host deployment. Do not commit real credentials.
function dashboard_env($key, $fallback = null) {
    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        $value = $_ENV[$key] ?? null;
    }
    if ($value === false || $value === null || $value === '') {
        $dotenv = __DIR__ . '/.env';
        if (is_readable($dotenv)) {
            $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') === false) {
                    continue;
                }
                [$envKey, $envValue] = array_map('trim', explode('=', $line, 2));
                if ($envKey === $key) {
                    $value = trim($envValue, " \t\n\r\0\x0B\"'");
                    $_ENV[$key] = $value;
                    putenv($key . '=' . $value);
                    break;
                }
            }
        }
    }
    if ($value === false || $value === null || $value === '') {
        return $fallback;
    }
    return $value;
}

$dotenvFile = __DIR__ . '/.env';
if (is_readable($dotenvFile)) {
    $lines = file($dotenvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key !== '') {
                $_ENV[$key] = trim($value, " \t\n\r\0\x0B\"'");
                putenv($key . '=' . $_ENV[$key]);
            }
        }
    }
}

$db = [
    'host' => dashboard_env('DASHBOARD_DB_HOST', 'localhost'),
    'port' => (int)dashboard_env('DASHBOARD_DB_PORT', '3306'),
    'dbname' => dashboard_env('DASHBOARD_DB_NAME', 'kitbinto_dashboard_db'),
    'user' => dashboard_env('DASHBOARD_DB_USER', 'kitbinto_dashboard_user'),
    // Credentials must be supplied through the environment or a local .env file.
    'pass' => dashboard_env('DASHBOARD_DB_PASS', ''),
    'charset' => 'utf8mb4',
];

function db_connect() {
    global $db;
    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['dbname']};charset={$db['charset']}";
    try {
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        error_log('Dashboard database connection failed: ' . $e->getMessage());
        echo json_encode(['error' => 'Database connection failed. Update the project credentials in api_config.php or your hosting environment settings.']);
        exit;
    }
}

// helper: read JSON POST body
function getJsonBody() {
    $data = file_get_contents('php://input');
    if (!$data) return null;
    $json = json_decode($data, true);
    return $json;
}

function dashboard_start_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function dashboard_json($payload, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function dashboard_csrf_token() {
    dashboard_start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function dashboard_require_csrf() {
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($provided === '') {
        $body = getJsonBody();
        $provided = is_array($body) ? ($body['csrf_token'] ?? '') : '';
    }
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!$expected || !$provided || !hash_equals($expected, $provided)) {
        dashboard_json(['error' => 'Invalid CSRF token'], 419);
    }
}

function dashboard_require_admin(PDO $pdo) {
    dashboard_start_session();
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId < 1) {
        dashboard_json(['error' => 'Authentication required'], 401);
    }

    $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || $user['role'] !== 'admin') {
        dashboard_json(['error' => 'Administrator access required'], 403);
    }
    return $user;
}

function dashboard_require_method($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        dashboard_json(['error' => 'Method not allowed'], 405);
    }
}
?>