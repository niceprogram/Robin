<?php
/**
 * Database & tournament configuration.
 *
 * This points at a shared-hosting database (kvannl_Zebra) where every
 * table is prefixed with tournament_ (see sql/schema.sql). DB_USER and
 * DB_PASS must be filled in with the MySQL user cPanel created/attached
 * to this database (Databases > MySQL Databases in cPanel) — it is
 * usually NOT the same as your cPanel login, and it is never "root" on
 * shared hosting.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'kvannl_Zebra');
define('DB_USER', 'kvannl_CHANGE_ME');   // <-- set to your cPanel MySQL user
define('DB_PASS', 'CHANGE_ME');          // <-- set to that user's password

// Every table in this project lives in the shared database above with
// this prefix, e.g. tournament_players, tournament_matches, etc.
define('DB_TABLE_PREFIX', 'tournament_');

// Default length of a single round, in seconds (7 minutes).
define('DEFAULT_ROUND_SECONDS', 420);

// Hard safety cap on simultaneous matches (we only have 10 physical stations).
define('STATIONS_MAX', 10);

// Show PHP errors during setup/development. Set to 0 for a live event.
ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Europe/Amsterdam');
