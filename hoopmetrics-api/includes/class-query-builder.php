<?php
/**
 * HM_Query_Builder v3.1
 * Fix: rimosso $this_is_static da dashboard_kpi().
 * Fix: PERCENT_RANK ORDER BY ASC → il giocatore migliore ha percentile 100.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Query_Builder {

    private const VALID_NATIONS = ['GRC','ITA','DEU','FRA','SPA','TUR','SRB','LTU'];
    private const VALID_YEARS   = ['2022','2023','2024','2025'];
    // Tutti i valori validi della colonna Competition del DB
    private const COMP_SPLITS     = ['RS','PO','TOT','Home','Away','CUP','SUPERCUP'];
    private const LOCATION_SPLITS = []; // Home/Away sono valori reali di Competition, non filtri IsHome

    private const VALID_PLAYER_METRICS = [
        'RaptorOff','RaptorDef','RaptorTotal','LebronOff','LebronDef','LebronTotal',
        'NetRtg','ORtg','DRtg','Bpm','Vorp','Spm','UsgPct','TsPct','EfgPct',
        'AstPct','StlPct','BlkPct','TovPct','RebPct','OrebPct','DrebPct',
        'Ws','GmSc','Fic','Pie','AstRatio',
        'PtsPer40','AstPer40','TrPer40','StlPer40','BlkPer40','ScoringEfficiency','HustleIndex',
    ];

    private const VALID_TEAM_METRICS = [
        'NetRtg','ORtg','DRtg','Pace','EfgPct','TsPct',
        'AstRatio','ToRatio','StlRatio','BlkRatio','OrbPct','RebPct','Pie','TeamEfficiency',
    ];

    private const METRIC_ALIASES = [
        'raptor'     => 'RaptorTotal', 'raptortotal' => 'RaptorTotal',
        'raptoroff'  => 'RaptorOff',   'raptordef'   => 'RaptorDef',
        'lebron'     => 'LebronTotal', 'lebrontotal'  => 'LebronTotal',
        'lebronoff'  => 'LebronOff',   'lebrondef'    => 'LebronDef',
        'netrtg'     => 'NetRtg',      'ortg'         => 'ORtg',
        'drtg'       => 'DRtg',        'bpm'          => 'Bpm',
        'vorp'       => 'Vorp',        'ws'           => 'Ws',
        'usgpct'     => 'UsgPct',      'tspct'        => 'TsPct',
        'efgpct'     => 'EfgPct',      'gmsc'         => 'GmSc',
        'gmscore'    => 'GmSc',        'pie'          => 'Pie',
        'pace'       => 'Pace',        'spm'          => 'Spm',
    ];

    // ── Nome tabelle ──────────────────────────────────────────────────────

    public static function tbl_anagrafiche  (string $n, string $y): string { self::guard($n,$y); return "[Anagrafiche].[{$n}_{$y}]"; }
    public static function tbl_team_registry(string $n, string $y): string { self::guard($n,$y); return "[Anagrafiche].[Team_{$n}_{$y}]"; }
    public static function tbl_player_stats (string $n, string $y): string { self::guard($n,$y); return "[Analisi].[AdvancedStats_Player_{$n}_{$y}]"; }
    public static function tbl_player_game  (string $n, string $y): string { self::guard($n,$y); return "[Analisi].[AdvancedStats_Player_{$n}_{$y}_Game]"; }
    public static function tbl_onoff        (string $n, string $y): string { self::guard($n,$y); return "[Analisi].[AdvancedStatsOnOffCourt_{$n}_{$y}]"; }
    public static function tbl_team_stats   (string $n, string $y): string { self::guard($n,$y); return "[Analisi].[AdvancedStatsTeam_{$n}_{$y}]"; }
    public static function tbl_player_roles (string $n, string $y): string { self::guard($n,$y); return "[Analisi].[PlayerRoles_{$n}_{$y}]"; }
    public static function tbl_pbp          (string $n, string $y): string { self::guard($n,$y); return "[PBP].[{$n}_{$y}]"; }
    public static function tbl_lineups      (string $n, string $y): string { self::guard($n,$y); return "[Analisi].[Lineups_{$n}_{$y}]"; }

    public static function comp_filter(string $comp, string $alias = 's'): array {
        $p = $alias !== '' ? "{$alias}." : '';
        if ( in_array($comp, self::COMP_SPLITS, true) )
            return ['where' => "AND {$p}Competition = '{$comp}'", 'params' => []];
        if ($comp === 'Home') return ['where' => "AND {$p}IsHome = 1", 'params' => []];
        if ($comp === 'Away') return ['where' => "AND {$p}IsHome = 0", 'params' => []];
        return ['where' => '', 'params' => []];
    }

    // ── Leaderboard ───────────────────────────────────────────────────────

    public static function leaderboard(
        string $nation,  string $year,   string $metric  = 'RaptorTotal',
        string $comp     = 'RS', int $limit = 50, int $min_min = 0,
        string $pos      = 'all', int $age_min = 0, int $age_max = 99
    ): array {
        $metric  = self::normalize_metric($metric, self::VALID_PLAYER_METRICS);
        $limit   = max(1, min(200, $limit));
        $min_min = max(0, $min_min);

        $pos_filter = '';
        $pos_param  = [];
        if ( $pos !== 'all' && in_array(strtoupper($pos), ['PG','SG','SF','PF','C'], true) ) {
            $pos_clean  = strtoupper($pos);
            $pos_filter = "AND a.Pos = :pos_val";
            $pos_param  = [':pos_val' => $pos_clean];
        }

        $age_filter = '';
        $age_params = [];
        if ( $age_min > 0 || $age_max < 99 ) {
            $age_filter = 'AND s.Age BETWEEN :age_min AND :age_max';
            $age_params = [':age_min' => $age_min, ':age_max' => $age_max];
        }

        $ana = self::tbl_anagrafiche($nation, $year);
        $reg = self::tbl_team_registry($nation, $year);

        // Tutti i valori di Competition (RS, PO, TOT, Home, Away…) passano dalla stats table
        $stats  = self::tbl_player_stats($nation, $year);
        $filter = self::comp_filter($comp, 's');

        $sql = "SELECT s.Id AS db_id,
            CASE WHEN LEN(a.NormalizedPlayerName)>2 THEN a.NormalizedPlayerName ELSE a.PlayerName END AS PlayerName,
            a.TeamName, r.Id AS TeamId, a.Pos, a.BirthDate,
            CAST(s.Min / NULLIF(s.Games,0) AS decimal(18,2)) AS Min,
            s.[{$metric}]  AS metric_val,
            s.RaptorOff,   s.RaptorDef,   s.RaptorTotal,
            s.LebronOff,   s.LebronDef,   s.LebronTotal,
            s.NetRtg,      s.ORtg,        s.DRtg,
            s.UsgPct,      s.TsPct,       s.EfgPct,
            s.Bpm,         s.Vorp,        s.Spm,
            s.Ws,          s.GmSc,        s.Pie,
            s.Age,
            PERCENT_RANK() OVER (ORDER BY s.[{$metric}] ASC) * 100 AS pct_rank
        FROM {$stats} s
        LEFT JOIN {$ana} a ON s.Id = a.Id
        LEFT JOIN {$reg} r ON a.TeamName = r.ShortName
        WHERE s.Min >= {$min_min}
        {$filter['where']}
        {$pos_filter}
        {$age_filter}
        ORDER BY s.[{$metric}] DESC
        OFFSET 0 ROWS FETCH NEXT {$limit} ROWS ONLY";

        return [
            'sql'    => $sql,
            'params' => array_merge($filter['params'], $pos_param, $age_params),
        ];
    }

    // ── Dashboard ─────────────────────────────────────────────────────────

    public static function dashboard_kpi(string $nation, string $year, string $comp = 'RS'): array {
        $stats  = self::tbl_player_stats($nation, $year);
        $filter = self::comp_filter($comp, 's');

        $sql = "SELECT
            AVG(RaptorTotal) AS avg_raptor,
            AVG(LebronTotal) AS avg_lebron,
            AVG(NetRtg)      AS avg_netrtg,
            AVG(DRtg)        AS avg_drtg,
            COUNT(DISTINCT Id) AS total_players
        FROM (
            SELECT TOP 50 RaptorTotal, LebronTotal, NetRtg, DRtg, Id
            FROM {$stats} s
            WHERE Min >= 200
            {$filter['where']}
            ORDER BY RaptorTotal DESC
        ) sub";

        return ['sql' => $sql, 'params' => []];
    }

    public static function home_away_diff(string $nation, string $year, string $comp = 'RS'): array {
        $game   = self::tbl_player_game($nation, $year);
        $filter = self::comp_filter($comp, 'g');

        $sql = "SELECT
            ROUND(AVG(CASE WHEN g.IsHome=1 THEN g.NetRtg  END) - AVG(CASE WHEN g.IsHome=0 THEN g.NetRtg  END), 2) AS netrtg_home_away_diff,
            ROUND(AVG(CASE WHEN g.IsHome=1 THEN g.ORtg    END) - AVG(CASE WHEN g.IsHome=0 THEN g.ORtg    END), 2) AS ortg_diff,
            ROUND(AVG(CASE WHEN g.IsHome=1 THEN g.DRtg    END) - AVG(CASE WHEN g.IsHome=0 THEN g.DRtg    END), 2) AS drtg_diff,
            ROUND(AVG(CASE WHEN g.IsHome=1 THEN g.RaptorTotal END) - AVG(CASE WHEN g.IsHome=0 THEN g.RaptorTotal END), 2) AS raptor_diff
        FROM {$game} g
        WHERE g.Min >= 15
        {$filter['where']}";

        return ['sql' => $sql, 'params' => []];
    }

    // ── Player ────────────────────────────────────────────────────────────

    public static function player_all_splits(string $nation, string $year, string $db_id): array {
        $stats = self::tbl_player_stats($nation, $year);
        return [
            'sql'    => "SELECT s.* FROM {$stats} s WHERE s.Id = :id ORDER BY
                         CASE s.Competition WHEN 'TOT' THEN 0 WHEN 'RS' THEN 1 WHEN 'PO' THEN 2 ELSE 3 END",
            'params' => [':id' => $db_id],
        ];
    }

    public static function player_last_games(string $nation, string $year, string $db_id, int $n = 10, string $comp = 'RS'): array {
        $n    = max(1, min(500, $n));
        $game = self::tbl_player_game($nation, $year);
        $pbp  = self::tbl_pbp($nation, $year);

        $filter_where  = '';
        $filter_params = [];
        if ( $comp !== '' && $comp !== 'TOT' && in_array($comp, self::COMP_SPLITS, true) ) {
            $filter_where  = "AND g.Competition = :comp";
            $filter_params = [':comp' => $comp];
        }

        $team = self::tbl_team_registry($nation, $year);

        $sql = "
        WITH AllGames AS (
            -- Prima deduplicazione: una riga per partita
            SELECT g.*,
                   ROW_NUMBER() OVER (PARTITION BY g.Game ORDER BY g.Timestamp DESC) AS rn
            FROM {$game} g
            WHERE g.Id = :id {$filter_where}
        ),
        Numbered AS (
            -- Numerazione cronologica: partita #1 = prima della stagione
            SELECT a.*,
                   ROW_NUMBER() OVER (ORDER BY a.Timestamp ASC) AS GameNum
            FROM AllGames a
            WHERE a.rn = 1
        )
        SELECT TOP {$n}
            n.GameNum,
            n.Game, n.Sf, n.TeamId, n.IsHome, n.Competition, n.Timestamp,
            n.Min, n.Pts, n.Ast, n.Tr, n.[Or], n.Dr,
            n.[2Fgm], n.[2Fga], n.[2P],
            n.[3Fgm], n.[3Fga], n.[3P],
            n.Ftm, n.Fta, n.Ft,
            n.Stl, n.Blk, n.[To], n.Pf,
            n.ValLega, n.PlusMinus,
            n.TsPct, n.EfgPct, n.UsgPct, n.AstPct,
            n.ORtg, n.DRtg, n.NetRtg,
            n.RaptorOff, n.RaptorDef, n.RaptorTotal,
            n.LebronOff, n.LebronDef, n.LebronTotal,
            n.GmSc, n.Bpm, n.Ws, n.Pie,
            n.PtsPer40, n.AstPer40, n.TrPer40,
            opp.ShortName AS Opponent
        FROM Numbered n
        OUTER APPLY (
            SELECT TOP 1 t.ShortName
            FROM {$pbp} pbp
            JOIN {$team} t ON t.Id = pbp.TeamId
            WHERE pbp.GameCode = n.Game
              AND pbp.HomeClub = CASE WHEN n.IsHome = 1 THEN 0 ELSE 1 END
        ) opp
        ORDER BY n.Timestamp DESC";

        return ['sql' => $sql, 'params' => array_merge([':id' => $db_id], $filter_params)];
    }

    public static function player_profile(string $nation, string $year, string $db_id, string $comp = ''): array {
        $stats  = self::tbl_player_stats($nation, $year);
        $ana    = self::tbl_anagrafiche($nation, $year);
        $reg    = self::tbl_team_registry($nation, $year);
        $filter = ( $comp !== '' ) ? self::comp_filter($comp, 's') : ['where' => '', 'params' => []];

        $sql = "SELECT TOP 1 s.*,
            CASE WHEN LEN(a.NormalizedPlayerName)>2 THEN a.NormalizedPlayerName ELSE a.PlayerName END AS PlayerName,
            a.Nat, a.Nat2, a.BirthDate, a.Cm, a.Weight, a.ShirtNumber, a.Pos, a.TeamName,
            r.Id AS TeamId
        FROM {$stats} s
        LEFT JOIN {$ana} a ON s.Id = a.Id
        LEFT JOIN {$reg} r ON a.TeamName = r.ShortName
        WHERE s.Id = :id {$filter['where']}
        ORDER BY CASE s.Competition WHEN 'TOT' THEN 0 WHEN 'RS' THEN 1 ELSE 2 END";

        return ['sql' => $sql, 'params' => [':id' => $db_id]];
    }

    /**
     * Tiri dal PBP.
     * Restituisce due query separate:
     *   sql_pt  — aggregazione per PlayType (sempre disponibile)
     *   sql_xy  — righe con X/Y se le colonne esistono (dipende dalla nazione)
     */
    public static function player_shots(string $nation, string $year, string $db_id, string $comp = ''): array {
        $pbp = self::tbl_pbp($nation, $year);

        // La game table PBP non ha Competition='TOT' — escludilo dal filtro
        $filter = ( $comp !== '' && $comp !== 'TOT' )
            ? "AND p.Competition = :comp"
            : '';
        $params = ( $comp !== '' && $comp !== 'TOT' )
            ? [':id' => $db_id, ':comp' => $comp]
            : [':id' => $db_id];

        $sql_pt = "SELECT p.PlayType, COUNT(*) AS cnt
                   FROM {$pbp} p
                   WHERE p.Player = :id
                     AND p.PlayType IS NOT NULL
                     {$filter}
                   GROUP BY p.PlayType
                   ORDER BY cnt DESC";

        $sql_xy = "SELECT p.X, p.Y, p.PlayType,
                          CASE WHEN p.And1 IS NOT NULL THEN p.And1 ELSE 0 END AS Made,
                          p.Competition
                   FROM {$pbp} p
                   WHERE p.Player = :id
                     AND p.X IS NOT NULL AND p.Y IS NOT NULL
                     {$filter}";

        return [
            'sql_pt' => $sql_pt,
            'sql_xy' => $sql_xy,
            'params' => $params,
        ];
    }

    /**
     * Quintetti più usati dal PBP.
     * HomeLineup / AwayLineup contengono ID separati da ' - '.
     * Usa Score delta (format "home:away") per calcolare ORtg/DRtg/NetRtg per lineup.
     * PlayType 'IN'/'OUT' esclusi. Raggruppa per stringa lineup.
     */
    public static function player_lineup_stats(string $nation, string $year, string $db_id, string $comp = ''): array {
        $pbp     = self::tbl_pbp($nation, $year);
        $pattern = '%' . $db_id . '%';

        // Filtra per competition solo se è un valore reale della colonna (non TOT, non vuoto)
        $use_comp = $comp !== '' && $comp !== 'TOT' && in_array($comp, self::COMP_SPLITS, true);
        $filter   = $use_comp ? "AND p.Competition = :comp" : '';

        // PDO/sqlsrv non supporta named params riusati: uso nomi distinti per ogni occorrenza
        $params = [':p1'=>$pattern,':p2'=>$pattern,':p3'=>$pattern,':p4'=>$pattern,':p5'=>$pattern];
        if ($use_comp) $params[':comp'] = $comp;

        $sql = "
        WITH Parsed AS (
    SELECT
        CASE WHEN p.HomeLineup LIKE :p1 THEN p.HomeLineup ELSE p.AwayLineup END AS RawLineup,
        CASE WHEN p.HomeLineup LIKE :p2 THEN 1 ELSE 0 END                       AS IsHome,
        TRY_CAST(LEFT(p.Score, CHARINDEX(':', p.Score) - 1)       AS int)       AS HScore,
        TRY_CAST(SUBSTRING(p.Score, CHARINDEX(':', p.Score)+1, 10) AS int)      AS AScore,
        p.GameCode, p.Timestamp, p.PlayType,
        ROW_NUMBER() OVER (ORDER BY p.GameCode, p.Timestamp)                    AS RowId
    FROM {$pbp} p
    WHERE (p.HomeLineup LIKE :p3 OR p.AwayLineup LIKE :p4)
      AND p.PlayType NOT IN ('IN','OUT')
      {$filter}
),
Split AS (
    SELECT pa.RowId, pa.IsHome, pa.HScore, pa.AScore,
           pa.GameCode, pa.Timestamp, pa.PlayType,
           TRIM(s.value) AS Player
    FROM Parsed pa
    CROSS APPLY STRING_SPLIT(pa.RawLineup, '-') s
),
Normalized AS (
    SELECT RowId, IsHome, HScore, AScore, GameCode, Timestamp, PlayType,
        MAX(CASE WHEN rn=1 THEN Player END) + ' - ' +
        MAX(CASE WHEN rn=2 THEN Player END) + ' - ' +
        MAX(CASE WHEN rn=3 THEN Player END) + ' - ' +
        MAX(CASE WHEN rn=4 THEN Player END) + ' - ' +
        MAX(CASE WHEN rn=5 THEN Player END) AS MyLineup
    FROM (
        SELECT *, ROW_NUMBER() OVER (PARTITION BY RowId ORDER BY Player) AS rn
        FROM Split
    ) r
    GROUP BY RowId, IsHome, HScore, AScore, GameCode, Timestamp, PlayType
),
WithPrev AS (
    SELECT *,
        LAG(HScore) OVER (PARTITION BY GameCode, MyLineup ORDER BY Timestamp) AS PH,
        LAG(AScore) OVER (PARTITION BY GameCode, MyLineup ORDER BY Timestamp) AS PA
    FROM Normalized
),
Deltas AS (
    SELECT MyLineup, PlayType, GameCode,
        CASE WHEN IsHome=1 THEN CASE WHEN HScore-PH BETWEEN 0 AND 4 THEN HScore-PH ELSE 0 END
                           ELSE CASE WHEN AScore-PA BETWEEN 0 AND 4 THEN AScore-PA ELSE 0 END END AS PtsFor,
        CASE WHEN IsHome=1 THEN CASE WHEN AScore-PA BETWEEN 0 AND 4 THEN AScore-PA ELSE 0 END
                           ELSE CASE WHEN HScore-PH BETWEEN 0 AND 4 THEN HScore-PH ELSE 0 END END AS PtsAgainst
    FROM WithPrev
    WHERE PH IS NOT NULL AND PA IS NOT NULL
)
SELECT TOP 8
    MyLineup                                                                             AS Lineup,
    COUNT(*)                                                                             AS Poss,
    COUNT(DISTINCT GameCode)                                                             AS Games,
    SUM(PtsFor)                                                                          AS TotFor,
    SUM(PtsAgainst)                                                                      AS TotAgainst,
    CASE WHEN COUNT(*)>0 THEN ROUND(CAST(SUM(PtsFor)     AS float)/COUNT(*)*100, 1) END AS ORtg,
    CASE WHEN COUNT(*)>0 THEN ROUND(CAST(SUM(PtsAgainst) AS float)/COUNT(*)*100, 1) END AS DRtg,
    CASE WHEN COUNT(*)>0 THEN ROUND((CAST(SUM(PtsFor) AS float)-SUM(PtsAgainst))/COUNT(*)*100, 1) END AS NetRtg
FROM Deltas
WHERE MyLineup LIKE :p5
GROUP BY MyLineup
ORDER BY Poss DESC
";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * PlayType breakdown per ogni lineup (top 8 per possessi).
     * Usa subquery per pre-calcolare MyLineup ed evitare CASE WHEN nel GROUP BY
     * (pdo_sqlsrv non supporta named params dentro CASE in GROUP BY).
     */
    public static function player_lineup_playtypes(string $nation, string $year, string $db_id, string $comp = ''): array {
        $pbp     = self::tbl_pbp($nation, $year);
        $pattern = '%' . $db_id . '%';

        $use_comp = $comp !== '' && $comp !== 'TOT' && in_array($comp, self::COMP_SPLITS, true);
        $filter   = $use_comp ? "AND PlayType NOT IN ('IN','OUT') AND Competition = :comp" : "AND PlayType NOT IN ('IN','OUT')";
        $filter2  = $use_comp ? "AND p.PlayType NOT IN ('IN','OUT') AND p.Competition = :comp2" : "AND p.PlayType NOT IN ('IN','OUT')";

        $params = [':p1'=>$pattern,':p2'=>$pattern,':p3'=>$pattern,':p4'=>$pattern,':p5'=>$pattern];
        if ($use_comp) { $params[':comp'] = $comp; $params[':comp2'] = $comp; }

        $sql = "
        WITH Base AS (
            SELECT
                CASE WHEN HomeLineup LIKE :p1 THEN HomeLineup ELSE AwayLineup END AS MyLineup,
                PlayType
            FROM {$pbp}
            WHERE (HomeLineup LIKE :p2 OR AwayLineup LIKE :p3)
              {$filter}
        ),
        TopLineups AS (
            SELECT TOP 8 MyLineup, COUNT(*) AS Poss
            FROM Base
            GROUP BY MyLineup
            ORDER BY Poss DESC
        )
        SELECT t.MyLineup AS Lineup, p.PlayType, COUNT(*) AS Cnt
        FROM {$pbp} p
        JOIN TopLineups t ON (p.HomeLineup = t.MyLineup OR p.AwayLineup = t.MyLineup)
        WHERE (p.HomeLineup LIKE :p4 OR p.AwayLineup LIKE :p5)
          {$filter2}
        GROUP BY t.MyLineup, p.PlayType
        ORDER BY t.MyLineup, Cnt DESC";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Distribuzione per quarto dei top lineup del giocatore.
     * Restituisce: Lineup, Quarter, Cnt
     */
    public static function player_lineup_quarters(string $nation, string $year, string $db_id, string $comp = ''): array {
        $pbp     = self::tbl_pbp($nation, $year);
        $pattern = '%' . $db_id . '%';
        $use_comp = $comp !== '' && $comp !== 'TOT' && in_array($comp, self::COMP_SPLITS, true);
        $filter   = $use_comp ? "AND Competition = :comp" : '';

        // Named params distinti — PDO non supporta riuso
        $params = [':p1'=>$pattern, ':p2'=>$pattern, ':p3'=>$pattern, ':p4'=>$pattern];
        if ($use_comp) $params[':comp'] = $comp;

        // Base include Quarter — TopLineups identifica i top 8 — finale aggrega per quarto
        $sql = "
        WITH Base AS (
            SELECT
                CASE WHEN HomeLineup LIKE :p1 THEN HomeLineup ELSE AwayLineup END AS MyLineup,
                Quarter,
                PlayType
            FROM {$pbp}
            WHERE (HomeLineup LIKE :p2 OR AwayLineup LIKE :p3)
              AND PlayType NOT IN ('IN','OUT')
              {$filter}
        ),
        TopLineups AS (
            SELECT TOP 8 MyLineup, COUNT(*) AS Poss
            FROM Base
            GROUP BY MyLineup
            ORDER BY Poss DESC
        )
        SELECT b.MyLineup AS Lineup, b.Quarter, COUNT(*) AS Cnt
        FROM Base b
        INNER JOIN TopLineups t ON b.MyLineup = t.MyLineup
        WHERE b.MyLineup LIKE :p4
        GROUP BY b.MyLineup, b.Quarter
        ORDER BY b.MyLineup, b.Quarter";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * PlayType del giocatore con conversion rate da And1.
     * And1=1 indica che l'azione è risultata in un canestro.
     */
    public static function player_playtypes_conversion(string $nation, string $year, string $db_id, string $comp = ''): array {
        $pbp      = self::tbl_pbp($nation, $year);
        $use_comp = $comp !== '' && $comp !== 'TOT' && in_array($comp, self::COMP_SPLITS, true);
        $filter   = $use_comp ? "AND p.Competition = :comp" : '';
        $params   = $use_comp ? [':id' => $db_id, ':comp' => $comp] : [':id' => $db_id];

        $sql = "SELECT
                    p.PlayType,
                    COUNT(*)                                              AS Total,
                    SUM(CASE WHEN p.And1 = 1 THEN 1 ELSE 0 END)          AS Made,
                    CASE WHEN COUNT(*) > 0
                         THEN ROUND(CAST(SUM(CASE WHEN p.And1=1 THEN 1 ELSE 0 END) AS float)/COUNT(*)*100, 1)
                    END                                                   AS ConvPct
                FROM {$pbp} p
                WHERE p.Player = :id
                  AND p.PlayType IS NOT NULL
                  AND p.PlayType NOT IN ('IN','OUT')
                  {$filter}
                GROUP BY p.PlayType
                ORDER BY Total DESC";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Giocatori simili basati su distanza euclidea su metriche chiave.
     * Esclude il giocatore corrente, richiede min_min per filtrare sample size.
     */
    public static function player_similar(string $nation, string $year, string $db_id, string $comp = 'TOT'): array {
        $stats  = self::tbl_player_stats($nation, $year);
        $ana    = self::tbl_anagrafiche($nation, $year);
        $reg    = self::tbl_team_registry($nation, $year);
        $filter = self::comp_filter($comp, 'b');

        $sql = "
        WITH Target AS (
            SELECT a.RaptorTotal, a.LebronTotal, a.UsgPct, a.TsPct,
                   a.RebPct, a.AstPct, a.BlkPct, a.StlPct, a.ORtg, a.DRtg
            FROM {$stats} a WHERE a.Id = :id {$filter['where']}
        ),
        Distances AS (
            SELECT b.Id,
                   ABS(ISNULL(b.RaptorTotal,0) - ISNULL(t.RaptorTotal,0)) * 3 +
                   ABS(ISNULL(b.LebronTotal,0) - ISNULL(t.LebronTotal,0)) * 3 +
                   ABS(ISNULL(b.UsgPct,0)      - ISNULL(t.UsgPct,0))      * 2 +
                   ABS(ISNULL(b.TsPct,0)        - ISNULL(t.TsPct,0))      * 2 +
                   ABS(ISNULL(b.RebPct,0)       - ISNULL(t.RebPct,0))     * 1 +
                   ABS(ISNULL(b.AstPct,0)       - ISNULL(t.AstPct,0))     * 1 +
                   ABS(ISNULL(b.BlkPct,0)       - ISNULL(t.BlkPct,0))     * 1 +
                   ABS(ISNULL(b.StlPct,0)       - ISNULL(t.StlPct,0))     * 1 +
                   ABS(ISNULL(b.ORtg,0)         - ISNULL(t.ORtg,0))       * 1 +
                   ABS(ISNULL(b.DRtg,0)         - ISNULL(t.DRtg,0))       * 1  AS Dist
            FROM {$stats} b
            CROSS JOIN Target t
            WHERE b.Id <> :id2
              AND b.Min >= 100
              {$filter['where']}
        )
        SELECT TOP 6 d.Dist,
            CASE WHEN LEN(a.NormalizedPlayerName)>2 THEN a.NormalizedPlayerName ELSE a.PlayerName END AS PlayerName,
            a.TeamName, a.Pos, r.Id AS TeamId,
            b.RaptorTotal, b.LebronTotal, b.UsgPct, b.TsPct, b.NetRtg, b.Id AS DbId
        FROM Distances d
        JOIN {$stats} b ON b.Id = d.Id {$filter['where']}
        JOIN {$ana}   a ON a.Id = d.Id
        LEFT JOIN {$reg} r ON a.TeamName = r.ShortName
        ORDER BY d.Dist ASC";

        return [
            'sql'    => $sql,
            'params' => array_merge([':id' => $db_id, ':id2' => $db_id], $filter['params']),
        ];
    }

    /**
     * Ruolo avanzato del giocatore dalla tabella PlayerRoles.
     * Restituisce null se la tabella non esiste.
     */
    public static function player_role(string $nation, string $year, string $db_id, string $comp = ''): array {
        $roles    = self::tbl_player_roles($nation, $year);
        $use_comp = $comp !== '' && $comp !== 'TOT' && in_array($comp, self::COMP_SPLITS, true);

        if ($use_comp) {
            // Comp specifica richiesta — filtra esattamente, nessun fallback
            $sql = "SELECT TOP 1
                        r.RuoloOffensivo, r.RuoloDifensivo, r.RuoloCombinato,
                        r.Position, r.Min, r.GamesPlayed, r.Pts, r.Ast, r.Tr, r.Competition,
                        CASE WHEN r.GamesPlayed > 0
                             THEN CAST(r.Min AS float) / r.GamesPlayed
                             ELSE NULL
                        END AS AvgMin
                    FROM {$roles} r
                    WHERE r.Id = :id AND r.Competition = :comp";
            return ['sql' => $sql, 'params' => [':id' => $db_id, ':comp' => $comp]];
        }

        // Nessun filtro — preferisci TOT, poi RS, poi prima disponibile
        $sql = "SELECT TOP 1
                    r.RuoloOffensivo, r.RuoloDifensivo, r.RuoloCombinato,
                    r.Position, r.Min, r.GamesPlayed, r.Pts, r.Ast, r.Tr, r.Competition,
                    CASE WHEN r.GamesPlayed > 0
                         THEN CAST(r.Min AS float) / r.GamesPlayed
                         ELSE NULL
                    END AS AvgMin
                FROM {$roles} r
                WHERE r.Id = :id
                ORDER BY CASE r.Competition
                    WHEN 'TOT' THEN 0
                    WHEN 'RS'  THEN 1
                    ELSE 2
                END";
        return ['sql' => $sql, 'params' => [':id' => $db_id]];
    }

    public static function player_onoff(string $nation, string $year, string $db_id, string $comp = 'RS'): array {
        $onoff = self::tbl_onoff($nation, $year);
        // Include tutte le competizioni valide incluse CUP e SUPERCUP
        $valid_comps = ['RS','PO','CUP','SUPERCUP','TOT','Home','Away'];
        $comp_safe   = in_array($comp, $valid_comps, true) ? $comp : 'TOT';

        $sql = "SELECT TOP 1 o.*
                FROM {$onoff} o
                WHERE o.Player = :id
                ORDER BY CASE o.Competition
                  WHEN :comp THEN 0
                  WHEN 'TOT' THEN 1
                  WHEN 'RS'  THEN 2
                  ELSE 3
                END";

        return ['sql' => $sql, 'params' => [':id' => $db_id, ':comp' => $comp_safe]];
    }

    // ── Team ──────────────────────────────────────────────────────────────

    public static function team_leaderboard(string $nation, string $year, string $metric = 'NetRtg', string $comp = 'RS'): array {
        $metric = self::normalize_metric($metric, self::VALID_TEAM_METRICS);
        $team   = self::tbl_team_stats($nation, $year);
        $reg    = self::tbl_team_registry($nation, $year);
        $filter = self::comp_filter($comp, 't');

        return [
            'sql'    => "SELECT t.TeamId, r.TeamName, r.ShortName,
                                t.[{$metric}] AS metric_val,
                                t.NetRtg, t.ORtg, t.DRtg, t.Pace, t.EfgPct, t.TsPct
                         FROM {$team} t
                         LEFT JOIN {$reg} r ON t.TeamId = r.Id
                         WHERE 1=1 {$filter['where']}
                         ORDER BY t.[{$metric}] DESC",
            'params' => [],
        ];
    }

    public static function team_profile(string $nation, string $year, string $team_id, string $comp = 'RS'): array {
        $team   = self::tbl_team_stats($nation, $year);
        $reg    = self::tbl_team_registry($nation, $year);
        $filter = self::comp_filter($comp, 't');

        return [
            'sql'    => "SELECT t.*, r.TeamName, r.ShortName
                         FROM {$team} t
                         LEFT JOIN {$reg} r ON t.TeamId = r.Id
                         WHERE t.TeamId = :team_id {$filter['where']}",
            'params' => [':team_id' => $team_id],
        ];
    }

    // ── Search ────────────────────────────────────────────────────────────

    public static function search(string $nation, string $year, string $term): array {
        $ana  = self::tbl_anagrafiche($nation, $year);
        $team = self::tbl_team_registry($nation, $year);
        $like = '%' . str_replace(['%','_'], ['[%]','[_]'], trim($term)) . '%';

        $sql = "SELECT TOP 15
                    a.Id,
                    CASE WHEN LEN(a.NormalizedPlayerName)>2 THEN a.NormalizedPlayerName ELSE a.PlayerName END AS name,
                    a.TeamName AS team, r.Id AS TeamId, 'player' AS type
                FROM {$ana} a
                LEFT JOIN {$team} r ON a.TeamName = r.ShortName
                WHERE a.PlayerName LIKE ? OR a.NormalizedPlayerName LIKE ?
            UNION ALL
                SELECT TOP 5
                    Id, TeamName AS name, '' AS team, Id AS TeamId, 'team' AS type
                FROM {$team}
                WHERE TeamName LIKE ?
            ORDER BY name";

        return ['sql' => $sql, 'params' => [$like, $like, $like]];
    }

    // ── Helpers privati ───────────────────────────────────────────────────

    private static function normalize_metric(string $m, array $whitelist): string {
        if ( in_array($m, $whitelist, true) ) return $m;
        $key = strtolower(trim($m));
        if ( isset(self::METRIC_ALIASES[$key]) ) {
            $canonical = self::METRIC_ALIASES[$key];
            if ( in_array($canonical, $whitelist, true) ) return $canonical;
        }
        foreach ( $whitelist as $v ) {
            if ( strtolower($v) === $key ) return $v;
        }
        throw new InvalidArgumentException("[HoopMetrics] Metrica non valida: {$m}");
    }

    private static function guard(string $nation, string $year): void {
        if ( ! in_array(strtoupper($nation), self::VALID_NATIONS, true) )
            throw new InvalidArgumentException("[HoopMetrics] Nazione non valida: {$nation}");
        if ( ! in_array($year, self::VALID_YEARS, true) )
            throw new InvalidArgumentException("[HoopMetrics] Anno non valido: {$year}");
    }

    public static function validate_nation(string $v): void { self::guard($v, '2024'); }
    public static function validate_year(string $v): void   { self::guard('GRC', $v); }
}