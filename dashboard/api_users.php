<?php
// api_users.php - list and create users (admin)
require_once __DIR__ . '/api_config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$pdo = db_connect();

if ($action === 'list') {
    dashboard_require_admin($pdo);
    $stmt = $pdo->query('SELECT id,name,email,role,created_at FROM users ORDER BY id ASC');
    $users = $stmt->fetchAll();
    echo json_encode(['users' => $users]); exit;
}

if ($action === 'create') {
    try {
        // only admin can create
        if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Not authorized']); exit; }
        $me = (int)$_SESSION['user_id'];
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$me]);
        $row = $stmt->fetch();
        if (!$row || $row['role'] !== 'admin') { echo json_encode(['error' => 'Not authorized']); exit; }

        $body = getJsonBody();
        $name = trim($body['name'] ?? '');
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $role = in_array($body['role'] ?? 'user', ['admin','user']) ? $body['role'] : 'user';

        if (!$name || !$email || !$password) { echo json_encode(['error' => 'Missing fields']); exit; }

        // check duplicate
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) { echo json_encode(['error' => 'Email already registered']); exit; }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name,email,password,role,created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$name, $email, $hash, $role]);
        $id = (int)$pdo->lastInsertId();
        echo json_encode(['user' => ['id'=>$id,'name'=>$name,'email'=>$email,'role'=>$role]]); exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]); exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
?>