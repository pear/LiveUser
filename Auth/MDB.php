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
 * MDB container for Authentication
 *
 * @package  LiveUser
 * @category authentication
 */

/**
 * Require parent class definition and PEAR::MDB class.
 */
require_once 'LiveUser/Auth/Common.php';
require_once 'MDB.php';
MDB::loadFile('Date');

/**
 * This is a PEAR::MDB backend driver for the LiveUser class.
 * A PEAR::MDB connection object can be passed to the constructor to reuse an
 * existing connection. Alternatively, a DSN can be passed to open a new one.
 *
 * Requirements:
 * - File "LiveUser.php" (contains the parent class "LiveUser")
 * - Array of connection options or a PEAR::MDB connection object must be
 *   passed to the constructor.
 *   Example: array('dsn'                   => 'mysql://user:pass@host/db_name',
 *                  'connection             => &$conn, # PEAR::MDB connection object
 *                  'loginTimeout'          => 0,
 *                  'allowDuplicateHandles' => 1);
 *
 * @author   Markus Wolff <wolff@21st.de>
 * @version  $Id$
 * @package  LiveUser
 * @category authentication
 */
class LiveUser_Auth_MDB extends LiveUser_Auth_Common
{
    /**
     * dsn to connect to
     *
     * @access private
     * @var    string
     */
    var $dsn = null;

    /**
     * disconnect
     *
     * @access private
     * @var    boolean
     */
    var $disconnect = false;

    /**
     * PEAR::MDB connection object
     *
     * @access private
     * @var    MDB
     */
    var $dbc = null;

    /**
     * Auth table
     * Table where the auth data is stored.
     *
     * @access public
     * @var    string
     */
    var $authTable = 'liveuser_users';

    /**
     * Columns of the auth table.
     * Associative array with the names of the auth table columns.
     * The 'auth_user_id', 'handle' and 'passwd' fields have to be set.
     * 'lastlogin' and 'is_active' are optional.
     * It doesn't make sense to set only one of the time columns without the
     * other.
     *
     * The type attribute is only useful when using MDB or MDB2.
     *
     * @access public
     * @var    array
     */
    var $authTableCols = array(
        'required' => array(
            'auth_user_id' => array('name' => 'auth_user_id', 'type' => ''),
            'handle'       => array('name' => 'handle',       'type' => ''),
            'passwd'       => array('name' => 'passwd',       'type' => ''),
        ),
        'optional' => array(
            'lastlogin'    => array('name' => 'lastlogin',    'type' => ''),
            'is_active'    => array('name' => 'is_active',    'type' => '')
        )
    );

    /**
     * Class constructor.
     *
     * @access protected
     * @param  array     configuration array
     * @return void
     */
    function LiveUser_Auth_MDB(&$connectOptions, $containerName)
    {
        $this->LiveUser_Auth_Common($connectOptions, $containerName);
        if (is_array($connectOptions)) {
            if (isset($connectOptions['connection']) &&
                MDB::isConnection($connectOptions['connection'])
            ) {
                $this->dbc     = &$connectOptions['connection'];
                $this->init_ok = true;
            } elseif (isset($connectOptions['dsn'])) {
                $this->dsn = $connectOptions['dsn'];
                $function = null;
                if (isset($connectOptions['function'])) {
                    $function = $connectOptions['function'];
                }
                $options = null;
                if (isset($connectOptions['options'])) {
                    $options = $connectOptions['options'];
                }
                $options['optimize'] = 'portability';
                if ($function == 'singleton') {
                    $this->dbc =& MDB::singleton($connectOptions['dsn'], $options);
                } else {
                    $this->dbc =& MDB::connect($connectOptions['dsn'], $options);
                }
                if (!MDB::isError($this->dbc)) {
                    $this->init_ok = true;
                }
            }
        }
    }

    /**
     * Properly disconnect from database
     *
     * @access public
     * @return void
     */
    function disconnect()
    {
        if ($this->disconnect) {
            $this->dbc->disconnect();
            $this->dbc = null;
            $this->init_ok = null;
        }
    }

    /**
     * Writes current values for user back to the database.
     * This method does nothing in the base class and is supposed to
     * be overridden in subclasses according to the supported backend.
     *
     * @access private
     * @return boolean true on success or false on failure
     */
    function _updateUserData()
    {
        if (!$this->init_ok) {
            $this->_stack->push(LIVEUSER_ERROR_INIT_ERROR, 'error', array('container' => get_class($this)));
            return false;
        }

        if (isset($this->authTableCols['optional']['lastlogin'])) {
            $sql  = 'UPDATE ' . $this->authTable.'
                     SET '    . $this->authTableCols['optional']['lastlogin']['name']
                        .'='  . $this->dbc->getValue($this->authTableCols['optional']['lastlogin']['type'], MDB_Date::unix2Mdbstamp($this->currentLogin)) . '
                     WHERE '  . $this->authTableCols['required']['auth_user_id']['name']
                        .'='  . $this->dbc->getValue($this->authTableCols['required']['auth_user_id']['type'], $this->authUserId);

            $res = $this->dbc->query($sql);

            if (MDB::isError($res)) {
                return false;
            }

            return true;
        }
        return false;
    }

