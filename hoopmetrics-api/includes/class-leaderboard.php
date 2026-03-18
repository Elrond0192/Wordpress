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

    // Stubs per metodi chiamati da REST API (esistevano prima, non rimuoverli)
    public function get_dashboard_summary( string $nation, string $year, string $comp ): array {
        $q = HM_Query_Builder::dashboard_kpi($nation, $year, $comp);
        return HM_DB::query($q['sql'], $q['params'])[0] ?? [];
    }
    public function get_nations_overview( string $year, string $comp ): array { return []; }
    public function get_team_leaderboard( string $nation, string $year, string $metric = 'NetRtg', string $comp = 'RS' ): array {
        $q = HM_Query_Builder::team_leaderboard($nation, $year, $metric, $comp);
        return [ 'data' => HM_DB::query($q['sql'], $q['params']), 'meta' => [] ];
    }

    private static function map_player(array $r, string $nation, string $year, string $comp): array {
        $db_id     = $r['db_id'] ?? $r['Id'] ?? '';
        // Anonymizer: usa se disponibile, altrimenti hash deterministico
        $public_id = class_exists('HM_Anonymizer')
            ? HM_Anonymizer::public_id( $db_id, $nation, $year, 'player' )
            : 'p_' . hash('fnv1a32', $db_id . $nation . $year);

        $bd   = $r['BirthDate'] ?? '';
        $born = $bd ? DateTime::createFromFormat('j-n-Y', trim($bd)) : false;
        $age  = $born ? (int)$born->diff(new DateTime('today'))->y : null;

        $pct_raw = isset($r['pct_rank']) ? (float)$r['pct_rank'] : null;
        $pct     = $pct_raw !== null ? round(100 - $pct_raw, 1) : null;

        return [
            'public_id'    => $public_id,
            'player_name'  => $r['PlayerName'] ?? '—',
            'team_name'    => $r['TeamName']   ?? '—',
            'team_id'      => $r['TeamId']     ?? null,
            'position'     => $r['Pos']        ?? ($r['Position'] ?? '—'),
            'birth_date'   => $bd              ?: null,
            'age'          => $age,
            'minutes'      => isset($r['Min'])        ? (int)$r['Min']                   : null,
            'metric_val'   => self::sf($r['metric_val']  ?? null),
            'raptor_off'   => self::sf($r['RaptorOff']   ?? null),
            'raptor_def'   => self::sf($r['RaptorDef']   ?? null),
            'raptor_total' => self::sf($r['RaptorTotal'] ?? null),
            'lebron_off'   => self::sf($r['LebronOff']   ?? null),
            'lebron_def'   => self::sf($r['LebronDef']   ?? null),
            'lebron_total' => self::sf($r['LebronTotal'] ?? null),
            'bpm'          => self::sf($r['Bpm']         ?? null),
            'vorp'         => self::sf($r['Vorp']        ?? null),
            'spm'          => self::sf($r['Spm']         ?? null),
            'ts_pct'       => self::sf($r['TsPct']       ?? null),
            'efg_pct'      => self::sf($r['EfgPct']      ?? null),
            'usg_pct'      => self::sf($r['UsgPct']      ?? null),
            'tusg_pct'     => self::sf($r['TusgPct']     ?? null),
            'pie'          => self::sf($r['Pie']         ?? null),
            'gmsc'         => self::sf($r['GmSc']        ?? null),
            'o_rtg'        => self::sf($r['ORtg']        ?? null),
            'd_rtg'        => self::sf($r['DRtg']        ?? null),
            'net_rtg'      => self::sf($r['NetRtg']      ?? null),
            'ws'           => self::sf($r['Ws']          ?? null),
            'pct_rank'     => $pct,
            'nation'       => $nation,
            'year'         => $year,
            'comp'         => $comp,
        ];
    }

    private static function sf($v, int $dec = 4): ?float {
        if ($v === null || $v === '' || !is_numeric($v)) return null;
        return round((float)$v, $dec);
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
