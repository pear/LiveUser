<?php
require_once 'MDB2.php';
require_once 'LiveUser.php';
// Plase configure the following file according to your environment

//$dsn = '{dbtype}://{user}:{passwd}@{dbhost}/{dbname}';
$dsn = 'mysql://root:@localhost/liveuser_test_example5';

$db = MDB2::connect($dsn);

if (PEAR::isError($db)) {
    echo $db->getMessage() . ' ' . $db->getUserInfo();
}

$db->setFetchMode(DB_FETCHMODE_ASSOC);


$conf =
    array(
        'autoInit' => false,
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
                'type'          => 'MDB2',
                'loginTimeout'  => 0,
                'expireTime'    => 3600,
                'idleTime'      => 1800,
                'allowDuplicateHandles' => 0,
                'storage' => array(
                    'dsn' => $dsn,
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
        'storage' => array('MDB2' => array('dsn' => $dsn, 'prefix' => 'liveuser_')),
    ),
);

PEAR::setErrorHandling(PEAR_ERROR_RETURN);

$usr = LiveUser::singleton($conf);

$username = (isset($_REQUEST['username'])) ? $_REQUEST['username'] : null;
$password = (isset($_REQUEST['password'])) ? $_REQUEST['password'] : null;
$logout = (isset($_REQUEST['logout'])) ? $_REQUEST['logout'] : false;
$remember = (isset($_REQUEST['rememberMe'])) ? $_REQUEST['rememberMe'] : false;

if (!$usr->init($username, $password, $logout, $remember)) {
    var_dump($usr->getErrors());
    die();
}
