<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("PRAGMA foreign_keys = ON;");
    $pdo->exec("PRAGMA journal_mode = WAL;");

    return $pdo;
}

function db_init(PDO $pdo): void {
    // USERS
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            pass_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('admin','tech')),
            created_at TEXT NOT NULL
        );
    ");

    // ASSETS (INVENTARIO)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS assets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            asset_tag TEXT UNIQUE,
            type TEXT NOT NULL CHECK(type IN ('pc','portatil','impresora','router','switch','movil','tablet','otro')),
            brand TEXT DEFAULT '',
            model TEXT DEFAULT '',
            serial TEXT DEFAULT '',
            os TEXT DEFAULT '',
            username TEXT DEFAULT '',
            ip TEXT DEFAULT '',
            mac TEXT DEFAULT '',
            location TEXT DEFAULT '',
            notes TEXT DEFAULT '',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );
    ");

    // CASES (con asset_id)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            asset_id INTEGER NULL,
            title TEXT NOT NULL,
            requester TEXT NOT NULL,
            location TEXT DEFAULT '',
            priority TEXT NOT NULL CHECK(priority IN ('baja','media','alta','critica')),
            status TEXT NOT NULL CHECK(status IN ('abierto','en_progreso','resuelto','cerrado')),
            description TEXT NOT NULL,
            solution TEXT DEFAULT '',
            created_by INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(created_by) REFERENCES users(id),
            FOREIGN KEY(asset_id) REFERENCES assets(id) ON DELETE SET NULL
        );
    ");

    // EVIDENCES
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS evidences (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            case_id INTEGER NOT NULL,
            original_name TEXT NOT NULL,
            stored_name TEXT NOT NULL,
            mime TEXT NOT NULL,
            size_bytes INTEGER NOT NULL,
            sha256 TEXT NOT NULL,
            uploaded_by INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(case_id) REFERENCES cases(id) ON DELETE CASCADE,
            FOREIGN KEY(uploaded_by) REFERENCES users(id)
        );
    ");

    // AUDIT
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            details TEXT NOT NULL,
            ip TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id)
        );
    ");

    // MIGRACIÓN SUAVE: si ya existía cases sin asset_id, lo añadimos
    $cols = $pdo->query("PRAGMA table_info(cases)")->fetchAll();
    $hasAssetId = false;
    foreach ($cols as $c) {
        if (($c['name'] ?? '') === 'asset_id') { $hasAssetId = true; break; }
    }
    if (!$hasAssetId) {
        $pdo->exec("ALTER TABLE cases ADD COLUMN asset_id INTEGER NULL;");
        // Nota: SQLite no añade constraint FK vía ALTER de forma simple; lo dejamos como columna (funciona igual a nivel app).
    }
}
