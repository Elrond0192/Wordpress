<?php
/**
 * HM_Teams – usa HM_DB::query() / query_row() (statici).
 * Fix: resolve_team_id() usa HM_Anonymizer::resolve() — niente più O(n) table scan.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Teams {

    public function get_profile( string $public_id, string $nation, string $year, string $comp ): array {
        $team_id = $this->resolve_team_id( $public_id, $nation, $year );

        $q   = HM_Query_Builder::team_profile( $nation, $year, $team_id, $comp );
        $row = HM_DB::query_row( $q['sql'], $q['params'] );
        if ( ! $row ) throw new RuntimeException( "Squadra non trovata: {$public_id}" );

        return [
            'public_id'  => $public_id,
            'team_name'  => $row['TeamName']  ?? '',
            'short_name' => $row['ShortName'] ?? '',
            'net_rtg'    => isset($row['NetRtg']) ? round((float)$row['NetRtg'], 2) : null,
            'o_rtg'      => isset($row['ORtg'])   ? round((float)$row['ORtg'],   2) : null,
            'd_rtg'      => isset($row['DRtg'])   ? round((float)$row['DRtg'],   2) : null,
            'pace'       => isset($row['Pace'])   ? round((float)$row['Pace'],   1) : null,
            'efg_pct'    => isset($row['EfgPct']) ? round((float)$row['EfgPct'], 1) : null,
            'nation'     => $nation,
            'year'       => $year,
        ];
    }

    public function get_roster( string $nation, string $year, string $public_id, string $comp ): array {
        $team_id = $this->resolve_team_id( $public_id, $nation, $year );

        $q    = HM_Query_Builder::leaderboard( $nation, $year, 'RaptorTotal', $comp, 30, 0 );
        $rows = HM_DB::query( $q['sql'], $q['params'] );

        return array_values( array_filter(
            array_map(function($r) use ($nation,$year,$team_id) {
                if ( (string)$r['TeamId'] !== (string)$team_id ) return null;
                return [
                    'public_id'    => hm_public_id( (string)$r['db_id'], $nation, $year, 'player' ),
                    'player_name'  => $r['PlayerName'],
                    'position'     => $r['Pos']          ?? '—',
                    'age'          => hm_age_from_birthdate( $r['BirthDate'] ?? null ),
                    'minutes'      => (int)($r['Min']    ?? 0),
                    'raptor_total' => isset($r['RaptorTotal']) ? round((float)$r['RaptorTotal'],2) : null,
                    'lebron_total' => isset($r['LebronTotal']) ? round((float)$r['LebronTotal'],2) : null,
                    'net_rtg'      => isset($r['NetRtg'])      ? round((float)$r['NetRtg'],     2) : null,
                ];
            }, $rows),
            fn($x) => $x !== null
        ));
    }

    /**
     * Risolve un public_id squadra ('hm_xxx') nel db_id reale usando
     * HM_Anonymizer::resolve(). O(1) dopo il primo warm-up della cache.
     *
     * @throws RuntimeException se l'ID non è trovato
     */
    private function resolve_team_id( string $public_id, string $nation, string $year ): string {
        // Modalità dev/test: accetta ID numerici diretti (solo in WP_DEBUG)
        if ( defined('WP_DEBUG') && WP_DEBUG && is_numeric($public_id) ) {
            return $public_id;
        }

        $resolved = HM_Anonymizer::resolve( $public_id, $nation, $year );

        if ( ! $resolved ) {
            throw new RuntimeException( "Squadra non trovata: {$public_id}" );
        }
        if ( $resolved['entity'] !== 'team' ) {
            throw new RuntimeException( "L'ID {$public_id} non appartiene a una squadra." );
        }

        return (string) $resolved['db_id'];
    }
}