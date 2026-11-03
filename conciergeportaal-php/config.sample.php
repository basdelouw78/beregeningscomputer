<?php
// Voorbeeldconfiguratie. Het installatiescherm (install.php) maakt config.php automatisch aan.
// Je kunt dit bestand ook zelf kopiëren naar config.php en invullen.
return [
    'db_host'     => 'localhost',     // bij Strato bijv. rdbms.strato.de
    'db_name'     => '',              // bijv. dbs1234567
    'db_user'     => '',              // bijv. dbu1234567
    'db_pass'     => '',
    'db_prefix'   => 'cp_',           // voorvoegsel voor de tabellen
    'register_code' => '',            // leeg = iedereen kan zich registreren
    'timezone'    => 'Europe/Amsterdam',
];
