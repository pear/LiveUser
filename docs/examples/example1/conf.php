<?php
// BC hack
if (!defined('PATH_SEPARATOR')) {
    if (defined('DIRECTORY_SEPARATOR') && DIRECTORY_SEPARATOR == '\\') {
        define('PATH_SEPARATOR', ';');
    } else {
        define('PATH_SEPARATOR', ':');
    }
}

// set this to the path in which the directory for liveuser resides
// more remove the following two lines to test LiveUser in the standard
// PEAR directory
# $path_to_liveuser_dir = 'PEAR/'.PATH_SEPARATOR;
# ini_set('include_path', $path_to_liveuser_dir.ini_get('include_path') );

$xml_is_readable = is_readable('Auth_XML.xml');
$xml_is_writable = is_writable('Auth_XML.xml');

if ($xml_is_readable && $xml_is_writable) {
    $liveuserConfig = array(
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
                        'auth_user_id' => 'userId',
                        'passwd' => 'password',
                        'lastlogin' => 'lastLogin',
                        'is_active' => 'isActive',
                    ),
                ),
           ),
        ),
    );
    // Get LiveUser class definition
    require_once 'LiveUser.php';
}
?>
