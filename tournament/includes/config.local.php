<?php
/**
 * TEMPLATE — copy this file to config.local.php (same folder) and fill
 * in the real values for THIS machine.
 *
 * config.local.php is listed in .gitignore, so it is never committed,
 * never pushed, and never pulled/overwritten on your live hosting. Your
 * PC (Laragon) and your live cPanel server each keep their own
 * config.local.php with their own real values, forever separate from
 * git.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'kvannl_zebra');
define('DB_USER', 'root');   // <-- set to your cPanel MySQL user
define('DB_PASS', 'x');          // <-- set to that user's password
