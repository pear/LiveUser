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

    function init(&$conf, $containerName)
    {
        parent::init($conf, $containerName);

        if (is_array($conf)) {
            if (isset($conf['connection']) &&
                MDB::isConnection($conf['connection'])
            ) {
                $this->dbc     = &$conf['connection'];
            } elseif (isset($conf['dsn'])) {
                $this->dsn = $conf['dsn'];
                $function = null;
                if (isset($conf['function'])) {
                    $function = $conf['function'];
                }
                $options = null;
                if (isset($conf['options'])) {
                    $options = $conf['options'];
                }
                $options['optimize'] = 'portability';
                if ($function == 'singleton') {
                    $this->dbc =& MDB::singleton($conf['dsn'], $options);
                } else {
                    $this->dbc =& MDB::connect($conf['dsn'], $options);
                }
                if (PEAR::isError($this->dbc)) {
                    $this->_stack->push(LIVEUSER_ERROR_INIT_ERROR, 'error',
                        array('container' => 'could not connect: '.$this->dbc->getMessage()));
                    return false;
                }
            }
        }
        return true;
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
            $result = $this->dbc->disconnect();
            if (PEAR::isError($result)) {
                $this->_stack->push(
                    LIVEUSER_ERROR, 'exception',
                    array('reason' => $result->getMessage() . '-' . $result->getUserInfo())
                );
                return false;
            }
            $this->dbc = null;
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
        if (!isset($this->authTableCols['optional']['lastlogin'])) {
            return true;
        }

        $sql  = 'UPDATE ' . $this->authTable.'
                 SET '    . $this->authTableCols['optional']['lastlogin']['name']
                    .'='  . $this->dbc->getValue($this->authTableCols['optional']['lastlogin']['type'], MDB_Date::unix2Mdbstamp($this->currentLogin)) . '
                 WHERE '  . $this->authTableCols['required']['auth_user_id']['name']
                    .'='  . $this->dbc->getValue($this->authTableCols['required']['auth_user_id']['type'], $this->authUserId);

        $result = $this->dbc->query($sql);

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
    function _readUserData($handle, $passwd = '')
    {
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

        if (isset($this->authTableCols['required']['passwd'])
            && $this->authTableCols['required']['passwd']
        ) {
            // If $passwd is set, try to find the first user with the given
            // handle and password.
            $sql .= ' AND '   . $this->authTableCols['required']['passwd']['name'] . '=' .
                $this->dbc->getValue($this->authTableCols['required']['passwd']['type'], $this->encryptPW($passwd));
        }

        // Query database
        $result = $this->dbc->queryRow($sql, $types, MDB_FETCHMODE_ASSOC);

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
            return false;
        }

        $this->handle       = $result['handle'];
        $this->passwd       = $this->decryptPW($result['passwd']);
        $this->authUserId   = $result['auth_user_id'];
        $this->isActive     = ((!isset($result['is_active']) || $result['is_active']) ? true : false);
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

        return true;
    }
}
?>