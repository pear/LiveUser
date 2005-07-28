<?php
// BC hack
if (!defined('PATH_SEPARATOR')) {
    if (defined('DIRECTORY_SEPARATOR') && DIRECTORY_SEPARATOR == '\\') {
        define('PATH_SEPARATOR', ';');
    } else {
        define('PATH_SEPARATOR', ':');
    }
}

require_once 'PEAR.php';

// The error handling stuff is not needed and used only for debugging
// while LiveUser is not yet mature
PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, 'eHandler');

function eHandler($errObj)
{
    echo('<hr /><span style="color: red">' . $errObj->getMessage() . ':<br />'. $errObj->getUserInfo() . '</span><hr />');
}

// set this to the path in which the directory for liveuser resides
// more remove the following two lines to test LiveUser in the standard
// PEAR directory
# $path_to_liveuser_dir = 'PEAR/'.PATH_SEPARATOR;
# ini_set('include_path', $path_to_liveuser_dir.ini_get('include_path') );

$xml_is_readable = is_readable('Auth_XML.xml');
$xml_is_writable = is_writable('Auth_XML.xml');

if ($xml_is_readable != false && $xml_is_writable != false) {
    $liveuserConfig = array(
        'cookie'            => array(
            'name' => 'loginInfo',
            'path' => '',
            'domain' => '',
            'lifetime' => 30,
            'savedir' => '.',
            'secure' => false,
        ),
        'authContainers'    => array(
            0 => array(
                'type' => 'XML',
                'loginTimeout' => 0,
                'expireTime'   => 3600,
                'idleTime'     => 1800,
                'allowDuplicateHandles'  => false,
                'passwordEncryptionMode' => 'MD5',
                'storage' => array(
                    'file' => 'Auth_XML.xml',
                    'alias' => array(
                        'auth_user_id' =>   'userId',
                        'passwd' =>         'password',
                        'lastlogin' =>      'lastLogin',
                        'is_active' =>      'isActive',
                    ),
                    'tables' => array(
                        'users' => array(
                            'fields' => array(
                                'lastlogin'         => false,
                                'is_active'         => false,
                                'owner_user_id'     => false,
                                'owner_group_id'    => false,
                            ),
                        ),
                    ),
                    'fields' => array(
                        'lastlogin'         => 'timestamp',
                        'is_active'         => 'boolean',
                        'owner_user_id'     => 'integer',
                        'owner_group_id'    => 'integer',
                    ),
                ),
           ),
        ),
        'permContainer'     => array(
            'type'  => 'Simple',
            'storage' => array('XML' => array('file' => 'Perm_XML.xml')),
        ),
    );
    // Get LiveUser class definition
    require_once 'LiveUser.php';

    // right definitions
    define('COOKING',               1);
    define('WASHTHEDISHES',         2);
    define('WATCHTV',               3);
    define('WATCHLATENIGHTTV',      4);
    define('USETHECOMPUTER',        5);
    define('CONNECTINGTHEINTERNET', 6);

    // Create new LiveUser (LiveUser) object.
    // We´ll only use the auth container, permissions are not used.
    $LU =& LiveUser::factory($liveuserConfig);

    $handle = isset($_REQUEST['handle']) ? $_REQUEST['handle'] : null;
    $password = isset($_REQUEST['password']) ? $_REQUEST['password'] : null;
    $logout = isset($_REQUEST['logout']) ? $_REQUEST['logout'] : null;
    $remember = isset($_REQUEST['remember']) ? $_REQUEST['remember'] : null;
    if (!$LU->init($handle, $password, $logout, $remember)) {
        var_dump($LU->getErrors());
        die();
    }

    var_dump($LU->statusMessage($LU->getStatus()));
}

?>