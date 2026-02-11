<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
db_init($pdo);

/** Router: /?r=... y también /Casos etc */
$path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

$prettyMap = [
    '' => 'cases',
    'Login' => 'login',
    'Casos' => 'cases',
    'Nuevo' => 'case_new',
    'Salir' => 'logout',

    'Inventario' => 'assets',
    'EquipoNuevo' => 'asset_new',
    'EquipoVer' => 'asset_view',
    'EquipoEditar' => 'asset_edit',
    'EquipoBorrar' => 'asset_delete',
    'EquipoCSV' => 'asset_import_csv',
    'EquipoCSVExport' => 'asset_export_csv',
];

$route = $_GET['r'] ?? ($prettyMap[$path] ?? 'cases');

$user = auth_user();
$flash = flash_get();
$flashHtml = $flash ? '<div class="flash">'.e($flash).'</div>' : '';

/* ---------------- LOGIN ---------------- */
if ($route === 'login') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_verify();
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        if (login($pdo, $email, $pass)) {
            audit($pdo, auth_user()['id'], 'login', 'Inicio de sesión');
            redirect('/Casos');
        }
        flash_set('Credenciales inválidas.');
        redirect('/Login');
    }

    $content = $flashHtml.'
    <form method="post">
      <input type="hidden" name="csrf" value="'.e(csrf_token()).'">
      <label>Email</label><input name="email" autocomplete="username" required>
      <label>Contraseña</label><input name="password" type="password" autocomplete="current-password" required>
      <div style="margin-top:12px"><button>Entrar</button></div>
      <p class="small">Tip: inicializa un admin con <code>php bin/init.php</code></p>
    </form>';

    layout('Login', $content);
    exit;
}

if ($route === 'logout') {
    if ($user) audit($pdo, $user['id'], 'logout', 'Cierre de sesión');
    logout();
    redirect('/Login');
}

/* ---------------- PROTEGIDO ---------------- */
$user = require_auth();

/* ---------------- HELPERS ASSETS ---------------- */
function assets_options(PDO $pdo, ?int $selectedId = null): string {
    $rows = $pdo->query("SELECT id, asset_tag, type, brand, model, serial, username, location FROM assets ORDER BY updated_at DESC LIMIT 500")->fetchAll();
    $html = '<option value="">(Sin equipo)</option>';
    foreach ($rows as $r) {
        $label = trim(($r['asset_tag'] ? ($r['asset_tag'].' · ') : '') . $r['type'].' · '.$r['brand'].' '.$r['model'].' · '.$r['serial'].' · '.$r['username'].' · '.$r['location']);
        $sel = ($selectedId !== null && (int)$r['id'] === $selectedId) ? 'selected' : '';
        $html .= '<option '.$sel.' value="'.(int)$r['id'].'">'.e($label).'</option>';
    }
    return $html;
}

/* ---------------- INVENTARIO LISTA ---------------- */
if ($route === 'assets') {
    $q = trim((string)($_GET['q'] ?? ''));
    $where = '';
    $params = [];
    if ($q !== '') {
        $where = "WHERE asset_tag LIKE ? OR serial LIKE ? OR ip LIKE ? OR username LIKE ? OR location LIKE ? OR brand LIKE ? OR model LIKE ?";
        $params = array_fill(0, 7, "%$q%");
    }

    $stmt = $pdo->prepare("SELECT * FROM assets $where ORDER BY updated_at DESC LIMIT 500");
    $stmt->execute($params);
    $assets = $stmt->fetchAll();

    $rows = '';
    foreach ($assets as $a) {
        $rows .= '<tr>
          <td><a href="/EquipoVer?id='.(int)$a['id'].'">'.e($a['asset_tag'] ?: ('#'.$a['id'])).'</a><div class="small">'.e($a['type']).' · '.e($a['brand'].' '.$a['model']).'</div></td>
          <td class="small">'.e($a['serial']).'<br>'.e($a['os']).'</td>
          <td class="small">'.e($a['username']).'<br>'.e($a['location']).'</td>
          <td class="small">'.e($a['ip']).'<br>'.e($a['mac']).'</td>
          <td class="small">'.e($a['updated_at']).'</td>
        </tr>';
    }

    $content = $flashHtml.'
      <form method="get" style="display:flex; gap:10px; align-items:end;">
        <input type="hidden" name="r" value="assets">
        <div style="flex:1">
          <label>Búsqueda inventario</label>
          <input name="q" value="'.e($q).'" placeholder="asset_tag, serial, ip, usuario, ubicación...">
        </div>
        <div><button>Buscar</button></div>
      </form>

      <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
        <a href="/EquipoNuevo">➕ Nuevo equipo</a>
        <a href="/EquipoCSV">⬆ Import CSV</a>
        <a href="/EquipoCSVExport">⬇ Export CSV</a>
      </div>

      <div style="margin-top:12px">
        <table class="table">
          <thead>
            <tr><th>Equipo</th><th>Serial/OS</th><th>Usuario/Ubicación</th><th>IP/MAC</th><th>Actualizado</th></tr>
          </thead>
          <tbody>'.($rows ?: '<tr><td colspan="5">Sin equipos</td></tr>').'</tbody>
        </table>
      </div>';

    layout('Inventario', $content, $user);
    exit;
}

