# HoopMetrics API Plugin v1.0.0

Plugin WordPress per la connessione sicura ad Azure SQL e la gestione
delle REST API del tema HoopMetrics.

## Struttura file
```
hoopmetrics-api/
├── hoopmetrics-api.php          # Main plugin file
├── includes/
│   ├── class-db.php             # Connessione PDO Azure SQL (singleton)
│   ├── class-anonymizer.php     # Hash SHA256 ID + IdGlobal bridge
│   ├── class-query-builder.php  # Builder nomi tabelle + whitelist validazione
│   ├── class-players.php        # Profilo giocatore (tutti gli split)
│   ├── class-teams.php          # Profilo squadra + roster
│   ├── class-leaderboard.php    # Classifiche + KPI + PERCENT_RANK
│   ├── class-oncourt.php        # Statistiche OnCourt
│   ├── class-search.php         # Ricerca full-text giocatori/squadre
│   └── class-rest-api.php       # register_rest_route() + permission_callback
└── README.md
```

## Installazione

### 1. wp-config.php (OBBLIGATORIO)
```php
// Credenziali Azure SQL — MAI committare nel VCS
define('HM_DB_SERVER', 'yourserver.database.windows.net');
define('HM_DB_NAME',   'HoopMetrics');
define('HM_DB_USER',   'username');
define('HM_DB_PASS',   'password');

// Salt per anonimizzazione ID — genera con wp_generate_password(64, true, true)
define('HM_ID_SALT',   'genera-una-stringa-casuale-qui-lunga-almeno-64-caratteri!!');
```

### 2. PHP Extension
Il server deve avere installata una di:
- `pdo_sqlsrv` (raccomandato — Microsoft PHP Driver per SQL Server)
- `pdo_odbc` con ODBC Driver 18 for SQL Server

Verifica con: `php -m | grep -i sqlsrv`

### 3. Attivazione
Carica `hoopmetrics-api/` in `wp-content/plugins/` e attiva da WP Admin.

## Endpoint REST

| Metodo | Endpoint | Parametri |
|--------|----------|-----------|
| GET | `/wp-json/hoopmetrics/v1/dashboard/kpis` | nation, season, comp |
| GET | `/wp-json/hoopmetrics/v1/leaderboard` | nation, season, comp, metric, limit |
| GET | `/wp-json/hoopmetrics/v1/player/{hm_xxx}` | nation, season, comp |
| GET | `/wp-json/hoopmetrics/v1/player/global/{hmg_xxx}` | nation, season |
| GET | `/wp-json/hoopmetrics/v1/teams` | nation, season, comp |
| GET | `/wp-json/hoopmetrics/v1/team/{hm_xxx}` | nation, season, comp |
| GET | `/wp-json/hoopmetrics/v1/oncourt` | nation, season, limit |
| GET | `/wp-json/hoopmetrics/v1/search` | q, nation, season, limit |
| GET | `/wp-json/hoopmetrics/v1/dashboard/summary` | — |

**Tutti gli endpoint richiedono l'header `X-WP-Nonce` generato da `wp_create_nonce('wp_rest')`.**

## Sicurezza

### ID Anonimizzazione
- `hm_xxx` = `'hm_' + SHA256(HM_ID_SALT + entity + '|' + nation + '|' + season + '|' + db_id)[:12]`
- `hmg_xxx` = hash basato su `IdGlobal` per collegare lo stesso giocatore tra nazioni/stagioni
- La mappa inversa è in WP transient (TTL 1h), mai esposta via API
- I db_id reali non compaiono mai in output JSON

### SQL Injection
- I nomi tabella sono costruiti da `HM_QueryBuilder` che valida su whitelist costanti
- Tutti i valori parametrici usano `PDO::prepare()` con placeholder nominali
- Nessun input dell'utente raggiunge mai una query non sanitizzata

### Nonce
- Ogni richiesta verifica `wp_verify_nonce($nonce, 'wp_rest')` in `check_nonce()`
- Il nonce viene generato da `wp_localize_script` nel tema e incluso nell'header JS

## IdGlobal — Cross-nation player linking
Quando `IdGlobal` è popolato in `Anagrafiche.{NATION}_{SEASON}`, il plugin:
1. Genera un `hmg_xxx` stabile per quel giocatore
2. Lo restituisce come `public_global` in tutti gli endpoint player
3. L'endpoint `/player/global/{hmg_xxx}` aggrega il profilo del giocatore
   attraverso tutte le nazioni/stagioni dove IdGlobal corrisponde
4. Se `IdGlobal` è NULL, `public_global` è null — nessun errore
