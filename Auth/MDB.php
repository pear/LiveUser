<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * A framework for authentication and authorization in PHP applications
 *
 * LiveUser is an authentication/permission framework designed
 * to be flexible and easily extendable.
 *
 * Since it is impossible to have a
 * "one size fits all" it takes a container
 * approach which should enable it to
 * be versatile enough to meet most needs.
 *
 * PHP version 4 and 5
 *
 * LICENSE: This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston,
 * MA  02111-1307  USA
 *
 *
 * @category authentication
 * @package LiveUser
 * @author  Markus Wolff <wolff@21st.de>
 * @author  Helgi �ormar �orbj�rnsson <dufuz@php.net>
 * @author  Lukas Smith <smith@pooteeweet.org>
 * @author  Arnaud Limbourg <arnaud@php.net>
 * @author  Pierre-Alain Joye <pajoye@php.net>
 * @author  Bjoern Kraus <krausbn@php.net>
 * @copyright 2002-2005 Markus Wolff
 * @license http://www.gnu.org/licenses/lgpl.txt
 * @version CVS: $Id$
 * @link http://pear.php.net/LiveUser
 */

/**
 * Require parent class definition and PEAR::MDB class.
 */
require_once 'LiveUser/Auth/Common.php';
require_once 'MDB.php';
MDB::loadFile('Date');

/**
 * MDB container for Authentication
 *
 * This is a PEAR::MDB backend driver for the LiveUser class.
 * A PEAR::MDB connection object can be passed to the constructor to reuse an
 * existing connection. Alternatively, a DSN can be passed to open a new one.
 *
 * Requirements:
 * - File "LiveUser.php" (contains the parent class "LiveUser")
 * - Array of connection options or a PEAR::MDB connection object must be
 *   passed to the constructor.
 *   Example: array('dsn' => 'mysql://user:pass@host/db_name',
 *                  'dbc' => &$conn, # PEAR::MDB connection object);
 *
 * @category authentication
 * @package LiveUser
 * @author   Markus Wolff <wolff@21st.de>
 * @copyright 2002-2005 Markus Wolff
 * @license http://www.gnu.org/licenses/lgpl.txt
 * @version Release: @package_version@
 * @link http://pear.php.net/LiveUser
 */
class LiveUser_Auth_MDB extends LiveUser_Auth_Common
{
    /**
     * dsn that was connected to
     *
     * @var    string
     * @access private
     */
    var $dsn = false;

    /**
     * Database connection object.
     *
     * @var    object
     * @access private
     */
    var $dbc = false;

    /**
     * Database connection options.
     *
     * @var    object
     * @access private
     */
    var $options = array();

    /**
     * Database connection functions
     *
     * @var    object
     * @access private
     */
    var $function = 'connect';
    /**
     * Table prefix
     * Prefix for all db tables the container has.
     *
     * @var    string
     * @access public
     */
    var $prefix = 'liveuser_';

    /**
     * Load the storage container
     *
     * @param array   Name of array containing the configuration.
     * @param string  name of the container that should be used
     * @return bool true on success or false on failure
     *
     * @access public
     */
    function init(&$conf, $containerName)
    {
        parent::init($conf, $containerName);

        if (!MDB::isConnection($this->dbc) && !is_null($this->dsn)) {
            $this->options['optimize'] = 'portability';
            if ($function == 'singleton') {
                $dbc =& MDB::singleton($this->dsn, $this->options);
            } else {
                $dbc =& MDB::connect($this->dsn, $this->options);
            }
            if (PEAR::isError($dbc)) {
                $this->_stack->push(LIVEUSER_ERROR_INIT_ERROR, 'error',
                    array('container' => 'could not connect: '.$dbc->getMessage(),
                    'debug' => $dbc->getUserInfo()));
                return false;
            }
            $this->dbc =& $dbc;
        }

        if (!MDB::isConnection($this->dbc)) {
            $this->_stack->push(LIVEUSER_ERROR_INIT_ERROR, 'error',
                array('container' => 'storage layer configuration missing'));
            return false;
        }

        return true;
    }

