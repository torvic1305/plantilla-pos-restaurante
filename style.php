<?php
header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$configPath = __DIR__ . '/config.json';
$config = is_file($configPath) ? json_decode(file_get_contents($configPath), true) : null;
$config = is_array($config) ? $config : [];
$fontFamily = $config['fuente'] ?? 'Arial, sans-serif';
if (!is_string($fontFamily) || !preg_match('/^[a-zA-Z0-9 ,\'"-]+$/', $fontFamily)) {
    $fontFamily = 'Arial, sans-serif';
}
$textColor = $config['color_letra'] ?? '#211f1a';
if (!is_string($textColor) || !preg_match('/^(#[a-fA-F0-9]{3,8}|[a-zA-Z]{1,20})$/', $textColor)) {
    $textColor = '#211f1a';
}
$backgroundColor = $config['color_fondo'] ?? '#f4f1e9';
if (!is_string($backgroundColor) || !preg_match('/^(#[a-fA-F0-9]{3,8}|[a-zA-Z]{1,20})$/', $backgroundColor)) {
    $backgroundColor = '#f4f1e9';
}
$buttonColor = $config['color_boton'] ?? '#171717';
if (!is_string($buttonColor) || !preg_match('/^(#[a-fA-F0-9]{3,8}|[a-zA-Z]{1,20})$/', $buttonColor)) {
    $buttonColor = '#171717';
}
$buttonHoverColor = $config['color_boton_hover'] ?? '#7a5e16';
if (!is_string($buttonHoverColor) || !preg_match('/^(#[a-fA-F0-9]{3,8}|[a-zA-Z]{1,20})$/', $buttonHoverColor)) {
    $buttonHoverColor = '#7a5e16';
}
$fontSize = $config['tamano_letra'] ?? '14px';
if (!is_string($fontSize) || !preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|%)$/', $fontSize)) {
    $fontSize = '14px';
}
?>
:root {
    --brand: <?= $buttonColor ?>;
    --brand-dark: <?= $buttonHoverColor ?>;
    --topbar: #171717;
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
    outline: 3px solid rgba(181, 139, 43, .38);
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
.sales-chart-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; flex-wrap: wrap; }
.sales-chart-heading h2 { margin: 0 0 4px; text-align: left; }
.sales-chart-heading p { margin: 0; color: var(--muted); }
.sales-chart-filters, .sales-chart-date-range { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
.sales-chart-filters label { display: flex; flex-direction: column; gap: 4px; font-size: .88rem; }
#rango-personalizado[hidden] { display: none; }
.chart-scroll { overflow-x: auto; }
.chart-bars { min-width: 560px; height: 190px; display: flex; align-items: flex-end; gap: 12px; padding: 18px 10px 0; border-bottom: 1px solid var(--border); }
.chart-day { flex: 1; min-width: 38px; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; gap: 7px; color: var(--muted); font-size: .72rem; }
.chart-bar { width: min(100%, 54px); min-height: 4px; border-radius: 4px 4px 0 0; background: #5a96f5; }
.stock-alert { margin-top: 20px; padding: 16px 18px; border: 1px solid #f4c7c7; border-radius: var(--radius); background: #fff5f5; color: #8d2b2b; }
.stock-alert h2 { margin-bottom: 8px; text-align: left; }
.settings-page, .employees-page { max-width: 1180px; }
.settings-form { display: grid; gap: 16px; }
.settings-section, .employees-card { padding: 20px; border: 1px solid var(--border); border-radius: var(--radius); background: #fff; box-shadow: var(--shadow); }
.settings-section-heading, .permission-editor-heading { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 18px; }
.settings-section-heading h3, .permission-editor-heading h3 { margin: 0 0 3px; }
.settings-section-heading p, .permission-editor-heading p { margin: 0; color: var(--muted); font-size: .9rem; }
.settings-section-icon { width: 38px; height: 38px; display: grid; flex: 0 0 38px; place-items: center; border-radius: 7px; color: var(--brand-dark); background: #f7f0dc; font-size: 1.15rem; }
.settings-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 18px; }
.settings-fields label, .new-employee-form label { display: flex; flex-direction: column; gap: 6px; margin: 0; color: #4a5665; font-weight: 600; }
.settings-fields input, .settings-fields select, .settings-fields textarea, .new-employee-form input, .new-employee-form select { width: 100%; }
.settings-fields small, .setting-toggle small { display: block; margin-top: 3px; color: var(--muted); font-size: .82rem; font-weight: 400; }
.settings-fields .field-wide { grid-column: 1 / -1; }
.settings-colors label { max-width: 350px; }
.settings-colors input[type="color"] { min-height: 44px; padding: 4px; }
.theme-preset-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; padding: 12px 14px; border: 1px solid #e8dfc7; border-radius: 7px; background: #fbf8ef; }
.theme-preset-row strong, .theme-preset-row small { display: block; }
.theme-preset-row small { margin-top: 3px; color: var(--muted); font-size: .84rem; }
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
.receipt-preview-panel { margin-top: 20px; padding: 16px; border: 1px solid var(--border); border-radius: 7px; background: #f5f7fa; }
.receipt-preview-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.receipt-preview-heading h4 { margin: 0 0 4px; }
.receipt-preview-heading p, .receipt-preview-note { margin: 0; color: var(--muted); font-size: .85rem; }
.receipt-preview-heading output { color: #4a5665; font-size: .85rem; text-align: right; }
.receipt-preview-scroll { max-height: 620px; overflow: auto; padding: 12px; border: 1px solid #d5dce5; border-radius: 5px; background: #e7ebf0; }
.receipt-preview-paper { box-sizing: border-box; display: flex; flex-direction: column; margin: 0 auto; padding: 0 2mm; color: #111; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.18); font-family: Arial, sans-serif; line-height: 1.35; }
.receipt-preview-content { flex: 0 0 auto; overflow-wrap: anywhere; font-size: var(--receipt-preview-font-size, 12px); }
.receipt-preview-content > div { margin: 0 0 3px; }
.receipt-preview-content .receipt-preview-center { text-align: center; }
.receipt-preview-content .receipt-preview-strong { font-weight: 700; }
.receipt-preview-content .receipt-preview-separator { margin: 7px 0; color: #555; white-space: nowrap; overflow: hidden; }
.receipt-preview-content .receipt-preview-item-price { color: #444; }
.receipt-preview-margin { position: relative; flex: 0 0 auto; margin: 0 -2mm; background: #fff0c2; box-shadow: inset 0 1px #c57d00, inset 0 -1px #c57d00; }
.receipt-preview-margin { cursor: ns-resize; touch-action: none; }
.receipt-preview-margin span { position: absolute; top: 0; left: 3mm; color: #815000; font: 10px/1 Arial, sans-serif; white-space: nowrap; pointer-events: none; }
.receipt-preview-margin-bottom { margin-top: auto; }
.receipt-preview-cut-feed { flex: 0 0 8mm; display: grid; place-items: center; margin: 0 -2mm; border-top: 1px dashed #7b8795; color: #596575; background: #e7ebf0; font: 10px Arial, sans-serif; }
.receipt-preview-note { margin-top: 10px; }
.receipt-preview-dimensions { display: block; margin: 0 0 10px; color: #4a5665; font-size: .85rem; }
.receipt-preview-tabs { display: flex; gap: 6px; }
.receipt-preview-tabs button[aria-pressed="true"] { color: #fff; background: var(--brand-dark); }
.settings-employee-panel { margin-bottom: 16px; }
.employee-panel-summary { display: flex; align-items: center; gap: 10px; color: var(--muted); }
.employee-panel-summary > strong { color: var(--brand); font-size: 1.5rem; }
.employee-panel-summary .dashboard-primary-action { margin-left: auto; }
.employees-card { margin-bottom: 18px; }
.table-scroll { overflow-x: auto; }
.employees-table { min-width: 760px; }
.role-badge { display: inline-block; padding: 3px 9px; border-radius: 20px; color: #705518; background: #f5efdE; font-size: .82rem; }
.employees-page { font-size: 15px; }
.employees-page .page-heading { margin-bottom: 20px; }
.employees-page .page-heading h2 { font-size: 1.35rem; }
.employees-page .page-heading p { max-width: 720px; font-size: 1rem; }
.employee-summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 20px; }
.employee-summary-card { display: flex; align-items: center; gap: 14px; padding: 16px 18px; border: 1px solid var(--border); border-radius: var(--radius); background: #fff; box-shadow: var(--shadow); }
.employee-summary-icon { width: 44px; height: 44px; display: grid; flex: 0 0 44px; place-items: center; border-radius: 12px; color: #94701e; background: #f7f0dc; }
.employee-summary-icon svg, .employee-add-heading svg { width: 23px; height: 23px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.employee-summary-icon-muted { color: #57534b; background: #eeece7; }
.employee-summary-card strong, .employee-summary-card small { display: block; }
.employee-summary-card strong { font-size: 1.35rem; line-height: 1.2; }
.employee-summary-card small { margin-top: 3px; color: var(--muted); }
.employee-tabs { display: flex; gap: 8px; margin-bottom: 14px; border-bottom: 1px solid var(--border); }
.employee-tabs a { display: inline-flex; align-items: center; gap: 8px; margin-bottom: -1px; padding: 10px 14px; border-bottom: 2px solid transparent; color: #5f6470; font-weight: 600; text-decoration: none; }
.employee-tabs a.is-active { border-color: #9c7825; color: #211f1a; }
.employee-tabs a span { min-width: 22px; padding: 1px 6px; border-radius: 12px; color: #5b4717; background: #f3ead0; font-size: .78rem; text-align: center; }
.employee-list-card { padding: 0; overflow: hidden; }
.employee-card-heading { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 20px; }
.employee-card-heading h2 { margin: 0 0 4px; }
.employee-card-heading p { margin: 0; color: var(--muted); }
.employee-list-card .table-scroll { padding: 0 20px 20px; }
.employees-table { min-width: 900px; }
.employees-table th { color: #5b4717; background: #f5f2eb; font-size: .82rem; letter-spacing: .03em; text-transform: uppercase; }
.employees-table td { vertical-align: middle; }
.employee-status { display: inline-flex; align-items: center; gap: 6px; padding: 4px 9px; border-radius: 999px; font-size: .8rem; font-weight: 700; white-space: nowrap; }
.employee-status::before { width: 7px; height: 7px; border-radius: 50%; background: currentColor; content: ""; }
.employee-status-active { color: #376849; background: #eaf4ec; }
.employee-status-suspended { color: #805d18; background: #f8f0dc; }
.employee-status-note { display: block; margin-top: 4px; color: var(--muted); }
.employee-actions { display: flex; align-items: flex-start; gap: 7px; flex-wrap: wrap; }
.employee-actions form { margin: 0; }
.employee-action-button { min-height: 34px; padding: 6px 10px; font-size: .83rem; white-space: nowrap; }
.employee-suspend-details { position: relative; }
.employee-suspend-details > summary { display: list-item; cursor: pointer; list-style: none; }
.employee-suspend-details > summary::-webkit-details-marker { display: none; }
.employee-inline-form { position: absolute; z-index: 3; top: calc(100% + 6px); right: 0; width: 220px; display: grid; gap: 9px; padding: 12px; border: 1px solid var(--border); border-radius: 7px; background: #fff; box-shadow: 0 8px 24px #0002; }
.employee-inline-form label { display: grid; gap: 5px; font-size: .8rem; font-weight: 600; }
.employee-inline-form select { width: 100%; }
.button-warning { color: #fff; background: #80621c; }
.button-warning:hover { background: #624a13; }
.button-danger { color: #fff; background: #8f3434; }
.button-danger:hover { background: #702626; }
.employee-empty-state { padding: 30px; color: var(--muted); text-align: center; }
.employee-add-heading { display: flex; align-items: center; gap: 9px; }
.employee-add-heading svg { color: #94701e; }
@media (max-width: 1100px) {
    .employees-table { min-width: 0; }
    .employees-table thead { position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; clip-path: inset(50%); }
    .employees-table tbody, .employees-table tbody tr:not(.permission-row) { display: block; width: 100%; }
    .employees-table tbody tr:not(.permission-row) { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0 0 12px; border: 1px solid var(--border); border-radius: 7px; background: #fff; }
    .employees-table tbody tr:not(.permission-row) td { display: flex; min-width: 0; flex-direction: column; gap: 5px; padding: 10px 12px; border: 0; overflow-wrap: anywhere; }
    .employees-table tbody tr:not(.permission-row) td::before { color: #705518; font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; content: attr(data-label); }
    .employees-table tbody tr:not(.permission-row) td.employee-actions { grid-column: 1 / -1; flex-direction: row; align-items: center; padding-top: 8px; border-top: 1px solid var(--border); }
    .employees-table tbody tr:not(.permission-row) td.employee-actions::before { display: none; }
    .employees-table tbody tr:not(.permission-row) td[colspan] { grid-column: 1 / -1; }
    .employees-table .permission-row:not([hidden]) { display: block; margin: -12px 0 12px; }
    .employees-table .permission-row:not([hidden]) > td { display: block; width: 100%; }
}
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

.inventory-content { max-width: 1600px; }
.inventory-toolbar { display: flex; align-items: flex-end; justify-content: space-between; gap: 14px; margin-bottom: 18px; padding: 14px; border: 1px solid var(--border); background: #fff; }
.inventory-search { display: flex; align-items: flex-end; flex: 1; flex-wrap: wrap; gap: 10px; margin: 0; }
.inventory-search > input { width: min(230px, 100%); }
.inventory-search label { margin: 0; color: #596575; font-size: .82rem; }
.inventory-search select { min-width: 130px; }
.inventory-toolbar-actions { display: flex; align-items: center; gap: 8px; }
.inventory-new-button { display: inline-flex; min-height: 38px; align-items: center; padding: 8px 14px; border-radius: 5px; color: #fff; background: var(--topbar); text-decoration: none; white-space: nowrap; }
.inventory-new-button:hover { color: #fff; background: #454852; }
.inventory-dashboard-link { min-width: 38px; display: grid; place-items: center; text-decoration: none; }
.inventory-list-card, .inventory-form-card { padding: 0; overflow: hidden; border: 1px solid var(--border); border-radius: 7px; background: #fff; box-shadow: var(--shadow); }
.inventory-list-heading { min-height: 58px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 18px; border-bottom: 1px solid var(--border); }
.inventory-list-heading h2 { margin: 0; }
.inventory-count { display: inline-block; min-width: 28px; padding: 1px 8px; border-radius: 14px; color: #fff; background: #343944; font-size: .8rem; text-align: center; vertical-align: middle; }
.inventory-heading-meta { display: flex; align-items: center; gap: 12px; }
.inventory-low-stock { color: #9a5b00; font-size: .85rem; }
.inventory-settings-icon { padding: 5px 9px; border: 1px solid var(--border); border-radius: 4px; color: var(--muted); }
.inventory-table-scroll { width: 100%; overflow-x: auto; }
.inventory-table { min-width: 820px; }
.inventory-table thead th { padding: 13px 10px; border-bottom: 1px solid #444; color: #384354; background: #fff; font-size: .83rem; white-space: nowrap; }
.inventory-table tbody td { height: 58px; padding: 10px; border-bottom: 1px dashed #cbd1d9; }
.numeric-cell { text-align: right; white-space: nowrap; }
.inventory-actions { white-space: nowrap; }
.inventory-actions a { display: inline-block; padding: 5px 10px; border: 1px solid var(--border); border-radius: 4px; text-decoration: none; }
.inventory-actions form { display: inline-block; margin: 0 0 0 4px; }
.inventory-delete { min-height: auto; padding: 5px 8px; border: 0; color: #b33; background: transparent; }
.inventory-delete:hover { color: #fff; background: #b33; }
.quantity-low { color: #b33; font-weight: 700; }
.stock-status { display: inline-block; margin-left: 5px; padding: 1px 6px; border-radius: 10px; color: #8d2b2b; background: #fff0ef; font-size: .72rem; }
.inventory-empty { padding: 24px; color: var(--muted); text-align: center; }
.inventory-alert-details { padding: 12px 18px; border-top: 1px solid var(--border); color: #8a5706; }
.inventory-alert-details summary { cursor: pointer; font-weight: 600; }
.inventory-alert-details ul { margin-bottom: 0; }
.inventory-form-card { margin-top: 18px; padding: 20px; }
.inventory-form-card h2 { margin-bottom: 16px; }
.inventory-item-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin: 0; }
.inventory-item-form label { display: flex; flex-direction: column; gap: 6px; margin: 0; color: #4a5665; font-weight: 600; }
.inventory-form-actions { display: flex; align-items: center; gap: 12px; grid-column: 1 / -1; }
.inventory-form-actions a { color: var(--muted); text-decoration: none; }

.purchase-content { max-width: 1500px; }
.purchase-heading, .purchase-card-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.purchase-heading { margin-bottom: 20px; }
.purchase-heading h1, .purchase-card-heading h2 { margin: 0; }
.purchase-heading p, .purchase-card-heading p { margin: 5px 0 0; color: var(--muted); }
.purchase-card { margin-bottom: 20px; padding: 20px; border: 1px solid var(--border); border-radius: 7px; background: #fff; box-shadow: var(--shadow); }
.purchase-card-heading { margin-bottom: 18px; }
.purchase-header-fields { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.purchase-header-fields label, .purchase-notes { display: flex; flex-direction: column; gap: 6px; margin: 0; color: #4a5665; font-weight: 600; }
.purchase-items-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 22px 0 10px; }
.purchase-items-heading h3 { margin: 0; }
.purchase-secondary-action, .purchase-primary-action { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; padding: 8px 14px; border: 1px solid var(--border); border-radius: 5px; color: var(--text); background: #fff; text-decoration: none; cursor: pointer; }
.purchase-secondary-action:hover { border-color: var(--brand-dark); color: var(--brand-dark); }
.purchase-primary-action { border-color: var(--topbar); color: #fff; background: var(--topbar); }
.purchase-primary-action:hover { background: #454852; }
.purchase-lines-scroll, .purchase-history-scroll { width: 100%; overflow-x: auto; }
.purchase-lines, .purchase-history { min-width: 760px; }
.purchase-lines th, .purchase-history th { padding: 11px 9px; border-bottom: 1px solid #444; color: #384354; background: #fff; font-size: .83rem; text-align: left; }
.purchase-lines td, .purchase-history td { padding: 10px 9px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.purchase-lines select, .purchase-lines input { min-width: 100px; width: 100%; }
.purchase-lines td:first-child { min-width: 250px; }
.purchase-line-total { min-width: 100px; font-weight: 600; white-space: nowrap; }
.purchase-remove-item { min-height: auto; padding: 6px 9px; border: 1px solid var(--border); border-radius: 4px; color: #a33; background: #fff; cursor: pointer; }
.purchase-remove-item:disabled { opacity: .5; cursor: not-allowed; }
.purchase-total { margin: 16px 0; text-align: right; font-size: 1.1rem; }
.purchase-total strong { margin-left: 8px; color: var(--brand-dark); }
.purchase-notes textarea { width: 100%; }
.purchase-form-actions { display: flex; justify-content: flex-end; margin-top: 16px; }
.purchase-empty { padding: 20px; color: var(--muted); text-align: center; }
.purchase-detail summary { color: var(--brand-dark); cursor: pointer; font-weight: 600; }
.purchase-detail ul { min-width: 250px; padding-left: 20px; }
.purchase-detail li { margin: 6px 0; }

@media (max-width: 1050px) {
    .pos-container { grid-template-columns: minmax(200px, .8fr) minmax(280px, 1.2fr); }
    .orden { position: static; grid-column: 1 / -1; }
    .inventory-item-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .purchase-header-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
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
    .settings-fields, .settings-toggles, .permission-groups, .employee-summary-grid { grid-template-columns: 1fr; }
    .settings-fields .field-wide { grid-column: auto; }
    .receipt-preview-heading { flex-direction: column; }
    .receipt-preview-heading output { text-align: left; }
    .theme-preset-row { align-items: flex-start; flex-direction: column; }
    .employee-actions { min-width: 230px; }
    .new-employee-form { grid-template-columns: 1fr; }
    .new-employee-form button { grid-column: auto; }
    .employee-panel-summary { align-items: flex-start; flex-wrap: wrap; }
    .employee-panel-summary .dashboard-primary-action { width: 100%; margin: 6px 0 0; text-align: center; }
    .inventory-toolbar { align-items: stretch; flex-direction: column; }
    .inventory-search { align-items: stretch; flex-direction: column; }
    .inventory-search > input, .inventory-search select { width: 100%; }
    .inventory-toolbar-actions { justify-content: flex-end; }
    .inventory-item-form { grid-template-columns: 1fr; }
    .inventory-form-actions { grid-column: auto; }
    .purchase-heading, .purchase-card-heading, .purchase-items-heading { align-items: flex-start; flex-direction: column; }
    .purchase-header-fields { grid-template-columns: 1fr; }
    .purchase-heading .purchase-secondary-action, .purchase-items-heading .purchase-secondary-action { width: 100%; }
    .pos-container { grid-template-columns: 1fr; }
    .orden { grid-column: auto; }
    header { flex-direction: column; align-items: flex-start; padding: 12px 16px; }
    header nav { gap: 4px; }
    main { overflow-x: auto; }
    .login-container { margin: 24px auto; }
    .login-content { padding: 24px 20px; }
}

.pos-page {
    --pos-bg: #0e1115;
    --pos-panel: #171c22;
    --pos-panel-raised: #20262e;
    --pos-border: #2b333d;
    --pos-text: #f2efe8;
    --pos-muted: #9aa3ad;
    --pos-gold: #d7b46a;
    --pos-gold-bright: #f1d08a;
    color: var(--pos-text);
    background: var(--pos-bg);
}
.pos-page .pos-header {
    position: sticky;
    top: 0;
    z-index: 30;
    min-height: 72px;
    padding: 10px clamp(16px, 3vw, 40px);
    border-bottom: 1px solid var(--pos-border);
    background: #13171c;
    box-shadow: 0 8px 26px rgba(0,0,0,.24);
}
.pos-header-brand, .pos-header-actions { display: flex; align-items: center; gap: 14px; }
.pos-brand-mark { display: grid; width: 42px; height: 42px; place-items: center; border: 1px solid rgba(215,180,106,.55); border-radius: 12px; color: var(--pos-gold-bright); background: linear-gradient(145deg, #29251c, #191a1b); font-size: 1.15rem; font-weight: 800; }
.pos-eyebrow, .pos-section-kicker { display: block; color: var(--pos-gold); font-size: .68rem; font-weight: 800; letter-spacing: .13em; }
.pos-header h1 { margin: 2px 0 0; font-size: 1.05rem; font-weight: 650; }
.pos-user-chip { display: inline-flex; align-items: center; gap: 8px; color: #dce1e6; font-size: .86rem; }
.pos-status-dot { width: 8px; height: 8px; border-radius: 50%; background: #7dcc87; box-shadow: 0 0 0 3px rgba(125,204,135,.13); }
.pos-page .pos-header-actions > a { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border: 1px solid var(--pos-border); border-radius: 8px; color: #e8e3d8; background: #1b2026; font-size: .84rem; }
.pos-page .pos-header-actions > a:hover { border-color: #887247; color: var(--pos-gold-bright); background: #24221d; }
.pos-main { width: min(1740px, 100%); padding: 20px clamp(14px, 2.4vw, 34px) 34px; }
.pos-container { grid-template-columns: minmax(0, 1fr) minmax(310px, 370px); align-items: start; gap: 18px; }
.pos-page .mesas, .pos-page .menu, .pos-page .orden, .pos-page .suspendidas {
    min-width: 0;
    padding: 18px;
    border: 1px solid var(--pos-border);
    border-radius: 12px;
    color: var(--pos-text);
    background: var(--pos-panel);
    box-shadow: 0 12px 30px rgba(0,0,0,.17);
}
.pos-page .mesas, .pos-page .suspendidas { grid-column: 1 / -1; }
.pos-service-heading, .pos-menu-heading, .pos-order-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.pos-service-heading { margin-bottom: 14px; }
.pos-service-heading h2, .pos-menu-heading h2, .pos-order-heading h2 { margin: 3px 0 0; color: var(--pos-text); font-size: 1.13rem; }
.pos-waiter-select { display: flex; align-items: center; gap: 10px; margin: 0; color: var(--pos-muted); font-size: .83rem; }
.pos-page input, .pos-page select, .pos-page textarea { border-color: var(--pos-border); color: var(--pos-text); background: #101419; }
.pos-page input::placeholder { color: #7f8993; }
.pos-page input:focus, .pos-page select:focus, .pos-page textarea:focus { border-color: #a98d54; outline-color: rgba(215,180,106,.25); }
.mesas-config { position: relative; margin-left: auto; color: #d9d3c7; font-size: .8rem; }
.mesas-config summary { padding: 8px 10px; border: 1px solid var(--pos-border); border-radius: 7px; color: #d9d3c7; cursor: pointer; list-style: none; }
.mesas-config summary::-webkit-details-marker { display: none; }
.mesas-config summary::before { content: "⚙ "; color: var(--pos-gold); }
.mesas-config[open] { display: flex; align-items: center; gap: 8px; }
.mesas-config[open] summary { display: none; }
.mesas-config label { display: flex; align-items: center; gap: 8px; margin: 0; }
.mesas-config input { width: 78px; min-height: 34px; }
.pos-page .mesas-config button { min-height: 34px; padding: 6px 10px; border-color: #826a3e; color: #171717; background: var(--pos-gold); }
.mesas-grid { display: flex; gap: 8px; overflow-x: auto; padding: 2px 1px 9px; }
.pos-page .mesa-btn { min-width: 82px; min-height: 39px; padding: 8px 13px; border-color: var(--pos-border); border-radius: 8px; color: #c6cbd0; background: #20262d; white-space: nowrap; }
.pos-page .mesa-btn:hover, .pos-page .mesa-btn.selected { border-color: #9b8048; color: #171717; background: var(--pos-gold); }
.pos-service-selection { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 7px 0 0; padding-top: 11px; border-top: 1px solid var(--pos-border); color: var(--pos-muted); font-size: .79rem; }
.pos-service-selection strong { display: flex; align-items: center; gap: 8px; color: #e2d4b3; font-weight: 600; }
.pos-selection-divider { color: #6c737a; }
.pos-menu-heading { margin-bottom: 16px; }
.pos-result-count { color: var(--pos-muted); font-size: .8rem; white-space: nowrap; }
.menu-filtros { margin-bottom: 12px; }
.menu-filtros .pos-menu-search { display: flex; align-items: center; gap: 10px; min-width: 0; margin: 0; padding: 0 12px; border: 1px solid var(--pos-border); border-radius: 9px; color: var(--pos-gold); background: #101419; font-size: 1.3rem; }
.pos-menu-search input { width: 100%; min-height: 43px; padding: 8px 0; border: 0; outline: 0; background: transparent; }
.pos-category-bar { display: flex; gap: 7px; margin-bottom: 15px; overflow-x: auto; padding: 2px 1px 5px; scrollbar-width: thin; }
.pos-page .pos-category-chip { flex: 0 0 auto; min-height: 32px; padding: 6px 12px; border-color: var(--pos-border); border-radius: 18px; color: #c4cbd2; background: #20262d; font-size: .76rem; }
.pos-page .pos-category-chip:hover, .pos-page .pos-category-chip.active { border-color: #987d48; color: #171717; background: var(--pos-gold); }
.menu-grid { grid-template-columns: repeat(auto-fill, minmax(148px, 1fr)); gap: 11px; }
.pos-page .menu-item {
    position: relative;
    display: flex;
    min-width: 0;
    min-height: 0;
    flex-direction: column;
    padding: 0;
    overflow: hidden;
    border: 1px solid var(--pos-border);
    border-radius: 10px;
    color: var(--pos-text);
    background: #1d232a;
    text-align: left;
}
.pos-page .menu-item:hover { transform: translateY(-2px); border-color: #9d824a; color: var(--pos-text); background: #242a31; box-shadow: 0 8px 20px rgba(0,0,0,.24); }
.menu-item-visual { position: relative; display: grid; width: 100%; height: 92px; place-items: center; overflow: hidden; background: radial-gradient(ellipse at 50% 70%, #514636 0%, #2d2b25 38%, #20252a 74%); }
.menu-item:nth-of-type(3n + 1) .menu-item-visual { background: radial-gradient(ellipse at 50% 70%, #514537 0%, #2e2925 40%, #20252a 76%); }
.menu-item:nth-of-type(3n + 2) .menu-item-visual { background: radial-gradient(ellipse at 50% 70%, #3c493f 0%, #282f2b 40%, #20252a 76%); }
.menu-item:nth-of-type(3n) .menu-item-visual { background: radial-gradient(ellipse at 50% 70%, #4b3c37 0%, #302826 40%, #20252a 76%); }
.menu-item-visual::before, .menu-item-visual::after, .menu-item-plate { position: absolute; display: block; border-radius: 50%; content: ""; }
.menu-item-visual::before { width: 62px; height: 62px; border: 1px solid rgba(244,231,201,.5); background: linear-gradient(145deg, #d6c6a2, #99866a); box-shadow: 0 5px 13px rgba(0,0,0,.35); }
.menu-item-visual::after { width: 48px; height: 48px; border: 2px solid rgba(61,52,39,.62); background: radial-gradient(circle at 38% 36%, #b47736 0 12%, transparent 13%), radial-gradient(circle at 62% 40%, #66814c 0 13%, transparent 14%), radial-gradient(circle at 48% 63%, #b64d34 0 12%, transparent 13%), radial-gradient(circle at 35% 64%, #e4c66d 0 8%, transparent 9%), radial-gradient(circle, #80613d, #4b4737 72%); }
.menu-item-plus { position: absolute; right: 10px; bottom: 9px; z-index: 1; display: grid; width: 27px; height: 27px; place-items: center; border: 1px solid rgba(241,208,138,.44); border-radius: 8px; color: var(--pos-gold-bright); background: rgba(15,18,21,.78); font-size: 1.12rem; }
.menu-item-details { display: grid; min-height: 83px; align-content: start; gap: 3px; padding: 10px 11px 11px; }
.menu-item-name { overflow: hidden; color: #f0eee8; font-size: .84rem; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
.menu-item-category { overflow: hidden; color: var(--pos-muted); font-size: .69rem; text-overflow: ellipsis; white-space: nowrap; }
.menu-item-price { margin-top: 3px; color: var(--pos-gold-bright); font-size: .9rem; font-weight: 750; }
.pos-page .pos-inventory-heading { grid-column: 1 / -1; margin: 8px 0 0; padding-top: 14px; border-top: 1px solid var(--pos-border); color: #ded8cb; font-size: .9rem; }
.pos-page [hidden] { display: none !important; }
.pos-order-heading { margin-bottom: 12px; }
.pos-order-count { display: grid; min-width: 30px; height: 30px; place-items: center; padding: 0 8px; border: 1px solid rgba(215,180,106,.35); border-radius: 10px; color: var(--pos-gold-bright); background: rgba(215,180,106,.09); font-size: .82rem; font-weight: 700; }
.pos-page .orden { position: sticky; top: 88px; max-height: calc(100vh - 106px); overflow-y: auto; }
.pos-page .estado-info { margin: 0 0 10px; padding: 9px 10px; border: 1px solid var(--pos-border); border-radius: 8px; color: var(--pos-muted); background: #12171c; font-size: .75rem; }
.pos-page #orden-list { display: grid; gap: 0; max-height: min(35vh, 360px); margin: 0; padding: 0; overflow-y: auto; list-style: none; }
.pos-page #orden-list:empty::after { padding: 20px 10px; color: #77818b; content: "Tu pedido aparecerá aquí al elegir productos."; font-size: .8rem; text-align: center; }
.pos-page .order-line { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 1px; border-bottom: 1px solid var(--pos-border); }
.order-line-info { display: grid; min-width: 0; gap: 3px; }
.order-line-name { overflow: hidden; color: #ece9e1; font-size: .81rem; text-overflow: ellipsis; white-space: nowrap; }
.order-line-meta, .order-line-note { color: var(--pos-muted); font-size: .72rem; }
.order-line-note { color: #d4b778; }
.order-line-controls { display: flex; flex: 0 0 auto; align-items: center; gap: 5px; }
.pos-page .order-line-quantity { width: 46px; min-height: 31px; padding: 4px; text-align: center; }
.order-line-total { color: var(--pos-gold-bright); font-size: .79rem; }
.pos-page .order-line-stepper { min-width: 27px; min-height: 29px; padding: 2px 6px; border-color: var(--pos-border); border-radius: 7px; color: #e3d3af; background: #252b32; }
.pos-page .order-line-stepper:disabled { opacity: .4; }
.pos-page .order-line-note-button, .pos-page .order-line-remove { min-width: 30px; min-height: 30px; padding: 3px 7px; border-color: var(--pos-border); border-radius: 7px; color: #d1d6dc; background: #252b32; font-size: .72rem; }
.pos-page .order-line-remove { color: #e99e96; font-size: 1.05rem; }
.pos-page .venta-resumen { margin: 13px 0; padding: 12px; border: 1px solid var(--pos-border); border-radius: 9px; background: #11161b; }
.pos-page .venta-resumen p { margin: 5px 0; color: #aeb6be; font-size: .8rem; }
.pos-page .venta-resumen p:nth-last-child(1) { margin-top: 10px; padding-top: 9px; border-top: 1px solid var(--pos-border); color: var(--pos-gold-bright); font-size: 1.12rem; }
.pos-page .venta-resumen p:last-child span { color: inherit; font-weight: 750; }
.pos-page .venta-acciones { gap: 7px; margin: 10px 0; }
.pos-page .venta-acciones label { flex: 1 1 100%; margin: 0; color: #aeb6be; font-size: .76rem; }
.pos-page .venta-acciones input, .pos-page .venta-acciones select { min-height: 36px; margin-top: 4px; }
.pos-page #imprimir-comandas-rapido, .pos-page #imprimir-pre-recibo { flex: 1 1 100%; min-height: 36px; border-color: var(--pos-border); color: #d4d9df; background: #232a31; font-size: .76rem; }
.pos-page #cobrar { flex: 1 1 100%; min-height: 47px; border-color: #b39352; border-radius: 9px; color: #171717; background: linear-gradient(135deg, #f0d18f, #c59c4f); font-size: .95rem; font-weight: 800; }
.pos-page #cobrar:hover { background: #f5dca8; }
.pos-page #suspender, .pos-page #limpiar-orden { flex: 1 1 calc(50% - 7px); min-height: 34px; border-color: var(--pos-border); color: #bcc4cb; background: transparent; font-size: .74rem; }
.pos-page #limpiar-orden { color: #df9c94; }
.pos-page #imprimir, .pos-page #imprimir-cocina, .pos-page #imprimir-barra { min-height: 32px; margin: 4px 3px 0 0; padding: 5px 8px; border-color: var(--pos-border); color: var(--pos-muted); background: #1e242b; font-size: .7rem; }
.pos-page #estado-impresion-comandas { color: #b4c29b; font-size: .76rem; }
.pos-page .promo-banner { border: 1px solid #6f5c34; color: var(--pos-gold-bright) !important; background: #282217 !important; font-size: .75rem; }
.pos-page .tip-panel { border-color: var(--pos-border) !important; }
.pos-page .tip-panel h4, .pos-page .tip-panel p { color: #e0dbd1; }
.pos-page .tip-options { gap: 5px; }
.pos-page .tip-options .tip-btn { min-height: 32px; padding: 6px 9px; border-color: #73603a; color: var(--pos-gold-bright); background: #282319; font-size: .75rem; }
.pos-page .suspendidas { margin-top: 0; }
.pos-page .suspendidas h3 { margin-bottom: 12px; color: var(--pos-text); }
.pos-page .suspendidas-search { display: flex; gap: 8px; }
.pos-page .suspendidas-search input { flex: 1; min-width: 0; }
.pos-page .suspendidas-search button { border-color: #826a3e; color: #171717; background: var(--pos-gold); }
.pos-page .suspendidas [id$="-results"] > * { margin-top: 8px; padding: 10px; border: 1px solid var(--pos-border); border-radius: 8px; color: #d6dbe0; background: #1e242b; }
.pos-page .suspendidas [id$="-results"] button { min-height: 30px; padding: 5px 9px; font-size: .75rem; }
.pos-page .modal { background: rgba(0,0,0,.72) !important; backdrop-filter: blur(4px); }
.pos-page .modal-content { border: 1px solid var(--pos-border); color: var(--pos-text); background: #191e24 !important; box-shadow: 0 18px 55px rgba(0,0,0,.5); }
.pos-page .modal-content h3 { color: var(--pos-gold-bright); }
.pos-page .modal-content button { border-color: var(--pos-border); }

@media (max-width: 1050px) {
    .pos-container { grid-template-columns: minmax(0, 1fr) minmax(280px, 340px); }
    .pos-page .orden { grid-column: auto; position: sticky; top: 88px; }
    .pos-page .mesas, .pos-page .suspendidas { grid-column: 1 / -1; }
}
@media (max-width: 760px) {
    .pos-page .pos-header { position: static; align-items: center; flex-direction: row; padding: 10px 14px; }
    .pos-header-actions { gap: 8px; }
    .pos-user-chip { display: none; }
    .pos-page .pos-header-actions > a { padding: 7px 9px; font-size: .76rem; }
    .pos-main { padding: 12px; overflow: visible; }
    .pos-container { grid-template-columns: minmax(0, 1fr); gap: 12px; }
    .pos-page .mesas, .pos-page .menu, .pos-page .orden, .pos-page .suspendidas { grid-column: auto; padding: 14px; }
    .pos-page .orden { position: static; max-height: none; }
    .pos-service-heading { flex-wrap: wrap; }
    .pos-service-heading > div { flex: 1 1 100%; }
    .pos-waiter-select { flex: 1 1 170px; justify-content: space-between; }
    .pos-waiter-select select { max-width: 60%; }
    .mesas-config { margin-left: 0; }
    .mesas-config[open] { flex-wrap: wrap; }
    .mesas-grid { margin-right: -4px; }
    .pos-page .menu-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .menu-item-visual { height: 78px; }
    .pos-page .order-line { align-items: flex-start; }
    .pos-page .venta-acciones label { flex: 1 1 100%; }
    .pos-page .suspendidas-search { flex-wrap: wrap; }
}
@media (max-width: 380px) {
    .pos-brand-mark { width: 36px; height: 36px; }
    .pos-page .pos-header h1 { font-size: .92rem; }
    .pos-page .pos-header-actions > a { font-size: 0; }
    .pos-page .pos-header-actions > a span { font-size: 1.2rem; }
    .pos-page .menu-grid { grid-template-columns: minmax(0, 1fr); }
    .menu-item-visual { height: 86px; }
}

.pos-page .pos-header-actions > .pos-notification-link {
    display: grid;
    width: 40px;
    height: 40px;
    place-items: center;
    padding: 0;
    border-radius: 9px;
}
.pos-notification-link svg { width: 21px; height: 21px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.7; }
.pos-page .pos-header-actions > .pos-pending-sales-link { border-color: rgba(215,180,106,.35); color: var(--pos-gold-bright); background: #252319; }
.pos-container { grid-template-columns: minmax(210px, 250px) minmax(0, 1fr) minmax(310px, 370px); grid-template-areas: "service menu order"; align-items: start; }
.pos-container > .menu { grid-area: menu; }
.pos-container > .orden { grid-area: order; }
.pos-container > .mesas { grid-area: service; grid-column: auto; width: auto; min-height: 0; }
.pos-page .mesas-grid { display: grid; max-height: 320px; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 7px; overflow-y: auto; }
.pos-page .mesas-grid .mesa-btn { min-width: 0; padding: 7px 5px; font-size: .75rem; }
.pos-page .pos-service-heading { align-items: flex-start; flex-wrap: wrap; }
.pos-page .pos-service-heading > div { flex: 1 1 100%; }
.pos-page .pos-waiter-select { width: 100%; justify-content: space-between; }
.pos-page .pos-waiter-select select { max-width: 60%; }
.pos-page .mesas-config { margin-left: 0; }
.pos-service-selection { align-items: flex-start; flex-direction: column; gap: 3px; }
.pos-service-selection strong { flex-wrap: wrap; }
.workflow-main { width: min(1180px, 100%); margin: 0 auto; padding: 28px clamp(14px, 3vw, 36px) 42px; }
.workflow-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 20px; }
.workflow-heading h2 { margin: 4px 0; color: var(--pos-text); font-size: clamp(1.35rem, 3vw, 1.8rem); }
.workflow-heading p { max-width: 680px; margin: 6px 0 0; color: var(--pos-muted); line-height: 1.5; }
.workflow-status { flex: 0 0 auto; padding: 7px 11px; border: 1px solid #6f4845; border-radius: 18px; color: #e4a29b; background: #2b1f1f; font-size: .78rem; font-weight: 700; }
.workflow-card { margin-bottom: 14px; padding: 18px; border: 1px solid var(--pos-border); border-radius: 12px; color: var(--pos-text); background: var(--pos-panel); box-shadow: 0 10px 28px rgba(0,0,0,.16); }
.workflow-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 14px; }
.workflow-toolbar h3 { margin: 0; color: var(--pos-text); font-size: 1rem; }
.workflow-count { color: var(--pos-gold-bright); font-size: .84rem; font-weight: 750; }
.workflow-search { display: flex; align-items: center; gap: 8px; width: min(380px, 100%); margin: 0; }
.workflow-search input { min-width: 0; flex: 1; }
.workflow-search button, .workflow-back-link, .workflow-action-link { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: 7px; padding: 8px 13px; border: 1px solid var(--pos-border); border-radius: 8px; color: #e8e3d8; background: #222830; text-decoration: none; white-space: nowrap; }
.workflow-search button, .workflow-action-link { border-color: #8d7442; color: #191714; background: linear-gradient(135deg, #f0d18f, #c59c4f); font-weight: 750; }
.workflow-back-link:hover, .workflow-search button:hover, .workflow-action-link:hover { filter: brightness(1.08); color: inherit; }
.pending-sale-list { display: grid; gap: 12px; }
.pending-sale-card { padding: 16px; border: 1px solid var(--pos-border); border-radius: 10px; background: #1c2229; }
.pending-sale-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.pending-sale-heading h3 { margin: 0; color: #f0e9d8; font-size: 1rem; }
.pending-sale-meta { display: flex; flex-wrap: wrap; gap: 7px 16px; margin-top: 6px; color: var(--pos-muted); font-size: .78rem; }
.pending-sale-subtotal { color: var(--pos-gold-bright); font-size: 1.05rem; font-weight: 800; white-space: nowrap; }
.pending-sale-items { display: grid; gap: 7px; margin: 0; padding: 12px 0; border-top: 1px solid var(--pos-border); border-bottom: 1px solid var(--pos-border); list-style: none; }
.pending-sale-items li { display: flex; justify-content: space-between; gap: 12px; color: #d9dee2; font-size: .83rem; }
.pending-sale-items small { display: block; margin-top: 3px; color: var(--pos-muted); }
.pending-sale-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 12px; color: var(--pos-muted); font-size: .78rem; }
.workflow-empty { padding: 28px 20px; border: 1px dashed #46505c; border-radius: 10px; color: var(--pos-muted); text-align: center; line-height: 1.6; }
.workflow-empty strong { display: block; margin-bottom: 5px; color: #e8e3d8; font-size: 1rem; }
.delivery-fields { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-top: 18px; }
.delivery-field { padding: 12px; border: 1px solid var(--pos-border); border-radius: 8px; background: #1c2229; }
.delivery-field span { display: block; margin-bottom: 5px; color: var(--pos-gold); font-size: .67rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.delivery-field strong { color: #dce1e6; font-size: .8rem; }
.pos-page .suspendida-checkout { position: fixed; inset: 0; z-index: 1100; align-items: center; justify-content: center; padding: 18px; background: rgba(0,0,0,.76); backdrop-filter: blur(5px); }
.pos-page .suspendida-checkout-content { width: min(680px, 100%); max-height: min(90vh, 880px); margin: 0; padding: 20px; overflow-y: auto; border: 1px solid #3a424c; border-radius: 14px; color: var(--pos-text); background: #171c22; box-shadow: 0 22px 70px rgba(0,0,0,.58); }
.suspendida-sale-meta { display: flex; flex-wrap: wrap; gap: 9px 18px; margin-bottom: 10px; padding: 10px 12px; border: 1px solid var(--pos-border); border-radius: 9px; background: #11161b; }
.suspendida-sale-meta p { margin: 0; color: var(--pos-muted); font-size: .78rem; }
.suspendida-sale-meta strong { margin-left: 5px; color: #e7d8b8; }
.suspendida-sale-items { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 15px; }
.suspendida-sale-items h3 { margin: 0; color: #d6c498; font-size: .78rem; }
.suspendida-sale-items ul { max-height: 140px; margin: 0; padding: 0; overflow-y: auto; list-style: none; }
.suspendida-sale-items li { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 7px 2px; border-bottom: 1px solid var(--pos-border); color: #e0e4e6; font-size: .75rem; }
.suspendida-add-products { margin: 12px 0; padding: 11px; border: 1px solid var(--pos-border); border-radius: 9px; background: #11161b; }
.suspendida-add-products label { display: block; margin-bottom: 6px; color: #d6c498; font-size: .76rem; font-weight: 700; }
.suspendida-add-products > div { display: flex; gap: 8px; }
.suspendida-add-products select { min-width: 0; flex: 1; }
.pos-page .suspendida-add-products button { min-height: 36px; border-color: #8d7442; color: #191714; background: var(--pos-gold); }
.suspendida-edit-item { align-items: flex-start !important; flex-direction: column; }
.suspendida-edit-item-name { color: #e0e4e6; }
.suspendida-edit-controls { display: flex; width: 100%; align-items: center; gap: 6px; }
.suspendida-edit-controls input[type="number"] { width: 58px; min-height: 30px; padding: 4px; }
.suspendida-edit-controls input[type="text"] { min-width: 0; min-height: 30px; flex: 1; padding: 4px 7px; }
.suspendida-edit-controls button { min-height: 28px; padding: 4px 8px; border-color: var(--pos-border); color: #e5a099; background: #282222; font-size: .7rem; }
.pos-page .suspendida-checkout-content .venta-acciones label { flex: 1 1 calc(50% - 8px); }
.pos-page .suspendida-checkout-content #completar-suspendida { flex: 1 1 100%; min-height: 44px; border-color: #b39352; color: #171717; background: linear-gradient(135deg, #f0d18f, #c59c4f); font-weight: 800; }
.pos-page .suspendida-checkout-content #guardar-suspendida { flex: 1 1 100%; border-color: var(--pos-border); color: #d9d4c9; background: #242a31; }
@media (max-width: 1050px) {
    .pos-container { grid-template-columns: minmax(200px, 235px) minmax(0, 1fr); grid-template-areas: "service menu" "order order"; }
    .pos-container > .orden { position: static; max-height: none; }
}
@media (max-width: 760px) {
    .pos-page .pos-header { flex-wrap: wrap; }
    .pos-header-actions { flex: 1 1 100%; justify-content: flex-end; gap: 7px; }
    .pos-container { grid-template-columns: minmax(0, 1fr); grid-template-areas: "service" "menu" "order"; }
    .pos-container > .mesas { min-height: 0; }
    .pos-page .mesas-grid { max-height: 200px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .workflow-heading { flex-direction: column; }
    .workflow-toolbar, .pending-sale-footer { align-items: flex-start; flex-direction: column; }
    .workflow-search { width: 100%; }
    .delivery-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .suspendida-sale-items { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 420px) {
    .workflow-main { padding-top: 18px; }
    .workflow-card, .pending-sale-card { padding: 13px; }
    .delivery-fields { grid-template-columns: minmax(0, 1fr); }
    .pending-sale-heading { flex-direction: column; }
    .pending-sale-footer { align-items: stretch; }
    .workflow-action-link { width: 100%; }
}
