<?php
/**
 * HM_OnCourt – Statistiche OnCourt standalone.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HM_OnCourt {

    private HM_DB           $db;
    private HM_QueryBuilder $qb;

    public function __construct( string $nation, string $season ) {
        $this->db = HM_DB::instance();
        $this->qb = new HM_QueryBuilder( $nation, $season );
    }

    public function get_leaderboard( int $limit = 50 ): array {
        $t  = $this->qb->oncourt();
        $ta = $this->qb->anagrafiche();

        $rows = $this->db->query(
            "SELECT TOP :limit
                oc.Player AS db_id, a.PlayerName, a.Pos, a.IdGlobal,
                tnam.TeamName,
                oc.Poss, oc.DefRating, oc.ForcedTovPerPoss,
                oc.OrebPerPoss, oc.Fg2PctAllowed, oc.Fg3PctAllowed,
                PERCENT_RANK() OVER (ORDER BY oc.DefRating ASC) * 100 AS pct_defrtg
             FROM {$t} oc
             JOIN {$ta} a ON oc.Player = a.Id
             LEFT JOIN {$this->qb->anagrafiche_team()} tnam ON a.TeamId = tnam.Id
             WHERE oc.Poss >= 100
             ORDER BY oc.DefRating ASC",
            [':limit' => $limit]
        );

        return array_map( function($r) {
            $public_id = HM_Anonymizer::register(
                $r['db_id'], $this->qb->get_nation(), $this->qb->get_season(), 'player'
            );
            $global_id = HM_Anonymizer::public_id_global( $r['IdGlobal'] ?? null );
            unset( $r['db_id'], $r['IdGlobal'] );
            $r['public_id']     = $public_id;
            $r['public_global'] = $global_id;
            $r['pct_defrtg']    = round( floatval($r['pct_defrtg'] ?? 0), 1 );
            return $r;
        }, $rows );
    }
}
