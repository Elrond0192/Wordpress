<?php
/**
 * HM_Anonymizer v1.1
 * Fix: build_map() e build_global_map() usano HM_DB::query() statico
 *      invece di HM_DB::instance()->query() (che ora restituisce PDO, non HM_DB).
 * Fix: nomi tabella con bracket corretti, coerenti con HM_Query_Builder.
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
     * Prefisso 'hm_' + 12 chars di SHA256.
     *
     * @param int|string $db_id   ID reale del database
     * @param string     $nation  es. 'GRC'
     * @param string     $season  es. '2024'
     * @param string     $entity  'player' | 'team'
     * @return string  es. 'hm_a3f8c2d14b9e'
     */
    public static function public_id( $db_id, string $nation, string $season, string $entity = 'player' ): string {
        $raw = self::salt() . $entity . '|' . $nation . '|' . $season . '|' . $db_id;
        return 'hm_' . substr( hash( 'sha256', $raw ), 0, 12 );
    }

    /**
     * Genera un public_id GLOBALE basato su IdGlobal (cross-nation/stagione).
     * Ritorna null se IdGlobal è vuoto.
     */
    public static function public_id_global( $id_global, string $entity = 'player' ): ?string {
        if ( empty( $id_global ) ) return null;
        $raw = self::salt() . 'global|' . $entity . '|' . $id_global;
        return 'hmg_' . substr( hash( 'sha256', $raw ), 0, 12 );
    }

    /**
     * Risolve un public_id nel record originale { db_id, nation, season, entity }.
     * La mappa inversa viene costruita lazy e salvata in WP transient (TTL 1h).
     *
     * @return array|null  es. ['db_id'=>'42','nation'=>'GRC','season'=>'2024','entity'=>'player']
     */
    public static function resolve( string $public_id, string $nation, string $season ): ?array {
        $cache_key = 'hm_idmap_' . $nation . '_' . $season;
        $map       = get_transient( $cache_key );

        // Ricostruisci se: non è un array, oppure è vuoto
        // (un [] potrebbe essere stato salvato da una versione precedente con bug)
        if ( ! is_array( $map ) || count( $map ) === 0 ) {
            $map = self::build_map( $nation, $season );
            // Non salvare una mappa vuota: verrebbe riusata senza mai ricostruire
            if ( count( $map ) > 0 ) {
                set_transient( $cache_key, $map, HOUR_IN_SECONDS );
            }
        }

        return $map[ $public_id ] ?? null;
    }

    /**
     * Risolve un public_id globale (hmg_*) → ['id_global', 'entity'].
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
     * Registra un ID nella mappa e aggiorna il transient.
     * Chiamato da HM_Leaderboard::map_player() per warm-up on-demand.
     */
    public static function register( $db_id, string $nation, string $season, string $entity = 'player' ): string {
        $pid       = self::public_id( $db_id, $nation, $season, $entity );
        $cache_key = 'hm_idmap_' . $nation . '_' . $season;
        $map       = get_transient( $cache_key );
        if ( ! is_array( $map ) ) $map = [];

        if ( ! isset( $map[ $pid ] ) ) {
            $map[ $pid ] = [
                'db_id'  => (string) $db_id,
                'nation' => $nation,
                'season' => $season,
                'entity' => $entity,
            ];
            set_transient( $cache_key, $map, HOUR_IN_SECONDS );
        }
        return $pid;
    }

    /**
     * Invalida la mappa per una nazione+stagione (utile dopo refresh dati).
     */
    public static function invalidate( string $nation, string $season ): void {
        delete_transient( 'hm_idmap_' . $nation . '_' . $season );
    }

    // ── Build maps ───────────────────────────────────────────────────────

    private static function build_map( string $nation, string $season ): array {
        $map = [];
        try {
            // Nomi tabella con bracket, coerenti con HM_Query_Builder
            $ana_table  = "[Anagrafiche].[{$nation}_{$season}]";
            $team_table = "[Anagrafiche].[Team_{$nation}_{$season}]";

            // Giocatori — usa HM_DB::query() statico (ritorna array, non PDOStatement)
            $rows = HM_DB::query( "SELECT Id FROM {$ana_table}" );
            foreach ( $rows as $row ) {
                $pid       = self::public_id( $row['Id'], $nation, $season, 'player' );
                $map[$pid] = [
                    'db_id'  => (string) $row['Id'],
                    'nation' => $nation,
                    'season' => $season,
                    'entity' => 'player',
                ];
            }

            // Squadre
            $trows = HM_DB::query( "SELECT Id FROM {$team_table}" );
            foreach ( $trows as $row ) {
                $pid       = self::public_id( $row['Id'], $nation, $season, 'team' );
                $map[$pid] = [
                    'db_id'  => (string) $row['Id'],
                    'nation' => $nation,
                    'season' => $season,
                    'entity' => 'team',
                ];
            }
        } catch ( \Exception $e ) {
            error_log( "[HoopMetrics] HM_Anonymizer::build_map({$nation},{$season}): " . $e->getMessage() );
        }
        return $map;
    }

    private static function build_global_map(): array {
        $map     = [];
        $nations = get_option( 'hm_available_nations', ['GRC'] );
        $seasons = get_option( 'hm_available_seasons', ['2024'] );

        foreach ( $nations as $n ) {
            foreach ( $seasons as $s ) {
                try {
                    $table = "[Anagrafiche].[{$n}_{$s}]";
                    $rows  = HM_DB::query(
                        "SELECT Id, IdGlobal FROM {$table} WHERE IdGlobal IS NOT NULL"
                    );
                    foreach ( $rows as $row ) {
                        $pid       = self::public_id_global( $row['IdGlobal'], 'player' );
                        $map[$pid] = [
                            'id_global' => $row['IdGlobal'],
                            'entity'    => 'player',
                        ];
                    }
                } catch ( \Exception $e ) {
                    // Tabella non ancora disponibile per questa combinazione — skip silenzioso
                }
            }
        }
        return $map;
    }
}