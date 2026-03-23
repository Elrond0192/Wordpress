<?php
/**
 * HM_Players – usa HM_DB::query() / query_row() (statici).
 * Fix: resolve_id() usa HM_Anonymizer::resolve() — niente più O(n) table scan.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Players {

    public function get_profile( string $public_id, string $nation, string $year, string $comp ): array {
        $db_id = $this->resolve_id( $public_id, $nation, $year, 'player' );

        error_log( "[HoopMetrics] get_profile: db_id={$db_id} nation={$nation} year={$year} comp={$comp}" );

        // Prova prima con la comp richiesta, poi con TOT, RS, infine senza filtro
        $row = null;
        $comps_to_try = array_unique( [$comp, 'TOT', 'RS', ''] );
        foreach ( $comps_to_try as $try_comp ) {
            try {
                $q   = HM_Query_Builder::player_profile( $nation, $year, $db_id, $try_comp );
                $row = HM_DB::query_row( $q['sql'], $q['params'] );
                if ( $row ) {
                    error_log( "[HoopMetrics] get_profile: trovato con comp='{$try_comp}'" );
                    break;
                }
                error_log( "[HoopMetrics] get_profile: nessuna riga con comp='{$try_comp}'" );
            } catch ( \Exception $e ) {
                error_log( "[HoopMetrics] get_profile: errore con comp='{$try_comp}': " . $e->getMessage() );
            }
        }

        if ( ! $row ) {
            error_log( "[HoopMetrics] get_profile: FALLITO per db_id={$db_id} — tutti i fallback esauriti" );
            throw new RuntimeException( "Giocatore non trovato: {$public_id}" );
        }

        return [
            'public_id'    => $public_id,
            'player_name'  => $row['PlayerName']  ?? '',
            'team_name'    => $row['TeamName']     ?? '',
            'team_id'      => $row['TeamId']       ?? null,
            'position'     => $row['Pos']          ?? '—',
            'nationality'  => $row['Nat']          ?? '—',
            'nationality2' => $row['Nat2']         ?? null,
            'birth_date'   => $row['BirthDate']    ?? null,
            'age'          => hm_age_from_birthdate( $row['BirthDate'] ?? null ),
            'height_cm'    => $row['Cm']           ?? null,
            'weight'       => $row['Weight']       ?? null,
            'shirt_number' => $row['ShirtNumber']  ?? '—',
            'role'         => $row['Role']         ?? null,
            'raptor_total' => isset($row['RaptorTotal']) ? round((float)$row['RaptorTotal'], 2) : null,
            'lebron_total' => isset($row['LebronTotal']) ? round((float)$row['LebronTotal'], 2) : null,
            'net_rtg'      => isset($row['NetRtg'])      ? round((float)$row['NetRtg'],      2) : null,
            'bpm'          => isset($row['Bpm'])         ? round((float)$row['Bpm'],         2) : null,
            'vorp'         => isset($row['Vorp'])        ? round((float)$row['Vorp'],         2) : null,
            'ws'           => isset($row['Ws'])          ? round((float)$row['Ws'],           2) : null,
            'usg_pct'      => isset($row['UsgPct'])      ? round((float)$row['UsgPct'],       1) : null,
            'ts_pct'       => isset($row['TsPct'])       ? round((float)$row['TsPct'],        1) : null,
            'nation'       => $nation,
            'year'         => $year,
            'comp'         => $comp,
        ];
    }

    public function get_shots( string $public_id, string $nation, string $year, string $comp ): array {
        $db_id = $this->resolve_id( $public_id, $nation, $year, 'player' );
        try {
            $q = HM_Query_Builder::player_shots( $nation, $year, $db_id, $comp );

            // 1. PlayType aggregation — sempre disponibile se la tabella PBP esiste
            $pt_rows = HM_DB::query( $q['sql_pt'], $q['params'] );
            $total   = array_sum( array_column($pt_rows, 'cnt') );
            $play_types = array_map(fn($r) => [
                'play_type' => $r['PlayType'] ?? '—',
                'count'     => (int)$r['cnt'],
                'pct'       => $total > 0 ? round($r['cnt'] / $total * 100, 1) : 0,
            ], $pt_rows);

            // 2. X/Y shots — tenta la query, restituisce [] se le colonne non esistono
            $xy_shots  = [];
            $has_coords = false;
            try {
                $xy_rows = HM_DB::query( $q['sql_xy'], $q['params'] );
                if ( ! empty($xy_rows) ) {
                    $has_coords = true;
                    $xy_shots   = array_map(fn($r) => [
                        'x'         => round((float)($r['X'] ?? 0), 2),
                        'y'         => round((float)($r['Y'] ?? 0), 2),
                        'play_type' => $r['PlayType']   ?? '',
                        'made'      => (bool)($r['Made'] ?? false),
                    ], $xy_rows);
                }
            } catch ( \Exception $e ) {
                // Colonne X/Y non presenti in questa nazione — silenzioso
                error_log("[HoopMetrics] get_shots xy: X/Y non disponibili per {$nation}_{$year}: " . $e->getMessage());
            }

            return [
                'play_types' => $play_types,
                'shots'      => $xy_shots,
                'has_coords' => $has_coords,
                'total'      => $total,
            ];

        } catch ( \Exception $e ) {
            // Tabella PBP non disponibile
            error_log("[HoopMetrics] get_shots: " . $e->getMessage());
            return ['play_types' => [], 'shots' => [], 'has_coords' => false, 'total' => 0];
        }
    }

    public function get_lineups( string $public_id, string $nation, string $year, string $comp ): array {
        $db_id = $this->resolve_id( $public_id, $nation, $year, 'player' );
        try {
            $q_stats = HM_Query_Builder::player_lineup_stats( $nation, $year, $db_id, $comp );
            $stats   = HM_DB::query( $q_stats['sql'], $q_stats['params'] );
            if ( empty($stats) ) return [];

            $q_pt   = HM_Query_Builder::player_lineup_playtypes( $nation, $year, $db_id, $comp );
            $pt_raw = HM_DB::query( $q_pt['sql'], $q_pt['params'] );
            $pt_by_lineup = [];
            foreach ( $pt_raw as $r ) {
                $pt_by_lineup[ $r['Lineup'] ][] = ['play_type' => $r['PlayType'], 'cnt' => (int)$r['Cnt']];
            }

            // Quarter distribution per lineup
            $q_by_lineup = [];
            try {
                $q_qtr   = HM_Query_Builder::player_lineup_quarters( $nation, $year, $db_id, $comp );
                $qtr_raw = HM_DB::query( $q_qtr['sql'], $q_qtr['params'] );
                foreach ( $qtr_raw as $r ) {
                    $q_by_lineup[ $r['Lineup'] ][ (int)($r['Quarter'] ?? 0) ] = (int)$r['Cnt'];
                }
            } catch ( \Exception $e ) {
                error_log("[HoopMetrics] get_lineups quarters: " . $e->getMessage());
            }

            $all_ids = [];
            foreach ( $stats as $row ) {
                foreach ( explode(' - ', $row['Lineup'] ?? '') as $id ) {
                    $id = trim($id);
                    if ( $id ) $all_ids[$id] = true;
                }
            }
            $names = [];
            if ( $all_ids ) {
                $ana       = HM_Query_Builder::tbl_anagrafiche( $nation, $year );
                $ids_sql   = implode(',', array_map(fn($id) => "'{$id}'", array_keys($all_ids)));
                $name_rows = HM_DB::query(
                    "SELECT Id, CASE WHEN LEN(NormalizedPlayerName)>2 THEN NormalizedPlayerName ELSE PlayerName END AS Name
                     FROM {$ana} WHERE Id IN ({$ids_sql})"
                );
                foreach ( $name_rows as $nr ) $names[trim($nr['Id'])] = $nr['Name'];
            }

            $resolve = function(string $lineup_str) use ($names, $db_id): array {
                return array_map(fn($id) => [
                    'id'      => $id = trim($id),
                    'name'    => $names[$id] ?? $id,
                    'is_self' => $id === $db_id,
                ], array_filter(explode(' - ', $lineup_str)));
            };

            return array_map(function($row) use ($resolve, $pt_by_lineup, $q_by_lineup) {
                $lineup = $row['Lineup'] ?? '';
                $pts    = $pt_by_lineup[$lineup] ?? [];
                $total  = array_sum(array_column($pts, 'cnt'));
                $qtrs   = $q_by_lineup[$lineup] ?? [];
                return [
                    'lineup'      => $lineup,
                    'players'     => $resolve($lineup),
                    'poss'        => (int)($row['Poss']       ?? 0),
                    'games'       => (int)($row['Games']      ?? 0),
                    'tot_for'     => (int)($row['TotFor']     ?? 0),
                    'tot_against' => (int)($row['TotAgainst'] ?? 0),
                    'o_rtg'       => isset($row['ORtg'])   ? round((float)$row['ORtg'],   1) : null,
                    'd_rtg'       => isset($row['DRtg'])   ? round((float)$row['DRtg'],   1) : null,
                    'net_rtg'     => isset($row['NetRtg']) ? round((float)$row['NetRtg'], 1) : null,
                    'play_types'  => array_map(fn($p) => [
                        'play_type' => $p['play_type'],
                        'cnt'       => $p['cnt'],
                        'pct'       => $total > 0 ? round($p['cnt'] / $total * 100, 1) : 0,
                    ], $pts),
                    'quarters'    => $qtrs, // [1=>cnt, 2=>cnt, 3=>cnt, 4=>cnt]
                ];
            }, $stats);

        } catch ( \Exception $e ) {
            error_log("[HoopMetrics] get_lineups: " . $e->getMessage());
            return [];
        }
    }

    public function get_shots_conversion( string $public_id, string $nation, string $year, string $comp ): array {
        $db_id = $this->resolve_id( $public_id, $nation, $year, 'player' );
        try {
            $q    = HM_Query_Builder::player_playtypes_conversion( $nation, $year, $db_id, $comp );
            $rows = HM_DB::query( $q['sql'], $q['params'] );
            $total = array_sum(array_column($rows, 'Total'));

            // Media lega PlayType — calcolata dalla stessa tabella PBP aggregata
            return array_map(fn($r) => [
                'play_type' => $r['PlayType']   ?? '—',
                'total'     => (int)$r['Total'],
                'made'      => (int)$r['Made'],
                'conv_pct'  => isset($r['ConvPct']) ? round((float)$r['ConvPct'], 1) : null,
                'share_pct' => $total > 0 ? round($r['Total'] / $total * 100, 1) : 0,
            ], $rows);
        } catch ( \Exception $e ) {
            error_log("[HoopMetrics] get_shots_conversion: " . $e->getMessage());
            return [];
        }
    }

    public function get_similar( string $public_id, string $nation, string $year, string $comp ): array {
        $db_id = $this->resolve_id( $public_id, $nation, $year, 'player' );
        try {
            $q    = HM_Query_Builder::player_similar( $nation, $year, $db_id, $comp );
            $rows = HM_DB::query( $q['sql'], $q['params'] );

            // Calcola max dist per normalizzare il punteggio similarità
            $dists = array_column($rows, 'Dist');
            $maxD  = max($dists) ?: 1;

            return array_map(function($r) use ($maxD, $nation, $year) {
                $dist = (float)$r['Dist'];
                return [
                    'public_id'    => hm_public_id($r['DbId'], $nation, $year, 'player'),
                    'player_name'  => $r['PlayerName'] ?? '—',
                    'team_name'    => $r['TeamName']   ?? '—',
                    'position'     => $r['Pos']        ?? '—',
                    'similarity'   => round((1 - $dist / max($maxD, 1)) * 100, 0),
                    'raptor_total' => isset($r['RaptorTotal']) ? round((float)$r['RaptorTotal'], 1) : null,
                    'lebron_total' => isset($r['LebronTotal']) ? round((float)$r['LebronTotal'], 1) : null,
                    'net_rtg'      => isset($r['NetRtg'])      ? round((float)$r['NetRtg'],      1) : null,
                    'ts_pct'       => isset($r['TsPct'])       ? round((float)$r['TsPct'] * 100, 1) : null,
                    'usg_pct'      => isset($r['UsgPct'])      ? round((float)$r['UsgPct'],      1) : null,
                ];
            }, $rows);
        } catch ( \Exception $e ) {
            error_log("[HoopMetrics] get_similar: " . $e->getMessage());
            return [];
        }
    }

    public function get_all_splits( string $public_id, string $nation, string $year ): array {
        $db_id = $this->resolve_id( $public_id, $nation, $year, 'player' );
        $q     = HM_Query_Builder::player_all_splits( $nation, $year, $db_id );
        $rows  = HM_DB::query( $q['sql'], $q['params'] );

        $splits = [];
        foreach ( $rows as $r ) {
            $comp          = $r['Competition'];
            $splits[$comp] = $this->format_split( $r );
        }

        return ['splits' => $splits, 'public_id' => $public_id];
    }

    public function get_last_games( string $public_id, string $nation, string $year, int $limit, string $comp ): array {
        $db_id = $this->resolve_id( $public_id, $nation, $year, 'player' );
        $q     = HM_Query_Builder::player_last_games( $nation, $year, $db_id, $limit, $comp );
        $rows  = HM_DB::query( $q['sql'], $q['params'] );

        return array_map(function($r) {
            $sf = $r['Sf'] ?? null;
            return [
                'game'         => $r['Game']       ?? '—',
                'game_num'     => isset($r['GameNum']) ? (int)$r['GameNum'] : null,
                'opponent'     => $r['Opponent']   ?? null,
                'is_home'      => (bool)($r['IsHome']    ?? false),
                'starter'      => $sf !== null && $sf !== '0' && $sf !== '',
                'competition'  => $r['Competition'] ?? '—',
                'timestamp'    => $r['Timestamp']   ?? null,
                // Conteggi
                'minutes'      => (int)($r['Min']  ?? 0),
                'points'       => (int)($r['Pts']  ?? 0),
                'ast'          => (int)($r['Ast']  ?? 0),
                'tr'           => (int)($r['Tr']   ?? 0),
                'or'           => (int)($r['Or']   ?? 0),
                'dr'           => (int)($r['Dr']   ?? 0),
                'stl'          => (int)($r['Stl']  ?? 0),
                'blk'          => (int)($r['Blk']  ?? 0),
                'to'           => (int)($r['To']   ?? 0),
                'pf'           => (int)($r['Pf']   ?? 0),
                'fgm2'         => (int)($r['2Fgm'] ?? 0),
                'fga2'         => (int)($r['2Fga'] ?? 0),
                'fgm3'         => (int)($r['3Fgm'] ?? 0),
                'fga3'         => (int)($r['3Fga'] ?? 0),
                'ftm'          => (int)($r['Ftm']  ?? 0),
                'fta'          => (int)($r['Fta']  ?? 0),
                'val_lega'     => isset($r['ValLega'])   ? (int)$r['ValLega']                  : null,
                'plus_minus'   => isset($r['PlusMinus']) ? round((float)$r['PlusMinus'],   1)  : null,
                // Shooting % — 2P/3P/Ft già in formato 0-100 nel DB, TsPct/EfgPct in formato 0-1
                'fg2_pct'      => isset($r['2P'])        ? round((float)$r['2P'],           1)  : null,
                'fg3_pct'      => isset($r['3P'])        ? round((float)$r['3P'],           1)  : null,
                'ft_pct'       => isset($r['Ft'])        ? round((float)$r['Ft'],           1)  : null,
                'ts_pct'       => isset($r['TsPct'])     ? round((float)$r['TsPct']  * 100, 1)  : null,
                'efg_pct'      => isset($r['EfgPct'])    ? round((float)$r['EfgPct'] * 100, 1)  : null,
                'usg_pct'      => isset($r['UsgPct'])    ? round((float)$r['UsgPct'],       1)  : null,
                // Impact
                'net_rtg'      => isset($r['NetRtg'])      ? round((float)$r['NetRtg'],      1) : null,
                'o_rtg'        => isset($r['ORtg'])        ? round((float)$r['ORtg'],        1) : null,
                'd_rtg'        => isset($r['DRtg'])        ? round((float)$r['DRtg'],        1) : null,
                'raptor_off'   => isset($r['RaptorOff'])   ? round((float)$r['RaptorOff'],   2) : null,
                'raptor_def'   => isset($r['RaptorDef'])   ? round((float)$r['RaptorDef'],   2) : null,
                'raptor_total' => isset($r['RaptorTotal']) ? round((float)$r['RaptorTotal'], 2) : null,
                'lebron_off'   => isset($r['LebronOff'])   ? round((float)$r['LebronOff'],   2) : null,
                'lebron_def'   => isset($r['LebronDef'])   ? round((float)$r['LebronDef'],   2) : null,
                'lebron_total' => isset($r['LebronTotal']) ? round((float)$r['LebronTotal'], 2) : null,
                'gm_sc'        => isset($r['GmSc'])        ? round((float)$r['GmSc'],        2) : null,
                'bpm'          => isset($r['Bpm'])         ? round((float)$r['Bpm'],         2) : null,
                'pie'          => isset($r['Pie'])         ? round((float)$r['Pie'],          3) : null,
            ];
        }, $rows);
    }

    public function get_role( string $public_id, string $nation, string $year, string $comp = '' ): ?array {
        try {
            $db_id = $this->resolve_id( $public_id, $nation, $year, 'player' );
            error_log("[HoopMetrics] get_role: public_id={$public_id} → db_id={$db_id}");
            $q   = HM_Query_Builder::player_role( $nation, $year, $db_id, $comp );
            $row = HM_DB::query_row( $q['sql'], $q['params'] );
            error_log("[HoopMetrics] get_role: row=" . ($row ? json_encode(array_keys($row)) : 'NULL'));
            if ( ! $row ) return null;
            return [
                'role_off'     => $row['RuoloOffensivo'] ?? null,
                'role_def'     => $row['RuoloDifensivo'] ?? null,
                'role_combo'   => $row['RuoloCombinato'] ?? null,
                'position'     => $row['Position']       ?? null,
                'min'          => (int)($row['Min']         ?? 0),
                'avg_min'      => isset($row['AvgMin']) ? round((float)$row['AvgMin'], 1) : null,
                'games_played' => (int)($row['GamesPlayed'] ?? 0),
                'competition'  => $row['Competition']       ?? null,
            ];
        } catch ( \Exception $e ) {
            error_log("[HoopMetrics] get_role EXCEPTION: " . $e->getMessage());
            return null;
        }
    }

    public function get_onoff( string $public_id, string $nation, string $year, string $comp ): array {
        $db_id = $this->resolve_id( $public_id, $nation, $year, 'player' );
        $q     = HM_Query_Builder::player_onoff( $nation, $year, $db_id, $comp );
        return HM_DB::query( $q['sql'], $q['params'] );
    }

    public function get_percentiles( string $public_id, string $nation, string $year, string $comp ): array {
        $db_id  = $this->resolve_id( $public_id, $nation, $year, 'player' );
        $prof_q = HM_Query_Builder::player_profile( $nation, $year, $db_id, $comp );
        $player = HM_DB::query_row( $prof_q['sql'], $prof_q['params'] );
        if ( ! $player ) throw new RuntimeException( 'Giocatore non trovato' );

        $lb_q  = HM_Query_Builder::leaderboard( $nation, $year, 'RaptorTotal', $comp, 200, 0 );
        $all   = HM_DB::query( $lb_q['sql'], $lb_q['params'] );
        $total = count( $all );
        if ( ! $total ) return [];

        $metrics = ['RaptorTotal','LebronTotal','NetRtg','ORtg','DRtg','UsgPct','TsPct','Bpm','Ws'];
        $pcts    = [];
        foreach ( $metrics as $m ) {
            if ( ! isset($player[$m]) ) continue;
            $val      = (float) $player[$m];
            $below    = count( array_filter($all, fn($x) => isset($x[$m]) && (float)$x[$m] < $val) );
            $pcts[$m] = round( $below / $total * 100, 1 );
        }
        return $pcts;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function resolve_id( string $public_id, string $nation, string $year, string $type ): string {
        if ( defined('WP_DEBUG') && WP_DEBUG && is_numeric($public_id) ) return $public_id;

        $resolved = HM_Anonymizer::resolve( $public_id, $nation, $year );
        if ( ! $resolved ) {
            error_log( "[HoopMetrics] resolve_id FALLITO: public_id={$public_id} nation={$nation} year={$year} type={$type}" );
            HM_Anonymizer::invalidate( $nation, $year );
            $resolved = HM_Anonymizer::resolve( $public_id, $nation, $year );
        }
        if ( ! $resolved )
            throw new RuntimeException( "ID non trovato: {$public_id} (nation={$nation}, year={$year})" );
        if ( $resolved['entity'] !== $type )
            throw new RuntimeException( "Tipo errato per ID {$public_id}: atteso {$type}, trovato {$resolved['entity']}" );

        return (string) $resolved['db_id'];
    }

    private function format_split( array $r ): array {
        $f  = fn($v, $d=2) => isset($r[$v]) && is_numeric($r[$v]) ? round((float)$r[$v], $d) : null;
        $fp = fn($v, $d=1) => isset($r[$v]) && is_numeric($r[$v]) ? round((float)$r[$v] * 100, $d) : null;
        return [
            // Volume
            'minutes'      => (int)($r['Min']   ?? 0),
            'games'        => (int)($r['Games'] ?? 0),
            'pts'          => $f('Pts',1),
            'ast'          => $f('Ast',1),
            'tr'           => $f('Tr',1),
            'or'           => $f('Or',1),
            'dr'           => $f('Dr',1),
            'stl'          => $f('Stl',1),
            'blk'          => $f('Blk',1),
            'to'           => $f('To',1),
            'pf'           => $f('Pf',1),
            // Shooting
            'ts_pct'       => $fp('TsPct'),
            'efg_pct'      => $fp('EfgPct'),
            'ftr'          => $f('Ftr',3),
            'three_par'    => $fp('ThreePAr'),
            // Advanced rates
            'usg_pct'      => $fp('UsgPct'),
            'tusg_pct'     => $fp('TusgPct'),
            'ast_pct'      => $fp('AstPct'),
            'stl_pct'      => $fp('StlPct'),
            'blk_pct'      => $fp('BlkPct'),
            'tov_pct'      => $fp('TovPct'),
            'reb_pct'      => $fp('RebPct'),
            'oreb_pct'     => $fp('OrebPct'),
            'dreb_pct'     => $fp('DrebPct'),
            'ast_ratio'    => $f('AstRatio',1),
            // Impact
            'o_rtg'        => $f('ORtg',1),
            'd_rtg'        => $f('DRtg',1),
            'net_rtg'      => $f('NetRtg',1),
            'pie'          => $f('Pie',3),
            'gm_sc'        => $f('GmSc',1),
            'fic'          => isset($r['Fic']) ? (int)$r['Fic'] : null,
            'val_lega'     => isset($r['ValLega']) ? (int)$r['ValLega'] : null,
            'ws'           => $f('Ws'),
            'scoring_eff'  => $f('ScoringEfficiency',3),
            'hustle'       => isset($r['HustleIndex']) ? (int)$r['HustleIndex'] : null,
            'ppsa'         => $f('Ppsa',3),
            // Models
            'raptor_off'   => $f('RaptorOff'),
            'raptor_def'   => $f('RaptorDef'),
            'raptor_total' => $f('RaptorTotal'),
            'lebron_off'   => $f('LebronOff'),
            'lebron_def'   => $f('LebronDef'),
            'lebron_total' => $f('LebronTotal'),
            'bpm'          => $f('Bpm'),
            'vorp'         => $f('Vorp'),
            'spm'          => $f('Spm'),
            // Per 40
            'pts_p40'      => $f('PtsPer40',1),
            'ast_p40'      => $f('AstPer40',1),
            'tr_p40'       => $f('TrPer40',1),
            'stl_p40'      => $f('StlPer40',1),
            'blk_p40'      => $f('BlkPer40',1),
            'to_p40'       => $f('ToPer40',1),
        ];
    }
}