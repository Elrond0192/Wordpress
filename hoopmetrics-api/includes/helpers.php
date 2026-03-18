<?php
/**
 * HoopMetrics – helpers.php
 * Funzioni globali usate da leaderboard, players, teams.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Calcola l'età da BirthDate nel formato "d-m-yyyy" (es. "23-5-2000").
 * Age non esiste come colonna nel DB → calcolato in PHP.
 *
 * @param string|null $birthdate  Es. "23-5-2000" oppure "3-11-1998"
 * @return int|null  Età in anni, null se non parsabile
 */
function hm_age_from_birthdate( ?string $birthdate ): ?int {
    if ( ! $birthdate || trim($birthdate) === '' ) return null;
    // Formato: giorno-mese-anno senza zero padding (j-n-Y in PHP)
    $dt = DateTime::createFromFormat( 'j-n-Y', trim($birthdate) );
    if ( ! $dt ) {
        // Fallback: prova con zero padding (dd-mm-yyyy)
        $dt = DateTime::createFromFormat( 'd-m-Y', trim($birthdate) );
    }
    if ( ! $dt ) return null;
    return (int) $dt->diff( new DateTime('today') )->y;
}

/**
 * Genera public_id anonimizzato da db_id reale.
 * Usato per mascherare gli ID del DB nel frontend.
 */
function hm_public_id( string $db_id, string $nation, string $year, string $type = 'player' ): string {
    if ( ! defined('HM_ID_SALT') ) {
        throw new RuntimeException('[CA] HM_ID_SALT non definito in wp-config.php');
    }
    $hash = hash( 'sha256', HM_ID_SALT . $type . '|' . $nation . '|' . $year . '|' . $db_id );
    return 'ca_' . substr( $hash, 0, 14 );
}
