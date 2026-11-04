<?php
header('Content-Type: text/css; charset=UTF-8');
$configPath = __DIR__ . '/config.json';
$config = is_file($configPath) ? json_decode(file_get_contents($configPath), true) : null;
$config = is_array($config) ? $config : [];
$fontFamily = $config['fuente'] ?? 'Arial, sans-serif';
if (!is_string($fontFamily) || !preg_match('/^[a-zA-Z0-9 ,\'"-]+$/', $fontFamily)) {
    $fontFamily = 'Arial, sans-serif';
}
$textColor = $config['color_letra'] ?? '#303642';
if (!is_string($textColor) || !preg_match('/^(#[a-fA-F0-9]{3,8}|[a-zA-Z]{1,20})$/', $textColor)) {
    $textColor = '#303642';
}
$backgroundColor = $config['color_fondo'] ?? '#edf0f4';
if (!is_string($backgroundColor) || !preg_match('/^(#[a-fA-F0-9]{3,8}|[a-zA-Z]{1,20})$/', $backgroundColor)) {
    $backgroundColor = '#edf0f4';
}
$buttonColor = $config['color_boton'] ?? '#368fdb';
if (!is_string($buttonColor) || !preg_match('/^(#[a-fA-F0-9]{3,8}|[a-zA-Z]{1,20})$/', $buttonColor)) {
    $buttonColor = '#368fdb';
}
$buttonHoverColor = $config['color_boton_hover'] ?? '#1768ad';
if (!is_string($buttonHoverColor) || !preg_match('/^(#[a-fA-F0-9]{3,8}|[a-zA-Z]{1,20})$/', $buttonHoverColor)) {
    $buttonHoverColor = '#1768ad';
}
$fontSize = $config['tamano_letra'] ?? '14px';
if (!is_string($fontSize) || !preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|%)$/', $fontSize)) {
    $fontSize = '14px';
}
?>
:root {
    --brand: <?= $buttonColor ?>;
    --brand-dark: <?= $buttonHoverColor ?>;
    --topbar: #30323b;
    --canvas: <?= $backgroundColor ?>;
    --surface: #fff;
    --text: <?= $textColor ?>;
    --muted: #788292;
    --border: #e1e6ed;
    --success: #62cf4a;
    --danger: #fa5c61;
    --warning: #f49a1b;
    --shadow: 0 2px 8px rgba(35, 48, 65, .08);
    --radius: 8px;
    --tamano-configurado: <?= $fontSize ?>;
    --fuente-configurada: <?= $fontFamily ?>;
}

* { box-sizing: border-box; }

body {
    margin: 0;
    min-height: 100vh;
    padding: 0;
    color: var(--text);
    background: var(--canvas);
    font: var(--tamano-configurado)/1.5 var(--fuente-configurada);
}

h1, h2, h3, h4, p { margin-top: 0; }
h1 { font-size: 1.35rem; }
h2 { font-size: 1.2rem; }
h3 { font-size: 1.05rem; }
a { color: var(--brand-dark); }
button, input, select, textarea { font: inherit; }
button {
    min-height: 38px;
    padding: 8px 14px;
    border: 1px solid var(--border);
    border-radius: 5px;
    color: #fff;
    background: var(--brand);
    cursor: pointer;
    transition: background .15s ease, box-shadow .15s ease, transform .15s ease;
}
button:hover { background: var(--brand-dark); }
button:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {
    outline: 3px solid rgba(54, 143, 219, .28);
    outline-offset: 2px;
}
button:disabled { cursor: not-allowed; opacity: .58; }
input, select, textarea {
    min-height: 38px;
    padding: 8px 10px;
    border: 1px solid #d5dce5;
    border-radius: 5px;
    color: var(--text);
    background: #fff;
}

