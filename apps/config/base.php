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
    ]
];

if (file_exists('../apps/config/override.php')) {
    include 'override.php';
}