<?php
/**
 * HM_DB v1.5
 * Fix: aggiunto instance() alias di get(), aggiunto query_val().
 * Rimosso diagnose() pubblico (dati sensibili esposti). Usa HM_Admin per la diagnostica.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HM_DB {

    private static ?PDO $pdo = null;

    /**
     * Restituisce l'istanza PDO (singleton).
     * Lancia RuntimeException se la connessione fallisce.
     */
    public static function get(): PDO {
        if ( self::$pdo !== null ) return self::$pdo;

        if ( ! extension_loaded('pdo_sqlsrv') ) {
            $loaded = implode( ', ', array_filter(
                get_loaded_extensions(),
                fn($e) => stripos($e,'sql') !== false || stripos($e,'pdo') !== false
            ));
            error_log( "[HoopMetrics] pdo_sqlsrv non caricata. Estensioni trovate: {$loaded}" );
            throw new RuntimeException( 'Estensione pdo_sqlsrv non caricata sul server.' );
        }

        foreach ( ['HM_DB_SERVER','HM_DB_NAME','HM_DB_USER','HM_DB_PASS'] as $c ) {
            if ( ! defined($c) || empty( constant($c) ) ) {
                error_log( "[HoopMetrics] Costante {$c} non definita in wp-config.php" );
                throw new RuntimeException( "Costante {$c} mancante in wp-config.php" );
            }
        }

        $encrypt = defined('HM_DB_ENCRYPT')    ? HM_DB_ENCRYPT    : 'yes';
        $trust   = defined('HM_DB_TRUST_CERT') ? HM_DB_TRUST_CERT : 'no';

        $dsn = "sqlsrv:server=" . HM_DB_SERVER
             . ";Database="     . HM_DB_NAME
             . ";Encrypt={$encrypt}"
             . ";TrustServerCertificate={$trust}"
             . ";LoginTimeout=30";

        try {
            self::$pdo = new PDO(
                $dsn, HM_DB_USER, HM_DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            error_log( '[HoopMetrics] Connessione Azure SQL OK ✅' );
        } catch ( PDOException $e ) {
            error_log( '[HoopMetrics] PDO FAILED [' . $e->getCode() . ']: ' . $e->getMessage() );
            throw new RuntimeException( 'Connessione al database fallita.' );
        }

        return self::$pdo;
    }

    /**
     * Alias di get() — compatibilità con codice che chiama instance().
     */
    public static function instance(): PDO {
        return self::get();
    }

    /**
     * Esegue una query e restituisce tutte le righe.
     *
     * @throws RuntimeException su errore SQL
     */
    public static function query( string $sql, array $params = [] ): array {
        try {
            $stmt = self::get()->prepare( $sql );
            $stmt->execute( $params );
            return $stmt->fetchAll();
        } catch ( PDOException $e ) {
            error_log(
                '[HoopMetrics] Query error: ' . $e->getMessage()
                . ' | SQL: ' . substr( $sql, 0, 300 )
            );
            throw new RuntimeException( 'Errore nella query al database.' );
        }
    }

    /**
     * Esegue una query e restituisce solo la prima riga, o null se vuota.
     */
    public static function query_row( string $sql, array $params = [] ): ?array {
        $rows = self::query( $sql, $params );
        return $rows[0] ?? null;
    }

    /**
     * Esegue una query e restituisce il valore della prima colonna della prima riga.
     * Utile per SELECT COUNT(*), SELECT @@VERSION, ecc.
     */
    public static function query_val( string $sql, array $params = [] ): mixed {
        $row = self::query_row( $sql, $params );
        if ( $row === null ) return null;
        return reset( $row ); // primo valore della riga
    }

    /**
     * Informazioni diagnostiche SICURE (senza credenziali).
     * Usato solo da HM_Admin — non esporre via REST.
     *
     * @internal
     */
    public static function diagnose_safe(): array {
        $has_driver  = extension_loaded('pdo_sqlsrv') || extension_loaded('pdo_odbc');
        $conn_ok     = false;
        $conn_msg    = 'Non testato';

        if ( $has_driver && defined('HM_DB_SERVER') ) {
            try {
                self::get();
                $ver      = self::query_val("SELECT @@VERSION");
                $conn_ok  = true;
                $conn_msg = substr( $ver ?? '', 0, 80 ) . '…';
            } catch ( \Exception $e ) {
                $conn_msg = $e->getMessage();
            }
        }

        return [
            'php_version'    => phpversion(),
            'pdo_sqlsrv'     => extension_loaded('pdo_sqlsrv'),
            'pdo_odbc'       => extension_loaded('pdo_odbc'),
            'has_driver'     => $has_driver,
            'HM_DB_SERVER'   => defined('HM_DB_SERVER') ? '✅ definita' : '❌ mancante',
            'HM_DB_NAME'     => defined('HM_DB_NAME')   ? '✅ definita' : '❌ mancante',
            'HM_DB_USER'     => defined('HM_DB_USER')   ? '✅ definita' : '❌ mancante',
            'HM_DB_PASS'     => defined('HM_DB_PASS')
                                    ? '✅ definita (' . strlen(HM_DB_PASS) . ' chars)'
                                    : '❌ mancante',
            'HM_ID_SALT'     => defined('HM_ID_SALT')
                                    ? '✅ definita (' . strlen(HM_ID_SALT) . ' chars)'
                                    : '❌ mancante',
            'connection_ok'  => $conn_ok,
            'connection_msg' => $conn_msg,
        ];
    }
}