header {
    min-height: 58px;
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    color: #fff;
    background: var(--topbar);
    box-shadow: 0 2px 5px rgba(0, 0, 0, .12);
}
header h1 { margin: 0; color: inherit; }
header a { color: #fff; text-decoration: none; }
header > a {
    padding: 7px 12px;
    border: 1px solid rgba(255,255,255,.25);
    border-radius: 5px;
}
header > a:hover { background: rgba(255,255,255,.12); }
header nav { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
header nav a { margin: 0; padding: 7px 9px; border-radius: 4px; }
header nav a:hover { background: rgba(255,255,255,.13); }

main {
    width: min(1440px, 100%);
    margin: 0 auto;
    padding: 24px;
}
main > h2 { margin-bottom: 6px; }
main > p { color: var(--muted); }
section, .mesas, .menu, .orden, .reportes-container, .config-container {
    padding: 20px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--surface);
    box-shadow: var(--shadow);
}

table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
}
th, td { padding: 11px 12px; border-bottom: 1px solid var(--border); text-align: left; }
th { color: #566273; background: #f5f7fa; font-weight: 600; }
tbody tr:hover { background: #f8fbff; }

form { margin-bottom: 18px; }
form label { display: inline-flex; flex-direction: column; gap: 5px; margin: 0 12px 10px 0; }
form input[type="checkbox"], form input[type="radio"] { min-height: auto; }

.login-container {
    width: min(420px, calc(100% - 32px));
    margin: max(8vh, 40px) auto;
    padding: 0;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: #fff;
    box-shadow: 0 3px 14px rgba(35, 48, 65, .08);
}
.login-screen { display: grid; min-height: 100vh; align-items: center; background: #e9ebef; }
.login-brand {
    min-height: 146px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    color: #fff;
    background: var(--topbar);
}
.login-brand-icon { color: #dceaff; font-size: 3.2rem; line-height: 1; }
.login-brand-name { color: #cbd9ed; font-size: 1.15rem; letter-spacing: .12em; }
.login-brand-name strong { color: #fff; }
.login-content { padding: 30px 34px 32px; }
.login-container h1 { margin-bottom: 8px; font-size: 1.35rem; text-align: center; text-transform: uppercase; }
.login-intro { margin-bottom: 22px; color: #586575; line-height: 1.7; letter-spacing: .035em; }
.login-container form { display: flex; flex-direction: column; gap: 16px; }
.login-container input {
    width: 100%;
    min-height: 54px;
    margin: 0;
    padding: 12px;
    border-color: #d0d8e4;
    border-radius: 3px;
    background: #eaf1fc;
}
.login-container input:focus-visible { background: #fff; }
.login-container button {
    min-height: 46px;
    margin-top: 4px;
    border: 0;
    border-radius: 0;
    background: var(--topbar);
    font-size: 1rem;
    text-transform: uppercase;
}
.login-container button:hover { background: #454852; }
.login-help { margin: 16px 0 0; text-align: right; }
.login-version { margin: 16px 0 0; color: var(--muted); font-size: .82rem; text-align: center; }
.login-version span { margin-right: 5px; padding: 2px 7px; border-radius: 12px; color: #fff; background: #8bcf55; font-weight: 700; }
.login-alert { display: grid; gap: 4px; margin-bottom: 16px; padding: 12px; border: 1px solid #efc3c3; border-radius: 4px; color: #842d2d; background: #fff4f4; }
.login-alert a { font-weight: 600; }
.visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

.app-layout { min-height: 100vh; }
.sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 20;
    width: 196px;
    overflow-y: auto;
    color: #fff;
    background: var(--brand);
}
.sidebar-brand {
    min-height: 58px;
    padding: 17px 18px;
    color: #fff;
    background: #2f86ce;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: .04em;
}
.sidebar nav { display: grid; padding: 6px 0; }
.sidebar nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 43px;
    padding: 9px 16px;
    border-bottom: 1px solid rgba(20, 75, 120, .25);
    color: rgba(255,255,255,.95);
    text-decoration: none;
}
.sidebar nav a:hover, .sidebar nav a.active { background: var(--brand-dark); color: #fff; }
.sidebar-icon { width: 20px; text-align: center; font-size: 16px; }
.app-main { min-width: 0; margin-left: 196px; }
.topbar {
    position: sticky;
    top: 0;
    z-index: 10;
    min-height: 58px;
    padding: 0 24px;
    color: #fff;
    background: var(--topbar);
}
.topbar .breadcrumb { color: #c4cbd5; font-size: .9rem; }
.topbar .user-name { color: #fff; font-weight: 600; }
.dashboard-content { max-width: 1600px; margin: 0 auto; padding: 24px; }
.page-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.page-heading h1 { margin: 0 0 4px; }
.page-heading p { margin: 0; color: var(--muted); }
.dashboard-primary-action { padding: 9px 14px; border-radius: 5px; color: #fff; background: var(--brand); text-decoration: none; }
.dashboard-primary-action:hover { background: var(--brand-dark); color: #fff; }
.stat-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
.stat-card {
    min-height: 110px;
    display: flex;
    align-items: stretch;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: #fff;
    box-shadow: var(--shadow);
}
.stat-copy { flex: 1; padding: 17px 20px; }
.stat-value { display: block; margin-bottom: 4px; color: var(--brand); font-size: 1.8rem; font-weight: 600; line-height: 1.2; }
.stat-label { color: var(--muted); }
.stat-icon { width: 92px; display: grid; place-items: center; color: #fff; background: var(--brand); font-size: 1.8rem; }
.stat-icon.green { background: var(--success); }
.stat-icon.red { background: var(--danger); }
.stat-icon.orange { background: var(--warning); }
.dashboard-welcome { margin: 20px 0 14px; color: #596575; text-align: center; }
.quick-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 18px; }
.quick-link {
    min-height: 48px;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 5px;
    color: #586474;
    background: #fff;
    text-decoration: none;
    box-shadow: var(--shadow);
}
.quick-link:hover { border-color: #b7d7f2; color: var(--brand-dark); background: #f8fbff; }
.quick-icon { width: 28px; color: var(--topbar); text-align: center; font-size: 1.05rem; }
.dashboard-panel { margin-top: 18px; padding: 20px; border: 1px solid var(--border); border-radius: var(--radius); background: #fff; box-shadow: var(--shadow); }
.dashboard-panel h2 { text-align: center; }
.sales-launch-panel { display: flex; align-items: center; justify-content: space-between; gap: 18px; }
.sales-launch-panel h2 { margin: 0 0 4px; text-align: left; }
.sales-launch-panel p { margin: 0; color: var(--muted); }
.chart-bars { height: 190px; display: flex; align-items: flex-end; gap: 12px; padding: 18px 10px 0; border-bottom: 1px solid var(--border); }
.chart-day { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; gap: 7px; color: var(--muted); font-size: .78rem; }
.chart-bar { width: min(100%, 54px); min-height: 4px; border-radius: 4px 4px 0 0; background: #5a96f5; }
.stock-alert { margin-top: 20px; padding: 16px 18px; border: 1px solid #f4c7c7; border-radius: var(--radius); background: #fff5f5; color: #8d2b2b; }
.stock-alert h2 { margin-bottom: 8px; text-align: left; }
.settings-page, .employees-page { max-width: 1180px; }
.settings-form { display: grid; gap: 16px; }
.settings-section, .employees-card { padding: 20px; border: 1px solid var(--border); border-radius: var(--radius); background: #fff; box-shadow: var(--shadow); }
.settings-section-heading, .permission-editor-heading { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 18px; }
.settings-section-heading h3, .permission-editor-heading h3 { margin: 0 0 3px; }
.settings-section-heading p, .permission-editor-heading p { margin: 0; color: var(--muted); font-size: .9rem; }
.settings-section-icon { width: 38px; height: 38px; display: grid; flex: 0 0 38px; place-items: center; border-radius: 7px; color: var(--brand-dark); background: #eaf4fd; font-size: 1.15rem; }
.settings-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 18px; }
.settings-fields label, .new-employee-form label { display: flex; flex-direction: column; gap: 6px; margin: 0; color: #4a5665; font-weight: 600; }
.settings-fields input, .settings-fields select, .settings-fields textarea, .new-employee-form input, .new-employee-form select { width: 100%; }
.settings-fields small, .setting-toggle small { display: block; margin-top: 3px; color: var(--muted); font-size: .82rem; font-weight: 400; }
.settings-fields .field-wide { grid-column: 1 / -1; }
.settings-colors label { max-width: 350px; }
.settings-colors input[type="color"] { min-height: 44px; padding: 4px; }
.settings-toggles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.setting-toggle { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid var(--border); border-radius: 6px; cursor: pointer; }
.setting-toggle > input { width: 18px; height: 18px; min-height: 18px; flex: 0 0 18px; margin-top: 2px; accent-color: var(--brand); }
.setting-toggle strong { display: block; font-size: .92rem; }
.settings-actions { display: flex; align-items: center; gap: 10px; margin-top: 18px; }
.settings-actions a { padding: 8px 12px; color: var(--muted); text-decoration: none; }
.printer-test-row { grid-column: 1 / -1; margin-top: -8px; }
.settings-message { margin-bottom: 16px; padding: 12px 14px; border: 1px solid; border-radius: 5px; }
.settings-message.error { border-color: #efc3c3; color: #842d2d; background: #fff4f4; }
.settings-message.success { border-color: #b7dfc3; color: #23603a; background: #effaf2; }
.employees-card { margin-bottom: 18px; }
.table-scroll { overflow-x: auto; }
.employees-table { min-width: 760px; }
.role-badge { display: inline-block; padding: 3px 9px; border-radius: 20px; color: var(--brand-dark); background: #eaf4fd; font-size: .82rem; }
.button-secondary { color: #4d5968; background: #f3f5f8; }
.button-secondary:hover { color: #fff; }
.permission-row > td { padding: 16px; background: #f8fafc; }
.permission-editor-heading { justify-content: space-between; }
.permission-groups { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.permission-group { min-width: 0; margin: 0; padding: 13px; border: 1px solid var(--border); border-radius: 6px; }
.permission-group legend { padding: 0 5px; color: #3c4857; font-weight: 700; }
.permission-option { display: flex; align-items: flex-start; gap: 8px; margin: 8px 0; color: #536171; font-size: .87rem; }
.permission-option input { width: 16px; height: 16px; min-height: 16px; flex: 0 0 16px; margin-top: 2px; accent-color: var(--brand); }
.new-employee-form { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); align-items: end; gap: 14px; }
.new-employee-form button { grid-column: 1 / -1; justify-self: start; }

.pos-container { display: grid; grid-template-columns: minmax(210px, 1fr) minmax(300px, 1.45fr) minmax(300px, 1.25fr); align-items: start; gap: 16px; }
.pos-container h3 { margin-bottom: 14px; }
.mesas-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(64px, 1fr)); gap: 8px; }
.mesa-btn { color: #536171; background: #f1f5f9; }
.mesa-btn:hover, .mesa-btn.selected { border-color: var(--brand); color: #fff; background: var(--brand); }
.menu-filtros { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
.menu-filtros label { flex: 1; min-width: 130px; }
.menu-filtros input, .menu-filtros select { width: 100%; }
.menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 9px; }
.menu-item { min-height: 64px; color: #4a5a6c; background: #f5f8fb; }
.menu-item:hover { border-color: var(--brand); color: var(--brand-dark); background: #eaf4fd; }
.orden { position: sticky; top: 76px; }
.orden ul { padding-left: 20px; }
.orden li { padding: 7px 0; border-bottom: 1px solid var(--border); }
.venta-resumen { margin: 16px 0; padding: 12px; border-radius: 5px; background: #f5f7fa; }
.venta-resumen p { display: flex; justify-content: space-between; margin: 4px 0; }
.venta-resumen p:last-child { color: var(--brand-dark); font-size: 1.08rem; font-weight: 700; }
.venta-acciones { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0; }
.venta-acciones label { flex: 1 1 145px; }
.venta-acciones input, .venta-acciones select { width: 100%; }
.estado-info { padding: 10px; border-radius: 5px; color: #566273; background: #f1f6fb; }

.tip-options { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0; }
.tip-btn { color: #fff; background: var(--brand); }
.modal { position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center; justify-content: center; background: rgba(31, 39, 50, .55); }
.modal-content { width: min(500px, calc(100% - 32px)); max-height: 80vh; overflow-y: auto; padding: 22px; border-radius: var(--radius); background: #fff; box-shadow: 0 12px 35px rgba(0,0,0,.2); }

@media (max-width: 1050px) {
    .pos-container { grid-template-columns: minmax(200px, .8fr) minmax(280px, 1.2fr); }
    .orden { position: static; grid-column: 1 / -1; }
}
@media (max-width: 760px) {
    .sidebar { position: static; width: auto; max-height: none; }
    .sidebar nav { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .app-main { margin-left: 0; }
    .topbar { position: static; padding: 12px 16px; }
    .dashboard-content, main { padding: 16px; }
    .sales-launch-panel { align-items: flex-start; flex-direction: column; }
    .stat-grid { grid-template-columns: 1fr; gap: 10px; }
    .quick-grid { grid-template-columns: 1fr; }
    .settings-fields, .settings-toggles, .permission-groups { grid-template-columns: 1fr; }
    .settings-fields .field-wide { grid-column: auto; }
    .new-employee-form { grid-template-columns: 1fr; }
    .new-employee-form button { grid-column: auto; }
    .pos-container { grid-template-columns: 1fr; }
    .orden { grid-column: auto; }
    header { flex-direction: column; align-items: flex-start; padding: 12px 16px; }
    header nav { gap: 4px; }
    main { overflow-x: auto; }
    .login-container { margin: 24px auto; }
    .login-content { padding: 24px 20px; }
}
