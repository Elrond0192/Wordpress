<?php
/**
 * HM_Players – usa HM_DB::query() / query_row() (statici).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Players {

    public function get_profile( string $public_id, string $nation, string $year, string $comp ): array {
        $db_id = $this->resolve_id($public_id, $nation, $year, 'player');
        $q     = HM_Query_Builder::player_profile($nation, $year, $db_id, $comp);
        $row   = HM_DB::query_row($q['sql'], $q['params']);
        if ( ! $row ) throw new RuntimeException("Giocatore non trovato: {$public_id}");

        return [
            'public_id'    => $public_id,
            'player_name'  => $row['PlayerName']  ?? '',
            'team_name'    => $row['TeamName']     ?? '',
            'team_id'      => $row['TeamId']       ?? null,
            'position'     => $row['Pos']          ?? '—',
            'nationality'  => $row['Nat']          ?? '—',
            'birth_date'   => $row['BirthDate']    ?? null,
            'age'          => hm_age_from_birthdate($row['BirthDate'] ?? null),
            'height_cm'    => $row['Cm']           ?? null,
            'weight'       => $row['Weight']       ?? null,
            'shirt_number' => $row['ShirtNumber']  ?? '—',
            'role'         => $row['Role']         ?? null,
            'raptor_total' => isset($row['RaptorTotal']) ? round((float)$row['RaptorTotal'], 2) : null,
            'lebron_total' => isset($row['LebronTotal']) ? round((float)$row['LebronTotal'], 2) : null,
            'net_rtg'      => isset($row['NetRtg'])      ? round((float)$row['NetRtg'],      2) : null,
            'bpm'          => isset($row['Bpm'])         ? round((float)$row['Bpm'],         2) : null,
            'nation'       => $nation,
            'year'         => $year,
            'comp'         => $comp,
        ];
    }

    public function get_all_splits( string $public_id, string $nation, string $year ): array {
        $db_id = $this->resolve_id($public_id, $nation, $year, 'player');
        $q     = HM_Query_Builder::player_all_splits($nation, $year, $db_id);
        $rows  = HM_DB::query($q['sql'], $q['params']);

        $splits = [];
        foreach ( $rows as $r ) {
            $comp = $r['Competition'];
            $splits[$comp] = $this->format_split($r);
        }

        // Home/Away dalla tabella game (aggregati)
        foreach ( ['Home','Away'] as $loc ) {
            try {
                $q2   = HM_Query_Builder::player_last_games($nation, $year, $db_id, 200, $loc);
                $rows2 = HM_DB::query($q2['sql'], $q2['params']);
                if ( $rows2 ) $splits[$loc] = $this->aggregate_game_splits($rows2, $loc);
            } catch ( Exception $e ) {
                error_log("[CA] Home/Away split error {$public_id}: " . $e->getMessage());
            }
        }

        return ['splits' => $splits, 'public_id' => $public_id];
    }

    public function get_last_games( string $public_id, string $nation, string $year, int $limit, string $comp ): array {
        $db_id = $this->resolve_id($public_id, $nation, $year, 'player');
        $q     = HM_Query_Builder::player_last_games($nation, $year, $db_id, $limit, $comp);
        $rows  = HM_DB::query($q['sql'], $q['params']);

        return array_map(function($r) {
            return [
                'game'         => $r['Game']        ?? '—',
                'is_home'      => (bool)($r['IsHome'] ?? false),
                'competition'  => $r['Competition'] ?? '—',
                'timestamp'    => $r['Timestamp']   ?? null,
                'points'       => (int)($r['Pts']   ?? 0),
                'minutes'      => (int)($r['Min']   ?? 0),
                'plus_minus'   => isset($r['PlusMinus'])   ? round((float)$r['PlusMinus'],   1) : null,
                'net_rtg'      => isset($r['NetRtg'])      ? round((float)$r['NetRtg'],      2) : null,
                'raptor_total' => isset($r['RaptorTotal']) ? round((float)$r['RaptorTotal'], 2) : null,
                'lebron_total' => isset($r['LebronTotal']) ? round((float)$r['LebronTotal'], 2) : null,
                'ts_pct'       => isset($r['TsPct'])       ? round((float)$r['TsPct'],       1) : null,
                'usg_pct'      => isset($r['UsgPct'])      ? round((float)$r['UsgPct'],      1) : null,
                'gm_sc'        => isset($r['GmSc'])        ? round((float)$r['GmSc'],        2) : null,
            ];
        }, $rows);
    }

    public function get_onoff( string $public_id, string $nation, string $year, string $comp ): array {
        $db_id = $this->resolve_id($public_id, $nation, $year, 'player');
        $q     = HM_Query_Builder::player_onoff($nation, $year, $db_id, $comp);
        return HM_DB::query($q['sql'], $q['params']);
    }

    public function get_percentiles( string $public_id, string $nation, string $year, string $comp ): array {
        // Prende il profilo del giocatore e calcola percentili vs leaderboard
        $db_id   = $this->resolve_id($public_id, $nation, $year, 'player');
        $prof_q  = HM_Query_Builder::player_profile($nation, $year, $db_id, $comp);
        $player  = HM_DB::query_row($prof_q['sql'], $prof_q['params']);
        if ( ! $player ) throw new RuntimeException("Giocatore non trovato");

        $lb_q    = HM_Query_Builder::leaderboard($nation, $year, 'RaptorTotal', $comp, 200, 0);
        $all     = HM_DB::query($lb_q['sql'], $lb_q['params']);
        $total   = count($all);
        if ( ! $total ) return [];

        $metrics = ['RaptorTotal','LebronTotal','NetRtg','ORtg','DRtg','UsgPct','TsPct','Bpm','Ws'];
        $pcts    = [];
        foreach ( $metrics as $m ) {
            if ( ! isset($player[$m]) ) continue;
            $val   = (float)$player[$m];
            $below = count(array_filter($all, fn($x) => isset($x[$m]) && (float)$x[$m] < $val));
            $pcts[$m] = round($below / $total * 100, 1);
        }
        return $pcts;
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    private function resolve_id( string $public_id, string $nation, string $year, string $type ): string {
        // Cerca in Anagrafiche usando l'hash public_id
        // Per ora: se public_id inizia con "ca_" → è un hash → cerca nel DB
        // Se è numerico diretto → usalo direttamente (modalità dev/test)
        if ( is_numeric($public_id) ) return $public_id;

        // Scansione: trova l'Id reale che genera questo public_id
        $ana  = HM_Query_Builder::tbl_anagrafiche($nation, $year);
        $rows = HM_DB::query("SELECT Id FROM {$ana}");
        foreach ( $rows as $r ) {
            if ( hm_public_id((string)$r['Id'], $nation, $year, $type) === $public_id ) {
                return (string)$r['Id'];
            }
        }
        throw new RuntimeException("ID non trovato: {$public_id}");
    }

    private function format_split( array $r ): array {
        $f = fn($v, $d=2) => isset($r[$v]) ? round((float)$r[$v], $d) : null;
        return [
            'minutes'      => (int)($r['Min'] ?? 0),
            'raptor_off'   => $f('RaptorOff'),  'raptor_def'   => $f('RaptorDef'),
            'raptor_total' => $f('RaptorTotal'), 'lebron_off'   => $f('LebronOff'),
            'lebron_def'   => $f('LebronDef'),   'lebron_total' => $f('LebronTotal'),
            'net_rtg'      => $f('NetRtg'),      'o_rtg'        => $f('ORtg'),
            'd_rtg'        => $f('DRtg'),        'usg_pct'      => $f('UsgPct',1),
            'ts_pct'       => $f('TsPct',1),     'efg_pct'      => $f('EfgPct',1),
            'bpm'          => $f('Bpm'),         'vorp'         => $f('Vorp'),
            'ws'           => $f('Ws'),          'gm_sc'        => $f('GmSc'),
            'pie'          => $f('Pie',1),
        ];
    }

    private function aggregate_game_splits( array $rows, string $loc ): array {
        $n = count($rows); if ( ! $n ) return [];
        $avg = fn($key) => round(array_sum(array_column($rows, $key)) / $n, 2);
        return [
            'minutes'      => array_sum(array_column($rows,'Min')),
            'games_played' => $n,
            'raptor_total' => $avg('RaptorTotal'), 'lebron_total' => $avg('LebronTotal'),
            'net_rtg'      => $avg('NetRtg'),      'o_rtg'        => $avg('ORtg'),
            'd_rtg'        => $avg('DRtg'),        'usg_pct'      => $avg('UsgPct'),
            'ts_pct'       => $avg('TsPct'),       'gm_sc'        => $avg('GmSc'),
        ];
    }
}
