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
 * XML driver for authentication
 *
 * @package  LiveUser
 * @category authentication
 */

/**
 * Require parent class definition and XML::Tree class.
 */
require_once 'LiveUser/Auth/Common.php';
require_once 'XML/Tree.php';

/**
 * This is a XML backend driver for the LiveUser class.
 *
 * @author  Björn Kraus <krausbn@php.net>
 * @version $Id$
 * @package LiveUser
 * @category authentication
 */
class LiveUser_Auth_XML extends LiveUser_Auth_Common
{
    /**
     * XML file in which the auth data is stored.
     *
     * @access private
     * @var    string
     */
    var $file = '';

    /**
     * XML::Tree object.
     *
     * @access private
     * @var    XML_Tree
     */
    var $tree = null;

    /**
     * XML::Tree object of the user logged in.
     *
     * @access private
     * @var    XML_Tree
     * @see    _readUserData()
     */
    var $userObj = null;

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
            'auth_user_id' => array('name' => 'userId', 'type' => 'text'),
            'handle'       => array('name' => 'handle',       'type' => 'text'),
            'passwd'       => array('name' => 'password',       'type' => 'text'),
        ),
        'optional' => array(
            'lastlogin'    => array('name' => 'lastLogin',    'type' => 'timestamp'),
            'is_active'    => array('name' => 'isActive',    'type' => 'boolean')
        )
    );

    function init(&$conf, $containerName)
    {
        parent::init($conf, $containerName);

        if (is_array($conf)) {
            if (!is_file($this->file)) {
                if (!is_file(getenv('DOCUMENT_ROOT') . $this->file)) {
                    $this->_stack->push(LIVEUSER_ERROR_INIT_ERROR, 'error',
                        array('container' => "Auth initialisation failed. Can't find xml file."));
                    return false;
                }
                $this->file = getenv('DOCUMENT_ROOT') . $this->file;
            }
            if ($this->file) {
                if (class_exists('XML_Tree')) {
                    $tree =& new XML_Tree($this->file);
                    $err =& $tree->getTreeFromFile();
                    if (PEAR::isError($err)) {
                        $this->_stack->push(LIVEUSER_ERROR_INIT_ERROR, 'error',
                            array('container' => 'could not connect: '.$err->getMessage()));
                        return false;
                    }
                    $this->tree = $tree;
                } else {
                    $this->_stack->push(LIVEUSER_ERROR_INIT_ERROR, 'error',
                        array('container' => "Auth initialisation failed. Can't find XML_Tree class."));
                    return falseM
                   ;
                }
            } else {
                $this->_stack->push(LIVEUSER_ERROR_INIT_ERROR, 'error',
                    array('container' => "Auth initialisation failed. Can't find xml file."));
                return false;
            }
        }
        return true;
    }

    /**
     * Properly disconnect from resources
     *
     * @access public
     * @return void
     */
    function disconnect()
    {
        $this->tree = null;
        $this->userObj = null;
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
        $data = array('lastLogin' => $this->currentLogin);

        $index = 0;
        foreach ($this->userObj->children as $value) {
            if (in_array($value->name, array_keys($data))) {
                $el =& $this->userObj->getElement(array($index));
                $el->setContent($data[$value->name]);
            }
            $index++;
        }

        $success = false;
        do {
          $fp = fopen($this->file, 'wb');
          if (!$fp) {
              $errorMsg = "Auth freeze failure. Failed to open the xml file.";
              break;
          }
          if (!flock($fp, LOCK_EX)) {
              $errorMsg = "Auth freeze failure. Couldn't get an exclusive lock on the file.";
              break;
          }
          if (!fwrite($fp, $this->tree->get())) {
              $errorMsg = "Auth freeze failure. Write error when writing back the file.";
              break;
          }
          @fflush($fp);
          $success = true;
        } while (false);

        @flock($fp, LOCK_UN);
        @fclose($fp);

        if (!$success) {
            $this->_stack->push(LIVEUSER_ERROR, 'exception',
                array(), 'Cannot read XML Auth file: '.$errorMsg);
        }

        return $success;
    }

    /**
     *
     * Reads auth_user_id, password from the xml file
     * If only $handle is given, it will read the data
     * from the first user with that handle and return
     * true on success.
     * If $handle and $passwd are given, it will try to
     * find the first user with both handle and passwd
     * matching and return true on success (this allows
     * multiple users having the same handle but different
     * passwords - yep, some people want this).
     * If no match is found, false is being returned.
     *
     * @param string    Handle of the current user.
     * @param mixed     Can be a string with an
     *                  unencrypted pwd or false.
     * @return boolean true on success or false on failure
     */
    function _readUserData($handle, $passwd = '')
    {
        $success = false;
        $index = 0;

        foreach ($this->tree->root->children as $user) {
            $result = array();
            foreach ($user->children as $value) {
                $result[$value->name] = $value->content;
            }

            if (isset($result[$this->authTableCols['required']['handle']['name']]) &&
                $handle === $result[$this->authTableCols['required']['handle']['name']]
            ) {
                if (isset($this->authTableCols['required']['passwd'])
                    && $this->authTableCols['required']['passwd']
                ) {
                    if (isset($result[$this->authTableCols['required']['passwd']['name']]) &&
                        $this->encryptPW($passwd) === $result[$this->authTableCols['required']['passwd']['name']]
                    ) {
                        $success = true;
                        break;
                    } elseif(!$this->allowDuplicateHandles) {
                        // dont look for any further matching handles
                        break;
                    }
                } else {
                    $success = true;
                    break;
                }
            }

            $index++;
        }

        if (!$success) {
            return false;
        }

        // If a user was found, read data into class variables and save
        // the tree object for faster access in the other functions.
        $this->handle       = $result[$this->authTableCols['required']['handle']['name']];
        $this->passwd       = $this->decryptPW($result[$this->authTableCols['required']['passwd']['name']]);
        $this->authUserId   = $result[$this->authTableCols['required']['auth_user_id']['name']];
        $this->isActive     = ((isset($this->authTableCols['optional']['is_active']) && isset($result[$this->authTableCols['optional']['is_active']['name']]))
            ? (bool)$result[$this->authTableCols['optional']['is_active']['name']] : false);
        $this->lastLogin     = ((isset($this->authTableCols['optional']['lastlogin']) && isset($result[$this->authTableCols['optional']['lastlogin']['name']]))
            ? $result[$this->authTableCols['optional']['lastlogin']['name']] : null);
        $this->ownerUserId  = isset($result['ownerUserId']) ? $result['ownerUserId'] : null;
        $this->ownerGroupid = isset($result['ownerGroupId']) ? $result['ownerGroupId'] : null;
        if (isset($this->authTableCols['custom'])) {
            foreach ($this->authTableCols['custom'] as $alias => $value) {
                $alias = strtolower($alias);
                $this->propertyValues['custom'][$alias] = $result[$alias];
            }
        }
        $this->userObj      =& $this->tree->root->getElement(array($index));

        return true;
    }
}
?>