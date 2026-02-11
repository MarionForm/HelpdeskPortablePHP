<?php
declare(strict_types=1);

function now_iso(): string {
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never {
    header("Location: {$path}");
    exit;
}

function require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }
}

function audit(PDO $pdo, ?int $userId, string $action, string $details): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, details, ip, created_at) VALUES (?,?,?,?,?)");
    $stmt->execute([$userId, $action, $details, $ip, now_iso()]);
}

function is_pdf_available(): bool {
    return file_exists(BASE_PATH . '/vendor/autoload.php');
}