    /**
     * Writes current values for user back to the database.
     *
     * @return bool true on success or false on failure
     *
     * @access private
     */
    function _updateUserData()
    {
        if (!isset($this->tables['users']['fields']['lastlogin'])) {
            return true;
        }

        $query  = 'UPDATE ' . $this->prefix . $this->alias['users'].'
                 SET '    . $this->alias['lastlogin']
                    .'='  . $this->dbc->getValue($this->fields['lastlogin'], MDB_Date::unix2Mdbstamp($this->currentLogin)) . '
                 WHERE '  . $this->alias['auth_user_id']
                    .'='  . $this->dbc->getValue($this->fields['auth_user_id'], $this->propertyValues['auth_user_id']);

        $result = $this->dbc->query($query);

        if (PEAR::isError($result)) {
            $this->_stack->push(
                LIVEUSER_ERROR, 'exception',
                array('reason' => $result->getMessage() . '-' . $result->getUserInfo())
            );
            return false;
        }

        return true;
    }

    /**
     * Reads user data from the given data source
     * If only $handle is given, it will read the data
     * from the first user with that handle and return
     * true on success.
     * If $handle and $passwd are given, it will try to
     * find the first user with both handle and password
     * matching and return true on success (this allows
     * multiple users having the same handle but different
     * passwords - yep, some people want this).
     * if only an auth_user_id is passed it will try to read the data based on the id
     * If no match is found, false is being returned.
     *
     * @param  string user handle
     * @param bool user password
     * @param  string auth user id
     * @return bool true on success or false on failure
     *
     * @access public
     */
    function readUserData($handle = '', $passwd = '', $auth_user_id = false)
    {
        $fields = $types = array();
        foreach ($this->tables['users']['fields'] as $field => $req) {
            $fields[] = $this->alias[$field] . ' AS ' . $field;
            $types[] = $this->fields[$field];
        }

        // Setting the default query.
        $query = 'SELECT ' . implode(',', $fields) . '
                   FROM '   . $this->prefix . $this->alias['users'] . '
                   WHERE  ';
        if ($auth_user_id) {
            $query .= $this->alias['auth_user_id'] . '='
                . $this->dbc->getValue($this->fields['auth_user_id'], $this->propertyValues['auth_user_id']);
        } else {
            $query .= $this->alias['handle'] . '='
                . $this->dbc->getValue($this->fields['handle'], $handle);

            if (!is_null($this->tables['users']['fields']['passwd'])) {
                // If $passwd is set, try to find the first user with the given
                // handle and password.
                $query .= ' AND   ' . $this->alias['passwd'] . '='
                    . $this->dbc->getValue($this->fields['passwd'], $this->encryptPW($passwd));
            }
        }

        // Query database
        $result = $this->dbc->queryRow($query, $types, MDB_FETCHMODE_ASSOC);

        // If a user was found, read data into class variables and set
        // return value to true
        if (PEAR::isError($result)) {
            $this->_stack->push(
                LIVEUSER_ERROR, 'exception',
                array('reason' => $result->getMessage() . '-' . $result->getUserInfo())
            );
            return false;
        }

        if (!is_array($result)) {
            return null;
        }

        if (array_key_exists('lastlogin', $result) && !empty($result['lastlogin'])) {
            $result['lastlogin'] = MDB_Date::mdbstamp2Unix($result['lastlogin']);
        }
        $this->propertyValues = $result;

        return true;
    }

    /**
     * Properly disconnect from database
     *
     * @return bool true on success or false on failure
     *
     * @access public
     */
    function disconnect()
    {
        if ($this->dsn) {
            $result = $this->dbc->disconnect();
            if (PEAR::isError($result)) {
                $this->_stack->push(
                    LIVEUSER_ERROR, 'exception',
                    array('reason' => $result->getMessage() . '-' . $result->getUserInfo())
                );
                return false;
            }
            $this->dbc = false;
        }
        return true;
    }
}
?>
