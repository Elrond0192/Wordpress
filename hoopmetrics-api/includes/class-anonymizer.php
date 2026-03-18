<?php
/**
 * HM_Anonymizer
 *
 * Converte ID del database in hash pubblici e viceversa.
 * Gli ID reali non lasciano mai il server.
 *
 * public_id  = 'hm_' + SHA256(HM_ID_SALT + nation + '|' + season + '|' + db_id)[:12]
 *
 * La mappa inversa (public_id → db_id) è salvata in WP transient per
 * ogni combinazione nation+season, con TTL 1 ora.
 *
 * IdGlobal:  quando popolato, unifica un giocatore attraverso nazioni/stagioni diverse.
 *            hm_public_id_global() genera un hash separato basato su IdGlobal.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Anonymizer {

    private static string $salt = '';

    private static function salt(): string {
        if ( self::$salt === '' ) {
            self::$salt = defined('HM_ID_SALT') ? HM_ID_SALT : AUTH_SALT;
        }
        return self::$salt;
    }

    /**
     * Genera un public_id univoco per un giocatore/squadra.
     *
     * @param int|string $db_id    ID reale del database
     * @param string     $nation   es. 'GRC'
     * @param string     $season   es. '2024'
     * @param string     $entity   'player' | 'team'
     * @return string  es. 'hm_a3f8c2d14b9e'
     */
    public static function public_id( $db_id, string $nation, string $season, string $entity = 'player' ): string {
        $raw = self::salt() . $entity . '|' . $nation . '|' . $season . '|' . $db_id;
        return 'hm_' . substr( hash( 'sha256', $raw ), 0, 12 );
    }

    /**
     * Genera un public_id GLOBALE basato su IdGlobal (cross-nation/stagione).
     * Se IdGlobal è null/vuoto, ritorna null.
     */
    public static function public_id_global( $id_global, string $entity = 'player' ): ?string {
        if ( empty( $id_global ) ) return null;
        $raw = self::salt() . 'global|' . $entity . '|' . $id_global;
        return 'hmg_' . substr( hash( 'sha256', $raw ), 0, 12 );
    }

    /**
     * Risolve un public_id tornando [ 'db_id', 'nation', 'season', 'entity' ]
     * oppure null se non trovato.
     *
     * La mappa viene costruita lazy al primo utilizzo per ogni nazione+stagione.
     */
    public static function resolve( string $public_id, string $nation, string $season ): ?array {
        $cache_key = 'hm_idmap_' . $nation . '_' . $season;
        $map = get_transient( $cache_key );

        if ( ! is_array( $map ) ) {
            $map = self::build_map( $nation, $season );
            set_transient( $cache_key, $map, HOUR_IN_SECONDS );
        }

        return $map[ $public_id ] ?? null;
    }

    /**
     * Risolve un public_id globale (hmg_*) → [ 'id_global', 'entity' ]
     */
    public static function resolve_global( string $public_id ): ?array {
        $map = get_transient( 'hm_global_idmap' );
        if ( ! is_array( $map ) ) {
            $map = self::build_global_map();
            set_transient( 'hm_global_idmap', $map, HOUR_IN_SECONDS );
        }
        return $map[ $public_id ] ?? null;
    }

    /**
     * Aggiunge un ID alla mappa (usato durante query player/team)
     * e lo salva nel transient.
     */
    public static function register( $db_id, string $nation, string $season, string $entity = 'player' ): string {
        $pid       = self::public_id( $db_id, $nation, $season, $entity );
        $cache_key = 'hm_idmap_' . $nation . '_' . $season;
        $map       = get_transient( $cache_key );
        if ( ! is_array( $map ) ) $map = [];

        if ( ! isset( $map[ $pid ] ) ) {
            $map[ $pid ] = [
                'db_id'  => $db_id,
                'nation' => $nation,
                'season' => $season,
                'entity' => $entity,
            ];
            set_transient( $cache_key, $map, HOUR_IN_SECONDS );
        }
        return $pid;
    }

    // ── Build maps ───────────────────────────────────────────────────────

    private static function build_map( string $nation, string $season ): array {
        $map = [];
        try {
            $db    = HM_DB::instance();

            // Giocatori
            $table = "Anagrafiche.{$nation}_{$season}";
            $rows  = $db->query( "SELECT Id FROM {$table}" );
            foreach ( $rows as $row ) {
                $pid        = self::public_id( $row['Id'], $nation, $season, 'player' );
                $map[$pid]  = [ 'db_id' => $row['Id'], 'nation' => $nation, 'season' => $season, 'entity' => 'player' ];
            }

            // Squadre
            $ttable = "Anagrafiche.Team_{$nation}_{$season}";
            $trows  = $db->query( "SELECT Id FROM {$ttable}" );
            foreach ( $trows as $row ) {
                $pid       = self::public_id( $row['Id'], $nation, $season, 'team' );
                $map[$pid] = [ 'db_id' => $row['Id'], 'nation' => $nation, 'season' => $season, 'entity' => 'team' ];
            }
        } catch ( Exception $e ) {
            error_log( "[HoopMetrics] build_map error: " . $e->getMessage() );
        }
        return $map;
    }

    private static function build_global_map(): array {
        $map = [];
        try {
            $db      = HM_DB::instance();
            $nations = get_option( 'hm_available_nations', ['GRC'] );
            $seasons = get_option( 'hm_available_seasons', ['2024'] );
            foreach ( $nations as $n ) {
                foreach ( $seasons as $s ) {
                    try {
                        $table = "Anagrafiche.{$n}_{$s}";
                        $rows  = $db->query( "SELECT Id, IdGlobal FROM {$table} WHERE IdGlobal IS NOT NULL" );
                        foreach ( $rows as $row ) {
                            $pid       = self::public_id_global( $row['IdGlobal'], 'player' );
                            $map[$pid] = [ 'id_global' => $row['IdGlobal'], 'entity' => 'player' ];
                        }
                    } catch ( Exception $e ) { /* tabella non ancora disponibile */ }
                }
            }
        } catch ( Exception $e ) {
            error_log( "[HoopMetrics] build_global_map error: " . $e->getMessage() );
        }
        return $map;
    }
}
