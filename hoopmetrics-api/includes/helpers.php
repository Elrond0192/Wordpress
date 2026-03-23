<?php
/**
 * HoopMetrics – helpers.php
 * Funzioni globali usate da leaderboard, players, teams, search.
 *
 * NOTA SUL PREFISSO ID:
 * Tutta la generazione di ID pubblici passa per HM_Anonymizer::public_id()
 * che produce hash con prefisso 'hm_'. La funzione hm_public_id() qui sotto
 * è un thin wrapper per compatibilità con il codice esistente: non duplica
 * la logica di hashing, la delega ad HM_Anonymizer.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Calcola l'età da BirthDate nel formato "d-m-yyyy" (es. "23-5-2000").
 *
 * @param string|null $birthdate  Es. "23-5-2000" oppure "3-11-1998"
 * @return int|null  Età in anni, null se non parsabile
 */
function hm_age_from_birthdate( ?string $birthdate ): ?int {
    if ( ! $birthdate || trim($birthdate) === '' ) return null;

    // Formato primario: giorno-mese-anno senza zero padding (j-n-Y)
    $dt = DateTime::createFromFormat( 'j-n-Y', trim($birthdate) );
    if ( ! $dt ) {
        // Fallback: con zero padding (dd-mm-yyyy)
        $dt = DateTime::createFromFormat( 'd-m-Y', trim($birthdate) );
    }
    if ( ! $dt ) return null;

    return (int) $dt->diff( new DateTime('today') )->y;
}

/**
 * Genera public_id anonimizzato delegando ad HM_Anonymizer.
 * Garantisce che un solo algoritmo e un solo prefisso ('hm_') siano usati
 * in tutto il codebase, eliminando l'inconsistenza con il vecchio prefisso 'ca_'.
 *
 * @param string $db_id   ID reale del database
 * @param string $nation  Es. 'GRC'
 * @param string $year    Es. '2024'
 * @param string $type    'player' | 'team'
 * @return string         Es. 'hm_a3f8c2d14b9e'
 *
 * @throws RuntimeException se HM_ID_SALT non è definito
 */
function hm_public_id( string $db_id, string $nation, string $year, string $type = 'player' ): string {
    if ( ! class_exists('HM_Anonymizer') ) {
        // Fallback di emergenza se HM_Anonymizer non è ancora stato caricato
        if ( ! defined('HM_ID_SALT') ) {
            throw new RuntimeException('[HoopMetrics] HM_ID_SALT non definito in wp-config.php');
        }
        $hash = hash( 'sha256', HM_ID_SALT . $type . '|' . $nation . '|' . $year . '|' . $db_id );
        return 'hm_' . substr( $hash, 0, 12 );
    }
    return HM_Anonymizer::public_id( $db_id, $nation, $year, $type );
}

/**
 * Scansiona il database Azure SQL per trovare le combinazioni nazione/stagione
 * disponibili leggendo i nomi delle tabelle in [Anagrafiche].
 * Salva i risultati in due WP options:
 *   - hm_available_nations  (es. ['GRC','ITA'])
 *   - hm_available_seasons  (es. ['2023','2024'])
 *
 * Viene chiamata:
 *  - Manualmente dalla pagina admin "Configurazione"
 *  - Automaticamente a ogni attivazione del plugin
 *  - (opzionale) da un cron giornaliero
 *
 * Non lancia eccezioni: in caso di errore logga e ritorna silenziosamente.
 */
function hm_api_refresh_meta(): void {
    if ( ! class_exists('HM_DB') ) {
        error_log('[HoopMetrics] hm_api_refresh_meta: HM_DB non disponibile.');
        return;
    }

    try {
        // Legge i nomi delle tabelle nello schema Anagrafiche.
        // La convenzione è: [Anagrafiche].[{NATION}_{YEAR}]
        // dove NATION = 3 lettere maiuscole, YEAR = 4 cifre.
        $rows = HM_DB::query(
            "SELECT TABLE_NAME
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = 'Anagrafiche'
               AND TABLE_NAME NOT LIKE 'Team_%'
             ORDER BY TABLE_NAME"
        );

        $nations = [];
        $seasons = [];

        foreach ( $rows as $row ) {
            $name  = $row['TABLE_NAME'] ?? '';
            // Formato atteso: GRC_2024, ITA_2023, ecc.
            if ( preg_match('/^([A-Z]{2,4})_(\d{4})$/', $name, $m) ) {
                $nations[] = $m[1];
                $seasons[]  = $m[2];
            }
        }

        $nations = array_values( array_unique($nations) );
        $seasons = array_values( array_unique($seasons) );
        sort($nations);
        rsort($seasons); // stagioni più recenti prima

        update_option('hm_available_nations', $nations, false);
        update_option('hm_available_seasons', $seasons, false);

        error_log(
            '[HoopMetrics] hm_api_refresh_meta OK — nazioni: ' . implode(',', $nations)
            . ' | stagioni: ' . implode(',', $seasons)
        );

    } catch ( \Exception $e ) {
        error_log('[HoopMetrics] hm_api_refresh_meta errore: ' . $e->getMessage());
    }
}