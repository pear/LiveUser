<?php
// LiveUser: A framework for authentication and authorization in PHP applications
// Copyright (C) 2002-2003 Markus Wolff
//
// This library is free software; you can redistribute it and/or
// modify it under the terms of the GNU Lesser General Public
// License as published by the Free Software Foundation; either
// version 2.1 of the License, or (at your option) any later version.
//
// This library is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
// Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public
// License along with this library; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

/**
 * LiveUser is an authentication/permission framework designed
 * to be flexible and easily extendable.
 *
 * Since it is impossible to have a
 * "one size fits all" it takes a container
 * approach which should enable it to
 * be versatile enough to meet most needs.
 *
 * @package  LiveUser
 * @category authentication
 */

/**
 * Include PEAR ErrorStack class
 */
require_once 'PEAR/ErrorStack.php';
require_once 'PEAR.php';

/**#@+
 * Error related constants definition
 *
 * @var integer
 */
define('LIVEUSER_ERROR',                        -1);
define('LIVEUSER_ERROR_NOT_SUPPORTED',          -2);
define('LIVEUSER_ERROR_CONFIG',                 -3);
define('LIVEUSER_ERROR_MISSING_DEPS',           -4);
define('LIVEUSER_ERROR_MISSING_LOGINFUNCTION',  -5);
define('LIVEUSER_ERROR_MISSING_LOGOUTFUNCTION', -6);
define('LIVEUSER_ERROR_COOKIE',                 -7);
define('LIVEUSER_ERROR_MISSING_FILE',           -8);
define('LIVEUSER_ERROR_FAILED_INSTANTIATION',   -9);
define('LIVEUSER_ERROR_INIT_ERROR',            -10);
define('LIVEUSER_ERROR_MISSING_CLASS',         -11);
define('LIVEUSER_ERROR_WRONG_CREDENTIALS',     -12);
define('LIVEUSER_ERROR_UNKNOWN_EVENT',         -13);
define('LIVEUSER_ERROR_NOT_CALLABLE',          -14);
/**#@-*/

/**#@+
 * Statuses of the current object.
 *
 * @var integer
 */
define('LIVEUSER_STATUS_OK',              1);
define('LIVEUSER_STATUS_IDLED',          -1);
define('LIVEUSER_STATUS_EXPIRED',        -2);
define('LIVEUSER_STATUS_ISINACTIVE',     -3);
define('LIVEUSER_STATUS_PERMINITERROR',  -4);
define('LIVEUSER_STATUS_AUTHINITERROR',  -5);
define('LIVEUSER_STATUS_UNKNOWN',        -6);
define('LIVEUSER_STATUS_AUTHNOTFOUND',   -7);
define('LIVEUSER_STATUS_LOGGEDOUT',      -8);
define('LIVEUSER_STATUS_AUTHFAILED',     -9);
define('LIVEUSER_STATUS_UNFROZEN',      -10);
/**#@-*/

/**
 * The higest possible right level
 *
 * @var integer
 */
define('LIVEUSER_MAX_LEVEL',            3);

/**#@+
 * Usertypes
 *
 * @var integer
 */
/**
 * lowest user type id
 */
define('LIVEUSER_ANONYMOUS_TYPE_ID',    0);
/**
 * lowest user type id
 */
// higest user type id
define('LIVEUSER_USER_TYPE_ID',         1);
/**
 * lowest admin type id
 */
define('LIVEUSER_ADMIN_TYPE_ID',        2);
define('LIVEUSER_AREAADMIN_TYPE_ID',    3);
define('LIVEUSER_SUPERADMIN_TYPE_ID',   4);
/**
 * higest admin type id
 */
define('LIVEUSER_MASTERADMIN_TYPE_ID',  5);
/**#@-*/

/**
 * Debug global. When set to true the
 * error stack will be printed to
 * a separate window using the Win implementation
 * of PEAR::Log (for which PEAR::ErrorStack has builtin
 * knowledge).
 *
 * @var boolean
 */
$GLOBALS['_LIVEUSER_DEBUG'] = false;

/**
 * This is a manager class for a user login system using the LiveUser
 * class. It creates a LiveUser object, takes care of the whole login
 * process and stores the LiveUser object in a session.
 *
 * You can also configure this class to try to connect to more than
 * one server that can store user information - each server requiring
 * a different backend class. This way you can for example create a login
 * system for a live website that first queries the local database and
 * if the requested user is not found, it tries to find im in your
 * company's LDAP server. That way you don't have to create lots of
 * user accounts for your employees so that they can access closed
 * sections of your website - everyone can use his existing account.
 *
 * NOTE: No browser output may be made before using this class, because
 * it will try to send HTTP headers such as cookies and redirects.
 *
 * Requirements:
 * - Should run on PHP version 4.2.0 (required for PEAR_Errorstack or higher,
 *   tested only from 4.2.1 onwards
 *
 * Thanks to:
 * Bjoern Schotte, Kristian Koehntopp, Antonio Guerra
 *
 * @author   Markus Wolff       <wolff@21st.de>
 * @author   Bjoern Kraus       <krausbn@php.net>
 * @author   Lukas Smith        <smith@backendmedia.com>
 * @author   Pierre-Alain Joye  <pajoye@php.net>
 * @author   Arnaud Limbourg    <arnaud@php.net>
 * @version  $Id$
 * @package  LiveUser
 */
class LiveUser
{
    /**
     * LiveUser options set in the configuration file.
     *
     * @access  private
     * @var     array
     */
    var $_options = array(
        'autoInit'=> false,
        'session' => array(
            'name'     => 'PHPSESSID',
            'varname'  => 'ludata',
        ),
        'session_save_handler' => false,
        'session_cookie_params' => false,
        'cache_perm' => false,
        'login'   => array(
            'force'    => false,
            'function' => '',
            'regenid'    => false,
        ),
        'logout'  => array(
            'redirect' => '',
            'destroy'  => true,
            'function' => '',
        )
    );

    /**
     * The auth container object.
     *
     * @access private
     * @var    object
     */
    var $_auth = null;

    /**
     * The permission container object.
     *
     * @access private
     * @var    object
     */
    var $_perm = null;

