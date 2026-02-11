# HelpdeskPortablePHP
Portable Helpdesk &amp; IT Ops mini-suite built with PHP 8 + SQLite. Includes ticket management, asset inventory, evidence hashing (SHA256), CSV import/export and Windows portable launcher.
# Helpdesk Portable PHP + SQLite

Mini suite de Helpdesk / IT Ops portable:
- PHP 8 + SQLite (sin servidor externo)
- Casos + evidencias (uploads) con hash SHA256
- Export HTML y PDF (Dompdf)
- Inventario de equipos + import/export CSV
- Pack portable Windows (.bat)

## Requisitos
- PHP 8.x con pdo_sqlite
- (Opcional) Composer para PDF

## Arranque rápido
```bash
php -S 127.0.0.1:8080 -t public
