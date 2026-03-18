<?php
/**
 * HM_Admin – Pagina impostazioni HoopMetrics nel menu WordPress
 * Aggiunge: Impostazioni HoopMetrics → voce nel menu WP Admin
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Admin {

    public static function init(): void {
        add_action( 'admin_menu',    [self::class, 'add_menu'] );
        add_action( 'admin_init',    [self::class, 'handle_actions'] );
        add_action( 'admin_notices', [self::class, 'show_notices'] );
    }

    public static function add_menu(): void {
        add_menu_page(
            'HoopMetrics',          // Titolo pagina
            'HoopMetrics',          // Testo nel menu
            'manage_options',       // Capability richiesta
            'hoopmetrics-settings', // Slug
            [self::class, 'render_page'],
            'dashicons-chart-bar',  // Icona
            30
        );
    }

    public static function handle_actions(): void {
        if ( ! isset($_POST['hm_action']) ) return;
        if ( ! check_admin_referer('hm_admin_action') ) wp_die('Nonce non valido');
        if ( ! current_user_can('manage_options') ) wp_die('Permessi insufficienti');

        $action = sanitize_text_field($_POST['hm_action']);

        if ( $action === 'flush_cache' ) {
            $deleted = HM_Leaderboard::flush_cache();
            set_transient('hm_admin_notice', "✅ Cache svuotata ({$deleted} voci rimosse).", 30);
        }

        if ( $action === 'save_settings' ) {
            $ttl = max(60, min(86400, (int)($_POST['hm_cache_ttl'] ?? 1800)));
            update_option('hm_cache_ttl', $ttl);
            set_transient('hm_admin_notice', '✅ Impostazioni salvate.', 30);
        }

        wp_redirect( admin_url('admin.php?page=hoopmetrics-settings') );
        exit;
    }

    public static function show_notices(): void {
        $msg = get_transient('hm_admin_notice');
        if ( $msg ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
            delete_transient('hm_admin_notice');
        }
    }

    public static function render_page(): void {
        if ( ! current_user_can('manage_options') ) return;
        $ttl = (int) get_option('hm_cache_ttl', 1800);

        // Conta le voci di cache attive
        global $wpdb;
        $count = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_hm_lb_%'"
        );
        ?>
        <div class="wrap">
          <h1>⚙️ HoopMetrics – Impostazioni</h1>

          <!-- ── Cache Status ───────────────────────────────────── -->
          <div class="card" style="max-width:600px;padding:1.2rem 1.5rem;margin-bottom:1.5rem;">
            <h2 style="margin-top:0">📦 Cache Leaderboard</h2>
            <p>Voci di cache attive: <strong><?php echo $count; ?></strong></p>
            <form method="post">
              <?php wp_nonce_field('hm_admin_action'); ?>
              <input type="hidden" name="hm_action" value="flush_cache">
              <button type="submit" class="button button-secondary">
                🗑️ Svuota tutta la cache
              </button>
            </form>
          </div>

          <!-- ── Impostazioni ──────────────────────────────────── -->
          <div class="card" style="max-width:600px;padding:1.2rem 1.5rem;">
            <h2 style="margin-top:0">🔧 Configurazione</h2>
            <form method="post">
              <?php wp_nonce_field('hm_admin_action'); ?>
              <input type="hidden" name="hm_action" value="save_settings">
              <table class="form-table">
                <tr>
                  <th><label for="hm_cache_ttl">Durata cache (secondi)</label></th>
                  <td>
                    <input type="number" id="hm_cache_ttl" name="hm_cache_ttl"
                           value="<?php echo esc_attr($ttl); ?>"
                           min="60" max="86400" step="60" class="small-text">
                    <p class="description">
                      Min 60s · Max 86400s (24h) · Attuale: <strong><?php echo gmdate('H\h i\m', $ttl); ?></strong>
                    </p>
                    <p class="description">
                      Preset comuni:
                      <a href="#" onclick="document.getElementById('hm_cache_ttl').value=300;return false;">5 min</a> ·
                      <a href="#" onclick="document.getElementById('hm_cache_ttl').value=1800;return false;">30 min</a> ·
                      <a href="#" onclick="document.getElementById('hm_cache_ttl').value=3600;return false;">1 ora</a> ·
                      <a href="#" onclick="document.getElementById('hm_cache_ttl').value=86400;return false;">24 ore</a>
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
}