    /**
     * Nested array with the auth containers that shall be queried for user information.
     * Format:
     * <code>
     * array('name' => array("option1" => "value", ....))
     * </code>
     * Typical options are:
     * <ul>
     * - server: The adress of the server being queried (ie. "localhost").
     * - handle: The user name used to login for the server.
     * - password: The password used to login for the server.
     * - database: Name of the database containing user information (this is
     *   usually used only by RDBMS).
     * - baseDN: Obviously, this is what you need when using an LDAP server.
     * - connection: Present only if an existing connection shall be used. This
     *   contains a reference to an already existing connection resource or object.
     * - type: The container type. This option must always be present, otherwise
     *   the LoginManager can't include the correct container class definition.
     * - name: The name of the auth container. You can freely define this name,
     *   it can be used from within the permission container to see from which
     *   auth container a specific user was coming from.
     *</ul>
     *
     * @access private
     * @var    array
     */
    var $authContainers = array();

    /**
     * Array of settings for the permission container to use for retrieving
     * user rights.
     * If set to false, no permission container will be used.
     * If that is the case, all calls to checkRight() will return false.
     * The array element 'type' must be present for the LoginManager to be able
     * to include the correct class definition (example: "DB_Complex").
     *
     * @access private
     * @var    mixed
     */
    var $permContainer = false;

    /**
     * Current status of the LiveUser object.
     *
     * @access private
     * @var    string
     * @see    LIVEUSER_STATUS_* constants
     */
    var $status = LIVEUSER_STATUS_UNKNOWN;

    /**
     * Error stack
     *
     * @access private
     * @var    PEAR_ErrorStack
     */
    var $_stack = null;

    /**
     * PEAR::Log object
     * used for error logging by ErrorStack
     *
     * @access private
     * @var    Log
     */
    var $_log = null;

    /**
     * Error codes to message mapping array
     *
     * @access private
     * @var    array
     */
    var $_errorMessages = array(
        LIVEUSER_ERROR                        => 'Unknown error',
        LIVEUSER_ERROR_NOT_SUPPORTED          => 'Feature not supported by the container: %feature%',
        LIVEUSER_ERROR_CONFIG                 => 'There is an errror in the configuration parameters',
        LIVEUSER_ERROR_MISSING_DEPS           => 'Missing package depedencies: %msg%',
        LIVEUSER_ERROR_MISSING_LOGINFUNCTION  => 'The Login function cannot be found',
        LIVEUSER_ERROR_MISSING_LOGOUTFUNCTION => 'The Logout function cannot be found',
        LIVEUSER_ERROR_COOKIE                 => 'There was an error processing the Remember Me cookie',
        LIVEUSER_ERROR_MISSING_FILE           => 'The file %file% is missing',
        LIVEUSER_ERROR_FAILED_INSTANTIATION   => 'Cannot instantiate class %class%',
        LIVEUSER_ERROR_INIT_ERROR             => 'Container %container% was not initialized properly',
        LIVEUSER_ERROR_MISSING_CLASS          => 'Class %class% does not exist in file %file%',
        LIVEUSER_ERROR_WRONG_CREDENTIALS      => 'The username and/or password you submitted are not known',
        LIVEUSER_ERROR_UNKNOWN_EVENT          => 'The event %event% is not known',
        LIVEUSER_ERROR_NOT_CALLABLE           => 'Callback %callback% is not callable'
    );

    /**
     * Events that are allowed to be triggered (built in events are preset).
     *
     * @access protected
     * @var    array
     */
    var $_events = array(
        'onLogin',     // successfully logged in
        'forceLogin',  // login required -> you could display a login form
        'onLogout',    // before logout -> can be used to cleanup own stuff
        'postLogout',  // after logout -> e.g. do a redirect to another page
        'onIdled',     // maximum idle time is reached
        'onExpired'    // authentication session is expired
    );

    /**
     * Used to store attached observers.
     *
     * @access protected
     * @var    array
     */
    var $_observers = array();

    /**
     * Constructor
     *
     * @access protected
     * @return void
     */
    function LiveUser()
    {
        $this->_stack = &PEAR_ErrorStack::singleton('LiveUser');

        if ($GLOBALS['_LIVEUSER_DEBUG']) {
            if (!is_object($this->_log)) {
                $this->loadPEARLog();
            }
            $this->_log->addChild(Log::factory('win', 'LiveUser'));
        }

        $this->_stack->setErrorMessageTemplate($this->_errorMessages);
    }