/* ---------------- NUEVO EQUIPO ---------------- */
if ($route === 'asset_new') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_verify();

        $asset_tag = trim((string)($_POST['asset_tag'] ?? ''));
        $type = (string)($_POST['type'] ?? 'pc');
        $brand = trim((string)($_POST['brand'] ?? ''));
        $model = trim((string)($_POST['model'] ?? ''));
        $serial = trim((string)($_POST['serial'] ?? ''));
        $os = trim((string)($_POST['os'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $ip = trim((string)($_POST['ip'] ?? ''));
        $mac = trim((string)($_POST['mac'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        $stmt = $pdo->prepare("INSERT INTO assets
          (asset_tag,type,brand,model,serial,os,username,ip,mac,location,notes,created_at,updated_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$asset_tag ?: null,$type,$brand,$model,$serial,$os,$username,$ip,$mac,$location,$notes,now_iso(),now_iso()]);
        $id = (int)$pdo->lastInsertId();

        audit($pdo, $user['id'], 'asset_create', "Equipo #$id creado");
        flash_set("Equipo creado (#$id).");
        redirect('/EquipoVer?id='.$id);
    }

    $content = $flashHtml.'
    <form method="post">
      <input type="hidden" name="csrf" value="'.e(csrf_token()).'">

      <div class="row">
        <div><label>Asset tag (opcional)</label><input name="asset_tag" placeholder="PC-VAL-001"></div>
        <div>
          <label>Tipo</label>
          <select name="type">
            <option value="pc">pc</option>
            <option value="portatil">portatil</option>
            <option value="impresora">impresora</option>
            <option value="router">router</option>
            <option value="switch">switch</option>
            <option value="movil">movil</option>
            <option value="tablet">tablet</option>
            <option value="otro">otro</option>
          </select>
        </div>
      </div>

      <div class="row">
        <div><label>Marca</label><input name="brand" placeholder="Dell"></div>
        <div><label>Modelo</label><input name="model" placeholder="OptiPlex 7090"></div>
      </div>

      <div class="row">
        <div><label>Serial</label><input name="serial"></div>
        <div><label>OS</label><input name="os" placeholder="Windows 11 Pro"></div>
      </div>

      <div class="row">
        <div><label>Usuario</label><input name="username" placeholder="usuario / alumno / aula"></div>
        <div><label>Ubicación</label><input name="location" placeholder="Aula 2 / Recepción"></div>
      </div>

      <div class="row">
        <div><label>IP</label><input name="ip" placeholder="192.168.1.50"></div>
        <div><label>MAC</label><input name="mac" placeholder="AA:BB:CC:DD:EE:FF"></div>
      </div>

      <label>Notas</label><textarea name="notes" rows="4"></textarea>

      <div style="margin-top:12px"><button>Crear equipo</button></div>
    </form>';

    layout('Nuevo equipo', $content, $user);
    exit;
}

/* ---------------- VER EQUIPO ---------------- */
if ($route === 'asset_view') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id=?");
    $stmt->execute([$id]);
    $a = $stmt->fetch();
    if (!$a) { http_response_code(404); exit('No existe'); }

    // casos asociados
    $cs = $pdo->prepare("SELECT id,title,status,priority,updated_at FROM cases WHERE asset_id=? ORDER BY updated_at DESC LIMIT 100");
    $cs->execute([$id]);
    $cases = $cs->fetchAll();

    $caseRows = '';
    foreach ($cases as $c) {
        $caseRows .= '<tr>
          <td><a href="/?r=case_view&id='.(int)$c['id'].'">#'.(int)$c['id'].' · '.e($c['title']).'</a></td>
          <td><span class="badge">'.e($c['priority']).'</span></td>
          <td><span class="badge">'.e($c['status']).'</span></td>
          <td class="small">'.e($c['updated_at']).'</td>
        </tr>';
    }

    $content = $flashHtml.'
      <div class="row">
        <div>
          <div><span class="badge">'.e($a['type']).'</span> <span class="badge">'.e($a['asset_tag'] ?: ('#'.$a['id'])).'</span></div>
          <p class="small"><strong>'.e($a['brand'].' '.$a['model']).'</strong></p>
          <p class="small">Serial: '.e($a['serial']).'<br>OS: '.e($a['os']).'</p>
          <p class="small">Usuario: '.e($a['username']).'<br>Ubicación: '.e($a['location']).'</p>
          <p class="small">IP: '.e($a['ip']).'<br>MAC: '.e($a['mac']).'</p>
          <p class="small">Actualizado: '.e($a['updated_at']).'</p>
        </div>
        <div>
          <form method="post" action="/EquipoEditar?id='.(int)$a['id'].'">
            <input type="hidden" name="csrf" value="'.e(csrf_token()).'">
            <label>Notas</label>
            <textarea name="notes" rows="8">'.e((string)$a['notes']).'</textarea>
            <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
              <button>Guardar</button>
              <a href="/Nuevo">Crear caso</a>
              <a href="/EquipoBorrar?id='.(int)$a['id'].'" onclick="return confirm(\'¿Borrar equipo? (Los casos quedan sin equipo)\')">Borrar</a>
            </div>
          </form>
        </div>
      </div>

      <h3 style="margin-top:14px">Casos asociados</h3>
      <table class="table">
        <thead><tr><th>Caso</th><th>Prioridad</th><th>Estado</th><th>Actualizado</th></tr></thead>
        <tbody>'.($caseRows ?: '<tr><td colspan="4">Sin casos asociados</td></tr>').'</tbody>
      </table>
    ';

    layout('Equipo · '.($a['asset_tag'] ?: ('#'.$a['id'])), $content, $user);
    exit;
}

/* ---------------- EDITAR EQUIPO (solo notas rápido) ---------------- */
if ($route === 'asset_edit') {
    require_post();
    csrf_verify();
    $id = (int)($_GET['id'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));

    $stmt = $pdo->prepare("UPDATE assets SET notes=?, updated_at=? WHERE id=?");
    $stmt->execute([$notes, now_iso(), $id]);

    audit($pdo, $user['id'], 'asset_update', "Equipo #$id actualizado");
    flash_set('Equipo actualizado.');
    redirect('/EquipoVer?id='.$id);
}

/* ---------------- BORRAR EQUIPO ---------------- */
if ($route === 'asset_delete') {
    $id = (int)($_GET['id'] ?? 0);
    // desvincular casos
    $pdo->prepare("UPDATE cases SET asset_id=NULL WHERE asset_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM assets WHERE id=?")->execute([$id]);

    audit($pdo, $user['id'], 'asset_delete', "Equipo #$id borrado");
    flash_set('Equipo borrado.');
    redirect('/Inventario');
}

/* ---------------- IMPORT CSV ---------------- */
if ($route === 'asset_import_csv') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_verify();
        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash_set('Error subiendo CSV.');
            redirect('/EquipoCSV');
        }

        $tmp = (string)$_FILES['file']['tmp_name'];
        $handle = fopen($tmp, 'rb');
        if (!$handle) {
            flash_set('No se pudo leer CSV.');
            redirect('/EquipoCSV');
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            flash_set('CSV vacío.');
            redirect('/EquipoCSV');
        }

        // columnas esperadas
        // asset_tag,type,brand,model,serial,os,username,ip,mac,location,notes
        $map = array_map('trim', $header);
        $expected = ['asset_tag','type','brand','model','serial','os','username','ip','mac','location','notes'];

        $idx = [];
        foreach ($expected as $col) {
            $pos = array_search($col, $map, true);
            $idx[$col] = ($pos === false) ? null : $pos;
        }
        if ($idx['type'] === null) {
            fclose($handle);
            flash_set('CSV inválido: falta columna "type".');
            redirect('/EquipoCSV');
        }

        $ins = $pdo->prepare("INSERT INTO assets
            (asset_tag,type,brand,model,serial,os,username,ip,mac,location,notes,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $get = fn(string $k) => ($idx[$k] === null) ? '' : trim((string)($row[$idx[$k]] ?? ''));

            $type = $get('type') ?: 'otro';
            // normalizar type
            $allowed = ['pc','portatil','impresora','router','switch','movil','tablet','otro'];
            if (!in_array($type, $allowed, true)) $type = 'otro';

            $asset_tag = $get('asset_tag');
            $brand = $get('brand');
            $model = $get('model');
            $serial = $get('serial');
            $os = $get('os');
            $username = $get('username');
            $ip = $get('ip');
            $mac = $get('mac');
            $location = $get('location');
            $notes = $get('notes');

            $ins->execute([
                $asset_tag ?: null, $type, $brand, $model, $serial, $os, $username, $ip, $mac, $location, $notes,
                now_iso(), now_iso()
            ]);
            $count++;
        }

        fclose($handle);
        audit($pdo, $user['id'], 'asset_import_csv', "Importados $count equipos");
        flash_set("Importados $count equipos desde CSV.");
        redirect('/Inventario');
    }

    $sample = "asset_tag,type,brand,model,serial,os,username,ip,mac,location,notes\n".
              "PC-VAL-001,pc,Dell,OptiPlex 7090,ABC123,Windows 11 Pro,Juan,192.168.1.50,AA:BB:CC:DD:EE:FF,Aula 2,Equipo de prácticas\n";

    $content = $flashHtml.'
      <p class="small">Sube un CSV con cabecera:</p>
      <pre style="white-space:pre-wrap; background:#0d141d; padding:10px; border-radius:12px; border:1px solid #223044;">'.e($sample).'</pre>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="'.e(csrf_token()).'">
        <input type="file" name="file" accept=".csv,text/csv" required>
        <div style="margin-top:10px"><button>Importar CSV</button></div>
      </form>
    ';

    layout('Importar CSV · Inventario', $content, $user);
    exit;
}

/* ---------------- EXPORT CSV ---------------- */
if ($route === 'asset_export_csv') {
    $rows = $pdo->query("SELECT asset_tag,type,brand,model,serial,os,username,ip,mac,location,notes,created_at,updated_at FROM assets ORDER BY id ASC")->fetchAll();

    audit($pdo, $user['id'], 'asset_export_csv', "Export ".count($rows)." equipos");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventario_assets_'.date('Ymd_His').'.csv"');

    $out = fopen('php://output', 'wb');
    fputcsv($out, ['asset_tag','type','brand','model','serial','os','username','ip','mac','location','notes','created_at','updated_at']);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

/* ---------------- LISTADO CASOS ---------------- */
if ($route === 'cases') {
    $q = trim((string)($_GET['q'] ?? ''));
    $where = '';
    $params = [];
    if ($q !== '') {
        $where = "WHERE c.title LIKE ? OR c.requester LIKE ? OR c.description LIKE ?";
        $params = ["%$q%","%$q%","%$q%"];
    }

    $stmt = $pdo->prepare("
      SELECT c.*,
             a.asset_tag AS a_tag, a.type AS a_type, a.brand AS a_brand, a.model AS a_model
      FROM cases c
      LEFT JOIN assets a ON a.id = c.asset_id
      $where
      ORDER BY c.updated_at DESC
      LIMIT 200
    ");
    $stmt->execute($params);
    $cases = $stmt->fetchAll();

    $rows = '';
    foreach ($cases as $c) {
        $assetLine = '';
        if (!empty($c['asset_id'])) {
            $label = trim(($c['a_tag'] ? ($c['a_tag'].' · ') : '') . ($c['a_type'] ?? '').' · '.($c['a_brand'] ?? '').' '.($c['a_model'] ?? ''));
            $assetLine = '<div class="small">🖥️ <a href="/EquipoVer?id='.(int)$c['asset_id'].'">'.e($label).'</a></div>';
        }

        $rows .= '<tr>
          <td>
            <a href="/?r=case_view&id='.(int)$c['id'].'">#'.(int)$c['id'].' · '.e($c['title']).'</a>
            <div class="small">'.e($c['requester']).' · '.e($c['location']).'</div>
            '.$assetLine.'
          </td>
          <td><span class="badge">'.e($c['priority']).'</span></td>
          <td><span class="badge">'.e($c['status']).'</span></td>
          <td class="small">'.e($c['updated_at']).'</td>
        </tr>';
    }

    $content = $flashHtml.'
      <form method="get" style="display:flex; gap:10px; align-items:end;">
        <input type="hidden" name="r" value="cases">
        <div style="flex:1">
          <label>Búsqueda</label>
          <input name="q" value="'.e($q).'" placeholder="titulo, solicitante, texto...">
        </div>
        <div><button>Buscar</button></div>
      </form>
      <div style="margin-top:12px">
        <table class="table">
          <thead><tr><th>Caso</th><th>Prioridad</th><th>Estado</th><th>Actualizado</th></tr></thead>
          <tbody>'.($rows ?: '<tr><td colspan="4">Sin casos</td></tr>').'</tbody>
        </table>
      </div>';

    layout('Casos', $content, $user);
    exit;
}

/* ---------------- NUEVO CASO (con selector equipo) ---------------- */
if ($route === 'case_new') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_verify();
        $title = trim((string)($_POST['title'] ?? ''));
        $requester = trim((string)($_POST['requester'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $priority = (string)($_POST['priority'] ?? 'media');
        $desc = trim((string)($_POST['description'] ?? ''));
        $assetId = (string)($_POST['asset_id'] ?? '');
        $assetId = $assetId === '' ? null : (int)$assetId;

        if ($title === '' || $requester === '' || $desc === '') {
            flash_set('Faltan campos obligatorios.');
            redirect('/Nuevo');
        }

        $stmt = $pdo->prepare("INSERT INTO cases
          (asset_id, title, requester, location, priority, status, description, solution, created_by, created_at, updated_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$assetId, $title,$requester,$location,$priority,'abierto',$desc,'',$user['id'],now_iso(),now_iso()]);
        $id = (int)$pdo->lastInsertId();

        audit($pdo, $user['id'], 'case_create', "Caso #$id creado");
        flash_set("Caso #$id creado.");
        redirect('/?r=case_view&id='.$id);
    }

    $content = $flashHtml.'
    <form method="post">
      <input type="hidden" name="csrf" value="'.e(csrf_token()).'">

      <label>Equipo afectado</label>
      <select name="asset_id">'.assets_options($pdo).'</select>
      <p class="small">Tip: si no existe, créalo en <a href="/EquipoNuevo">Nuevo equipo</a>.</p>

      <label>Título</label><input name="title" required>
      <div class="row">
        <div><label>Solicitante</label><input name="requester" required></div>
        <div><label>Ubicación</label><input name="location"></div>
      </div>

      <label>Prioridad</label>
      <select name="priority">
        <option value="baja">baja</option>
        <option value="media" selected>media</option>
        <option value="alta">alta</option>
        <option value="critica">critica</option>
      </select>

      <label>Descripción</label><textarea name="description" rows="6" required></textarea>
      <div style="margin-top:12px"><button>Crear</button></div>
    </form>';

    layout('Nuevo caso', $content, $user);
    exit;
}

/* ---------------- VER CASO (mostrando equipo) ---------------- */
if ($route === 'case_view') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("
      SELECT c.*, u.name AS created_by_name,
             a.asset_tag AS a_tag, a.type AS a_type, a.brand AS a_brand, a.model AS a_model,
             a.serial AS a_serial, a.os AS a_os, a.username AS a_user, a.ip AS a_ip, a.location AS a_loc
      FROM cases c
      JOIN users u ON u.id=c.created_by
      LEFT JOIN assets a ON a.id=c.asset_id
      WHERE c.id=?
    ");
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    if (!$c) { http_response_code(404); exit('No existe'); }

    $assetBlock = '';
    if (!empty($c['asset_id'])) {
        $label = trim(($c['a_tag'] ? ($c['a_tag'].' · ') : '') . ($c['a_type'] ?? '').' · '.($c['a_brand'] ?? '').' '.($c['a_model'] ?? ''));
        $assetBlock = '
          <div class="meta" style="margin-top:10px; padding:10px; border:1px solid #223044; border-radius:14px; background:#0d141d;">
            <div class="small"><strong>Equipo:</strong> <a href="/EquipoVer?id='.(int)$c['asset_id'].'">'.e($label).'</a></div>
            <div class="small">Serial: '.e((string)$c['a_serial']).' · OS: '.e((string)$c['a_os']).'</div>
            <div class="small">Usuario: '.e((string)$c['a_user']).' · IP: '.e((string)$c['a_ip']).' · Ubicación: '.e((string)$c['a_loc']).'</div>
          </div>
        ';
    }

    $ev = $pdo->prepare("SELECT * FROM evidences WHERE case_id=? ORDER BY id DESC");
    $ev->execute([$id]);
    $evidences = $ev->fetchAll();

    $evRows = '';
    foreach ($evidences as $e) {
        $evRows .= '<tr>
          <td>'.e($e['original_name']).'<div class="small"><code>'.e($e['sha256']).'</code></div></td>
          <td class="small">'.number_format((int)$e['size_bytes']/1024,1).' KB<br>'.e($e['mime']).'</td>
          <td class="small">'.e($e['created_at']).'</td>
          <td><a href="/?r=download_evidence&id='.(int)$e['id'].'">Descargar</a></td>
        </tr>';
    }

    $content = $flashHtml.'
      <div class="row">
        <div>
          <div><span class="badge">Prioridad: '.e($c['priority']).'</span> <span class="badge">Estado: '.e($c['status']).'</span></div>
          <p class="small">Solicitante: <strong>'.e($c['requester']).'</strong> · Ubicación: '.e($c['location']).'</p>
          <p class="small">Creado por: '.e($c['created_by_name']).' · '.e($c['created_at']).'</p>
          '.$assetBlock.'
        </div>
        <div>
          <form method="post" action="/?r=case_update&id='.(int)$c['id'].'">
            <input type="hidden" name="csrf" value="'.e(csrf_token()).'">
            <label>Estado</label>
            <select name="status">
              <option '.($c['status']==='abierto'?'selected':'').' value="abierto">abierto</option>
              <option '.($c['status']==='en_progreso'?'selected':'').' value="en_progreso">en_progreso</option>
              <option '.($c['status']==='resuelto'?'selected':'').' value="resuelto">resuelto</option>
              <option '.($c['status']==='cerrado'?'selected':'').' value="cerrado">cerrado</option>
            </select>
            <label>Solución / acciones</label>
            <textarea name="solution" rows="4">'.e((string)$c['solution']).'</textarea>
            <div style="margin-top:10px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
              <button>Guardar</button>
              <a href="/?r=export&id='.(int)$c['id'].'">Export HTML</a>
              '.(is_pdf_available() ? '<a href="/?r=export_pdf&id='.(int)$c['id'].'">Export PDF</a>' : '').'
            </div>
          </form>
        </div>
      </div>

      <h3>Descripción</h3>
      <div>'.nl2br(e((string)$c['description'])).'</div>

      <h3 style="margin-top:14px">Evidencias</h3>
      <form method="post" action="/?r=upload_evidence&id='.(int)$c['id'].'" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="'.e(csrf_token()).'">
        <input type="file" name="file" required>
        <div style="margin-top:10px"><button>Subir evidencia</button></div>
        <p class="small">Máx recomendado: 15MB · Se guarda hash SHA256 para integridad.</p>
      </form>

      <table class="table" style="margin-top:10px">
        <thead><tr><th>Archivo</th><th>Info</th><th>Fecha</th><th></th></tr></thead>
        <tbody>'.($evRows ?: '<tr><td colspan="4">Sin evidencias</td></tr>').'</tbody>
      </table>
    ';

    layout('Caso #'.(int)$c['id'].' · '.$c['title'], $content, $user);
    exit;
}

/* ---------------- UPDATE CASO ---------------- */
if ($route === 'case_update') {
    require_post();
    csrf_verify();
    $id = (int)($_GET['id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'abierto');
    $solution = trim((string)($_POST['solution'] ?? ''));

    $stmt = $pdo->prepare("UPDATE cases SET status=?, solution=?, updated_at=? WHERE id=?");
    $stmt->execute([$status, $solution, now_iso(), $id]);

    audit($pdo, $user['id'], 'case_update', "Caso #$id actualizado");
    flash_set('Guardado.');
    redirect('/?r=case_view&id='.$id);
}

/* ---------------- UPLOAD EVIDENCIA ---------------- */
if ($route === 'upload_evidence') {
    require_post();
    csrf_verify();
    $caseId = (int)($_GET['id'] ?? 0);

    if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash_set('Error subiendo archivo.');
        redirect('/?r=case_view&id='.$caseId);
    }

    $f = $_FILES['file'];
    $size = (int)$f['size'];
    if ($size <= 0 || $size > 15 * 1024 * 1024) {
        flash_set('Archivo inválido o demasiado grande (máx 15MB).');
        redirect('/?r=case_view&id='.$caseId);
    }

    $orig = basename((string)$f['name']);
    $tmp  = (string)$f['tmp_name'];

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
    $sha  = hash_file('sha256', $tmp);

    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $stored = bin2hex(random_bytes(16)) . ($ext ? ('.'.$ext) : '');
    $dest = UPLOADS_PATH . '/' . $stored;

    if (!move_uploaded_file($tmp, $dest)) {
        flash_set('No se pudo mover el archivo.');
        redirect('/?r=case_view&id='.$caseId);
    }

    $stmt = $pdo->prepare("INSERT INTO evidences
      (case_id, original_name, stored_name, mime, size_bytes, sha256, uploaded_by, created_at)
      VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$caseId, $orig, $stored, $mime, $size, $sha, $user['id'], now_iso()]);

    $pdo->prepare("UPDATE cases SET updated_at=? WHERE id=?")->execute([now_iso(), $caseId]);

    audit($pdo, $user['id'], 'evidence_upload', "Caso #$caseId · $orig");
    flash_set('Evidencia subida + hash generado.');
    redirect('/?r=case_view&id='.$caseId);
}

/* ---------------- DOWNLOAD EVIDENCIA ---------------- */
if ($route === 'download_evidence') {
    $eid = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM evidences WHERE id=?");
    $stmt->execute([$eid]);
    $e = $stmt->fetch();
    if (!$e) { http_response_code(404); exit('No existe'); }

    $path = UPLOADS_PATH . '/' . $e['stored_name'];
    if (!is_file($path)) { http_response_code(404); exit('Archivo no encontrado'); }

    audit($pdo, $user['id'], 'evidence_download', "Evidencia #$eid");

    header('Content-Type: '.$e['mime']);
    header('Content-Disposition: attachment; filename="'.rawurlencode($e['original_name']).'"');
    header('Content-Length: '.filesize($path));
    readfile($path);
    exit;
}

/* ---------------- EXPORT HTML/PDF ---------------- */
if ($route === 'export') {
    $id = (int)($_GET['id'] ?? 0);
    $file = export_case_html($pdo, $id);
    audit($pdo, $user['id'], 'export_html', "Caso #$id -> $file");
    flash_set("Exportado: $file (storage/exports)");
    redirect('/?r=case_view&id='.$id);
}
if ($route === 'export_pdf') {
    $id = (int)($_GET['id'] ?? 0);
    $pdf = export_case_pdf($pdo, $id);
    if (!$pdf) {
        flash_set('PDF no disponible. Instala composer (dompdf) para habilitarlo.');
        redirect('/?r=case_view&id='.$id);
    }
    audit($pdo, $user['id'], 'export_pdf', "Caso #$id -> $pdf");
    flash_set("Exportado: $pdf (storage/exports)");
    redirect('/?r=case_view&id='.$id);
}

http_response_code(404);
exit('Ruta no encontrada');
