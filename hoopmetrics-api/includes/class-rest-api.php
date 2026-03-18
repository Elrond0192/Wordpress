<?php
/**
 * HM_Rest_API v1.2 – aggiunto endpoint /debug/status.
 * RIMUOVI l'endpoint debug prima di andare in produzione.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Rest_API {

    private const NS = 'hoopmetrics/v1';

    public function register_routes(): void {
        $pub = '__return_true';

        $routes = [
            '/debug/status'                              => [$this,'debug_status'],
            '/dashboard/summary'                         => [$this,'dashboard_summary'],
            '/dashboard/nations'                         => [$this,'dashboard_nations'],
            '/leaderboard/players'                       => [$this,'leaderboard_players'],
            '/leaderboard/teams'                         => [$this,'leaderboard_teams'],
            '/player/(?P<id>[a-zA-Z0-9_\-]+)'           => [$this,'player_profile'],
            '/player/(?P<id>[a-zA-Z0-9_\-]+)/splits'    => [$this,'player_splits'],
            '/player/(?P<id>[a-zA-Z0-9_\-]+)/games'     => [$this,'player_games'],
            '/player/(?P<id>[a-zA-Z0-9_\-]+)/onoff'     => [$this,'player_onoff'],
            '/player/(?P<id>[a-zA-Z0-9_\-]+)/percentiles'=> [$this,'player_percentiles'],
            '/team/(?P<id>[a-zA-Z0-9_\-]+)'             => [$this,'team_profile'],
            '/team/(?P<id>[a-zA-Z0-9_\-]+)/roster'      => [$this,'team_roster'],
            '/search'                                    => [$this,'search'],
        ];

        foreach ( $routes as $route => $callback ) {
            register_rest_route( self::NS, $route, [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => $callback,
                'permission_callback' => $pub,
            ]);
        }
    }

    // ── Debug ─────────────────────────────────────────────────────────────
    public function debug_status( WP_REST_Request $req ): WP_REST_Response {
        $diag = HM_DB::diagnose();

        // Prova anche la connessione reale e riporta l'errore PDO esatto
        try {
            HM_DB::get();
            $diag['connection'] = '✅ OK';
        } catch ( \Exception $e ) {
            $diag['connection'] = '❌ ' . $e->getMessage();
        }

        return new WP_REST_Response(['success' => true, 'data' => $diag], 200);
    }

    // ── Dashboard ─────────────────────────────────────────────────────────
    public function dashboard_summary( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Leaderboard())->get_dashboard_summary(
                $this->nation($req), $this->year($req), $this->comp($req)
            );
        });
    }

    public function dashboard_nations( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Leaderboard())->get_nations_overview(
                $this->year($req), $this->comp($req)
            );
        });
    }

    // ── Leaderboard ───────────────────────────────────────────────────────
    public function leaderboard_players( WP_REST_Request $req ): WP_REST_Response {
    return $this->handle(function() use ($req) {
        $pos     = strtoupper(sanitize_text_field($req->get_param('pos') ?? 'all'));
        $pos     = in_array($pos, ['PG','SG','SF','PF','C'], true) ? $pos : 'all';
        $age_min = max(0,  min(99, (int)($req->get_param('age_min') ?? 0)));
        $age_max = max(0,  min(99, (int)($req->get_param('age_max') ?? 99)));
        $limit   = min(200, max(10, (int)($req->get_param('limit')   ?? 50)));
        $min_min = max(0,          (int)($req->get_param('min_min')  ?? 150));

        return HM_Leaderboard::get_player_leaderboard(
            $this->nation($req), $this->year($req),
            $this->metric($req), $this->comp($req),
            $limit, $min_min,
            $pos, $age_min, $age_max
        );
    });
}

    public function leaderboard_teams( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Leaderboard())->get_team_leaderboard(
                $this->nation($req), $this->year($req),
                sanitize_text_field($req->get_param('metric') ?? 'NetRtg'),
                $this->comp($req)
            );
        });
    }

    // ── Player ────────────────────────────────────────────────────────────
    public function player_profile( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Players())->get_profile(
                $req['id'], $this->nation($req), $this->year($req), $this->comp($req)
            );
        });
    }

    public function player_splits( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Players())->get_all_splits(
                $req['id'], $this->nation($req), $this->year($req)
            );
        });
    }

    public function player_games( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Players())->get_last_games(
                $req['id'], $this->nation($req), $this->year($req),
                (int)($req->get_param('limit') ?? 10),
                sanitize_text_field($req->get_param('comp') ?? '')
            );
        });
    }

    public function player_onoff( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Players())->get_onoff(
                $req['id'], $this->nation($req), $this->year($req), $this->comp($req)
            );
        });
    }

    public function player_percentiles( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Players())->get_percentiles(
                $req['id'], $this->nation($req), $this->year($req), $this->comp($req)
            );
        });
    }

    // ── Team ──────────────────────────────────────────────────────────────
    public function team_profile( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Teams())->get_profile(
                $req['id'], $this->nation($req), $this->year($req), $this->comp($req)
            );
        });
    }

    public function team_roster( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Teams())->get_roster(
                $this->nation($req), $this->year($req), $req['id'], $this->comp($req)
            );
        });
    }

    // ── Search ────────────────────────────────────────────────────────────
    public function search( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            $q = sanitize_text_field($req->get_param('q') ?? '');
            if ( mb_strlen($q) < 2 )
                throw new \RuntimeException('Query troppo breve (min 2 caratteri)');
            return (new HM_Search())->search(
                $q,
                sanitize_text_field($req->get_param('nation') ?? ''),
                $this->year($req)
            );
        });
    }

    // ── Param helpers ─────────────────────────────────────────────────────
    private function nation( WP_REST_Request $r ): string {
        return strtoupper(sanitize_text_field($r->get_param('nation') ?? 'GRC'));
    }
    private function year( WP_REST_Request $r ): string {
        return sanitize_text_field($r->get_param('year') ?? '2024');
    }
    private function comp( WP_REST_Request $r ): string {
        return sanitize_text_field($r->get_param('comp') ?? 'RS');
    }
    private function metric( WP_REST_Request $r ): string {
        return sanitize_text_field($r->get_param('metric') ?? 'RaptorTotal');
    }

    // ── Handle wrapper ────────────────────────────────────────────────────
    private function handle( callable $fn ): WP_REST_Response {
        try {
            return new WP_REST_Response(['success' => true,  'data'  => $fn()], 200);
        } catch ( \InvalidArgumentException $e ) {
            return new WP_REST_Response(['success' => false, 'error' => $e->getMessage()], 400);
        } catch ( \RuntimeException $e ) {
            return new WP_REST_Response(['success' => false, 'error' => $e->getMessage()], 500);
        } catch ( \Exception $e ) {
            error_log('[HoopMetrics] Unhandled: ' . $e->getMessage());
            return new WP_REST_Response(['success' => false, 'error' => 'Errore interno'], 500);
        }
    }
}
