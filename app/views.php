<?php
declare(strict_types=1);

/**
 * Vistas / helpers UI
 * - layout() con topbar y menú "pretty" (/Casos, /Inventario, etc.)
 * - flash messages
 */

function layout(string $title, string $content, ?array $user = null): void {
    $userHtml = '';
    if ($user) {
        $userHtml = '
        <div class="topbar">
            <div><strong>'.e($user['name']).'</strong> · '.e($user['role']).'</div>
            <div>
              <a href="/Casos">Casos</a> ·
              <a href="/Inventario">Inventario</a> ·
              <a href="/Nuevo">Nuevo caso</a> ·
              <a href="/EquipoNuevo">Nuevo equipo</a> ·
              <a href="/Salir">Salir</a>
            </div>
        </div>';
    }

    echo '<!doctype html><html lang="es"><head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>'.e($title).'</title>
        <link rel="stylesheet" href="/assets/style.css">
    </head><body>
        <div class="container">
            <h1>'.e($title).'</h1>
            '.$userHtml.'
            <div class="card">'.$content.'</div>
            <div class="footer">Helpdesk Portable · PHP + SQLite</div>
        </div>
    </body></html>';
}

function flash_set(string $msg): void {
    $_SESSION['flash'] = $msg;
}

function flash_get(): string {
    $m = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    return $m;
}
