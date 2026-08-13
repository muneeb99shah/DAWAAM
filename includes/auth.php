<?php
/**
 * Dawaam - Local Business Continuity Software
 * Authentication & Granular Access Control (RBAC) Library
 */

require_once __DIR__ . '/functions.php';

/**
 * Check if current session user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Fetch authenticated user data with roles and permissions
 */
function current_user($force_refresh = false) {
    static $user_cache = null;

    if (!is_logged_in()) {
        $user_cache = null;
        return null;
    }

    if ($force_refresh || $user_cache === null || $user_cache['id'] != $_SESSION['user_id']) {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            SELECT id, user_code, name, username, email, status, created_at
            FROM users
            WHERE id = :id AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            logout_user();
            return null;
        }

        // Fetch User Roles
        $stmt_roles = $pdo->prepare("
            SELECT r.id, r.name, r.slug
            FROM roles r
            INNER JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = :user_id
        ");
        $stmt_roles->execute([':user_id' => $user['id']]);
        $user['roles'] = $stmt_roles->fetchAll();
        $user['role_slugs'] = array_column($user['roles'], 'slug');

        // 1. Fetch User Role Default Permissions
        $stmt_role_perms = $pdo->prepare("
            SELECT DISTINCT p.permission_key
            FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            INNER JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = :user_id
        ");
        $stmt_role_perms->execute([':user_id' => $user['id']]);
        $role_perm_keys = array_column($stmt_role_perms->fetchAll(), 'permission_key');
        $user['role_permissions'] = $role_perm_keys;

        // 2. Fetch Individual User Permission Overrides (DEFAULT / ALLOW / DENY)
        $stmt_user_overrides = $pdo->prepare("
            SELECT p.permission_key, up.state
            FROM user_permissions up
            INNER JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = :user_id
        ");
        $stmt_user_overrides->execute([':user_id' => $user['id']]);
        $overrides_raw = $stmt_user_overrides->fetchAll();
        $user_overrides = [];
        foreach ($overrides_raw as $ov) {
            $user_overrides[$ov['permission_key']] = $ov['state'];
        }
        $user['permission_overrides'] = $user_overrides;

        // 3. Calculate Effective Granted Permissions Array (Role Permissions + User Overrides)
        $all_sys_perms = $pdo->query("SELECT permission_key FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
        
        $effective_permissions = [];
        $is_super = in_array(ROLE_SUPER_ADMIN, $user['role_slugs']) || in_array('super_admin', $user['role_slugs']);

        foreach ($all_sys_perms as $pk) {
            if ($is_super) {
                $effective_permissions[] = $pk;
                continue;
            }

            $ov_state = $user_overrides[$pk] ?? 'DEFAULT';
            if ($ov_state === 'ALLOW') {
                $effective_permissions[] = $pk;
            } elseif ($ov_state === 'DENY') {
                continue;
            } else {
                if (in_array($pk, $role_perm_keys)) {
                    $effective_permissions[] = $pk;
                }
            }
        }

        $user['permissions'] = array_values(array_unique($effective_permissions));

        $user_cache = $user;
    }

    return $user_cache;
}

/**
 * Check if user has specific role slug
 */
function has_role($role_slug) {
    $user = current_user();
    if (!$user) return false;
    if (in_array(ROLE_SUPER_ADMIN, $user['role_slugs'])) return true; // Super admin bypass
    return in_array($role_slug, $user['role_slugs']);
}

/**
 * Check if user has specific granular permission key (e.g., 'products.create')
 */
function has_permission($permission_key) {
    $user = current_user();
    if (!$user) return false;
    if (in_array(ROLE_SUPER_ADMIN, $user['role_slugs'])) return true; // Super admin bypass
    return in_array($permission_key, $user['permissions']);
}

/**
 * Enforce Login Requirement
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash_message('danger', 'Please log in to access the system.');
        redirect('admin/login.php');
    }
}

/**
 * Enforce Granular Permission Requirement
 */
function require_permission($permission_key) {
    require_login();
    if (!has_permission($permission_key)) {
        log_audit_action('UNAUTHORIZED_ACCESS_ATTEMPT', 'security', null, "Attempted key: {$permission_key}");
        
        // Detect API / JSON request
        $is_api = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
               || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['REQUEST_URI']) && (str_contains($_SERVER['REQUEST_URI'], '/api/') || str_contains($_SERVER['REQUEST_URI'], '/cloud/')));

        if ($is_api) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => "403 Forbidden: Access Denied. Permission '{$permission_key}' required."
            ]);
            exit;
        }

        set_flash_message('danger', "Access Denied. You lack the '{$permission_key}' permission required for this module.");
        redirect('admin/403.php');
    }
}

/**
 * Authenticate User Credentials
 */
