<?php
/**
 * HoopMetrics – Pagina diagnostica WP Admin
 * Accessibile da: WP Admin → HoopMetrics → Diagnostica
 * Aggiunge questo file a: hoopmetrics-api/includes/class-admin-diagnostic.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Admin_Diagnostic {

    public static function init(): void {
        add_action( 'admin_menu', [ self::class, 'add_menu' ] );
    }

    public static function add_menu(): void {
        add_menu_page(
            'HoopMetrics',
            '🏀 HoopMetrics',
            'manage_options',
            'hoopmetrics',
            [ self::class, 'render_dashboard' ],
            'dashicons-chart-bar',
            3
        );
        add_submenu_page(
            'hoopmetrics',
            'Diagnostica sistema',
            'Diagnostica',
            'manage_options',
            'hoopmetrics-diagnostic',
            [ self::class, 'render_page' ]
        );
        add_submenu_page(
            'hoopmetrics',
            'Configurazione',
            'Configurazione',
            'manage_options',
            'hoopmetrics-config',
            [ self::class, 'render_config' ]
        );
    }

    // ── Pagina principale HoopMetrics ──────────────────────────────────────
    public static function render_dashboard(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $plugin_ok = class_exists('HM_DB');
        $theme_ok  = get_template() === 'court-analytics-pro';
        $db_ok     = false;
        if ( $plugin_ok ) {
            try { HM_DB::instance(); $db_ok = true; } catch(Exception $e) {}
        }
        echo '<div class="wrap">';
        echo '<h1>🏀 HoopMetrics Control Panel</h1>';
        echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;max-width:720px;margin-top:1.5rem">';
        foreach ([
            ['🔌 Plugin API',  $plugin_ok ? 'Attivo' : 'Errore',       $plugin_ok],
            ['🎨 Tema',        $theme_ok  ? 'Attivo' : 'Tema diverso',  $theme_ok],
            ['🗄️ Azure SQL',   $db_ok     ? 'Connesso' : 'Non connesso', $db_ok],
        ] as [$icon, $label, $ok]) {
            $color = $ok ? '#4ade80' : '#f87171';
            $bg    = $ok ? 'rgba(74,222,128,.08)' : 'rgba(248,113,113,.08)';
            echo "<div style='background:{$bg};border:1px solid {$color}33;border-radius:10px;padding:1.2rem;text-align:center'>
                    <div style='font-size:1.8rem'>{$icon}</div>
                    <div style='font-weight:700;margin:.4rem 0 .2rem;color:{$color}'>{$label}</div>
                  </div>";
        }
        echo '</div>';
        echo '<p style="margin-top:1.5rem"><a href="' . admin_url('admin.php?page=hoopmetrics-diagnostic') . '" class="button button-primary">Vai alla diagnostica completa →</a></p>';
        echo '</div>';
    }

    // ── Pagina diagnostica completa ────────────────────────────────────────
    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accesso negato.' );
        }

        $checks = self::run_checks();
        $all_ok = ! in_array( false, array_column( $checks, 'ok' ), true );

        echo '<div class="wrap">';
        echo '<h1>🏀 HoopMetrics – Diagnostica sistema</h1>';
        echo '<p style="color:#666;margin-bottom:1.5rem">Tutti i controlli necessari per il corretto funzionamento del tema.</p>';

        // Barra stato globale
        if ( $all_ok ) {
            echo '<div style="background:rgba(74,222,128,.1);border:1px solid #4ade8066;border-radius:10px;padding:1rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem">
                    <span style="font-size:1.5rem">✅</span>
                    <strong style="color:#4ade80">Tutto OK – il sistema è pronto</strong>
                  </div>';
        } else {
            $errors = count( array_filter( array_column( $checks, 'ok' ), fn($v) => $v === false ) );
            echo "<div style='background:rgba(248,113,113,.1);border:1px solid #f8717166;border-radius:10px;padding:1rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem'>
                    <span style='font-size:1.5rem'>❌</span>
                    <strong style='color:#f87171'>{$errors} problema/i da risolvere – scorri per i dettagli</strong>
                  </div>";
        }

        // Raggruppa per categoria
        $groups = [];
        foreach ( $checks as $c ) {
            $groups[ $c['group'] ][] = $c;
        }

        foreach ( $groups as $group => $items ) {
            echo "<h2 style='margin-top:2rem;border-bottom:1px solid #ddd;padding-bottom:.5rem'>{$group}</h2>";
            echo '<table class="widefat" style="max-width:900px;margin-top:.75rem"><thead><tr><th>Controllo</th><th>Stato</th><th>Dettaglio</th></tr></thead><tbody>';
            foreach ( $items as $c ) {
                $badge = $c['ok']
                    ? '<span style="background:#4ade8022;color:#4ade80;padding:2px 10px;border-radius:999px;font-weight:700;font-size:.85rem">✅ OK</span>'
                    : '<span style="background:#f8717122;color:#f87171;padding:2px 10px;border-radius:999px;font-weight:700;font-size:.85rem">❌ ERRORE</span>';
                if ( $c['ok'] === null ) {
                    $badge = '<span style="background:#fde04722;color:#ca8a04;padding:2px 10px;border-radius:999px;font-weight:700;font-size:.85rem">⚠️ WARN</span>';
                }
                echo "<tr>
                        <td><strong>" . esc_html( $c['label'] ) . "</strong></td>
                        <td>{$badge}</td>
                        <td style='color:#555;font-size:.88rem'>" . wp_kses_post( $c['detail'] ) . "</td>
                      </tr>";
            }
            echo '</tbody></table>';
        }

        // Sezione fix rapidi
        echo '<h2 style="margin-top:2rem;border-bottom:1px solid #ddd;padding-bottom:.5rem">🔧 Fix rapidi</h2>';
        echo '<div style="max-width:900px">';

        if ( ! defined('HM_DB_SERVER') ) {
            echo '<div style="background:#fff8e1;border-left:4px solid #f97316;padding:1rem 1.2rem;margin:.75rem 0;border-radius:0 8px 8px 0">
                    <strong>Aggiungi le costanti a wp-config.php</strong>
                    <pre style="background:#f1f3f4;padding:.75rem;border-radius:6px;margin-top:.5rem;font-size:.82rem">define(\'HM_DB_SERVER\', \'yourserver.database.windows.net\');
define(\'HM_DB_NAME\',   \'HoopMetrics\');
define(\'HM_DB_USER\',   \'username\');
define(\'HM_DB_PASS\',   \'password\');
define(\'HM_ID_SALT\',   \'' . wp_generate_password(48, false) . '\');</pre>
                  </div>';
        }

        if ( ! extension_loaded('pdo_sqlsrv') && ! extension_loaded('pdo_odbc') ) {
            echo '<div style="background:#fff8e1;border-left:4px solid #f97316;padding:1rem 1.2rem;margin:.75rem 0;border-radius:0 8px 8px 0">
                    <strong>Installa il driver PHP per SQL Server</strong>
                    <pre style="background:#f1f3f4;padding:.75rem;border-radius:6px;margin-top:.5rem;font-size:.82rem"># Ubuntu / Debian (XAMPP: usa PHP Manager)
sudo apt-get install php-sqlsrv php-pdo-sqlsrv

# macOS (Homebrew + PHP 8.x)
pecl install sqlsrv pdo_sqlsrv

# Windows XAMPP
# Scarica i DLL da: https://github.com/microsoft/msphpsql/releases
# Copia php_sqlsrv_82_ts_x64.dll e php_pdo_sqlsrv_82_ts_x64.dll in /php/ext/
# Aggiungi in php.ini:
extension=php_sqlsrv_82_ts_x64.dll
extension=php_pdo_sqlsrv_82_ts_x64.dll</pre>
                  </div>';
        }

        if ( get_template() !== 'court-analytics-pro' ) {
            echo '<div style="background:#fff8e1;border-left:4px solid #f97316;padding:1rem 1.2rem;margin:.75rem 0;border-radius:0 8px 8px 0">
                    <strong>Tema non attivo</strong><br>
                    <a href="' . admin_url('themes.php') . '" class="button button-primary" style="margin-top:.5rem">Vai a Aspetto → Temi →</a>
                  </div>';
        }

        $lb = get_page_by_path('leaderboard');
        if ( ! $lb ) {
            $pages_missing = [];
            foreach ( ['giocatore','squadra','leaderboard','confronto'] as $slug ) {
                if ( ! get_page_by_path($slug) ) $pages_missing[] = $slug;
            }
            if ( $pages_missing ) {
                echo '<div style="background:#fff8e1;border-left:4px solid #f97316;padding:1rem 1.2rem;margin:.75rem 0;border-radius:0 8px 8px 0">
                        <strong>Crea le pagine mancanti:</strong> ' . implode(', ', $pages_missing) . '<br>
                        <a href="' . admin_url('post-new.php?post_type=page') . '" class="button" style="margin-top:.5rem">Crea pagine →</a>
                      </div>';
            }
        }

        echo '</div>';

        // Bottone ricarica
        echo '<p style="margin-top:2rem">
                <a href="' . admin_url('admin.php?page=hoopmetrics-diagnostic') . '" class="button button-primary">🔄 Riesegui diagnostica</a>
              </p>';
        echo '</div>';
    }

    // ── Configurazione ─────────────────────────────────────────────────────
    public static function render_config(): void {
        if ( ! current_user_can('manage_options') ) wp_die('Accesso negato.');

        $nations = get_option('hm_available_nations', []);
        $seasons = get_option('hm_available_seasons', []);

        echo '<div class="wrap"><h1>⚙️ HoopMetrics – Configurazione</h1>';
        echo '<h2>Nazioni disponibili nel database</h2>';
        if ( $nations ) {
            echo '<ul style="list-style:disc;margin-left:1.5rem">';
            foreach ($nations as $n) echo '<li>' . esc_html($n) . '</li>';
            echo '</ul>';
        } else {
            echo '<p style="color:#f87171">Nessuna nazione trovata. Attiva il plugin e attendi il primo cron (max 1 ora) oppure <a href="' . admin_url('admin.php?page=hoopmetrics-diagnostic&refresh=1') . '">forza il refresh</a>.</p>';
        }
        echo '<h2 style="margin-top:1.5rem">Stagioni disponibili</h2>';
        if ( $seasons ) {
            echo '<ul style="list-style:disc;margin-left:1.5rem">';
            foreach ($seasons as $s) echo '<li>' . esc_html($s) . '</li>';
            echo '</ul>';
        }

        // Forza refresh manuale
        if ( isset($_GET['refresh']) && current_user_can('manage_options') ) {
            hm_api_refresh_meta();
            echo '<div class="notice notice-success"><p>✅ Nazioni e stagioni aggiornate.</p></div>';
        }

        echo '<p style="margin-top:1.5rem">
                <a href="' . admin_url('admin.php?page=hoopmetrics-config&refresh=1') . '" class="button button-primary">🔄 Forza refresh nazioni/stagioni</a>
              </p>';
        echo '</div>';
    }

    // ── Tutti i controlli ─────────────────────────────────────────────────
    private static function run_checks(): array {
        $checks = [];

        // PHP
        $phpv = phpversion();
        $checks[] = [
            'group'  => '🐘 PHP & Server',
            'label'  => 'Versione PHP',
            'ok'     => version_compare($phpv, '8.0.0', '>='),
            'detail' => "PHP {$phpv} " . (version_compare($phpv,'8.0.0','>=') ? '(richiede ≥ 8.0)' : '❌ Aggiorna a PHP 8.0+'),
        ];

        $exts = ['pdo','pdo_sqlsrv','pdo_odbc','mbstring','openssl'];
        foreach ($exts as $ext) {
            $loaded = extension_loaded($ext);
            $req    = in_array($ext, ['pdo']);
            $checks[] = [
                'group'  => '🐘 PHP & Server',
                'label'  => "Estensione {$ext}",
                'ok'     => $loaded ? true : ($req ? false : null),
                'detail' => $loaded ? 'Caricata' : ($req ? '❌ Obbligatoria – manca!' : '⚠️ Opzionale'),
            ];
        }

        // pdo_sqlsrv OR pdo_odbc
        $has_driver = extension_loaded('pdo_sqlsrv') || extension_loaded('pdo_odbc');
        $checks[] = [
            'group'  => '🐘 PHP & Server',
            'label'  => 'Driver PDO SQL Server',
            'ok'     => $has_driver,
            'detail' => $has_driver
                ? 'Disponibile (' . (extension_loaded('pdo_sqlsrv') ? 'pdo_sqlsrv' : 'pdo_odbc') . ')'
                : '❌ Installa php-sqlsrv o php-pdo-sqlsrv',
        ];

        // wp-config costanti
        foreach (['HM_DB_SERVER','HM_DB_NAME','HM_DB_USER','HM_DB_PASS','HM_ID_SALT'] as $c) {
            $def = defined($c);
            $checks[] = [
                'group'  => '⚙️ wp-config.php',
                'label'  => $c,
                'ok'     => $def,
                'detail' => $def ? '✅ Definita' : "❌ Aggiungi <code>define('{$c}', '...');</code> in wp-config.php",
            ];
        }

        // Connessione DB
        $db_ok  = false;
        $db_msg = 'Non testato (costanti mancanti o driver assente)';
        if ( defined('HM_DB_SERVER') && $has_driver && class_exists('HM_DB') ) {
            try {
                $db     = HM_DB::instance();
                $ver    = $db->query_val("SELECT @@VERSION");
                $db_ok  = true;
                $db_msg = 'Connesso – ' . substr($ver ?? '', 0, 60) . '…';
            } catch (Exception $e) {
                $db_msg = '❌ ' . esc_html($e->getMessage());
            }
        }
        $checks[] = [
            'group'  => '🗄️ Azure SQL',
            'label'  => 'Connessione PDO',
            'ok'     => $db_ok ?: ($has_driver && defined('HM_DB_SERVER') ? false : null),
            'detail' => $db_msg,
        ];

        // Tabelle nazioni
        if ( $db_ok ) {
            $nations = get_option('hm_available_nations', []);
            $seasons = get_option('hm_available_seasons', []);
            $checks[] = [
                'group'  => '🗄️ Azure SQL',
                'label'  => 'Nazioni trovate',
                'ok'     => ! empty($nations),
                'detail' => empty($nations)
                    ? '⚠️ Clicca "Forza refresh" in Configurazione'
                    : implode(', ', $nations),
            ];
            $checks[] = [
                'group'  => '🗄️ Azure SQL',
                'label'  => 'Stagioni trovate',
                'ok'     => ! empty($seasons),
                'detail' => empty($seasons) ? '⚠️ Nessuna stagione' : implode(', ', $seasons),
            ];
        }

        // Tema
        $checks[] = [
            'group'  => '🎨 Tema WordPress',
            'label'  => 'Tema attivo',
            'ok'     => get_template() === 'court-analytics-pro',
            'detail' => 'Tema corrente: ' . get_template(),
        ];

        // File tema
        $theme_dir = get_template_directory();
        $req_files = [
            'style.css', 'index.php', 'functions.php', 'header.php', 'footer.php',
            'assets/js/main.js', 'assets/js/api.js',
            'page-templates/dashboard.php', 'page-templates/player.php',
            'page-templates/team.php', 'page-templates/leaderboard.php',
        ];
        foreach ($req_files as $f) {
            $exists = file_exists("{$theme_dir}/{$f}");
            $checks[] = [
                'group'  => '🎨 Tema WordPress',
                'label'  => $f,
                'ok'     => $exists,
                'detail' => $exists ? get_template_directory_uri() . '/' . $f : "❌ File mancante in {$theme_dir}/{$f}",
            ];
        }

        // Pagine WP
        foreach ( ['giocatore','squadra','leaderboard','confronto'] as $slug ) {
            $page = get_page_by_path($slug);
            $checks[] = [
                'group'  => '📄 Pagine WordPress',
                'label'  => "Pagina /{$slug}/",
                'ok'     => ! empty($page),
                'detail' => $page
                    ? 'ID ' . $page->ID . ' – <a href="' . get_permalink($page->ID) . '" target="_blank">apri →</a>'
                    : '❌ <a href="' . admin_url("post-new.php?post_type=page") . '">Crea la pagina con slug <code>' . $slug . '</code></a>',
            ];
        }

        // REST API
        $rest_url = rest_url('hoopmetrics/v1/dashboard/summary');
        $checks[] = [
            'group'  => '🔌 REST API',
            'label'  => 'Namespace hoopmetrics/v1',
            'ok'     => class_exists('HM_Rest_API'),
            'detail' => class_exists('HM_Rest_API')
                ? 'Registrato – endpoint: <code>' . esc_html($rest_url) . '</code>'
                : '❌ Plugin non attivo o errore PHP nel plugin',
        ];

        // Permalink
        $permalink = get_option('permalink_structure');
        $checks[] = [
            'group'  => '🔌 REST API',
            'label'  => 'Struttura permalink',
            'ok'     => ! empty($permalink),
            'detail' => $permalink
                ? "Struttura: <code>{$permalink}</code>"
                : '❌ Imposta i permalink (non usare Default) – WP Admin → Impostazioni → Permalink',
        ];

        return $checks;
    }
}
