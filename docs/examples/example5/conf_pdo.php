<?php
require_once 'MDB2.php';
require_once 'LiveUser.php';
// Plase configure the following file according to your environment

$dsn = 'mysql:host=localhost;dbname=ex5';

$dsn_2 = 'mysqli://root:@localhost/ex5';

$db = MDB2::connect($dsn_2);

if (PEAR::isError($db)) {
    echo $db->getMessage() . ' ' . $db->getUserInfo();
}

$db->setFetchMode(MDB2_FETCHMODE_ASSOC);

$conf =
    array(
        'debug' => true,
        'session'  => array(
            'name'     => 'PHPSESSION',
            'varname'  => 'ludata'
        ),
        'login' => array(
            'force'    => false,
        ),
        'logout' => array(
            'destroy'  => true,
        ),
        'authContainers' => array(
            'DB' => array(
                'type'          => 'PDO',
                'expireTime'    => 3600,
                'idleTime'      => 1800,
                'allowDuplicateHandles' => 0,
                'storage' => array(
                    'dsn' => $dsn,
                    'options' => array(
                        'user'   => 'root',
                        'passwd' => ''
                    ),
                    'alias' => array(
                        'lastlogin' => 'lastlogin',
                        'is_active' => 'is_active',
                    ),
                    'fields' => array(
                        'lastlogin' => 'timestamp',
                        'is_active' => 'boolean',
                    ),
                    'tables' => array(
                        'users' => array(
                            'fields' => array(
                                'lastlogin' => false,
                                'is_active' => false,
                            ),
                        ),
                    ),
                )
            )
        ),
    'permContainer' => array(
        'type' => 'Medium',
        'storage' => array(
            'PDO' => array(
                'dsn'    => $dsn,
                'prefix' => 'liveuser_',
                'options' => array(
                    'user'   => 'root',
                    'passwd' => ''
                )
             )
        ),
    ),
);

PEAR::setErrorHandling(PEAR_ERROR_RETURN);

$usr = LiveUser::singleton($conf);

$handle = (array_key_exists('handle', $_REQUEST)) ? $_REQUEST['handle'] : null;
$passwd = (array_key_exists('passwd', $_REQUEST)) ? $_REQUEST['passwd'] : null;
$logout = (array_key_exists('logout', $_REQUEST)) ? $_REQUEST['logout'] : false;
$remember = (array_key_exists('rememberMe', $_REQUEST)) ? $_REQUEST['rememberMe'] : false;

if (!$usr->init($handle, $passwd, $logout, $remember)) {
    var_dump($usr->getErrors());
    die();
}