    /**
     * Reads auth_user_id, passwd, is_active flag
     * lastlogin timestamp from the database
     * If only $handle is given, it will read the data
     * from the first user with that handle and return
     * true on success.
     * If $handle and $passwd are given, it will try to
     * find the first user with both handle and password
     * matching and return true on success (this allows
     * multiple users having the same handle but different
     * passwords - yep, some people want this).
     * If no match is found, false is being returned.
     *
     * @access private
     * @param  string   user handle
     * @param  boolean  user password
     * @return boolean  true upon success or false on failure
     */
    function _readUserData($handle, $passwd = false)
    {
        if (!$this->init_ok) {
            $this->_stack->push(LIVEUSER_ERROR_INIT_ERROR, 'error', array('container' => get_class($this)));
            return false;
        }
        $success = false;

        $fields = array();
        foreach ($this->authTableCols as $key => $value) {
            if (sizeof($value) > 0) {
                foreach ($value as $alias => $field_data) {
                    $fields[] = $field_data['name'] . ' AS ' . $alias;
                    $types[]  = $field_data['type'];
                }
            }
        }

        // Setting the default query.
        $sql    = 'SELECT ' . implode(',', $fields) . '
                   FROM '   . $this->authTable . '
                   WHERE '  . $this->authTableCols['required']['handle']['name'] . '=' .
                    $this->dbc->getValue($this->authTableCols['required']['handle']['type'], $handle);

        if ($passwd !== false) {
            // If $passwd is set, try to find the first user with the given
            // handle and password.
            $sql .= ' AND '   . $this->authTableCols['required']['passwd']['name'] . '=' .
                $this->dbc->getValue($this->authTableCols['required']['passwd']['type'], $this->encryptPW($passwd));
        }

        // Query database
        $result = $this->dbc->queryRow($sql, $types, MDB_FETCHMODE_ASSOC);

        // If a user was found, read data into class variables and set
        // return value to true
        if (!MDB::isError($result) && is_array($result)) {

            $this->handle       = $result['handle'];
            $this->passwd       = $this->decryptPW($result['passwd']);
            $this->isActive     = ((!isset($result['is_active']) || $result['is_active']) ? true : false);
            $this->authUserId   = $result['auth_user_id'];
            $this->lastLogin    = isset($result['lastlogin'])?
                                    MDB_Date::mdbstamp2Unix($result['lastlogin']):'';
            $this->ownerUserId  = isset($result['owner_user_id']) ? $result['owner_user_id'] : null;
            $this->ownerGroupid = isset($result['owner_group_id']) ? $result['owner_group_id'] : null;
            if (isset($this->authTableCols['custom'])) {
                foreach ($this->authTableCols['custom'] as $alias => $value) {
                    $alias = strtolower($alias);
                    $this->propertyValues['custom'][$alias] = $result[$alias];
                }
            }

            $success = true;
        }
        return $success;
    }

    /**
     * Helper function that checks if there is a user in
     * the database who's matching the given parameters.
     * If $checkHandle is given and $checkPW is set to
     * false, it only checks if a user with that handle
     * exists. If only $checkPW is given and $checkHandle
     * is set to false, it will check if there exists a
     * user with that password. If both values are set to
     * anything but false, it will find the first user in
     * the database with both values matching.
     * Please note:
     * - If no match was found, the return value is false
     * - If a match was found, the auth_user_id from the database
     *   is being returned
     * Whatever is returned, please keep in mind that this
     * function only searches for the _first_ occurence
     * of the search values in the database. So when you
     * have multiple users with the same handle, only the
     * ID of the first one is returned. Same goes for
     * passwords. Searching for both password and handle
     * should be pretty safe, though - having more than
     * one user with the same handle/password combination
     * in the database would be pretty stupid anyway.
     *
     * @param  boolean The handle (username) to search
     * @param  boolean The password to check against
     * @return mixed   true or false if the user does not exist
     */
    function userExists($checkHandle = false, $checkPW = false)
    {
        if (!$this->init_ok) {
            $this->_stack->push(LIVEUSER_ERROR_INIT_ERROR, 'error', array('container' => get_class($this)));
            return false;
        }

        $sql    = 'SELECT ' . $this->authTableCols['required']['auth_user_id']['name'] . '
                   FROM '   . $this->authTable;

        if ($checkHandle !== false && $checkPW === false) {
            // only search for the first user with the given handle
            $sql .= 'WHERE '  . $this->authTableCols['required']['handle']['name'] . '=' .
                $this->dbc->getValue($this->authTableCols['required']['handle']['type'], $checkHandle);
        } elseif ($checkHandle === false && $checkPW !== false) {
            // only search for the first user with the given password
            $sql .= 'WHERE '  . $this->authTableCols['required']['passwd']['name'] . '=' .
                $this->dbc->getValue($this->authTableCols['required']['passwd']['type'], $this->encryptPW($checkPW));
        } else {
            // check for a user with both handle and password matching
            $sql .= 'WHERE ' . $this->authTableCols['required']['handle']['name'] . '=' .
                $this->dbc->getValue($this->authTableCols['required']['handle']['type'], $checkHandle) . '
                     AND '   . $this->authTableCols['required']['passwd']['name'] . '=' .
                        $this->dbc->getValue($this->authTableCols['required']['passwd']['type'], $this->encryptPW($checkPW));
        }

        $result = $this->dbc->queryOne($sql, $this->authTableCols['required']['auth_user_id']['type']);

        if (MDB::isError($result)) {
            return false;
        }

        if (is_null($result)) {
            return false;
        }

        return true;
    }
}
?>