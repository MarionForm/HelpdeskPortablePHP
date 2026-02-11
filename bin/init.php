<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

db_init($pdo);

$email = $argv[1] ?? 'admin@local';
$name  = $argv[2] ?? 'Admin';
$pass  = $argv[3] ?? 'admin1234';

$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT OR IGNORE INTO users (email,name,pass_hash,role,created_at) VALUES (?,?,?,?,?)");
$stmt->execute([$email, $name, $hash, 'admin', now_iso()]);

echo "OK. Admin: {$email} / {$pass}\n";
