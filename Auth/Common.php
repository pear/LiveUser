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
 * This class provides a set of functions for implementing a user
 * authorisation system on live websites. All authorisation
 * backends/containers must be extensions of this base class.
 *
 * Requirements:
 * - When using "DB" backend:
 *   PEAR::DB database abstraction layer
 * - LiveUser admin GUI for easy user administration and setup of
 *   authorisation areas and rights
 *
 * @author   Markus Wolff <wolff@21st.de>
 * @version  $Id$
 * @package  LiveUser
 * @category authentication
 */
class LiveUser_Auth_Common
{
    /**
     * The handle (username) of the current user
     *
     * @access protected
     * @var    string
     */
    var $handle = '';

    /**
     * The password of the current user as given to the
     * login() method.
     *
     * @access protected
     * @var    string
     */
    var $passwd = '';

    /**
     * Current user's database record id
     *
     * @access protected
     * @var    integer
     */
    var $authUserId = 0;

    /**
     * Is the current user allowed to login at all? If false,
     * a call to login() will not set $logged_in to true, even
     * if handle and password were submitted correctly. This is
     * useful when you want your users to be activated by an
     * administrator before they can actually use your application.
     * Default: false
     *
     * @access protected
     * @var    boolean
     * @see    LiveUser_Auth_Common::loggedIn
     */
    var $isActive = null;

    var $ownerUserId = null;
    var $ownerGroupId = null;

    /**
     * Has the current user successfully logged in?
     * Default: false
     *
     * @access protected
     * @var    boolean
     * @see    LiveUser_Auth_Common::isActive
     */
    var $loggedIn = null;

    /**
     * Timestamp of last login (previous to currentLogin)
     *
     * @access protected
     * @var    integer
     */
    var $lastLogin = 0;

    /**
     * Update the last login time or not
     *
     * @access protected
     * @var    boolean
     */
    var $updateLastLogin = true;

    /**
     * Timestamp of current login (last to be written)
     *
     * @access protected
     * @var    integer
     */
    var $currentLogin = 0;

    /**
     * Number of hours that must pass between two logins
     * to be counted as a new login. Comes in handy in
     * some situations. Default: 12
     *
     * @access protected
     * @var    integer
     */
    var $loginTimeout = 12;

    /**
     * Auth lifetime in seconds
     *
     * If this variable is set to 0, auth never expires
     *
     * @access protected
     * @var    integer
     */
    var $expireTime = 0;

    /**
     * Maximum time of idleness in seconds
     *
     * Idletime gets refreshed each time, init() is called. If this
     * variable is set to 0, idle time is never checked.
     *
     * @access protected
     * @var    integer
     */
    var $idleTime = 0;

    /**
     * Allow multiple users in the database to have the same
     * login handle. Default: false.
     *
     * @access protected
     * @var    boolean
     */
    var $allowDuplicateHandles = false;

    /**
     * Allow empty passwords to be passed to LiveUser. Default: false.
     *
     * @access protected
     * @var    boolean
     */
    var $allowEmptyPasswords = false;

    /**
     * Set posible encryption modes.
     *
     * @access protected
     * @var    array
     */
    var $encryptionModes = array('MD5'   => 'MD5',
                                 'PLAIN' => 'PLAIN',
                                 'RC4'   => 'RC4',
                                 'SHA1'  => 'SHA1');

    /**
     * Defines the algorithm used for encrypting/decrypting
     * passwords. Default: "MD5".
     *
     * @access protected
     * @var    string
     */
    var $passwordEncryptionMode = 'MD5';

    /**
     * Defines the secret to use for encryption if needed
     *
     * @access protected
     * @var    string
     */
    var $secret;

    /**
     * Defines the array index number of the LoginManager?s "backends" property.
     *
     * @access protected
     * @var    integer
     */
    var $backendArrayIndex = 0;

    /**
     * Error stack
     *
     * @access protected
     * @var    PEAR_ErrorStack
     */
    var $_stack = null;

    /**
    * Property values
    *
    * @access public
    * @var array
    */
    var $propertyValues = array();

    /**
     * The name associated with this auth container. The name is used
     * when adding users from this container to the reference table
     * in the permission container. This way it is possible to see
     * from which auth container the user data is coming from.
     *
     * @var    string
     * @access public
     */
    var $containerName = null;

    /**
     * External values to check (config settings)
     *
     * @var    array
     * @access public
     */
    var $externalValues = array();

