<?php
/**
 * Dawaam - Local Business Continuity Software
 * Connected System User Authentication REST API
 * Endpoint: /api/v1/auth/verify_user.php
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. POST required.'
    ]);
    exit;
}

// Parse Input Payload (JSON or Form)
$input_raw = file_get_contents('php://input');
$data = json_decode($input_raw, true);

if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

$user_code = trim($data['user_code'] ?? $data['username'] ?? '');
$password = $data['password'] ?? '';

if (empty($user_code) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: user_code (or username) and password are required.'
    ]);
    exit;
}

$pdo = get_db_connection();

// Query User by user_code OR username
$stmt = $pdo->prepare("
    SELECT u.id, u.user_code, u.name, u.username, u.email, u.password_hash, u.status,
           r.name AS role_name, r.slug AS role_slug
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    WHERE u.user_code = :user_code OR u.username = :username
    LIMIT 1
");
$stmt->execute([':user_code' => $user_code, ':username' => $user_code]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    log_audit_action('API_AUTH_FAILURE', 'security', null, "Invalid API authentication attempt for user code: {$user_code}");
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid User ID code or password.'
    ]);
    exit;
}

// Check Account Active Status
if ($user['status'] !== 'active') {
    log_audit_action('API_AUTH_DISABLED', 'security', $user['id'], "API login attempted for {$user['status']} account: {$user['user_code']}");
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => "User account status is '{$user['status']}'. Login access disabled."
    ]);
    exit;
}

// Fetch Assigned Permissions Array
$permissions = get_user_permission_keys($user['id']);
$is_super = ($user['role_slug'] === 'super_admin');

log_audit_action('API_AUTH_SUCCESS', 'users', $user['id'], "Connected system verified user {$user['user_code']} ({$user['name']})");

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'User authenticated successfully.',
    'timestamp' => date('c'),
    'user' => [
        'user_id' => (int)$user['id'],
        'user_code' => $user['user_code'],
        'name' => $user['name'],
        'username' => $user['username'],
        'email' => $user['email'] ?? '',
        'status' => $user['status'],
        'role' => $user['role_name'] ?? 'Regular User',
        'role_slug' => $user['role_slug'] ?? 'user',
        'is_super_admin' => $is_super,
        'permissions' => $permissions
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
