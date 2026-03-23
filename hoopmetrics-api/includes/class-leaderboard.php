<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Leaderboard {

    private static function cache_ttl(): int {
        return (int) get_option( 'hm_cache_ttl', 1800 );
    }

    public static function get_player_leaderboard(
        string $nation,  string $year,    string $metric  = 'RaptorTotal',
        string $comp     = 'RS', int $limit = 50, int $min_min = 150,
        string $pos      = 'all', int $age_min = 0, int $age_max = 99
    ): array {
        $cache_key = 'hm_lb_' . md5("{$nation}_{$year}_{$metric}_{$comp}_{$limit}_{$min_min}_{$pos}_{$age_min}_{$age_max}");
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            $cached['meta']['from_cache'] = true;
            return $cached;
        }

        $q    = HM_Query_Builder::leaderboard( $nation, $year, $metric, $comp, $limit, $min_min, $pos, $age_min, $age_max );
        $rows = HM_DB::query( $q['sql'], $q['params'] );

        $result = [
            'data' => array_map( fn($r) => self::map_player($r, $nation, $year, $comp), $rows ),
            'meta' => [
                'total'      => count($rows),
                'metric'     => $metric,
                'comp'       => $comp,
                'from_cache' => false,
                'cached_at'  => time(),
            ],
        ];

        set_transient( $cache_key, $result, self::cache_ttl() );
        return $result;
    }

    /**
     * Restituisce nazioni, stagioni e competizioni disponibili per i filtri.
     *
     * - nations:      tutte le nazioni con dati nel DB (da WP option)
     * - seasons:      stagioni disponibili per la nazione richiesta
     * - competitions: competizioni presenti nella tabella stats per nation+year
     */
    public static function get_meta( string $nation, string $year ): array {
        // Nazioni da WP option (popolata da hm_api_refresh_meta)
        $all_nations = get_option( 'hm_available_nations', [] );
        if ( empty($all_nations) ) $all_nations = ['GRC'];

        // Stagioni disponibili per questa nazione specifica:
        // verifica quali tabelle Anagrafiche.{NATION}_{YEAR} esistono
        $all_seasons = get_option( 'hm_available_seasons', [] );
        $seasons_for_nation = [];

        if ( ! empty($all_seasons) ) {
            try {
                $n_upper = strtoupper($nation);
                $rows    = HM_DB::query(
                    "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA = 'Anagrafiche'
                       AND TABLE_NAME LIKE :pattern
                       AND TABLE_NAME NOT LIKE 'Team_%'
                     ORDER BY TABLE_NAME DESC",
                    [':pattern' => $n_upper . '_%']
                );
                foreach ( $rows as $r ) {
                    if ( preg_match('/^' . preg_quote($n_upper, '/') . '_(\d{4})$/', $r['TABLE_NAME'], $m) ) {
                        $seasons_for_nation[] = $m[1];
                    }
                }
            } catch ( \Exception $e ) {
                // Fallback: tutte le stagioni note
                $seasons_for_nation = $all_seasons;
            }
        }

        if ( empty($seasons_for_nation) ) {
            $seasons_for_nation = ! empty($all_seasons) ? $all_seasons : [$year];
        }

        // Competizioni presenti per nation+year (query sulla tabella stats)
        // Valori da escludere: aggregati o alias che non corrispondono a filtri validi
        $exclude_comps = [''];

        $competitions = [];
        try {
            HM_Query_Builder::validate_nation( $nation );
            HM_Query_Builder::validate_year( $year );
            $stats  = HM_Query_Builder::tbl_player_stats( $nation, $year );
            $c_rows = HM_DB::query(
                "SELECT DISTINCT Competition FROM {$stats}
                 WHERE Competition IS NOT NULL AND Competition <> ''
                 ORDER BY Competition"
            );
            $raw = array_column( $c_rows, 'Competition' );

            // Filtra valori da escludere e deduplica (case-insensitive)
            $seen = [];
            foreach ( $raw as $c ) {
                $c = trim($c);
                $lower = strtolower($c);
                if ( in_array($lower, array_map('strtolower', $exclude_comps), true) ) continue;
                if ( isset($seen[$lower]) ) continue;
                $seen[$lower]   = true;
                $competitions[] = $c;
            }
        } catch ( \Exception $e ) {
            $competitions = ['RS'];
        }

        return [
            'nations'      => array_values( $all_nations ),
            'seasons'      => array_values( $seasons_for_nation ),
            'competitions' => array_values( $competitions ),
        ];
    }

    // ── Stubs per metodi chiamati da REST API ──────────────────────────────

    public function get_dashboard_summary( string $nation, string $year, string $comp ): array {
        $q = HM_Query_Builder::dashboard_kpi( $nation, $year, $comp );
        return HM_DB::query( $q['sql'], $q['params'] )[0] ?? [];
    }

    public function get_nations_overview( string $year, string $comp ): array {
        return [];
    }

    public function get_team_leaderboard( string $nation, string $year, string $metric = 'NetRtg', string $comp = 'RS' ): array {
        $q = HM_Query_Builder::team_leaderboard( $nation, $year, $metric, $comp );
        return [ 'data' => HM_DB::query( $q['sql'], $q['params'] ), 'meta' => [] ];
    }

    // ── map_player ─────────────────────────────────────────────────────────

    private static function map_player( array $r, string $nation, string $year, string $comp ): array {
        $db_id = $r['db_id'] ?? $r['Id'] ?? '';

        $public_id = class_exists('HM_Anonymizer')
            ? HM_Anonymizer::public_id( $db_id, $nation, $year, 'player' )
            : 'p_' . hash('fnv1a32', $db_id . $nation . $year);

        // team_public_id: hash del TeamId per rendere la squadra linkabile
        $team_db_id       = $r['TeamId'] ?? null;
        $team_public_id   = null;
        if ( $team_db_id && class_exists('HM_Anonymizer') ) {
            $team_public_id = HM_Anonymizer::public_id( (string)$team_db_id, $nation, $year, 'team' );
        }

        $bd   = $r['BirthDate'] ?? '';
        $born = $bd ? DateTime::createFromFormat('j-n-Y', trim($bd)) : false;
        $age  = $born ? (int)$born->diff(new DateTime('today'))->y : null;

        $pct_raw = isset($r['pct_rank']) ? (float)$r['pct_rank'] : null;
        $pct     = $pct_raw !== null ? round(100 - $pct_raw, 1) : null;

        return [
            'public_id'      => $public_id,
            'team_public_id' => $team_public_id,       // ← nuovo
            'player_name'    => $r['PlayerName']  ?? '—',
            'team_name'      => $r['TeamName']    ?? '—',
            'team_id'        => $team_db_id,
            'position'       => $r['Pos']         ?? ($r['Position'] ?? '—'),
            'birth_date'     => $bd               ?: null,
            'age'            => $age,
            'minutes'        => isset($r['Min'])        ? (int)$r['Min']                   : null,
            'metric_val'     => self::sf($r['metric_val']  ?? null),
            'raptor_off'     => self::sf($r['RaptorOff']   ?? null),
            'raptor_def'     => self::sf($r['RaptorDef']   ?? null),
            'raptor_total'   => self::sf($r['RaptorTotal'] ?? null),
            'lebron_off'     => self::sf($r['LebronOff']   ?? null),
            'lebron_def'     => self::sf($r['LebronDef']   ?? null),
            'lebron_total'   => self::sf($r['LebronTotal'] ?? null),
            'bpm'            => self::sf($r['Bpm']         ?? null),
            'vorp'           => self::sf($r['Vorp']        ?? null),
            'spm'            => self::sf($r['Spm']         ?? null),
            'ts_pct'         => self::sf($r['TsPct']       ?? null),
            'efg_pct'        => self::sf($r['EfgPct']      ?? null),
            'usg_pct'        => self::sf($r['UsgPct']      ?? null),
            'tusg_pct'       => self::sf($r['TusgPct']     ?? null),
            'pie'            => self::sf($r['Pie']         ?? null),
            'gmsc'           => self::sf($r['GmSc']        ?? null),
            'o_rtg'          => self::sf($r['ORtg']        ?? null),
            'd_rtg'          => self::sf($r['DRtg']        ?? null),
            'net_rtg'        => self::sf($r['NetRtg']      ?? null),
            'ws'             => self::sf($r['Ws']          ?? null),
            'pct_rank'       => $pct,
            'nation'         => $nation,
            'year'           => $year,
            'comp'           => $comp,
        ];
    }

    private static function sf( $v, int $dec = 4 ): ?float {
        if ( $v === null || $v === '' || ! is_numeric($v) ) return null;
        return round( (float)$v, $dec );
    }

    public static function flush_cache(): int {
        global $wpdb;
        return (int)$wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_hm_lb_%'
                OR option_name LIKE '_transient_timeout_hm_lb_%'"
        );
    }
}