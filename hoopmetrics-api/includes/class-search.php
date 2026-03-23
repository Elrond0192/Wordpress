<?php
/**
 * HM_Search – usa HM_DB::query() (statico).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Search {

    public function search( string $term, string $nation, string $year ): array {
        $q    = HM_Query_Builder::search($nation, $year, $term);
        $rows = HM_DB::query($q['sql'], $q['params']);

        return array_map(function($r) use ($nation,$year) {
            $type = $r['type'];
            return [
                'public_id'  => hm_public_id((string)$r['Id'], $nation, $year, $type),
                'name'       => $r['name'],
                'team'       => $r['team'] ?? '',
                'team_id'    => $r['TeamId'] ?? null,
                'type'       => $type,
                'nation'     => $nation,
                'year'       => $year,
                'url'        => $type === 'player'
                    ? "?page=player&id=" . urlencode(hm_public_id((string)$r['Id'],$nation,$year,'player'))
                    : "?page=team&id="   . urlencode(hm_public_id((string)$r['Id'],$nation,$year,'team')),
            ];
        }, $rows);
    }
}
