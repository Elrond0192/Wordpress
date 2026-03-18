<?php
/**
 * HoopMetrics – Diagnostica connessione
 * Carica questo file UNA VOLTA in wp-content/themes/court-analytics-pro/
 * poi accedi via browser: http://localhost/wp-content/themes/court-analytics-pro/diagnostic.php
 * ELIMINA questo file dopo il test!
 */

// Carica WP env
$wp_load = dirname(__FILE__, 4) . '/wp-load.php';
if (!file_exists($wp_load)) { die('wp-load.php non trovato'); }
require_once $wp_load;

if (!current_user_can('manage_options')) { die('Accesso negato – devi essere loggato come admin.'); }

header('Content-Type: text/html; charset=utf-8');
echo '<style>body{font-family:monospace;padding:2rem;background:#0a0e1a;color:#f1f5f9}
.ok{color:#4ade80}.fail{color:#f87171}.warn{color:#fde047}
h2{color:#f97316;margin:1.5rem 0 .5rem}pre{background:#111827;padding:1rem;border-radius:8px;}</style>';
echo '<h1>🏀 HoopMetrics Diagnostica</h1>';

// 1. Versione PHP
echo '<h2>1. PHP</h2>';
$phpv = phpversion();
$ok   = version_compare($phpv, '8.0.0', '>=');
echo '<p class="' . ($ok ? 'ok' : 'fail') . '">PHP ' . $phpv . ($ok ? ' ✅' : ' ❌ (richiede >= 8.0)') . '</p>';

// 2. Estensioni PDO
echo '<h2>2. Driver PDO per SQL Server</h2>';
$exts = ['pdo_sqlsrv' => 'pdo_sqlsrv (raccomandato)', 'pdo_odbc' => 'pdo_odbc (alternativa)'];
$has_driver = false;
foreach ($exts as $ext => $lbl) {
    $loaded = extension_loaded($ext);
    if ($loaded) $has_driver = true;
    echo '<p class="' . ($loaded ? 'ok' : 'warn') . '">' . $lbl . ': ' . ($loaded ? '✅ disponibile' : '⚠️ non caricato') . '</p>';
}
if (!$has_driver) {
    echo '<p class="fail">❌ Nessun driver PDO/SQL Server trovato. Installa php-sqlsrv o php-odbc.</p>';
}

// 3. Costanti wp-config.php
echo '<h2>3. Costanti wp-config.php</h2>';
$consts = ['HM_DB_SERVER','HM_DB_NAME','HM_DB_USER','HM_DB_PASS','HM_ID_SALT'];
foreach ($consts as $c) {
    $defined = defined($c);
    echo '<p class="' . ($defined ? 'ok' : 'fail') . '">' . $c . ': ' . ($defined ? '✅ definita' : '❌ MANCANTE') . '</p>';
}

// 4. Test connessione Azure SQL (solo se costanti presenti + driver disponibile)
echo '<h2>4. Test connessione Azure SQL</h2>';
if (defined('HM_DB_SERVER') && defined('HM_DB_NAME') && $has_driver) {
    try {
        $dsn = 'sqlsrv:Server=tcp:' . HM_DB_SERVER . ',1433;Database=' . HM_DB_NAME . ';Encrypt=yes;TrustServerCertificate=no;LoginTimeout=10';
        $pdo = new PDO($dsn, HM_DB_USER, HM_DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo '<p class="ok">✅ Connessione riuscita!</p>';
        $rows = $pdo->query("SELECT @@VERSION AS v")->fetchAll(PDO::FETCH_ASSOC);
        echo '<pre>' . htmlspecialchars($rows[0]['v'] ?? '—') . '</pre>';
    } catch (Exception $e) {
        echo '<p class="fail">❌ ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
} else {
    echo '<p class="warn">⚠️ Saltato (costanti mancanti o driver assente).</p>';
}

// 5. Plugin hoopmetrics-api attivo?
echo '<h2>5. Plugin hoopmetrics-api</h2>';
$active = is_plugin_active('hoopmetrics-api/hoopmetrics-api.php');
echo '<p class="' . ($active ? 'ok' : 'fail') . '">Plugin: ' . ($active ? '✅ attivo' : '❌ non attivo – attivalo da WP Admin › Plugin') . '</p>';

// 6. Struttura cartelle tema
echo '<h2>6. File tema</h2>';
$theme_dir = get_template_directory();
$required  = ['style.css','functions.php','index.php','header.php','footer.php',
               'assets/js/main.js','assets/js/api.js',
               'page-templates/dashboard.php','page-templates/leaderboard.php'];
foreach ($required as $f) {
    $exists = file_exists($theme_dir . '/' . $f);
    echo '<p class="' . ($exists ? 'ok' : 'fail') . '">' . $f . ': ' . ($exists ? '✅' : '❌ MANCANTE') . '</p>';
}

echo '<h2>✅ Fine diagnostica</h2>';
echo '<p class="warn">⚠️ ELIMINA questo file dopo il test: <code>' . __FILE__ . '</code></p>';
