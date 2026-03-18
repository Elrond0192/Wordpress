<?php
/**
 * HM_DB v1.4 – HM_DB_PASS + metodo diagnose() per debug.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HM_DB {

    private static ?PDO $pdo = null;

    /** Restituisce array diagnostico senza tentare la connessione */
    public static function diagnose(): array {
        return [
            'php_version'      => phpversion(),
            'pdo_sqlsrv'       => extension_loaded('pdo_sqlsrv'),
            'sqlsrv'           => extension_loaded('sqlsrv'),
            'loaded_extensions'=> array_values(array_filter(
                get_loaded_extensions(),
                fn($e) => stripos($e,'sql') !== false || stripos($e,'pdo') !== false
            )),
            'HM_DB_SERVER'  => defined('HM_DB_SERVER')  ? HM_DB_SERVER  : '❌ NON DEFINITA',
            'HM_DB_NAME'    => defined('HM_DB_NAME')    ? HM_DB_NAME    : '❌ NON DEFINITA',
            'HM_DB_USER'    => defined('HM_DB_USER')    ? HM_DB_USER    : '❌ NON DEFINITA',
            'HM_DB_PASS'    => defined('HM_DB_PASS')    ? '*** (' . strlen(HM_DB_PASS) . ' chars)' : '❌ NON DEFINITA',
            'HM_DB_TRUST_CERT' => defined('HM_DB_TRUST_CERT') ? HM_DB_TRUST_CERT : '(default: no)',
            'HM_ID_SALT'    => defined('HM_ID_SALT')    ? '*** (' . strlen(HM_ID_SALT) . ' chars)' : '❌ NON DEFINITA',
        ];
    }

    public static function get(): PDO {
        if ( self::$pdo !== null ) return self::$pdo;

        if ( ! extension_loaded('pdo_sqlsrv') ) {
            $msg = '[HoopMetrics] pdo_sqlsrv non caricata. '
                 . 'Extensions caricate: ' . implode(', ', get_loaded_extensions());
            error_log($msg);
            throw new RuntimeException('Estensione pdo_sqlsrv non caricata.');
        }

        foreach ( ['HM_DB_SERVER','HM_DB_NAME','HM_DB_USER','HM_DB_PASS'] as $c ) {
            if ( ! defined($c) || empty(constant($c)) ) {
                $msg = "[HoopMetrics] Costante {$c} non definita in wp-config.php";
                error_log($msg);
                throw new RuntimeException("Costante {$c} mancante in wp-config.php");
            }
        }

        $encrypt = defined('HM_DB_ENCRYPT')    ? HM_DB_ENCRYPT    : 'yes';
        $trust   = defined('HM_DB_TRUST_CERT') ? HM_DB_TRUST_CERT : 'no';

        $dsn = "sqlsrv:server=" . HM_DB_SERVER
             . ";Database="     . HM_DB_NAME
             . ";Encrypt={$encrypt}"
             . ";TrustServerCertificate={$trust}"
             . ";LoginTimeout=30";

        error_log("[HoopMetrics] DSN: {$dsn}");
        error_log("[HoopMetrics] USER: " . HM_DB_USER);

        try {
            self::$pdo = new PDO(
                $dsn, HM_DB_USER, HM_DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            error_log('[HoopMetrics] Connessione Azure SQL OK ✅');
        } catch ( PDOException $e ) {
            error_log('[HoopMetrics] PDO FAILED SQLSTATE[' . $e->getCode() . ']: ' . $e->getMessage());
            throw new RuntimeException('PDO: ' . $e->getMessage());
        }

        return self::$pdo;
    }

    public static function query( string $sql, array $params = [] ): array {
        try {
            $stmt = self::get()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch ( PDOException $e ) {
            error_log('[HoopMetrics] Query error: ' . $e->getMessage()
                . ' | SQL: ' . substr($sql, 0, 300));
            throw new RuntimeException($e->getMessage());
        }
    }

    public static function query_row( string $sql, array $params = [] ): ?array {
        $rows = self::query($sql, $params);
        return $rows[0] ?? null;
    }
}
