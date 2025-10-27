<?php
$is_production = ($_SERVER['HTTP_HOST'] === 'kyc.verify2b.com');
$base_path = __DIR__;

if ($is_production) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u640879529_kyc');
    define('DB_PASS', '005@Fabio');
    define('DB_NAME', 'u640879529_kyc');
    define('SITE_URL', 'https://kyc.verify2b.com');
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u640879529_fcont');
    define('DB_PASS', '005@Fabio');
    define('DB_NAME', 'u640879529_kyc');
    define('SITE_URL', 'http://localhost/fdbank/teste servidor 29_10/consulta_cnpj');
}
