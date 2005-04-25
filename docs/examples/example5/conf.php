<?php
require_once 'DB.php';
require_once 'LiveUser.php';
// Plase configure the following file according to your environment

//$dsn = '{dbtype}://{user}:{passwd}@{dbhost}/{dbname}';

$dsn = 'mysql://root:@localhost/pear_test';

$db = DB::connect($dsn);

if (DB::isError($db)) {
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
            'method'   => 'post',
            'username' => 'handle',
            'password' => 'passwd',
            'force'    => false,
            'function' => '',
            'remember' => 'rememberMe'
        ),
        'logout' => array(
            'trigger'  => 'logout',
            'redirect' => 'home.php',
            'destroy'  => true,
            'method' => 'get',
            'function' => ''
        ),
        'authContainers' => array('DB' =>
            array(
                'type'          => 'DB',
                'loginTimeout'  => 0,
                'expireTime'    => 3600,
                'idleTime'      => 1800,
                'dsn'           => $dsn,
                'allowDuplicateHandles' => 0,
                'authTable'     => 'liveuser_users',
                'authTableCols' => array(
                    'required' => array(
                        'auth_user_id' => array('name' => 'auth_user_id', 'type' => ''),
                        'handle'       => array('name' => 'handle',       'type' => ''),
                        'passwd'       => array('name' => 'passwd',       'type' => ''),
                    ),
                    'optional' => array(
                        'lastlogin'    => array('name' => 'lastlogin',    'type' => ''),
                        'is_active'    => array('name' => 'is_active',    'type' => '')
                    )
                )
            )
        ),
        'permContainer' => array(
            'dsn'        => $dsn,
            'type'       => 'DB_Medium',
            'prefix'     => 'liveuser_'
        )
    );

function logOut()
{
}

function logIn()
{
}

PEAR::setErrorHandling(PEAR_ERROR_RETURN);

$usr = LiveUser::singleton($conf);
$usr->setLoginFunction('logIn');
$usr->setLogOutFunction('logOut');

$e = $usr->init();

if (PEAR::isError($e)) {
//var_dump($usr);
    die($e->getMessage() . ' ' . $e->getUserinfo());
}
