<?php
// api_auth.php - simple auth endpoints: login, signup, logout, current
require_once __DIR__ . '/api_config.php';
dashboard_start_session();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$pdo = db_connect();

if ($action === 'login') {
    $body = getJsonBody();
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    if (!$email || !$password) {
        echo json_encode(['error' => 'Missing credentials']); exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) { echo json_encode(['error' => 'Invalid email or password']); exit; }

    // Accept plain-text seed passwords OR hashed passwords
    $stored = $user['password'];
    $valid = false;
    if ($stored === $password) $valid = true;
    elseif (password_verify($password, $stored)) $valid = true;

    if (!$valid) { echo json_encode(['error' => 'Invalid email or password']); exit; }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    dashboard_csrf_token();
    unset($user['password']);
    echo json_encode(['user' => $user]);
    exit;
}

if ($action === 'signup') {
    try {
        dashboard_require_admin($pdo);
        $body = getJsonBody();
        $name = trim($body['name'] ?? '');
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        // Public signup can never create an administrator.
        $role = 'user';

        if (!$name || !$email || !$password) { echo json_encode(['error' => 'Missing fields']); exit; }

        // check unique
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) { echo json_encode(['error' => 'Email already registered']); exit; }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name,email,password,role,created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$name, $email, $hash, $role]);
        $id = (int)$pdo->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        dashboard_csrf_token();
        echo json_encode(['user' => ['id' => $id, 'name' => $name, 'email' => $email, 'role' => $role]]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
        exit;
    }
}


if ($action === 'logout') {
    session_unset(); session_destroy();
    echo json_encode(['ok' => true]); exit;
}

if ($action === 'current') {
    if (!isset($_SESSION['user_id'])) { echo json_encode(['user' => null]); exit; }
    $id = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT id,name,email,role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) { echo json_encode(['user' => null]); exit; }
    echo json_encode(['user' => $user]); exit;
}

// admin route: set current user (useful for "login as")
if ($action === 'set') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'Missing id']); exit; }
    // make sure requester is logged in and admin
    if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Not authorized']); exit; }
    $me = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$me]);
    $row = $stmt->fetch();
    if (!$row || $row['role'] !== 'admin') { echo json_encode(['error' => 'Not authorized']); exit; }

    // set session
    $_SESSION['user_id'] = $id;
    $stmt = $pdo->prepare('SELECT id,name,email,role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    echo json_encode(['user' => $user]); exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
?>