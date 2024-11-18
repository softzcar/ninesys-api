<?php

/**
 *
 * CONFIGURACION DELS SITEMA
 *
 */

/** Woocommerce ninesys */
define("BASE_URL", 'https://tiendademo.nineteencustom.com/wp-json/');

// define("WC_CK",'ck_24e3f33eac53e7b7139a5b260360d966726038a6');
// define("WC_CS",'cs_a79cc6b9117167d2704e6f33b18c7e19afa4f337');

// Nueva valve
define("WC_CK", 'ck_ec5fc4895b5a80f14a1ab0e729e671b6a23716de');
define("WC_CS", 'cs_f5150117f34e0d91cf0a48a0d064cc426990fd21');

/** Local API */
define("LOCAL_API", "http://ninesys.ddns.net/");

/** Ping DIR */
define("PING_URL", "nineteencustom.com");

// define("LOCAL_DSN", 'mysql:host=localhost;dbname=app'); // ninesys DESARROLLO
define("LOCAL_DSN", 'mysql:host=localhost;dbname=admindata'); // ninesys PRUEBAS RICARDO
define("LOCAL_DSN_NINETEEN", 'mysql:host=localhost;dbname=nineteengreen'); // ninesys TEST
define("LOCAL_DSN_COPY", 'mysql:host=localhost;dbname=api_copy'); // ninesys COPY
define("LOCAL_DSN_COPY_HISTORY", 'mysql:host=localhost;dbname=api_copy_history'); // ninesys COPY HISTORY
define("LOCAL_USER", 'nineteengreen');
define("LOCAL_PASS", 'Ninesys@2024');


/** Localhost PDO Data Connection */
// define("LOCAL_DSN", 'mysql:host=localhost;dbname=appzone_ninesysapi2'); // ninesys TEST
/*define("LOCAL_DSN", 'mysql:host=localhost;dbname=appzone_ninesysapifull2'); // ninesys TEST
define("LOCAL_DSN_NINETEEN", 'mysql:host=localhost;dbname=appzone_wcninesys'); // ninesys TEST
define("LOCAL_DSN_COPY", 'mysql:host=localhost;dbname=api_copy'); // ninesys COPY
define("LOCAL_DSN_COPY_HISTORY", 'mysql:host=localhost;dbname=api_copy_history'); // ninesys COPY HISTORY
define("LOCAL_USER", 'appzone_ninesys');
define("LOCAL_PASS", 'Ninesys25.'); */

// MOCHA Connection
// define("LOCAL_DSN", 'mysql:host=mocha3036.mochahost.com;dbname=appzone_ninesys_test'); // ninesys TEST
// define("LOCAL_USER", 'appzone_nine_tester');
// define("LOCAL_PASS", '5105_sql_nine');
