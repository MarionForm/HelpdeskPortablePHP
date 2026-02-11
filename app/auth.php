<?php
declare(strict_types=1);

function auth_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_auth(): array {
    $u = auth_user();
    if (!$u) redirect('/?r=login');
    return $u;
}

function login(PDO $pdo, string $email, string $password): bool {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) return false;
    if (!password_verify($password, $user['pass_hash'])) return false;

    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'role' => $user['role'],
    ];
    return true;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
}
