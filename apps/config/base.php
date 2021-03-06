<?

ini_set('memory_limit', '512M');
ini_set('max_execution_time', 5);
mb_internal_encoding('UTF-8');

$settings = [
    'config' => [
        'domain' => 'orderist.smdmitry.com',
        'static' => 'orderist.smd.im',
        'mail' => [
            'email' => 'noreply@smd.im',
            'login' => 'noreply@smd.im',
            'password' => 'password',
            'smtp' => 'smtp.yandex.ru',
        ],
    ],
    'database' => [
        'host' => 'localhost',
        'username' => 'orderist',
        'password' => 'password',
        'dbname' => 'orderist',
        'charset' => 'utf8',
        'options' => [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
            PDO::ATTR_CASE => PDO::CASE_LOWER,
        ],
    ],
    'debug' => !empty($_COOKIE['debug']) ? true : false,
];

if (file_exists('../apps/config/override.php')) {
    include 'override.php';
}
@define('DEBUG', $settings['debug']);

if (DEBUG) {
    error_reporting(E_ALL ^ E_STRICT);
    ini_set('display_errors', 1);

    @define('FIRELOGGER_NO_VERSION_CHECK', true);
    @define('FIRELOGGER_NO_ERROR_HANDLER', true);
}