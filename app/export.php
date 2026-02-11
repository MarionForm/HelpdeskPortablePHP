<?php
declare(strict_types=1);

function export_case_html(PDO $pdo, int $caseId): string {
    $case = $pdo->prepare("SELECT c.*, u.name AS created_by_name FROM cases c JOIN users u ON u.id=c.created_by WHERE c.id=?");
    $case->execute([$caseId]);
    $c = $case->fetch();
    if (!$c) throw new RuntimeException("Caso no encontrado");

    $ev = $pdo->prepare("SELECT * FROM evidences WHERE case_id=? ORDER BY id DESC");
    $ev->execute([$caseId]);
    $evidences = $ev->fetchAll();

    $rows = '';
    foreach ($evidences as $e) {
        $rows .= '<tr>
            <td>'.e($e['original_name']).'</td>
            <td>'.e($e['mime']).'</td>
            <td>'.number_format((int)$e['size_bytes']/1024, 1).' KB</td>
            <td><code>'.e($e['sha256']).'</code></td>
            <td>'.e($e['created_at']).'</td>
        </tr>';
    }

    $html = '<!doctype html><html><head><meta charset="utf-8">
    <style>
      body{font-family:Arial, sans-serif; font-size:12px;}
      h2{margin:0 0 10px 0;}
      .meta{margin:8px 0; padding:10px; background:#f6f6f6;}
      table{width:100%; border-collapse:collapse; margin-top:10px;}
      th,td{border:1px solid #ddd; padding:6px; text-align:left;}
      th{background:#f0f0f0;}
      code{font-size:10px;}
    </style></head><body>
      <h2>Reporte de Caso #'.(int)$c['id'].' · '.e($c['title']).'</h2>
      <div class="meta">
        <div><strong>Solicitante:</strong> '.e($c['requester']).'</div>
        <div><strong>Ubicación:</strong> '.e($c['location']).'</div>
        <div><strong>Prioridad:</strong> '.e($c['priority']).' · <strong>Estado:</strong> '.e($c['status']).'</div>
        <div><strong>Creado por:</strong> '.e($c['created_by_name']).' · <strong>Fecha:</strong> '.e($c['created_at']).'</div>
      </div>
      <h3>Descripción</h3>
      <div>'.nl2br(e($c['description'])).'</div>
      <h3>Solución / Acciones</h3>
      <div>'.nl2br(e($c['solution'] ?? '')).'</div>

      <h3>Evidencias (hash SHA256)</h3>
      <table>
        <thead><tr><th>Archivo</th><th>MIME</th><th>Tamaño</th><th>SHA256</th><th>Fecha</th></tr></thead>
        <tbody>'.($rows ?: '<tr><td colspan="5">Sin evidencias</td></tr>').'</tbody>
      </table>
    </body></html>';

    $filename = 'case_'.$caseId.'_'.date('Ymd_His').'.html';
    $path = EXPORTS_PATH . '/' . $filename;
    file_put_contents($path, $html);

    return $filename;
}

function export_case_pdf(PDO $pdo, int $caseId): ?string {
    if (!is_pdf_available()) return null;

    require_once BASE_PATH . '/vendor/autoload.php';
    $htmlFile = export_case_html($pdo, $caseId);
    $html = file_get_contents(EXPORTS_PATH . '/' . $htmlFile);

    $dompdf = new Dompdf\Dompdf([
        'isRemoteEnabled' => false
    ]);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $pdfName = str_replace('.html', '.pdf', $htmlFile);
    file_put_contents(EXPORTS_PATH . '/' . $pdfName, $dompdf->output());
    return $pdfName;
}
