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
 * MDB2_Complex container for permission handling
 *
 * @package  LiveUser
 * @category authentication
 */

/**
 * Require parent class definition.
 */
require_once 'LiveUser/Perm/Storage.php';
require_once 'XML/Tree.php';

/**
 * This is a XML backend driver for the LiveUser class.
 *
 * Requirements:
 * - File "Liveuser.php" (contains the parent class "LiveUser")
 * - XML_Parser
 *
 * @author  Lukas Smith <smith@backendmedia.com>
 * @author  Bjoern Kraus <krausbn@php.net>
 * @version $Id$
 * @package LiveUser
 * @category authentication
 */
class LiveUser_Perm_Storage_XML extends LiveUser_Perm_Storage
{
    /**
     * XML file in which the auth data is stored.
     * @var string
     * @access private
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
     * @see    readUserData()
     */
    var $userObj = null;

    function init(&$storageConf)
    {
        parent::init($storageConf);

        if (!is_file($this->file)) {
            if (!is_file(getenv('DOCUMENT_ROOT') . $this->file)) {
                $this->_stack->push(LIVEUSER_ERROR_MISSING_DEPS, 'exception', array(),
                    "Perm initialisation failed. Can't find xml file.");
                return false;
            }
            $this->file = getenv('DOCUMENT_ROOT') . $this->file;
        }
        if (!class_exists('XML_Tree')) {
            $this->_stack->push(LIVEUSER_ERROR_MISSING_DEPS, 'exception', array(),
                "Perm initialisation failed. Can't find XML_Tree class");
            return false;
        }
        $tree =& new XML_Tree($this->file);
        $err =& $tree->getTreeFromFile();
        if (PEAR::isError($err)) {
            $this->_stack->push(LIVEUSER_ERROR, 'exception', array(),
                "Perm initialisation failed. Can't get tree from file");
            return false;
        }
        $this->tree = $tree;
        return true;
    }

    /**
     *
     *
     * @access public
     * @param int $authUserId
     * @param string $containerName
     * @return mixed array or false on failure
     */
    function mapUser($authUserId, $containerName)
    {
        $nodeIndex = 0;
        $userIndex = 0;

        if (isset($this->tree->root->children) && is_array($this->tree->root->children)) {
            foreach ($this->tree->root->children as $node) {
                if ($node->name == 'users') {
                    foreach ($node->children as $user) {
                        if ($user->name == 'user' &&
                            $authUserId == $user->attributes['authUserId'] &&
                            $containerName == $user->attributes['authContainerName']
                        ) {
                            $result['perm_user_id'] = $user->attributes['userId'];
                            $result['perm_type']   = $user->attributes['type'];
                            $this->userObj    =& $this->tree->root->getElement(array($nodeIndex, $userIndex));
                            return $result;
                        }
                        $userIndex++;
                    }
                }
                $nodeIndex++;
            }
        }

        return false;
    }

    /**
     * Reads all rights of current user into a
     * two-dimensional associative array, having the
     * area names as the key of the 1st dimension.
     * Group rights and invididual rights are being merged
     * in the process.
     *
     * @access public
     * @param int $permUserId
     * @return mixed array of false on failure
     */
    function readUserRights($permUserId)
    {
        $result = array();

        foreach ($this->userObj->children as $node) {
            if ($node->name == 'rights') {
                $tmp = explode(',', $node->content);
                foreach ($tmp as $value) {
                    $level = LIVEUSER_MAX_LEVEL;
                    // level syntax: 10(2) => right id 10 at level 2
                    if (preg_match('/(\d+)\((\d+)\)/', $value, $match)) {
                        $value = $match[1];
                        $level = $match[2];
                    }
                    $result[$value] = $level;
                }
            }
        }

        return $result;
    }

    /**
     * properly disconnect from resources
     *
     * @access  public
     */
    function disconnect()
    {
        $this->tree = null;
        $this->userObj = null;
    }
}
?>