function login_user($username, $password) {
    $pdo = get_db_connection();
    
    $stmt = $pdo->prepare("
        SELECT id, username, password_hash, status, name
        FROM users
        WHERE username = :username OR email = :email
        LIMIT 1
    ");
    $stmt->execute([':username' => $username, ':email' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['status'] !== 'active') {
            return [
                'success' => false,
                'message' => 'Your account is ' . $user['status'] . '. Please contact the system administrator.'
            ];
        }

        // Regenerate session for security if headers not yet sent
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['name'];

        log_audit_action('USER_LOGIN', 'users', $user['id'], "Successful login for user: {$user['username']}");

        return [
            'success' => true,
            'message' => 'Login successful.'
        ];
    }

    log_audit_action('FAILED_LOGIN_ATTEMPT', 'security', null, "Failed login attempt for username: {$username}");

    return [
        'success' => false,
        'message' => 'Invalid username or password.'
    ];
}

/**
 * Logout User
 */
function logout_user() {
    if (is_logged_in()) {
        log_audit_action('USER_LOGOUT', 'users', $_SESSION['user_id'], "User logged out");
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies") && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Generate Next Unique Sequential User Code (DW-0001, DW-0002, etc.)
 */
function generate_next_user_code() {
    $pdo = get_db_connection();
    $max_id = $pdo->query("SELECT MAX(id) FROM users")->fetchColumn();
    $next_num = ($max_id ? (int)$max_id : 0) + 1;
    return 'DW-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

/**
 * Fetch All Active System Roles
 */
function get_all_roles() {
    $pdo = get_db_connection();
    return $pdo->query("SELECT id, name, slug, description FROM roles ORDER BY id ASC")->fetchAll();
}

/**
 * Fetch Permission Keys Array for a Specific User ID
 */
function get_user_permission_keys($user_id) {
    $pdo = get_db_connection();
    
    // Fetch roles
    $stmt_roles = $pdo->prepare("SELECT r.slug FROM roles r INNER JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = :uid");
    $stmt_roles->execute([':uid' => $user_id]);
    $role_slugs = array_column($stmt_roles->fetchAll(), 'slug');
    $is_super = in_array(ROLE_SUPER_ADMIN, $role_slugs) || in_array('super_admin', $role_slugs);
    if ($is_super) {
        return $pdo->query("SELECT permission_key FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
    }

    // Role permissions
    $stmt_rp = $pdo->prepare("
        SELECT DISTINCT p.permission_key
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = :uid
    ");
    $stmt_rp->execute([':uid' => $user_id]);
    $role_perm_keys = array_column($stmt_rp->fetchAll(), 'permission_key');

    // Overrides
    $stmt_uo = $pdo->prepare("
        SELECT p.permission_key, up.state
        FROM user_permissions up
        INNER JOIN permissions p ON up.permission_id = p.id
        WHERE up.user_id = :uid
    ");
    $stmt_uo->execute([':uid' => $user_id]);
    $overrides_raw = $stmt_uo->fetchAll();
    $overrides = [];
    foreach ($overrides_raw as $ov) {
        $overrides[$ov['permission_key']] = $ov['state'];
    }

    $all_perms = $pdo->query("SELECT permission_key FROM permissions")->fetchAll(PDO::FETCH_COLUMN);
    $effective = [];

    foreach ($all_perms as $pk) {
        $ov_state = $overrides[$pk] ?? 'DEFAULT';
        if ($ov_state === 'ALLOW') {
            $effective[] = $pk;
        } elseif ($ov_state === 'DENY') {
            continue;
        } else {
            if (in_array($pk, $role_perm_keys)) {
                $effective[] = $pk;
            }
        }
    }

    return array_values(array_unique($effective));
}

/**
 * Calculate complete effective permissions matrix for a specific user ID
 */
function get_user_effective_permissions_matrix($target_user_id) {
    $pdo = get_db_connection();

    // Fetch user roles
    $stmt_roles = $pdo->prepare("
        SELECT r.id, r.name, r.slug
        FROM roles r
        INNER JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = :uid
    ");
    $stmt_roles->execute([':uid' => $target_user_id]);
    $roles = $stmt_roles->fetchAll();
    $role_slugs = array_column($roles, 'slug');
    $is_super = in_array(ROLE_SUPER_ADMIN, $role_slugs) || in_array('super_admin', $role_slugs);

    // Fetch role permissions
    $stmt_rp = $pdo->prepare("
        SELECT DISTINCT p.permission_key
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = :uid
    ");
    $stmt_rp->execute([':uid' => $target_user_id]);
    $role_perm_keys = array_column($stmt_rp->fetchAll(), 'permission_key');

    // Fetch user overrides
    $stmt_uo = $pdo->prepare("
        SELECT p.permission_key, up.state
        FROM user_permissions up
        INNER JOIN permissions p ON up.permission_id = p.id
        WHERE up.user_id = :uid
    ");
    $stmt_uo->execute([':uid' => $target_user_id]);
    $overrides_raw = $stmt_uo->fetchAll();
    $overrides = [];
    foreach ($overrides_raw as $ov) {
        $overrides[$ov['permission_key']] = $ov['state'];
    }

    // All system permissions
    $all_perms = $pdo->query("SELECT id, module, action, permission_key, description FROM permissions ORDER BY module ASC, id ASC")->fetchAll();

    $matrix = [];
    foreach ($all_perms as $p) {
        $pk = $p['permission_key'];
        $has_role_perm = in_array($pk, $role_perm_keys);
        $override_state = $overrides[$pk] ?? 'DEFAULT';

        if ($is_super) {
            $effective = true;
        } elseif ($override_state === 'ALLOW') {
            $effective = true;
        } elseif ($override_state === 'DENY') {
            $effective = false;
        } else {
            $effective = $has_role_perm;
        }

        $matrix[] = [
            'id' => $p['id'],
            'module' => $p['module'],
            'action' => $p['action'],
            'permission_key' => $pk,
            'description' => $p['description'],
            'role_has' => $has_role_perm,
            'override_state' => $override_state,
            'effective' => $effective
        ];
    }

    return $matrix;
}