    /**
     * Returns an instance of the login manager class.
     *
     * This array contains private options defined by
     * the following associative keys:
     *
     * <code>
     *
     * array(
     *  'autoInit' => false/true,
     *  'session'  => array(
     *      'name'    => 'liveuser session name',
     *      'varname' => 'liveuser session var name'
     *  ),
     * // The session_save_handler options are optional. If they are specified,
     * // session_set_save_handler() will be called with the parameters
     *  'session_save_handler' => array(
     *      'open'    => 'name of the open function/method',
     *      'close'   => 'name of the close function/method',
     *      'read'    => 'name of the read function/method',
     *      'write'   => 'name of the write function/method',
     *      'destroy' => 'name of the destroy function/method',
     *      'gc'      => 'name of the gc function/method',
     *  ),
     * // The session_cookie_params options are optional. If they are specified,
     * // session_set_cookie_params() will be called with the parameters
     *  'session_cookie_params' => array(
     *      'lifetime' => 'Cookie lifetime in days',
     *      'path'     => 'Cookie path',
     *      'domain'   => 'Cookie domain',
     *      'secure'   => 'Cookie send only over secure connections',
     *  ),
     *  'login' => array(
     *      'function' => '(optional) Function to be called when accessing a page without logging in first',
     *      'force'    => 'Should the user be forced to login'
     *      'regenid'  => 'Should the session be regenerated on login'
     *  ),
     *  'logout' => array(
     *      'redirect' => 'Page path to be redirected to after logout',
     *      'function' => '(optional) Function to be called when accessing a page without logging in first',
     *      'destroy'  => 'Whether to destroy the session on logout' false or true
     *  ),
     * // The cookie options are optional. If they are specified, the Remember Me
     * // feature is activated.
     *  'cookie' => array(
     *      'name'     => 'Name of Remember Me cookie',
     *      'lifetime' => 'Cookie lifetime in days',
     *      'path'     => 'Cookie path',
     *      'domain'   => 'Cookie domain',
     *      'secret'   => 'Secret key used for cookie value encryption',
     *      'savedir'  => '/absolute/path/to/writeable/directory' // No / at the end !
     *  ),
     *  'authContainers' => array(
     *      'name' => array(
     *            'type' => 'DB',
     *            'connection'    => 'db connection object, use this or dsn',
     *            'dsn'           => 'database dsn, use this or connection',
     *            'loginTimeout'  => 0,
     *            'expireTime'    => 3600,
     *            'idleTime'      => 1800,
     *            'allowDuplicateHandles' => 0,
     *            'authTable'     => 'liveuser_users',
     *            'authTableCols' => array(
     *                'required' => array(
     *                    'auth_user_id' => array('type' => 'text', 'name' => 'user_id'),
     *                    'handle'       => array('type' => 'text', 'name' => 'handle'),
     *                    'passwd'       => array('type' => 'text', 'name' => 'passwd')
     *                ),
     *                'optional' => array(
     *                    'owner_user_id'  => array('type' => 'integer', 'name' => 'owner_user_id'),
     *                    'owner_group_id' => array('type' => 'integer', 'name' => 'owner_group_id')
     *                    'lastlogin'    => array('type' => 'timestamp', 'name' => 'lastlogin'),
     *                    'is_active'    => array('type' => 'boolean', 'name' => 'is_active')
     *                ),
     *                'custom'   => array(
     *                    'myaliasforfield1' => array('type' => 'text', 'name' => 'myfield1')
     *                )
     *           )
     *      )
     *  ),
     *  'permContainer' => array(
     *      'type'       => 'DB_Complex',
     *      'connection' => 'db connection object, use this or dsn',
     *      'dsn'        => 'database dsn, use this or connection',
     *      'prefix'     => 'liveuser_',
     *      'groupTableCols' => array(
     *          'required' => array(
     *              'group_id' => array('type' => 'integer', 'name' => 'group_id')
     *              'group_define_name' => array('type' => 'text', 'name' => 'group_define_name')
     *          ),
     *          'optional' => array(
     *              'group_type'    => array('type' => 'integer', 'name' => 'group_type')
     *              'is_active'    => array('type' => 'boolean', 'name' => 'is_active')
     *              'owner_user_id'  => array('type' => 'integer', 'name' => 'owner_user_id'),
     *              'owner_group_id' => array('type' => 'integer', 'name' => 'owner_group_id')
     *          ),
     *          'custom'   => array(
     *              'myaliasforfield1' => array('type' => 'text', 'name' => 'myfield1')
     *          )
     *  )
     *
     * </code>
     *
     * Other options in the configuration file relative to
     * the Auth and Perm containers depend on what the
     * containers expect. Refer to the Containers documentation.
     * The examples for containers provided are just general
     * do not reflect all the options for all containers.
     *
     * @access public
     * @param  mixed           The config file or the config array to configure.
     * @param  string          Handle of the user trying to authenticate
     * @param  string          Password of the user trying to authenticate
     * @param  boolean         set to true if user wants to logout
     * @param  boolean         set if remember me is set
     * @param  mixed           Name of array containing the configuration.
     * @return LiveUser|false  Returns an object of either LiveUser or false on error
     *                         if so use LiveUser::getErrors() to get the errors
     * @see LiveUser::getErrors
     */
    function &factory($conf, $handle = '', $passwd = '',$logout = false,
        $remember = false, $confName = 'liveuserConfig')
    {
        $obj = &new LiveUser();

        if (!empty($conf)) {
            $init = $obj->_readConfig($conf, $confName);
            if (!$init) {
                return false;
            }
        }

        if (isset($obj->_options['autoInit']) && $obj->_options['autoInit']) {
            $init = $obj->init($handle, $passwd, $logout, $remember);
            if (!$init) {
                return false;
            }
        }

        return $obj;
    }

    /**
     * Makes your instance global.
     *
     * <b>In PHP4 you MUST call this method with the
     *  $var = &LiveUser::singleton() syntax.
     * Without the ampersand (&) in front of the method name, you will not get
     * a reference, you will get a copy.</b>
     *
     * @access public
     * @param  array|file      The config file or the config array to configure.
     * @param  string          Handle of the user trying to authenticate
     * @param  string          Password of the user trying to authenticate
     * @param  boolean         set to true if user wants to logout
     * @param  boolean         set if remember me is set
     * @param  string          Name of array containing the configuration.
     * @return LiveUser|false  Returns an object of either LiveUser or false on failure
     * @see    LiveUser::factory
     */
    function &singleton($conf, $handle = '', $passwd = '', $logout = false,
        $remember = false, $confName = 'liveuserConfig')
    {
        static $instances;
        if (!isset($instances)) {
            $instances = array();
        }

        $signature = serialize(array($handle, $passwd, $confName));
        if (!isset($instances[$signature])) {
            $obj = &LiveUser::factory(
                $conf, $handle, $passwd, $logout,
                $remember, $confName
            );
            $instances[$signature] =& $obj;
        }

        return $instances[$signature];
    }

    /**
     * Wrapper method to get the Error Stack
     *
     * @access public
     * @return array  an array of the errors
     */
    function getErrors()
    {
        return $obj->_stack->getErrors();
    }

