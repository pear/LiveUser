<?php
// BC hack
if (!defined('PATH_SEPARATOR')) {
    if (defined('DIRECTORY_SEPARATOR') && DIRECTORY_SEPARATOR == "\\") {
        define('PATH_SEPARATOR', ';');
    } else {
        define('PATH_SEPARATOR', ':');
    }
}

// set this to the path in which the directory for liveuser resides
// more remove the following two lines to test LiveUser in the standard
// PEAR directory
//$path_to_liveuser_dir = './pear/'.PATH_SEPARATOR;
//ini_set('include_path', $path_to_liveuser_dir.ini_get('include_path'));

// Data Source Name (DSN)
$dsn = 'mysql://root@localhost/liveuser_test';

$liveuserConfig = array(
    'session'           => array('name' => 'PHPSESSID','varname' => 'loginInfo'),
    'login'             => array('username' => 'handle', 'password' => 'passwd', 'remember' => 'rememberMe'),
    'logout'            => array('trigger' => 'logout', 'destroy'  => true, 'method' => 'get'),
    'cookie'            => array('name' => 'loginInfo', 'path' => '/', 'domain' => '', 'lifetime' => 30, 'secret' => 'mysecretkey'),
    'autoInit'          => true,
    'authContainers'    => array('DB' => array(
        'type' => 'DB',
                  'dsn' => $dsn,
                  'loginTimeout' => 0,
                  'expireTime'   => 0,
                  'idleTime'     => 0,
                  'allowDuplicateHandles'  => 1,
                  'passwordEncryptionMode' => 'PLAIN',
                  'authTableCols' => array(
                      'required' => array(
                          'auth_user_id' => array('name' => 'auth_user_id', 'type' => 'text'),
                          'handle'       => array('name' => 'handle',       'type' => 'text'),
                          'passwd'       => array('name' => 'passwd',       'type' => 'text'),
                      ),
                      'optional' => array(
                          'lastlogin'    => array('name' => 'lastlogin',    'type' => 'timestamp'),
                          'is_active'    => array('name' => 'is_active',    'type' => 'boolean')
                      )
                    )
    )
                                ),
    'permContainer' => array(
        'type'   => 'Complex',
        'storage' => array(
            'DB' => array(
                'dsn' => $dsn,
                'prefix' => 'liveuser_',
                'groupTableCols' => array(
                    'required' => array(
                        'group_id' => array('type' => 'integer', 'name' => 'group_id'),
                        'group_define_name' => array('type' => 'text', 'name' => 'group_define_name')
                    ),
                    'optional' => array(
                        'group_type'    => array('type' => 'integer', 'name' => 'group_type'),
                        'is_active'    => array('type' => 'boolean', 'name' => 'is_active'),
                        'owner_user_id'  => array('type' => 'integer', 'name' => 'owner_user_id'),
                        'owner_group_id' => array('type' => 'integer', 'name' => 'owner_group_id')
                    )
                )
            )
        )
    )
);

// Get LiveUser class definition
require_once 'LiveUser.php';
