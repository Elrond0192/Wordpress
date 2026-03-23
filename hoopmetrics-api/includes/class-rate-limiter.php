<?php
/**
 * HM_Rate_Limiter
 * Limita le richieste per IP usando WP transients.
 * Configura le soglie tramite costanti in wp-config.php:
 *   define('HM_RATE_LIMIT_MAX',    60);   // richieste per finestra
 *   define('HM_RATE_LIMIT_WINDOW', 60);   // secondi
 *   define('HM_RATE_LIMIT_BAN',    300);  // secondi di ban dopo burst eccessivo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Rate_Limiter {

    private const DEFAULT_MAX    = 60;
    private const DEFAULT_WINDOW = 60;
    private const BAN_THRESHOLD  = 5;   // moltiplicatore: ban se supera MAX × BAN_THRESHOLD
    private const DEFAULT_BAN    = 300; // secondi di ban

    /**
     * Controlla e registra la richiesta corrente.
     * Restituisce true se la richiesta è permessa, WP_Error altrimenti.
     */
    public static function check(): true|WP_Error {
        $ip  = self::get_ip();
        $key = 'hm_rl_' . md5( $ip );
        $ban = 'hm_ban_' . md5( $ip );

        // IP bannato? Risposta immediata senza logging ulteriore
        if ( get_transient( $ban ) !== false ) {
            return new WP_Error(
                'hm_rate_limit_banned',
                'Accesso temporaneamente sospeso. Riprova tra qualche minuto.',
                ['status' => 429]
            );
        }

        $max    = defined('HM_RATE_LIMIT_MAX')    ? (int) HM_RATE_LIMIT_MAX    : self::DEFAULT_MAX;
        $window = defined('HM_RATE_LIMIT_WINDOW') ? (int) HM_RATE_LIMIT_WINDOW : self::DEFAULT_WINDOW;
        $ban_s  = defined('HM_RATE_LIMIT_BAN')    ? (int) HM_RATE_LIMIT_BAN    : self::DEFAULT_BAN;

        $hits = (int) get_transient( $key );
        $hits++;
        set_transient( $key, $hits, $window );

        // Ban automatico per burst estremo (possibile attacco)
        if ( $hits >= $max * self::BAN_THRESHOLD ) {
            set_transient( $ban, 1, $ban_s );
            error_log( "[HoopMetrics] IP bannato per rate limit eccessivo: {$ip} ({$hits} richieste)" );
        }

        if ( $hits > $max ) {
            return new WP_Error(
                'hm_rate_limit',
                'Troppe richieste. Attendi qualche secondo e riprova.',
                [
                    'status'        => 429,
                    'retry_after'   => $window,
                ]
            );
        }

        return true;
    }

    /**
     * Restituisce l'IP reale del client.
     * Non usa X-Forwarded-For di default per evitare spoofing;
     * attivalo solo se sei dietro un reverse proxy fidato.
     */
    private static function get_ip(): string {
        if ( defined('HM_TRUST_PROXY') && HM_TRUST_PROXY ) {
            $candidates = [
                'HTTP_CF_CONNECTING_IP', // Cloudflare
                'HTTP_X_REAL_IP',
                'HTTP_X_FORWARDED_FOR',
            ];
            foreach ( $candidates as $h ) {
                if ( ! empty( $_SERVER[ $h ] ) ) {
                    // X-Forwarded-For può contenere una lista: prendi solo il primo
                    $ip = trim( explode( ',', $_SERVER[ $h ] )[0] );
                    if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Pulisce tutta la cache di rate limiting (utile in fase di debug).
     * Chiamabile manualmente da WP CLI: wp eval "HM_Rate_Limiter::flush();"
     */
    public static function flush(): int {
        global $wpdb;
        return (int) $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_hm_rl_%'
                OR option_name LIKE '_transient_timeout_hm_rl_%'
                OR option_name LIKE '_transient_hm_ban_%'
                OR option_name LIKE '_transient_timeout_hm_ban_%'"
        );
    }
}