    /**
     * Loads a PEAR class
     *
     * @access public
     * @param  string  classname
     * @return boolean true success or false on failure
     */
    function loadClass($classname)
    {
        if (!class_exists($classname)) {
            $filename = str_replace('_', '/', $classname).'.php';
            if (!LiveUser::fileExists($filename)) {
                return false;
            }
            include_once($filename);
            if (!class_exists($classname)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Creates an instance of an auth object
     *
     * @access public
     * @param  array|file    Name of array containing the configuration.
     * @return object|false  Returns an instance of an auth container
     *                       class or false on error
     */
    function &authFactory($conf, $containerName, $classprefix = 'LiveUser_')
    {
        $classname = $classprefix.'Auth_' . $conf['type'];
        if (!LiveUser::loadClass($classname)) {
            return false;
        }
        $auth = &new $classname($conf, $containerName);
        return $auth;
    }

    /**
     * Creates an instance of an perm object
     *
     * @access public
     * @param  mixed         Name of array containing the configuration.
     * @return object|false  Returns an instance of a perm container
     *                       class or false on error
     */
    function &permFactory($conf, $classprefix = 'LiveUser_')
    {
        $classname = $classprefix.'Perm_' . $conf['type'];
        if (!LiveUser::loadClass($classname)) {
            return false;
        }
        $perm = &new $classname($conf['storage']);
        return $perm;
    }
    /**
     * Returns an instance of a storage Container
     *
     * @access protected
     * @param  array        configuration array to pass to the storage container
     * @return object|false will return an instance of a Storage container
     *                      or false upon error
     */
    function &storageFactory($confArray, $classprefix = 'LiveUser_')
    {
        end($confArray);
        $storageName = $classprefix.'Perm_Storage_' . key($confArray);
        if (!LiveUser::loadClass($storageName) && count($confArray) > 1) {
            PEAR_ErrorStack::staticPush(
                LIVEUSER_ERROR_FAILED_INSTANTIATION,
                array('class' => $storageName)
            );
            return false;
        } elseif(count($confArray) > 1) {
            $storageConf =& array_pop($confArray);
            return LiveUser::storageFactory($confArray, $classprefix);
        }
        $storageConf =& array_pop($confArray);
        $storage = &new $storageName($confArray, $storageConf);

        return $storage;
    }

    /**
     * Clobbers two arrays together
     * taken from the user notes of array_merge_recursive
     * used in LiveUser::_readConfig()
     * may be called statically
     *
     * @access public
     * @param  array        array that should be clobbered
     * @param  array        array that should be clobbered
     * @return array|false  array on success and false on error
     * @author kc@hireability.com
     */
    function arrayMergeClobber($a1, $a2)
    {
        if (!is_array($a1) || !is_array($a2)) {
            return false;
        }
        foreach ($a2 as $key => $val) {
            if (is_array($val) &&
                isset($a1[$key]) &&
                is_array($a1[$key]))
            {
                $a1[$key] = LiveUser::arrayMergeClobber($a1[$key], $val);
            } else {
                $a1[$key] = $val;
            }
        }
        return $a1;
    }

    /**
     * checks if a file exists in the include path
     *
     * @access public
     * @param  string   filename
     * @return boolean  true success and false on error
     */
    function fileExists($file)
    {
        $dirs = split(PATH_SEPARATOR, ini_get('include_path'));
        foreach ($dirs as $dir) {
            if (file_exists($dir . DIRECTORY_SEPARATOR . $file)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reads the configuration
     *
     * @access private
     * @param  array|file  Conf array or file path to configuration
     * @param  string      Name of array containing the configuration
     * @return boolean     true on success or false on failure
     */
    function _readConfig($conf, $confName)
    {
        if (is_array($conf)) {
            if (isset($conf['authContainers'])) {
                $this->authContainers = $conf['authContainers'];
                unset($conf['authContainers']);
            }
            if (isset($conf['permContainer'])) {
                $this->permContainer = $conf['permContainer'];
                unset($conf['permContainer']);
            }

            $this->_options = $this->arrayMergeClobber($this->_options, $conf);
            if (isset($this->_options['cookie'])) {
                $cookie_default = array(
                    'name'     => 'ludata',
                    'lifetime' => '365',
                    'path'     => '/',
                    'domain'   => '',
                    'secret'   => 'secret',
                );
                $this->_options['cookie'] =
                    $this->arrayMergeClobber(
                        $cookie_default, $this->_options['cookie']
                    );
            }

            return true;
        }

        if (!LiveUser::fileExists($conf)) {
            $this->_stack->push(LIVEUSER_ERROR_CONFIG, 'exception', array(),
                "Configuration file does not exist in LiveUser::readConfig(): $conf");
            return false;
        }
        if (!include_once($conf)) {
            $this->_stack->push(LIVEUSER_ERROR_CONFIG, 'exception', array(),
                "Could not read the configuration file in LiveUser::readConfig(): $conf");
            return false;
        }
        if (isset(${$confName}) && is_array(${$confName})) {
            return $this->_readConfig(${$confName}, $confName);
        }
        $this->_stack->push(
            LIVEUSER_ERROR_CONFIG, 'exception',
            array(), 'Configuration array not found in LiveUser::readConfig()'
        );
        return false;
    }

    /**
     * Add error logger for use by Errorstack.
     *
     * Be aware that if you need add a log
     * at the beginning of your code if you
     * want it to be effective. A log will only
     * be taken into account after it's added.
     *
     * Sample usage:
     * <code>
     * $lu_object = &LiveUser::singleton($conf);
     * $logger = &Log::factory('mail', 'bug@example.com',
     *      'myapp_debug_mail_log', array('from' => 'application_bug@example.com'));
     * $lu_object->addErrorLog($logger);
     * </code>
     *
     * @access public
     * @param  Log     logger instance
     * @return boolean true on success or false on failure
     */
    function addErrorLog(&$log)
    {
        if (!is_object($this->_log)) {
            $this->loadPEARLog();
        }
        return $this->_log->addChild($log);
    }

    /**
     * Creates an instance of the PEAR::Crypt_Rc4 class
     *
     * @access public
     * @param  string  token to use to encrypt data
     * @return object  Returns an instance of the Crypt_RC4 class
     */
    function &CryptRC4Factory($secret)
    {
        if (!LiveUser::loadClass('Crypt_RC4')) {
            return false;
        }
        $rc4 =& new Crypt_RC4($secret);
        return $rc4;
    }

    /**
     * Crypts data using mcrypt or userland if not available
     *
     * @access private
     * @param  boolean true to crypt, false to decrypt
     * @param  string  data to crypt
     * @return string  crypted data
     */
    function _cookieCryptMode($crypt, $data)
    {
        if (function_exists('mcrypt_module_open')) {
            $td = mcrypt_module_open('tripledes', '', 'ecb', '');
            $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size ($td), MCRYPT_RAND);
            mcrypt_generic_init($td, $this->_options['cookie']['secret'], $iv);
            if ($crypt) {
                $data = mcrypt_generic($td, $data);
            } else {
                $data = mdecrypt_generic($td, $data);
            }
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
        } else {
            $rc4 =& LiveUser::CryptRC4Factory($this->_options['cookie']['secret']);
            if (!$rc4) {
                return false;
            }
            $this->rc4 =& $rc4;
            if ($crypt) {
                $rc4->crypt($data);
            } else {
                $rc4->decrypt($data);
            }
        }

        return $data;
    }

    /**
     * Sets an option.
     *
     * @access public
     * @param  string  option name
     * @param  mixed   value for the option
     * @return boolean true on success or false on failure
     * @see    LiveUser::_options
     */
    function setOption($option, $value)
    {
        if (isset($this->_options[$option])) {
            $this->_options[$option] = $value;
            return true;
        }
        $this->_stack->push(
            LIVEUSER_ERROR_CONFIG, 'exception',
            array(), "unknown option $option"
            );
        return false;
    }

    /**
     * Returns the value of an option
     *
     * @access public
     * @param  string option name
     * @return mixed  the option value or false on failure
     */
    function getOption($option)
    {
        if (isset($this->_options[$option])) {
            return $this->_options[$option];
        }
        $this->_stack->push(
            LIVEUSER_ERROR_CONFIG, 'exception',
            array(), "unknown option $option"
        );
        return false;
    }

    /**
     * Tries to retrieve auth object from session.
     * If this fails, the class attempts a login based on cookie or form
     * information (depends on class settings).
     * Returns true if a auth object was successfully retrieved or created.
     * Otherwise, false is returned.
     *
     * @access public
     * @param  string   handle of the user trying to authenticate
     * @param  string   password of the user trying to authenticate
     * @param  boolean  set to true if user wants to logout
     * @param  boolean  set if remember me is set
     * @return boolean  true if init process well, false if something
     *                  went wrong.
     */
    function init($handle = '', $passwd = '', $logout = false, $remember = false)
    {
        // set session save handler if needed
        if ($this->_options['session_save_handler']) {
            session_set_save_handler(
                $this->_options['session_save_handler']['open'],
                $this->_options['session_save_handler']['close'],
                $this->_options['session_save_handler']['read'],
                $this->_options['session_save_handler']['write'],
                $this->_options['session_save_handler']['destroy'],
                $this->_options['session_save_handler']['gc']
            );
        }
        if ($this->_options['session_cookie_params']) {
            $cookieTimeout = time() + (86400 * $this->_options['cookie']['lifetime']);
            session_set_cookie_params($cookieTimeout,
                $this->_options['cookie']['path'],
                $this->_options['cookie']['domain'],
                $this->_options['cookie']['secure']);
        }
        // Set the name of the current session
        session_name($this->_options['session']['name']);
        // If there's no session yet, start it now
        @session_start();

        if ($logout) {
            $this->logout(true);
        }

        // Try to fetch auth object from session
        $this->unfreeze();

        if ($this->isLoggedIn()) {
            // Check if user authenticated with new credentials
            if ($handle && $this->auth->handle != $handle) {
                $this->logout(false);
            } elseif ($this->_auth->expireTime > 0 && $this->_auth->currentLogin > 0) {
                // Check if authentication session is expired.
                if (($this->_auth->currentLogin + $this->_auth->expireTime) < time()) {
                    $this->logout();
                    $this->status = LIVEUSER_STATUS_EXPIRED;
                    $this->triggerEvent('onExpired');
                // Check if maximum idle time is reached.
                } elseif (isset($_SESSION[$this->_options['session']['varname']]['idle']) &&
                    ($_SESSION[$this->_options['session']['varname']]['idle'] + $this->_auth->idleTime) < time())
                {
                    $this->logout();
                    $this->status = LIVEUSER_STATUS_IDLED;
                    $this->triggerEvent('onIdled');
                }
            }
        }

        $_SESSION[$this->_options['session']['varname']]['idle'] = time();

        if (!$this->isLoggedIn()) {
            $onLogin = $this->login($handle, $passwd, $remember);
            if ($onLogin) {
                // user has just logged in
                $this->triggerEvent('onLogin');
            }
        }

        // Force user login.
        if (!$this->isLoggedIn() && $this->_options['login']['force']) {
            $this->triggerEvent('forceLogin');
        }

        // Return boolean that indicates whether a auth object has been created
        // or retrieved from session
        if ($this->isLoggedIn()) {
            if ($this->_options['login']['regenid']) {
                session_regenerate_id();
            }
        $this->_status = LIVEUSER_STATUS_OK;
            return true;
        }

        return false;
    }

    /**
     * Tries to log the user in by trying all the Auth containers defined
     * in the configuration file until there is a success or failure.
     *
     * @access private
     * @param  string   handle of the user trying to authenticate
     * @param  string   password of the user trying to authenticate
     * @param  boolean  set rememberMe cookie
     * @return boolean  true on success or false on failure
     */
    function login($handle = '', $passwd = '', $remember = false)
    {
        if (empty($handle)) {
            return false;
        }

        $counter     = 0;
        $backends    = array_keys($this->authContainers);
        $backend_cnt = count($backends);

        $this->status = LIVEUSER_STATUS_AUTHFAILED;
        //loop into auth containers
        while ($backend_cnt > $counter) {
            $auth = &$this->authFactory($this->authContainers[$backends[$counter]], $backends[$counter]);
            $auth->login($handle, $passwd, true);
            if ($auth->loggedIn) {
                $this->status = LIVEUSER_STATUS_OK;
                $this->_auth  = $auth;
                $this->_auth->backendArrayIndex = $backends[$counter];
                // Create permission object
                if (is_array($this->permContainer)) {
                    $this->_perm =& $this->permFactory($this->permContainer);
                    $this->_perm->init($this->_auth->authUserId, $this->_auth->backendArrayIndex);
                }
                $this->freeze();
                $this->setRememberCookie($handle, $passwd, $remember);
                break;
            } elseif ($auth->isActive === false) {
                $this->status = LIVEUSER_STATUS_ISINACTIVE;
                break;
            }
            $counter++;
        }

        if (!$this->isLoggedIn()) {
            $this->_stack->push(LIVEUSER_ERROR_WRONG_CREDENTIALS, 'error');
            return false;
        }
        return true;
    }

    /**
     * Gets auth and perm container objects back from session and tries
     * to give them an active database/whatever connection again
     *
     * @access private
     * @return boolean true on success or false on failure
     */
    function unfreeze()
    {
        if (isset($_SESSION[$this->_options['session']['varname']]['auth'])
            && is_array($_SESSION[$this->_options['session']['varname']]['auth'])
            && isset($_SESSION[$this->_options['session']['varname']]['auth_name'])
            && strlen($_SESSION[$this->_options['session']['varname']]['auth_name']) > 0)
        {
            $this->_auth->backendArrayIndex = $_SESSION[$this->_options['session']['varname']]['auth_name'];
            $containerName = $_SESSION[$this->_options['session']['varname']]['auth_name'];
            $auth = &$this->authFactory($this->authContainers[$containerName], $containerName);
            if($auth->unfreeze($_SESSION[$this->_options['session']['varname']]['auth'])) {
                if (isset($_SESSION[$this->_options['session']['varname']]['perm'])
                    && $_SESSION[$this->_options['session']['varname']]['perm'])
                {
                    $this->_auth = &$auth;
                    $this->_perm = &$this->permFactory($this->permContainer);
                    if ($this->_options['cache_perm']) {
                        $this->_perm->unfreeze($this->_options['session']['varname']);
                    } else {
                        $this->_perm->init($this->_auth->authUserId, $this->_auth->backendArrayIndex);
                    }
                }
                $this->_status = LIVEUSER_STATUS_UNFROZEN;
                return true;
            }
        }

        return false;
    }

    /**
     * Store all properties in an array
     *
     * @access  public
     * @return  boolean true on sucess or false on failure
     */
    function freeze()
    {
        if (is_a($this->_auth, 'LiveUser_Auth_Common') && $this->_auth->loggedIn) {
            // Bind objects to session
            $_SESSION[$this->_options['session']['varname']] = array();
            $_SESSION[$this->_options['session']['varname']]['auth'] = $this->_auth->freeze();
            $_SESSION[$this->_options['session']['varname']]['auth_name'] = $this->_auth->backendArrayIndex;
            if (is_a($this->_perm, 'LiveUser_Perm_Simple')) {
                $_SESSION[$this->_options['session']['varname']]['perm'] = true;
                if ($this->_options['cache_perm']) {
                     $this->_perm->freeze($this->_options['session']['varname']);
                }
            }
            return true;
        }
        $this->_stack->push(LIVEUSER_ERROR_CONFIG, 'exception', array(), 'No data available to store inside session');
        return false;
    }

    /**
     * Properly disconnect resources in the active container
     *
     * @access  public
     * @return  boolean true on success or false on failure
     */
    function disconnect()
    {
        if (is_a($this->_auth, 'LiveUser_Auth_Common') && $this->_auth->loggedIn) {
            $this->_auth->disconnect();
            $this->_auth = null;
            if (is_a($this->_perm, 'LiveUser_Perm_Simple')) {
                $this->_perm->disconnect();
                $this->_perm = null;
            }
            return true;
        }
        $this->_stack->push(LIVEUSER_ERROR_CONFIG, 'exception', array(), 'No connection to disconnect in LiveUser::disconnect()');
        return false;
    }

    /**
     * If cookies are allowed, this method checks if the user wanted
     * a cookie to be set so he doesn't have to enter handle and password
     * for his next login. If true, it will set the cookie.
     *
     * @access private
     * @param  string   handle of the user trying to authenticate
     * @param  string   password of the user trying to authenticate
     * @param  boolean  set if remember me is set
     * @return boolean  true if the cookie can be set, false otherwise
     */
    function setRememberCookie($handle, $passwd, $remember)
    {
        if ($remember && isset($this->_options['cookie'])) {
            // Calculate cookie timeout in days
            $cookieTimeout = time() + (86400 * $this->_options['cookie']['lifetime']);

            $store_id = md5($handle . $passwd);

            if (!$passwd_id = $this->_storeCookiePasswdId($passwd, $store_id)) {
                $this->_stack->push(LIVEUSER_ERROR_CONFIG, 'exception', array(), 'Cannot save cookie data');
                return false;
            }

            $setcookie = setcookie(
                          $this->_options['cookie']['name'],
                          serialize(array($store_id, $handle, $passwd_id)),
                          $cookieTimeout,
                          $this->_options['cookie']['path'],
                          $this->_options['cookie']['domain']);

            if (!$setcookie) {
                $this->_stack->push(LIVEUSER_ERROR_CONFIG, 'exception', array(), 'Unable to set cookie');
                return false;
            }
        }
        return true;
    }

    /**
     * A "store" on the server contains the password and the
     * cookie id in an encrypted form.
     *
     * This method generates a md5 from given password and writes it
     * into the "store" along with crypted password.
     *
     * Cookies are not secure but keeping the password in plain text
     * in the cookie is not the best way to go. Since some
     * containers like LDAP need the clear text password, we store
     * an encrypted version of the password using Crypt_Rc4 which
     * provides a simple two-way mechanism.
     *
     * To do this LiveUser needs access to a writeable directory.
     * If you do no have access to the ini_get() function please
     * set a constant named LIVEUSER_TMPDIR with an absolute
     * path to a writeable directory.
     *
     * @access private
     * @param  string   the password to store
     * @param  string   file name used as storage
     * @return boolean  true if success, false otherwise
     */
    function _storeCookiePasswdId($passwd, $store)
    {
        $dir = $this->_options['cookie']['savedir'];

        if (!is_writable($dir)) {
            $this->_stack->push(LIVEUSER_ERROR_CONFIG, 'exception', array(), 'Cannot create file, please check path and permissions');
            return false;
        }

        if (!$fh = @fopen($dir . "/$store.lu", 'wb')) {
            $this->_stack->push(LIVEUSER_ERROR_CONFIG, 'exception', array(), 'Cannot open file for writting');
            return false;
        }

        $data = serialize(array(md5($passwd), $passwd));

        $crypted_data = $this->_cookieCryptMode(true, $data);

        if (!fwrite($fh, $crypted_data)) {
            fclose($fh);
            $this->_stack->push(LIVEUSER_ERROR_CONFIG, 'exception', array(), 'Cannot save cookie data');
            return false;
        }

        fclose($fh);

        return true;
    }

    /**
     * This destroys the session object.
     *
     * @access public
     * @param  boolean  set to true if the logout was initiated directly
     * @return void
     */
    function logout($direct = true)
    {
        $this->status = LIVEUSER_STATUS_LOGGEDOUT;

        // trigger event 'onLogout' as replacement for logout callback function
        if ($direct) {
            $this->triggerEvent('onLogout');
        }

        // If there's a cookie and the session hasn't idled or expired, kill that one too...
        if (isset($this->_options['cookie']) &&
            isset($_COOKIE[$this->_options['cookie']['name']]))
        {
            // is this what we want?
            $cookieKillTime = time() - 86400;
            setcookie($this->_options['cookie']['name'],
                '',
                $cookieKillTime,
                          $this->_options['cookie']['path'],
                          $this->_options['cookie']['domain']
            );
            unset($_COOKIE[$this->_options['cookie']['name']]);
        }

        // If the session should be destroyed, do so now...
        if ($this->_options['logout']['destroy']) {
            session_unset();
            session_destroy();
            // set session save handler if needed
            if ($this->_options['session_save_handler']) {
                session_set_save_handler(
                    $this->_options['session_save_handler']['open'],
                    $this->_options['session_save_handler']['close'],
                    $this->_options['session_save_handler']['read'],
                    $this->_options['session_save_handler']['write'],
                    $this->_options['session_save_handler']['destroy'],
                    $this->_options['session_save_handler']['gc']
                );
            }

            if ($this->_options['session_cookie_params']) {
                $cookieTimeout = time() + (86400 * $this->_options['cookie']['lifetime']);
                session_set_cookie_params($cookieTimeout,
                    $this->_options['cookie']['path'],
                    $this->_options['cookie']['domain'],
                    $this->_options['cookie']['secure']);
            }
            // Set the name of the current session
            session_name($this->_options['session']['name']);
            // If there's no session yet, start it now
            @session_start();
        } else {
            unset($_SESSION[$this->_options['session']['varname']]);
        }

        // Delete the container objects
        $this->_auth = null;
        $this->_perm = null;

        // trigger event 'postLogout', can be used to do a redirect
        if ($direct) {
            $this->triggerEvent('postLogout');
        }
    }

    /**
     * Wrapper method for the permission object's own checkRight method.
     *
     * @access public
     * @param  array|int  A right id or an array of rights.
     * @return int|false  level if the user has the right/rights false if not
     */
    function checkRight($rights)
    {
        if (is_null($rights)) {
            return LIVEUSER_MAX_LEVEL;
        }

        if (is_a($this->_perm, 'LiveUser_Perm_Simple')) {
            $hasright = false;

            if (is_array($rights)) {
                // assume user has the right in order to have min() work
                $hasright = LIVEUSER_MAX_LEVEL;
                foreach ($rights as $currentright) {
                    if ($level = $this->_perm->checkRight($currentright)) {
                        $hasright = min($hasright, $level);
                    } else {
                        $hasright = false;
                        break;
                    }
                }
            } else {
                // Remember: $rights is a single value at this point!
                $hasright = $this->_perm->checkRight($rights);
            }

            return $hasright;
        }

        return false;
    }

    /**
     * Wrapper method for the permission object's own checkRightLevel method.
     *
     * @access public
     * @param  array|int  A right id or an array of rights.
     * @param  array|int  Id or array of Ids of the owner of the
                          ressource for which the right is requested.
     * @param  array|int  Id or array of Ids of the group of the
     *                    ressource for which the right is requested.
     * @return boolean    true on success or false on failure
     */
    function checkRightLevel($rights, $owner_user_id, $owner_group_id)
    {
        if (is_null($rights)) {
            return LIVEUSER_MAX_LEVEL;
        }

        if (is_a($this->_perm, 'LiveUser_Perm_Simple')) {
            $level = $this->checkRight($rights);
            $hasright = $this->_perm->checkLevel($level, $owner_user_id, $owner_group_id);
            return $hasright;
        }

        return false;
    }

    /**
     * Wrapper method for the permission object's own checkGroup method.
     *
     * @access public
     * @param  array|int  A group id or an array of groups.
     * @return boolean    true on success or false on failure
     */
    function checkGroup($groups)
    {
        if (is_null($groups)) {
            return true;
        }

        if (is_object($this->_perm)) {
            if (is_array($groups)) {
                // assume user has the group
                $ingroup = true;
                foreach ($groups as $group) {
                    if (!$this->_perm->checkGroup($group)) {
                        $ingroup = false;
                        break;
                    }
                }
                return $ingroup;
            } else {
                // Remember: $groups is a single value at this point!
                return $this->_perm->checkGroup($groups);
            }
        }

        return false;
    }
    /**
     * Checks if a user is logged in.
     *
     * @access public
     * @return boolean true if user is logged in, false if not
     */
    function isLoggedIn()
    {
        if (!is_a($this->_auth, 'LiveUser_Auth_Common')) {
            return false;
        }

        return $this->_auth->loggedIn;
    }

    /**
     * Function that determines if the user exists but hasn't yet been declared
     * "active" by an administrator.
     *
     * Use this to check if this was the reason
     * why a user was not able to login.
     * true ==  user account is NOT active
     * false == user account is active
     *
     * @access public
     * @return boolean true if the user account is *not* active
     *                 false if the user account *is* active
     */
    function isInactive()
    {
        return $this->status == LIVEUSER_STATUS_ISINACTIVE;
    }

    /**
     * Wrapper method to access properties from the auth and
     * permission containers.
     *
     * @access public
     * @param  string   Name of the property to be returned.
     * @param  string   'auth' or 'perm'
     * @return mixed    a value or an array.
     */
    function getProperty($what, $container = 'auth')
    {
        $that = null;
        if ($container == 'auth' && $this->_auth && $this->_auth->getProperty($what) !== null) {
            $that = $this->_auth ? $this->_auth->getProperty($what) : null;
        } elseif ($this->_perm && $this->_perm->getProperty($what) !== null) {
            $that = $this->_perm ? $this->_perm->getProperty($what) : null;
        }
        return $that;
    }

    /**
     * Get the current status.
     *
     * @access public
     * @return integer
     */
    function getStatus()
    {
        return $this->status;
    }

    /**
     * Add an observer to listen to a certain event
     *
     * An observer is any valid callback function. You may attach
     * multiple observers for each event. If an event is triggered
     * observers of that event are called in the order they were attached.
     * LiveUser object and optional settings from the trigger call are set
     * as first and second parameters for each observer notification.
     *
     * @access public
     * @param  string  event name
     * @param  mixed   callback function (string) or array($obj, $method)
     * @return bool    true on success, otherwise false
     * @see    LiveUser::triggerEvent
     */
    function attachObserver($event, &$observer)
    {
        if (!in_array($event, $this->_events)) {
            $this->_stack->push(
                LIVEUSER_ERROR_UNKNOWN_EVENT, 'exception',
                    array('event' => $event),
                    'attempt to attach to an unknown event');
            return false;
        }

        if (!is_callable($observer)) {
            $this->_stack->push(
                LIVEUSER_ERROR_NOT_CALLABLE, 'exception',
                array('callback' => $observer),
                'observer is not callable'
            );
            return false;
        }

        if (!isset($this->_observers[$event])) {
            $this->_observers[$event] = array();
        }

        $this->_observers[$event][] = &$observer;
        return true;
    }

    /**
     * Add an observer object to listen to multiple events
     *
     * In contrast to LiveUser::attachObserver() this can be used to add
     * an object providing observer methods for some or all events.
     * If you don't set parameter $methods it tries to find matching methods
     * for each registered event and adds them as observer callback.
     * You can use the $methods parameter to set what method should act
     * as an observer for what event.
     *
     * @access public
     * @param  object  object with observer methods
     * @param  array   optional used to change method names this way:
     *                 array('event' => 'realMethodName', ...)
     * @return bool    true on success, otherwise false
     * @see    LiveUser::triggerEvent
     */
    function attachObserverObj(&$object, $methods = array())
    {
        if (empty($methods)) {
            foreach ($this->_events as $event) {
                if (method_exists($object, $event)) {
                    $methods[$event] = $event;
                }
            }
        }
        foreach ($methods as $event => $method) {
            if (!isset($this->_observers[$event])) {
                $this->_observers[$event] = array();
            }

            $this->_observers[$event][] = array(&$object, $method);
        }
        return true;
    }

    /**
     * Notify all attached observers about a certain event
     *
     * LiveUser object ($this) and $params are set as first and
     * second parameters for each observer notification.
     * $event is always set as 'event' field in $params, so this can
     * not be used as a parameter but is useful if you want to use
     * one single observer callback function for multiple events.
     *
     * @access public
     * @param  string  event name
     * @param  array   optional params to send to observers
     * @return bool    true on success, false otherwise
     * @see    LiveUser::attachObserver(), LiveUser::attachObserverObj(), LiveUser::registerEvent()
     */
    function triggerEvent($event, $params = array())
    {
        if (!isset($this->_observers[$event]) or empty($this->_observers[$event])) {
            if ($GLOBALS['_LIVEUSER_DEBUG']) {
                $this->_stack->push(
                    LIVEUSER_ERROR_UNKNOWN_EVENT,
                    'notice', array('event' => $event),
                    'no observer to notify for event ' . $event);
            }
            // it is no error if no observer was attached to handle an event, so
            return true;
        }

        $params['event'] = $event;
        $success = true;

        $num = count($this->_observers[$event]);
        for ($i = 0; $i < $num; $i++) {
            if (!is_callable($this->_observers[$event][$i])) {
               call_user_func($this->_observers[$event][$i], &$this, $params);
                // no error push here, because it should be pushed by the handler
                $success = false;
            }
        }
        return $success;
    }

    /**
     * make a string representation of the object
     *
     * @access  public
     * @return  string
     */
    function __toString()
    {
        return get_class($this) . ' logged in: ' . ($this->isLoggedIn() ? 'Yes' : 'No');
    } // end func __toString

    /**
     * Return a textual status message for a LiveUser status code.
     *
     * @access  public
     * @param   int     status code
     * @return  string  error message
     */
    function statusMessage($value)
    {
        // make the variable static so that it only has to do the defining on the first call
        static $statusMessages;

        // define the varies error messages
        if (!isset($statusMessages)) {
            $statusMessages = array(
                LIVEUSER_STATUS_OK              => 'No authentication problems detected',
                LIVEUSER_STATUS_IDLED           => 'Maximum idle time is reached',
                LIVEUSER_STATUS_EXPIRED         => 'User session has expired',
                LIVEUSER_STATUS_ISINACTIVE      => 'User is set to inactive',
                LIVEUSER_STATUS_PERMINITERROR   => 'Cannot instantiate permission container',
                LIVEUSER_STATUS_AUTHINITERROR   => 'Cannot instantiate authentication configuration',
                LIVEUSER_STATUS_AUTHNOTFOUND    => 'Cannot retrieve Auth object from session',
                LIVEUSER_STATUS_UNKNOWN         => 'An undefined error occurred',
                LIVEUSER_STATUS_LOGGEDOUT       => 'User was logged out correctly',
                LIVEUSER_STATUS_AUTHFAILED      => 'Cannot authenticate, username/password is probably wrong',
                LIVEUSER_STATUS_UNFROZEN        => 'Object fetched from the session, the user was already logged in'
            );
        }

        // return the textual error message corresponding to the code
        return isset($statusMessages[$value]) ? $statusMessages[$value] : $statusMessages[LIVEUSER_STATUS_UNKNOWN];
    }

    /**
     * This method lazy loads PEAR::Log
     *
     * @access protected
     * @return void
     */
    function loadPEARLog()
    {
        require 'Log.php';
        $this->_log = &Log::factory('composite');
        $this->_stack->setLogger($this->_log);
    }
} // end class LiveUser
?>
