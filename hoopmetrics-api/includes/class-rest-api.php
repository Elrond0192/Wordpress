<?php
/**
 * HM_Rest_API v1.4
 * Aggiunto: /dashboard/meta per filtri dinamici (nazioni, stagioni, competizioni).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Rest_API {

    private const NS = 'hoopmetrics/v1';

    public function register_routes(): void {

        $routes = [
            '/debug/resolve'                                              => [$this,'debug_resolve'],
            '/dashboard/summary'                                          => [$this,'dashboard_summary'],
            '/dashboard/nations'                                          => [$this,'dashboard_nations'],
            '/dashboard/meta'                                             => [$this,'dashboard_meta'],
            '/leaderboard/players'                                        => [$this,'leaderboard_players'],
            '/leaderboard/teams'                                          => [$this,'leaderboard_teams'],
            // ⚠️ Le route specifiche PRIMA di quella generica /player/{id}
            '/player/(?P<id>[a-zA-Z0-9_\-]+)/shots'                      => [$this,'player_shots'],
            '/player/(?P<id>[a-zA-Z0-9_\-]+)/lineups'                    => [$this,'player_lineups'],
            '/player/(?P<id>[a-zA-Z0-9_\-]+)/role'                       => [$this,'player_role'],
            '/player/(?P<id>[a-zA-Z0-9_\-]+)/splits'                     => [$this,'player_splits'],
            '/player/(?P<id>[a-zA-Z0-9_\-]+)/games'                      => [$this,'player_games'],
            '/player/(?P<id>[a-zA-Z0-9_\-]+)/onoff'                      => [$this,'player_onoff'],
            '/player/(?P<id>[a-zA-Z0-9_\-]+)/percentiles'                => [$this,'player_percentiles'],
            '/player/(?P<id>[a-zA-Z0-9_\-]+)/similar'                    => [$this,'player_similar'],
            '/player/(?P<id>[a-zA-Z0-9_\-]+)/playtypes'                  => [$this,'player_playtypes'],
            // Route generica ULTIMA
            '/player/(?P<id>[a-zA-Z0-9_\-]+)'                            => [$this,'player_profile'],
            '/team/(?P<id>[a-zA-Z0-9_\-]+)/roster'                       => [$this,'team_roster'],
            '/team/(?P<id>[a-zA-Z0-9_\-]+)'                              => [$this,'team_profile'],
            '/search'                                                     => [$this,'search'],
        ];

        foreach ( $routes as $route => $callback ) {
            register_rest_route( self::NS, $route, [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => $callback,
                'permission_callback' => [$this, 'check_access'],
            ]);
        }
    }

    // ── Gate unico: nonce + rate limit ────────────────────────────────────
    /**
     * Verifica che la richiesta abbia un nonce WP valido e non superi
     * il rate limit per IP.
     * Restituisce true se tutto OK, WP_Error altrimenti.
     */
    public function check_access( WP_REST_Request $req ): true|WP_Error {

        // 1. Verifica nonce — obbligatorio per ogni richiesta autenticata
        $nonce = $req->get_header('X-WP-Nonce')
              ?? $req->get_param('_wpnonce')
              ?? '';

        if ( ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'hm_unauthorized',
                'Accesso non autorizzato. Ricarica la pagina.',
                ['status' => 401]
            );
        }

        // 2. Rate limiting per IP
        $rate_check = HM_Rate_Limiter::check();
        if ( is_wp_error( $rate_check ) ) {
            return $rate_check;
        }

        return true;
    }

    // ── Debug resolve (RIMUOVERE dopo il fix) ─────────────────────────────
    // GET /wp-json/hoopmetrics/v1/debug/resolve?id=hm_xxx&nation=GRC&year=2024
    public function debug_resolve( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            $id     = sanitize_text_field( $req->get_param('id')     ?? '' );
            $nation = $this->nation($req);
            $year   = $this->year($req);

            // 1. Invalida e ricostruisce la mappa
            HM_Anonymizer::invalidate( $nation, $year );
            $resolved = HM_Anonymizer::resolve( $id, $nation, $year );

            // 2. Controlla quanti giocatori ha la tabella anagrafiche
            $count = null;
            try {
                $ana   = HM_Query_Builder::tbl_anagrafiche( $nation, $year );
                $count = HM_DB::query_val("SELECT COUNT(*) FROM {$ana}");
            } catch(\Exception $e) {
                $count = 'ERRORE: ' . $e->getMessage();
            }

            // 3. Verifica hash manuale
            $salt        = defined('HM_ID_SALT') ? HM_ID_SALT : '(AUTH_SALT)';
            $test_ids    = [];
            try {
                $ana  = HM_Query_Builder::tbl_anagrafiche( $nation, $year );
                $rows = HM_DB::query("SELECT TOP 3 Id FROM {$ana}");
                foreach($rows as $r) {
                    $db_id = $r['Id'];
                    $hash  = HM_Anonymizer::public_id($db_id, $nation, $year, 'player');
                    $test_ids[] = ['db_id'=>$db_id,'public_id'=>$hash];
                }
            } catch(\Exception $e) {}

            return [
                'id_searched'  => $id,
                'resolved'     => $resolved,
                'player_count' => $count,
                'sample_ids'   => $test_ids,
                'salt_length'  => defined('HM_ID_SALT') ? strlen(HM_ID_SALT) : 0,
            ];
        });
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

    /**
     * Restituisce nazioni, stagioni e competizioni disponibili nel DB.
     * Usato dai filtri dinamici della leaderboard.
     * GET /dashboard/meta?nation=GRC&year=2024
     */
    public function dashboard_meta( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return HM_Leaderboard::get_meta(
                $this->nation($req),
                $this->year($req)
            );
        });
    }

    // ── Leaderboard ───────────────────────────────────────────────────────
    public function leaderboard_players( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            $pos     = strtoupper( sanitize_text_field( $req->get_param('pos') ?? 'all' ) );
            $pos     = in_array( $pos, ['PG','SG','SF','PF','C'], true ) ? $pos : 'all';
            $age_min = max( 0,   min( 99,  (int)($req->get_param('age_min') ?? 0)   ) );
            $age_max = max( 0,   min( 99,  (int)($req->get_param('age_max') ?? 99)  ) );
            $limit   = max( 10,  min( 200, (int)($req->get_param('limit')   ?? 50)  ) );
            $min_min = max( 0,             (int)($req->get_param('min_min') ?? 150) );

            return HM_Leaderboard::get_player_leaderboard(
                $this->nation($req), $this->year($req),
                $this->metric($req), $this->comp($req),
                $limit, $min_min, $pos, $age_min, $age_max
            );
        });
    }

    public function leaderboard_teams( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Leaderboard())->get_team_leaderboard(
                $this->nation($req), $this->year($req),
                sanitize_text_field( $req->get_param('metric') ?? 'NetRtg' ),
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

    public function player_similar( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Players())->get_similar(
                $req['id'], $this->nation($req), $this->year($req), $this->comp($req)
            );
        });
    }

    public function player_playtypes( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Players())->get_shots_conversion(
                $req['id'], $this->nation($req), $this->year($req), $this->comp($req)
            );
        });
    }

    public function player_shots( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Players())->get_shots(
                $req['id'], $this->nation($req), $this->year($req), $this->comp($req)
            );
        });
    }

    public function player_lineups( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Players())->get_lineups(
                $req['id'], $this->nation($req), $this->year($req), $this->comp($req)
            );
        });
    }

    public function player_role( WP_REST_Request $req ): WP_REST_Response {
        return $this->handle(function() use ($req) {
            return (new HM_Players())->get_role(
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
                (int)( $req->get_param('limit') ?? 10 ),
                sanitize_text_field( $req->get_param('comp') ?? '' )
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
            $q = sanitize_text_field( $req->get_param('q') ?? '' );
            if ( mb_strlen($q) < 2 ) {
                throw new \InvalidArgumentException( 'Query troppo breve (minimo 2 caratteri).' );
            }
            return (new HM_Search())->search(
                $q,
                sanitize_text_field( $req->get_param('nation') ?? '' ),
                $this->year($req)
            );
        });
    }

    // ── Param helpers ─────────────────────────────────────────────────────
    private function nation( WP_REST_Request $r ): string {
        return strtoupper( sanitize_text_field( $r->get_param('nation') ?? 'GRC' ) );
    }

    private function year( WP_REST_Request $r ): string {
        return sanitize_text_field( $r->get_param('year') ?? '2024' );
    }

    private function comp( WP_REST_Request $r ): string {
        return sanitize_text_field( $r->get_param('comp') ?? 'RS' );
    }

    private function metric( WP_REST_Request $r ): string {
        return sanitize_text_field( $r->get_param('metric') ?? 'RaptorTotal' );
    }

    // ── Handle wrapper ────────────────────────────────────────────────────
    private function handle( callable $fn ): WP_REST_Response {
        try {
            return new WP_REST_Response(['success' => true, 'data' => $fn()], 200);
        } catch ( \InvalidArgumentException $e ) {
            return new WP_REST_Response(['success' => false, 'error' => $e->getMessage()], 400);
        } catch ( \RuntimeException $e ) {
            // Non esporre dettagli tecnici al client
            error_log( '[HoopMetrics] RuntimeException: ' . $e->getMessage() );
            return new WP_REST_Response(['success' => false, 'error' => 'Errore interno del server.'], 500);
        } catch ( \Exception $e ) {
            error_log( '[HoopMetrics] Exception: ' . $e->getMessage() );
            return new WP_REST_Response(['success' => false, 'error' => 'Errore interno del server.'], 500);
        }
    }
}