<?php
/**
 * HM_Query_Builder v3.0
 * Fix: PERCENT_RANK ORDER BY ASC → il giocatore migliore ha percentile 100 (non 0).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HM_Query_Builder {

    private const VALID_NATIONS = ['GRC','ITA','DEU','FRA','SPA','TUR','SRB','LTU'];
    private const VALID_YEARS   = ['2022','2023','2024','2025'];
    private const COMP_SPLITS     = ['RS','PO','CUP','SUPERCUP'];
    private const LOCATION_SPLITS = ['Home','Away'];

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
        'raptor'=>'RaptorTotal','raptortotal'=>'RaptorTotal','raptoroff'=>'RaptorOff','raptordef'=>'RaptorDef',
        'lebron'=>'LebronTotal','lebrontotal'=>'LebronTotal','lebronoff'=>'LebronOff','lebrondef'=>'LebronDef',
        'netrtg'=>'NetRtg','ortg'=>'ORtg','drtg'=>'DRtg','bpm'=>'Bpm','vorp'=>'Vorp','ws'=>'Ws',
        'usgpct'=>'UsgPct','tspct'=>'TsPct','efgpct'=>'EfgPct','gmsc'=>'GmSc','gmscore'=>'GmSc',
        'pie'=>'Pie','pace'=>'Pace','spm'=>'Spm',
    ];

    public static function tbl_anagrafiche  (string $n,string $y):string{self::guard($n,$y);return "[Anagrafiche].[{$n}_{$y}]";}
    public static function tbl_team_registry(string $n,string $y):string{self::guard($n,$y);return "[Anagrafiche].[Team_{$n}_{$y}]";}
    public static function tbl_player_stats (string $n,string $y):string{self::guard($n,$y);return "[Analisi].[AdvancedStats_Player_{$n}_{$y}]";}
    public static function tbl_player_game  (string $n,string $y):string{self::guard($n,$y);return "[Analisi].[AdvancedStats_Player_{$n}_{$y}_Game]";}
    public static function tbl_onoff        (string $n,string $y):string{self::guard($n,$y);return "[Analisi].[AdvancedStatsOnOffCourt_{$n}_{$y}]";}
    public static function tbl_team_stats   (string $n,string $y):string{self::guard($n,$y);return "[Analisi].[AdvancedStatsTeam_{$n}_{$y}]";}
    public static function tbl_player_roles (string $n,string $y):string{self::guard($n,$y);return "[Analisi].[PlayerRoles_{$n}_{$y}]";}

    public static function comp_filter(string $comp, string $alias='s'): array {
        $p = $alias !== '' ? "{$alias}." : '';
        if (in_array($comp, self::COMP_SPLITS, true))
            return ['where' => "AND {$p}Competition = '{$comp}'", 'params' => []];
        if ($comp === 'Home') return ['where' => "AND {$p}IsHome = 1", 'params' => []];
        if ($comp === 'Away') return ['where' => "AND {$p}IsHome = 0", 'params' => []];
        return ['where' => '', 'params' => []];
    }

    private static function normalize_metric(string $m, array $wl): string {
        if (in_array($m, $wl, true)) return $m;
        $key = strtolower(trim($m));
        if (isset(self::METRIC_ALIASES[$key])) { $c = self::METRIC_ALIASES[$key]; if (in_array($c,$wl,true)) return $c; }
        foreach ($wl as $v) { if (strtolower($v)===$key) return $v; }
        throw new InvalidArgumentException("[CA] Metrica non valida: {$m}");
    }
    private static function guard_metric(string $m, array $wl): string { return self::normalize_metric($m,$wl); }

    public static function leaderboard(
        string $nation,  string $year,    string $metric   = 'RaptorTotal',
        string $comp     = 'RS', int $limit = 50, int $min_min = 0,
        string $pos      = 'all', int $age_min = 0, int $age_max = 99
    ): array {
        $metric   = self::guard_metric($metric, self::VALID_PLAYER_METRICS);
        $limit    = max(1, min(200, $limit));   // alzato da 50 a 200
        $min_min  = max(0, $min_min);

        // Filtro posizione
        $pos_filter   = '';
        $pos_param    = [];
        $valid_pos    = ['PG','SG','SF','PF','C'];
        if ($pos !== 'all' && in_array(strtoupper($pos), $valid_pos, true)) {
            $pos_clean  = strtoupper($pos);
            $pos_filter = "AND a.Pos = :pos_val";
            $pos_param  = [':pos_val' => $pos_clean];
        }

        // Filtro età: Age è bigint nella tabella stats (col.49)
        $age_filter = '';
        $age_params = [];
        if ($age_min > 0 || $age_max < 99) {
            $age_filter = 'AND s.Age BETWEEN :age_min AND :age_max';
            $age_params = [':age_min' => $age_min, ':age_max' => $age_max];
        }

        $ana = self::tbl_anagrafiche($nation, $year);
        $reg = self::tbl_team_registry($nation, $year);

        // ── Branch Home/Away (dati da Game table) ────────────────────────
        if (in_array($comp, self::LOCATION_SPLITS, true)) {
            $game    = self::tbl_player_game($nation, $year);
            $is_home = $comp === 'Home' ? 1 : 0;

            // Game table non ha Age diretta → filtro età post-fetch (via PHP)
            // oppure join con ana.BirthDate → troppo costoso; lasciamo il filtro solo per RS/PO
            $sql = "SELECT g.Id AS db_id,
                CASE WHEN LEN(a.NormalizedPlayerName)>2 THEN a.NormalizedPlayerName ELSE a.PlayerName END AS PlayerName,
                a.TeamName, r.Id AS TeamId, a.Pos, a.BirthDate,
                AVG(g.Min)  AS Min, AVG(g.[{$metric}]) AS metric_val,
                AVG(g.RaptorOff) AS RaptorOff, AVG(g.RaptorDef) AS RaptorDef,
                AVG(g.RaptorTotal) AS RaptorTotal,
                AVG(g.LebronOff) AS LebronOff, AVG(g.LebronDef) AS LebronDef,
                AVG(g.LebronTotal) AS LebronTotal,
                AVG(g.NetRtg) AS NetRtg, AVG(g.ORtg) AS ORtg, AVG(g.DRtg) AS DRtg,
                AVG(g.UsgPct) AS UsgPct, AVG(g.TsPct) AS TsPct, AVG(g.EfgPct) AS EfgPct,
                AVG(g.Bpm) AS Bpm, AVG(g.Vorp) AS Vorp, AVG(g.Ws) AS Ws,
                AVG(g.GmSc) AS GmSc, AVG(g.Pie) AS Pie,
                a.Age AS Age,
                PERCENT_RANK() OVER (ORDER BY AVG(g.[{$metric}]) ASC) * 100 AS pct_rank
            FROM {$game} g
            LEFT JOIN {$ana} a ON g.Id = a.Id
            LEFT JOIN {$reg} r ON a.TeamName = r.ShortName
            WHERE g.IsHome = {$is_home}
            {$pos_filter}
            GROUP BY g.Id, a.NormalizedPlayerName, a.PlayerName,
                     a.TeamName, r.Id, a.Pos, a.BirthDate, a.Age
            HAVING AVG(g.Min) >= {$min_min}
            ORDER BY metric_val DESC
            OFFSET 0 ROWS FETCH NEXT {$limit} ROWS ONLY";

            return ['sql' => $sql, 'params' => array_merge($pos_param)];
        }

        // ── Branch RS/PO (dati da Stats table) ───────────────────────────
        $stats  = self::tbl_player_stats($nation, $year);
        $filter = self::comp_filter($comp, 's');

        $sql = "SELECT s.Id AS db_id,
            CASE WHEN LEN(a.NormalizedPlayerName)>2 THEN a.NormalizedPlayerName ELSE a.PlayerName END AS PlayerName,
            a.TeamName, r.Id AS TeamId, a.Pos, a.BirthDate,
            CAST(s.Min / NULLIF(s.Games,0) AS decimal(18,2)) AS Min,
            s.[{$metric}] AS metric_val,
            s.RaptorOff, s.RaptorDef, s.RaptorTotal,
            s.LebronOff, s.LebronDef, s.LebronTotal,
            s.NetRtg, s.ORtg, s.DRtg,
            s.UsgPct, s.TsPct, s.EfgPct,
            s.Bpm, s.Vorp, s.Spm, s.Ws,
            s.GmSc, s.Pie,
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


    public static function dashboard_kpi(string $nation,string $year,string $comp='RS'):array{
        $stats=$this_is_static=self::tbl_player_stats($nation,$year);$filter=self::comp_filter($comp,'s');
        $sql="SELECT AVG(RaptorTotal) AS avg_raptor,AVG(LebronTotal) AS avg_lebron,AVG(NetRtg) AS avg_netrtg,AVG(DRtg) AS avg_drtg,COUNT(DISTINCT Id) AS total_players FROM (SELECT TOP 50 RaptorTotal,LebronTotal,NetRtg,DRtg,Id FROM {$stats} s WHERE Min>=200 {$filter['where']} ORDER BY RaptorTotal DESC) sub";
        return ['sql'=>$sql,'params'=>[]];
    }
    public static function home_away_diff(string $nation,string $year,string $comp='RS'):array{
        $game=self::tbl_player_game($nation,$year);$filter=self::comp_filter($comp,'g');
        $sql="SELECT ROUND(AVG(CASE WHEN g.IsHome=1 THEN g.NetRtg END)-AVG(CASE WHEN g.IsHome=0 THEN g.NetRtg END),2) AS netrtg_home_away_diff,ROUND(AVG(CASE WHEN g.IsHome=1 THEN g.ORtg END)-AVG(CASE WHEN g.IsHome=0 THEN g.ORtg END),2) AS ortg_diff,ROUND(AVG(CASE WHEN g.IsHome=1 THEN g.DRtg END)-AVG(CASE WHEN g.IsHome=0 THEN g.DRtg END),2) AS drtg_diff,ROUND(AVG(CASE WHEN g.IsHome=1 THEN g.RaptorTotal END)-AVG(CASE WHEN g.IsHome=0 THEN g.RaptorTotal END),2) AS raptor_diff FROM {$game} g WHERE g.Min>=15 {$filter['where']}";
        return ['sql'=>$sql,'params'=>[]];
    }
    public static function player_all_splits(string $nation,string $year,string $db_id):array{
        $stats=self::tbl_player_stats($nation,$year);
        return ['sql'=>"SELECT Competition,Min,RaptorOff,RaptorDef,RaptorTotal,LebronOff,LebronDef,LebronTotal,NetRtg,ORtg,DRtg,UsgPct,TsPct,EfgPct,Bpm,Vorp,Ws,GmSc,Pie FROM {$stats} WHERE Id=:id ORDER BY Competition",'params'=>[':id'=>$db_id]];
    }
    public static function player_profile(string $nation,string $year,string $db_id,string $comp='RS'):array{
        $stats=self::tbl_player_stats($nation,$year);$ana=self::tbl_anagrafiche($nation,$year);
        $reg=self::tbl_team_registry($nation,$year);$roles=self::tbl_player_roles($nation,$year);
        $filter=self::comp_filter($comp,'s');
        $sql="SELECT s.*,CASE WHEN LEN(a.NormalizedPlayerName)>2 THEN a.NormalizedPlayerName ELSE a.PlayerName END AS PlayerName,a.Nat,a.BirthDate,a.Cm,a.Weight,a.ShirtNumber,a.Pos,a.TeamName,r.Id AS TeamId,rl.Role FROM {$stats} s LEFT JOIN {$ana} a ON s.Id=a.Id LEFT JOIN {$reg} r ON a.TeamName=r.ShortName LEFT JOIN {$roles} rl ON s.Id=rl.Id AND rl.Competition=s.Competition WHERE s.Id=:id {$filter['where']}";
        return ['sql'=>$sql,'params'=>[':id'=>$db_id]];
    }
    public static function player_last_games(string $nation,string $year,string $db_id,int $n=10,string $comp='RS'):array{
        $n=max(1,min(500,$n));$game=self::tbl_player_game($nation,$year);$filter=self::comp_filter($comp,'g');
        $sql="SELECT TOP {$n} g.Game,g.IsHome,g.Competition,g.Timestamp,g.Pts,g.Min,g.NetRtg,g.RaptorTotal,g.LebronTotal,g.TsPct,g.UsgPct,g.GmSc,g.PlusMinus FROM {$game} g WHERE g.Id=:id {$filter['where']} ORDER BY g.Timestamp DESC";
        return ['sql'=>$sql,'params'=>[':id'=>$db_id]];
    }
    public static function player_onoff(string $nation,string $year,string $db_id,string $comp='RS'):array{
        $onoff=self::tbl_onoff($nation,$year);$filter=self::comp_filter($comp,'o');
        return ['sql'=>"SELECT o.* FROM {$onoff} o WHERE o.Player=:id {$filter['where']}",'params'=>[':id'=>$db_id]];
    }
    public static function team_leaderboard(string $nation,string $year,string $metric='NetRtg',string $comp='RS'):array{
        $metric=self::guard_metric($metric,self::VALID_TEAM_METRICS);$team=self::tbl_team_stats($nation,$year);$reg=self::tbl_team_registry($nation,$year);$filter=self::comp_filter($comp,'t');
        return ['sql'=>"SELECT t.TeamId,r.TeamName,r.ShortName,t.[{$metric}] AS metric_val,t.NetRtg,t.ORtg,t.DRtg,t.Pace,t.EfgPct,t.TsPct FROM {$team} t LEFT JOIN {$reg} r ON t.TeamId=r.Id WHERE 1=1 {$filter['where']} ORDER BY t.[{$metric}] DESC",'params'=>[]];
    }
    public static function team_profile(string $nation,string $year,string $team_id,string $comp='RS'):array{
        $team=self::tbl_team_stats($nation,$year);$reg=self::tbl_team_registry($nation,$year);$filter=self::comp_filter($comp,'t');
        return ['sql'=>"SELECT t.*,r.TeamName,r.ShortName FROM {$team} t LEFT JOIN {$reg} r ON t.TeamId=r.Id WHERE t.TeamId=:team_id {$filter['where']}",'params'=>[':team_id'=>$team_id]];
    }
    public static function search(string $nation,string $year,string $term):array{
        $ana=self::tbl_anagrafiche($nation,$year);$team=self::tbl_team_registry($nation,$year);
        $like='%'.str_replace(['%','_'],['[%]','[_]'],trim($term)).'%';
        $sql="SELECT TOP 15 a.Id,CASE WHEN LEN(a.NormalizedPlayerName)>2 THEN a.NormalizedPlayerName ELSE a.PlayerName END AS name,a.TeamName AS team,r.Id AS TeamId,'player' AS type FROM {$ana} a LEFT JOIN {$team} r ON a.TeamName=r.ShortName WHERE a.PlayerName LIKE ? OR a.NormalizedPlayerName LIKE ? UNION ALL SELECT TOP 5 Id,TeamName AS name,'' AS team,Id AS TeamId,'team' AS type FROM {$team} WHERE TeamName LIKE ? ORDER BY name";
        return ['sql'=>$sql,'params'=>[$like,$like,$like]];
    }
    private static function guard(string $nation,string $year):void{
        if(!in_array(strtoupper($nation),self::VALID_NATIONS,true))throw new InvalidArgumentException("[CA] Nazione non valida: {$nation}");
        if(!in_array($year,self::VALID_YEARS,true))throw new InvalidArgumentException("[CA] Anno non valido: {$year}");
    }
    public static function validate_nation(string $v):void{self::guard($v,'2024');}
    public static function validate_year(string $v):void{self::guard('GRC',$v);}
}
