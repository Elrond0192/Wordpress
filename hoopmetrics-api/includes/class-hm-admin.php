<?php
/**
 * HM_Admin v2.0 — consolidato.
 * Sostituisce sia class-hm-admin.php che class-admin-diagnostic.php.
 * Un solo menu, un solo punto di init.
 *
 * Menu WP Admin:
 *  └─ HoopMetrics (slug: hoopmetrics)
 *      ├─ Pannello di controllo
 *      ├─ Impostazioni (cache TTL)
 *      ├─ Diagnostica completa
 *      └─ Configurazione (nazioni/stagioni)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Admin {

    public static function init(): void {
        add_action( 'admin_menu',    [self::class, 'add_menu'] );
        add_action( 'admin_init',    [self::class, 'handle_actions'] );
        add_action( 'admin_notices', [self::class, 'show_notices'] );
    }

    // ── Menu ──────────────────────────────────────────────────────────────

    public static function add_menu(): void {
        add_menu_page(
            'HoopMetrics',
            '🏀 HoopMetrics',
            'manage_options',
            'hoopmetrics',
            [self::class, 'render_dashboard'],
            'dashicons-chart-bar',
            3
        );
        add_submenu_page('hoopmetrics', 'Pannello di controllo', 'Pannello',       'manage_options', 'hoopmetrics',              [self::class, 'render_dashboard']);
        add_submenu_page('hoopmetrics', 'Impostazioni',          'Impostazioni',   'manage_options', 'hoopmetrics-settings',     [self::class, 'render_settings']);
        add_submenu_page('hoopmetrics', 'Diagnostica sistema',   'Diagnostica',    'manage_options', 'hoopmetrics-diagnostic',   [self::class, 'render_diagnostic']);
        add_submenu_page('hoopmetrics', 'Configurazione',        'Configurazione', 'manage_options', 'hoopmetrics-config',       [self::class, 'render_config']);
    }

    // ── Azioni POST ───────────────────────────────────────────────────────

    public static function handle_actions(): void {
        if ( ! isset($_POST['hm_action']) ) return;
        if ( ! check_admin_referer('hm_admin_action') ) wp_die('Nonce non valido');
        if ( ! current_user_can('manage_options') ) wp_die('Permessi insufficienti');

        $action = sanitize_text_field( $_POST['hm_action'] );

        if ( $action === 'flush_cache' ) {
            $deleted = HM_Leaderboard::flush_cache();
            $flushed = HM_Rate_Limiter::flush();
            // Svuota anche le mappe ID dell'anonymizer (hm_idmap_* e hm_global_idmap)
            global $wpdb;
            $id_maps = (int)$wpdb->query(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE '_transient_hm_idmap_%'
                    OR option_name LIKE '_transient_timeout_hm_idmap_%'
                    OR option_name = '_transient_hm_global_idmap'
                    OR option_name = '_transient_timeout_hm_global_idmap'"
            );
            set_transient('hm_admin_notice', "✅ Cache svuotata: leaderboard ({$deleted}), rate limit ({$flushed}), ID maps ({$id_maps}).", 30);
        }

        if ( $action === 'save_settings' ) {
            $ttl = max( 60, min( 86400, (int)($_POST['hm_cache_ttl'] ?? 1800) ) );
            update_option('hm_cache_ttl', $ttl);
            set_transient('hm_admin_notice', '✅ Impostazioni salvate.', 30);
        }

        if ( $action === 'refresh_meta' ) {
            hm_api_refresh_meta();
            set_transient('hm_admin_notice', '✅ Nazioni e stagioni aggiornate.', 30);
        }

        wp_redirect( admin_url('admin.php?page=' . sanitize_key($_POST['hm_redirect'] ?? 'hoopmetrics')) );
        exit;
    }

    public static function show_notices(): void {
        $msg = get_transient('hm_admin_notice');
        if ( $msg ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
            delete_transient('hm_admin_notice');
        }
    }

    // ── Pagina: Pannello di controllo ─────────────────────────────────────

    public static function render_dashboard(): void {
        if ( ! current_user_can('manage_options') ) return;

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
            ['🔌 Plugin API', $plugin_ok ? 'Attivo'    : 'Errore',       $plugin_ok],
            ['🎨 Tema',       $theme_ok  ? 'Attivo'    : 'Tema diverso',  $theme_ok],
            ['🗄️ Azure SQL',  $db_ok     ? 'Connesso'  : 'Non connesso',  $db_ok],
        ] as [$icon, $label, $ok]) {
            $color = $ok ? '#4ade80' : '#f87171';
            $bg    = $ok ? 'rgba(74,222,128,.08)' : 'rgba(248,113,113,.08)';
            echo "<div style='background:{$bg};border:1px solid {$color}33;border-radius:10px;padding:1.2rem;text-align:center'>
                    <div style='font-size:1.8rem'>{$icon}</div>
                    <div style='font-weight:700;margin:.4rem 0 .2rem;color:{$color}'>{$label}</div>
                  </div>";
        }

        echo '</div>';
        echo '<div style="margin-top:1.5rem;display:flex;gap:.75rem;flex-wrap:wrap">';
        echo '<a href="' . admin_url('admin.php?page=hoopmetrics-diagnostic') . '" class="button button-primary">Diagnostica completa →</a>';
        echo '<a href="' . admin_url('admin.php?page=hoopmetrics-settings') . '" class="button">Impostazioni</a>';
        echo '</div>';
        echo '</div>';
    }

    // ── Pagina: Impostazioni (cache + rate limit) ──────────────────────────

    public static function render_settings(): void {
        if ( ! current_user_can('manage_options') ) return;

        $ttl = (int) get_option('hm_cache_ttl', 1800);

        global $wpdb;
        $lb_count = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_hm_lb_%'"
        );
        $rl_count = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_hm_rl_%'"
        );
        ?>
        <div class="wrap">
          <h1>⚙️ HoopMetrics – Impostazioni</h1>

          <div class="card" style="max-width:600px;padding:1.2rem 1.5rem;margin-bottom:1.5rem">
            <h2 style="margin-top:0">📦 Cache</h2>
            <p>Voci leaderboard: <strong><?php echo $lb_count; ?></strong> &nbsp;|&nbsp;
               Voci rate limit attive: <strong><?php echo $rl_count; ?></strong></p>
            <form method="post">
              <?php wp_nonce_field('hm_admin_action'); ?>
              <input type="hidden" name="hm_action"   value="flush_cache">
              <input type="hidden" name="hm_redirect" value="hoopmetrics-settings">
              <button type="submit" class="button button-secondary">🗑️ Svuota cache e reset rate limit</button>
            </form>
          </div>

          <div class="card" style="max-width:600px;padding:1.2rem 1.5rem">
            <h2 style="margin-top:0">🔧 Configurazione</h2>
            <form method="post">
              <?php wp_nonce_field('hm_admin_action'); ?>
              <input type="hidden" name="hm_action"   value="save_settings">
              <input type="hidden" name="hm_redirect" value="hoopmetrics-settings">
              <table class="form-table">
                <tr>
                  <th><label for="hm_cache_ttl">Durata cache (secondi)</label></th>
                  <td>
                    <input type="number" id="hm_cache_ttl" name="hm_cache_ttl"
                           value="<?php echo esc_attr($ttl); ?>"
                           min="60" max="86400" step="60" class="small-text">
                    <p class="description">
                      Attuale: <strong><?php echo gmdate('H\h i\m', $ttl); ?></strong> &nbsp;·
                      <a href="#" onclick="document.getElementById('hm_cache_ttl').value=300;return false">5 min</a> ·
                      <a href="#" onclick="document.getElementById('hm_cache_ttl').value=1800;return false">30 min</a> ·
                      <a href="#" onclick="document.getElementById('hm_cache_ttl').value=3600;return false">1 ora</a>
                    </p>
                  </td>
                </tr>
              </table>
              <?php submit_button('💾 Salva impostazioni'); ?>
            </form>
          </div>
        </div>
        <?php
    }

    // ── Pagina: Diagnostica completa ──────────────────────────────────────

    public static function render_diagnostic(): void {
        if ( ! current_user_can('manage_options') ) wp_die('Accesso negato.');

        $checks = self::run_checks();
        $all_ok = ! in_array(false, array_column($checks, 'ok'), true);

        echo '<div class="wrap">';
        echo '<h1>🏀 HoopMetrics – Diagnostica sistema</h1>';

        if ( $all_ok ) {
            echo '<div style="background:rgba(74,222,128,.1);border:1px solid #4ade8066;border-radius:10px;padding:1rem 1.5rem;margin-bottom:1.5rem">
                    <strong style="color:#4ade80">✅ Tutto OK — il sistema è pronto</strong></div>';
        } else {
            $errors = count(array_filter(array_column($checks, 'ok'), fn($v) => $v === false));
            echo "<div style='background:rgba(248,113,113,.1);border:1px solid #f8717166;border-radius:10px;padding:1rem 1.5rem;margin-bottom:1.5rem'>
                    <strong style='color:#f87171'>❌ {$errors} problema/i da risolvere</strong></div>";
        }

        $groups = [];
        foreach ( $checks as $c ) $groups[$c['group']][] = $c;

        foreach ( $groups as $group => $items ) {
            echo "<h2 style='margin-top:2rem;border-bottom:1px solid #ddd;padding-bottom:.5rem'>{$group}</h2>";
            echo '<table class="widefat" style="max-width:900px;margin-top:.75rem"><thead><tr><th>Controllo</th><th>Stato</th><th>Dettaglio</th></tr></thead><tbody>';
            foreach ( $items as $c ) {
                $badge = match(true) {
                    $c['ok'] === true  => '<span style="background:#4ade8022;color:#4ade80;padding:2px 10px;border-radius:999px;font-weight:700;font-size:.85rem">✅ OK</span>',
                    $c['ok'] === null  => '<span style="background:#fde04722;color:#ca8a04;padding:2px 10px;border-radius:999px;font-weight:700;font-size:.85rem">⚠️ WARN</span>',
                    default            => '<span style="background:#f8717122;color:#f87171;padding:2px 10px;border-radius:999px;font-weight:700;font-size:.85rem">❌ ERRORE</span>',
                };
                echo "<tr>
                    <td><strong>" . esc_html($c['label']) . "</strong></td>
                    <td>{$badge}</td>
                    <td style='color:#555;font-size:.88rem'>" . wp_kses_post($c['detail']) . "</td>
                </tr>";
            }
            echo '</tbody></table>';
        }

        // Fix rapidi
        echo '<h2 style="margin-top:2rem;border-bottom:1px solid #ddd;padding-bottom:.5rem">🔧 Fix rapidi</h2>';
        echo '<div style="max-width:900px">';

        if ( ! defined('HM_DB_SERVER') ) {
            echo '<div style="background:#fff8e1;border-left:4px solid #f97316;padding:1rem 1.2rem;margin:.75rem 0;border-radius:0 8px 8px 0">
                <strong>Aggiungi le costanti a wp-config.php:</strong>
                <pre style="background:#f1f3f4;padding:.75rem;border-radius:6px;margin-top:.5rem;font-size:.82rem">define(\'HM_DB_SERVER\', \'yourserver.database.windows.net\');
define(\'HM_DB_NAME\',   \'HoopMetrics\');
define(\'HM_DB_USER\',   \'username\');
define(\'HM_DB_PASS\',   \'password\');
define(\'HM_ID_SALT\',   \'' . wp_generate_password(48, false) . '\');</pre></div>';
        }

        if ( ! extension_loaded('pdo_sqlsrv') && ! extension_loaded('pdo_odbc') ) {
            echo '<div style="background:#fff8e1;border-left:4px solid #f97316;padding:1rem 1.2rem;margin:.75rem 0;border-radius:0 8px 8px 0">
                <strong>Installa il driver PHP per SQL Server:</strong>
                <pre style="background:#f1f3f4;padding:.75rem;border-radius:6px;margin-top:.5rem;font-size:.82rem"># Ubuntu/Debian
sudo apt-get install php-sqlsrv php-pdo-sqlsrv
# macOS
pecl install sqlsrv pdo_sqlsrv</pre></div>';
        }

        echo '</div>';
        echo '<p style="margin-top:2rem"><a href="' . admin_url('admin.php?page=hoopmetrics-diagnostic') . '" class="button button-primary">🔄 Riesegui diagnostica</a></p>';
        echo '</div>';
    }

    // ── Pagina: Configurazione ────────────────────────────────────────────

    public static function render_config(): void {
        if ( ! current_user_can('manage_options') ) wp_die('Accesso negato.');

        $nations = get_option('hm_available_nations', []);
        $seasons = get_option('hm_available_seasons', []);
        ?>
        <div class="wrap">
          <h1>⚙️ HoopMetrics – Configurazione</h1>
          <h2>Nazioni disponibili</h2>
          <?php if ($nations): ?>
            <ul style="list-style:disc;margin-left:1.5rem">
              <?php foreach($nations as $n) echo '<li>' . esc_html($n) . '</li>'; ?>
            </ul>
          <?php else: ?>
            <p style="color:#f87171">Nessuna nazione trovata. Clicca "Forza refresh" per avviare la scansione.</p>
          <?php endif; ?>
          <h2 style="margin-top:1.5rem">Stagioni disponibili</h2>
          <?php if ($seasons): ?>
            <ul style="list-style:disc;margin-left:1.5rem">
              <?php foreach($seasons as $s) echo '<li>' . esc_html($s) . '</li>'; ?>
            </ul>
          <?php else: ?>
            <p style="color:#f87171">Nessuna stagione trovata.</p>
          <?php endif; ?>
          <form method="post" style="margin-top:1.5rem">
            <?php wp_nonce_field('hm_admin_action'); ?>
            <input type="hidden" name="hm_action"   value="refresh_meta">
            <input type="hidden" name="hm_redirect" value="hoopmetrics-config">
            <button type="submit" class="button button-primary">🔄 Forza refresh nazioni/stagioni</button>
          </form>
        </div>
        <?php
    }

    // ── Check diagnostici ─────────────────────────────────────────────────

    private static function run_checks(): array {
        $checks = [];

        // PHP
        $phpv = phpversion();
        $checks[] = ['group'=>'🐘 PHP & Server','label'=>'Versione PHP','ok'=>version_compare($phpv,'8.0.0','>='),'detail'=>"PHP {$phpv}"];

        foreach (['pdo','pdo_sqlsrv','pdo_odbc','mbstring','openssl'] as $ext) {
            $loaded = extension_loaded($ext);
            $req    = $ext === 'pdo';
            $checks[] = ['group'=>'🐘 PHP & Server','label'=>"Estensione {$ext}",
                'ok'     => $loaded ? true : ($req ? false : null),
                'detail' => $loaded ? 'Caricata' : ($req ? '❌ Obbligatoria' : '⚠️ Opzionale'),
            ];
        }

        $has_driver = extension_loaded('pdo_sqlsrv') || extension_loaded('pdo_odbc');
        $checks[] = ['group'=>'🐘 PHP & Server','label'=>'Driver PDO SQL Server','ok'=>$has_driver,
            'detail'=>$has_driver ? 'Disponibile (' . (extension_loaded('pdo_sqlsrv') ? 'pdo_sqlsrv' : 'pdo_odbc') . ')' : '❌ Installa php-sqlsrv'];

        // wp-config
        foreach (['HM_DB_SERVER','HM_DB_NAME','HM_DB_USER','HM_DB_PASS','HM_ID_SALT'] as $c) {
            $def = defined($c);
            $checks[] = ['group'=>'⚙️ wp-config.php','label'=>$c,'ok'=>$def,
                'detail'=>$def ? '✅ Definita' : "❌ Aggiungi <code>define('{$c}','...');</code> in wp-config.php"];
        }

        // Connessione DB (usa diagnose_safe per non esporre credenziali)
        $diag = HM_DB::diagnose_safe();
        $checks[] = ['group'=>'🗄️ Azure SQL','label'=>'Connessione PDO',
            'ok'     => $diag['connection_ok'] ?: ($has_driver && defined('HM_DB_SERVER') ? false : null),
            'detail' => esc_html($diag['connection_msg']),
        ];

        if ( $diag['connection_ok'] ) {
            $nations = get_option('hm_available_nations',[]);
            $seasons = get_option('hm_available_seasons',[]);
            $checks[] = ['group'=>'🗄️ Azure SQL','label'=>'Nazioni trovate','ok'=>!empty($nations),
                'detail'=>empty($nations)? '⚠️ Clicca "Forza refresh"' : implode(', ',$nations)];
            $checks[] = ['group'=>'🗄️ Azure SQL','label'=>'Stagioni trovate','ok'=>!empty($seasons),
                'detail'=>empty($seasons)? '⚠️ Nessuna stagione' : implode(', ',$seasons)];
        }

        // Tema
        $checks[] = ['group'=>'🎨 Tema','label'=>'Tema attivo','ok'=>get_template()==='court-analytics-pro',
            'detail'=>'Tema corrente: '.get_template()];

        $theme_dir = get_template_directory();
        foreach (['style.css','index.php','functions.php','header.php','footer.php',
                  'assets/js/main.js','assets/js/api.js',
                  'page-templates/dashboard.php','page-templates/player.php',
                  'page-templates/team.php','page-templates/leaderboard.php'] as $f) {
            $exists = file_exists("{$theme_dir}/{$f}");
            $checks[] = ['group'=>'🎨 Tema','label'=>$f,'ok'=>$exists,
                'detail'=>$exists ? '✅' : "❌ Mancante: {$theme_dir}/{$f}"];
        }

        // Pagine WP
        foreach (['giocatore','squadra','leaderboard','confronto'] as $slug) {
            $page = get_page_by_path($slug);
            $checks[] = ['group'=>'📄 Pagine WP','label'=>"/{$slug}/", 'ok'=>!empty($page),
                'detail'=> $page
                    ? 'ID '.$page->ID.' – <a href="'.get_permalink($page->ID).'" target="_blank">apri →</a>'
                    : '❌ <a href="'.admin_url('post-new.php?post_type=page').'">Crea pagina con slug <code>'.$slug.'</code></a>'];
        }

        // REST
        $checks[] = ['group'=>'🔌 REST API','label'=>'Namespace hoopmetrics/v1','ok'=>class_exists('HM_Rest_API'),
            'detail'=>class_exists('HM_Rest_API') ? 'Registrato' : '❌ Plugin non attivo o errore PHP'];

        $permalink = get_option('permalink_structure');
        $checks[] = ['group'=>'🔌 REST API','label'=>'Permalink','ok'=>!empty($permalink),
            'detail'=>$permalink ? "Struttura: <code>{$permalink}</code>" : '❌ Imposta i permalink (non usare Default)'];

        return $checks;
    }
}