    /**
     * Columns of the auth table.
     * Associative array with the names of the auth table columns.
     * The 'auth_user_id', 'handle' and 'passwd' fields have to be set.
     * 'lastlogin' and 'is_active' are optional.
     * It doesn't make sense to set only one of the time columns without the
     * other.
     *
     * The type attribute is only useful when using MDB or CAPTCHA.
     *
     * @access public
     * @var    array
     */
    var $authTableCols = array(
        'required' => array(
            'auth_user_id' => array('name' => 'auth_user_id', 'type' => 'text'),
            'handle'       => array('name' => 'handle',       'type' => 'text'),
            'passwd'       => array('name' => 'passwd',       'type' => 'text'),
        ),
        'optional' => array(
            'lastlogin'    => array('name' => 'lastlogin',    'type' => 'timestamp'),
            'is_active'    => array('name' => 'is_active',    'type' => 'boolean')
        )
    );

    /**
     * Class constructor. Feel free to override in backend subclasses.
     *
     * @access protected
     * @var    array     configuration options
     * @return void
     */
    function LiveUser_Auth_Common($connectOptions, $containerName)
    {
        $this->_stack = &PEAR_ErrorStack::singleton('LiveUser');

        $this->containerName = $containerName;
        if (is_array($connectOptions)) {
            foreach ($connectOptions as $key => $value) {
                if (isset($this->$key)) {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * store all properties in an array
     *
     * @access  public
     * @return  array
     */
    function freeze()
    {
        // get values from $this->externalValues['values'] and
        // store them into $this->propertyValues['storedExternalValues']
        $this->setExternalValues();

        $propertyValues = array(
            'handle'       => $this->handle,
            'authUserId'   => $this->authUserId,
            'isActive'     => $this->isActive,
            'loggedIn'     => $this->loggedIn,
            'lastLogin'    => $this->lastLogin,
            'currentLogin' => $this->currentLogin,
            'ownerGroupId' => $this->ownerGroupId,
            'ownerUserId'  => $this->ownerUserId
        );

        $propertyValues['storedExternalValues'] = null;
        if (isset($this->propertyValues['storedExternalValues']) &&
            !empty($this->propertyValues['storedExternalValues'])
        ) {
            $propertyValues['storedExternalValues'] = $this->propertyValues['storedExternalValues'];
        }

        $propertyValues['custom'] = isset($this->propertyValues['custom'])
            ? $this->propertyValues['custom'] : null;

        return $propertyValues;
    }

    /**
     * properly disconnect from resources
     *
     * @access  public
     * @return  void
     */
    function disconnect()
    {
    }

    /**
     * Reinitializes properties
     *
     * @access  public
     * @param   array  $propertyValues
     * @return  boolean
     */
    function unfreeze($propertyValues)
    {
        foreach ($propertyValues as $key => $value) {
            if (is_array($value)) {
                $this->propertyValues[$key] = $value;
            } else {
                $this->{$key} = $value;
            }
        }

        return $this->externalValuesMatch();
    } // end func unfreeze

    /**
     * Decrypts a password so that it can be compared with the user
     * input. Uses the algorithm defined in the passwordEncryptionMode
     * property.
     *
     * @param  string the encrypted password
     * @return string The decrypted password
     */
    function decryptPW($encryptedPW)
    {
        $decryptedPW = 'Encryption type not supported.';

        switch (strtoupper($this->passwordEncryptionMode)) {
            case 'PLAIN':
                $decryptedPW = $encryptedPW;
                break;
            case 'MD5':
                // MD5 can't be decoded, so return the string unmodified
                $decryptedPW = $encryptedPW;
                break;
            case 'RC4':
                $decryptedPW = LiveUser::cryptRC4($decryptedPW, $this->secret, false);
                break;
            case 'SHA1':
                // SHA1 can't be decoded, so return the string unmodified
                $decryptedPW = $encryptedPW;
                break;
        }

        return $decryptedPW;
    }

    /**
     * Encrypts a password for storage in a backend container.
     * Uses the algorithm defined in the passwordEncryptionMode
     * property.
     *
     * @param string  encryption type
     * @return string The encrypted password
     */
    function encryptPW($plainPW)
    {
        $encryptedPW = 'Encryption type not supported.';

        switch (strtoupper($this->passwordEncryptionMode)) {
            case 'PLAIN':
                $encryptedPW = $plainPW;
                break;
            case 'MD5':
                $encryptedPW = md5($plainPW);
                break;
            case 'RC4':
                $encryptedPW = LiveUser::cryptRC4($plainPW, $this->secret, true);
                break;
            case 'SHA1':
                if (!function_exists('sha1')) {
                    $this->_stack->push(LIVEUSER_ERROR_NOT_SUPPORTED,
                        'exception', array(), 'SHA1 function doesn\'t exist. Upgrade your PHP version');
                    return false;
                }
                $encryptedPW = sha1($plainPW);
                break;
        }

        return $encryptedPW;
    }

    /**
     * Checks if there's enough time between lastLogin
     * and current login (now) to count as a new login.
     *
     * @access public
     * @return boolean  true if it is a new login, false if not
     */
    function isNewLogin()
    {
        $meantime = $this->loginTimeout * 3600;
        if (time() >= $this->lastLogin + $meantime) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Tries to make a login with the given handle and password.
     * A user can't login if he's not active.
     *
     * @param string   user handle
     * @param string   user password
     */
    function login($handle, $passwd)
    {
        // Init value: Is user logged in?
        $this->loggedIn = false;
        // Read user data from database
        $success = $this->_readUserData($handle, $passwd);

        // If login is successful (user data has been read)
        if ($success == true) {
            // ...we still need to check if this user is declared active
            if ($this->isActive !== false) {
                // ...and if so, we have a successful login (hooray)!
                $this->loggedIn = true;
                $this->currentLogin = time();
            }

            // In case Login was successful, check if this can be counted
            // as a _new_ login by definition...
            if ($this->updateLastLogin == true &&
                $this->isNewLogin() == true && $this->loggedIn == true
            ) {
                $this->_updateUserData();
            }
        }

        return $success;
    }

    /**
     * Writes current values for user back to the database.
     * This method does nothing in the base class and is supposed to
     * be overridden in subclasses according to the supported backend.
     *
     * @access private
     * @return void
     */
    function _updateUserData()
    {
        $this->_stack->push(LIVEUSER_ERROR_NOT_SUPPORTED, 'exception');
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
     * Again, this does nothing in the base class. The
     * described functionality must be implemented in a
     * subclass overriding this method.
     *
     * @access private
     * @param  string  user handle
     * @param  boolean user password
     * @return void
     */
    function _readUserData($handle, $passwd = '')
    {
        $this->_stack->push(LIVEUSER_ERROR_NOT_SUPPORTED, 'exception',
            array('feature' => '_readUserData'));
        return false;
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
     * Again, this does nothing in the base class. The
     * described functionality must be implemented in a
     * subclass overriding this method.
     *
     * @param  boolean check handle ?
     * @param  boolean check password ?
     * @return mixed  user id when there is a match, false otherwise
     */
    function userExists($checkHandle = false, $checkPW = false)
    {
        $this->_stack->push(LIVEUSER_ERROR_NOT_SUPPORTED, 'exception',
            array('feature' => 'userExists'));
        return false;
    }

    /**
     * Function returns the inquired value if it exists in the class.
     *
     * @param  string   Name of the property to be returned.
     * @return mixed    null, a value or an array.
     */
    function getProperty($what)
    {
        $that = null;
        $lwhat = strtolower($what);
        if (isset($this->$what)) {
            $that = $this->$what;
        } elseif (isset($this->propertyValues['custom'][$lwhat])) {
            $that = $this->propertyValues['custom'][$lwhat];
        }
        return $that;
    }

    /**
     * Creates associative array of values from $externalValues['values'] with $keysToCheck
     *
     * @access public
     */
    function setExternalValues()
    {
        if (isset($this->externalValues['keysToCheck']) &&
            is_array($this->externalValues['keysToCheck'])
        ) {
            foreach ($this->externalValues['keysToCheck'] as $keyToCheck) {
                if (isset($this->externalValues['values'][$keyToCheck])) {
                    $this->propertyValues['storedExternalValues'][$keyToCheck] =
                        md5($this->externalValues['values'][$keyToCheck]);
                }
            }
        }
    }

    /**
     * Check if the stored external values match the current external values
     *
     * @access  public
     * @param   array   $values
     */
    function externalValuesMatch()
    {
        if (isset($this->propertyValues['storedExternalValues']) &&
            is_array($this->propertyValues['storedExternalValues'])
        ) {
            foreach ($this->propertyValues['storedExternalValues'] as $keyToCheck => $storedValue) {
                // return false if any one of the stored values does not match the current value
                if (!isset($this->externalValues['values'][$keyToCheck]) ||
                    md5($this->externalValues['values'][$keyToCheck]) != $storedValue
                ) {
                    return false;
                }
            }
        }
        return true;
    }
}
?>
