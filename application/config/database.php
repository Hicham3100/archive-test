<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Database Connectivity Settings
| -------------------------------------------------------------------------
| This file will contain the settings needed to access your database.
|
| SEC-04 : Les credentials sont lus depuis le fichier .env via getenv().
| Ne jamais mettre des identifiants en dur dans ce fichier.
*/

$active_group = 'default';
$query_builder = TRUE;

$db['default'] = array(
    'dsn'      => '',
    'hostname' => getenv('DB_HOSTNAME') ?: 'localhost',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'database' => getenv('DB_DATABASE') ?: 'id_antique',
    'dbdriver' => 'mysqli',
    'dbprefix' => '',
    'pconnect' => FALSE,
    'db_debug' => (ENVIRONMENT !== 'production'),
    'cache_on' => FALSE,
    'cachedir' => '',
    'char_set' => 'utf8mb4',           // DB-01 — Support Unicode complet
    'dbcollat' => 'utf8mb4_unicode_ci', // DB-01 — Collation Unicode précise
    'swap_pre' => '',
    'encrypt'  => FALSE,
    'compress' => FALSE,
    'stricton' => FALSE,
    'failover' => array(),
    'save_queries'      => (ENVIRONMENT === 'development'),
    'store_query_tries' => FALSE
);

/* End of file database.php */
/* Location: ./application/config/database.php */