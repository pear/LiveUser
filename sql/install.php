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
 * @package  LiveUser
 * @author  Lukas Smith <smith@pooteeweet.org>
 * @copyright 2002-2005 Markus Wolff
 * @license http://www.gnu.org/licenses/lgpl.txt
 * @version CVS: $Id$
 * @link http://pear.php.net/LiveUser
 */

require_once 'LiveUser.php';
require_once 'MDB2/Schema.php';

/* ATTENTION: uncommented the following lines as needed

// error handler
function handleError($err)
{
   var_dump($err);
   return PEAR_ERRORSTACK_PUSH;
}

PEAR_ErrorStack::setDefaultCallback('handleError');

echo '<pre>';

// customize DSN as needed
$dsn = 'mysql://root:@localhost/liveuser_test_installer';

// customize config array as needed
$conf = array(
    'authContainers' => array(
        array(
            'type'         => 'MDB2',
            'loginTimeout' => 0,
            'expireTime'   => 3600,
            'idleTime'     => 1800,
            'allowDuplicateHandles' => 0,
            'storage' => array(
                'dsn' => $dsn,
                'alias' => array(
                    'auth_user_id' => 'authUserId',
                    'lastlogin' => 'lastLogin',
                    'is_active' => 'isActive',
                    'owner_user_id' => 'owner_user_id',
                    'owner_group_id' => 'owner_group_id',
                ),
                'fields' => array(
                    'lastlogin' => 'timestamp',
                    'is_active' => 'boolean',
                    'owner_user_id' => 'integer',
                    'owner_group_id' => 'integer',
                ),
                'tables' => array(
                    'users' => array(
                        'fields' => array(
                            'lastlogin' => false,
                            'is_active' => false,
                            'owner_user_id' => false,
                            'owner_group_id' => false,
                        ),
                    ),
                ),
            ),
        ),
    ),
    'permContainer'  => array(
        'type'  => 'Complex',
        'storage' => array(
            'MDB2' => array('dsn' => $dsn,
            'prefix' => 'liveuser_')
        )
    )
);

// customize MDB2_SCHEMA configuration options as needed
$options = array(
    'debug' => true,
    'log_line_break' => '<br>',
    'portability' => (MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL),
);

// create instance of the auth container
$auth =& LiveUser::authFactory($conf['authContainers'][0], 'foo');
// generate xml schema file for auth container
$result = LiveUser_Misc_Schema_Install::generateSchema($auth, 'auth_schema.xml');
var_dump($result);

// install the auth xml schema .. notice the 4th parameter controls if the
// database needs to be created or not
$variables = array();
$result = LiveUser_Misc_Schema_Install::installSchema(
    $auth,
    'auth_schema.xml',
    $variables,
    true,
    $options
);
var_dump($result);

// create instance of the perm container
$perm =& LiveUser::storageFactory($conf['permContainer']['storage']);
// generate xml schema file for perm container
$result = LiveUser_Misc_Schema_Install::generateSchema($perm, 'perm_schema.xml');
var_dump($result);

// install the perm xml schema .. notice the 4th parameter controls if the
// database needs to be created or not
$variables = array();
$result = LiveUser_Misc_Schema_Install::installSchema(
    $perm,
    'perm_schema.xml',
    $variables,
    false,
    $options
);
var_dump($result);

*/

class LiveUser_Misc_Schema_Install
{
    function generateSchema($instance, $file, $lengths = array())
    {
        if (!is_object($instance)) {
            return false;
        }

        // generate xml schema
        $tables = array();
        $sequences = array();
        foreach ($instance->tables as $table_name => $table) {
            $fields = array();
            $table_indexes = array();
            foreach($table['fields'] as $field_name => $required) {
                $type = $instance->fields[$field_name];
                $field_name = $instance->alias[$field_name];
                $fields[$field_name]['name'] = $field_name;
                $fields[$field_name]['type'] = $type;
                if ($fields[$field_name]['type'] == 'text') {
                    $length = isset($lengths[$field_name]) ? $lengths[$field_name] : 32;
                    $fields[$field_name]['length'] = $length;
                }

                // check if not null
                if ($required) {
                    $fields[$field_name]['notnull'] = true;
                    // todo set proper defaults on a per type basis .. especially for '*_level'
                    $fields[$field_name]['default'] = '';
                    // Sequences
                    if ($required === 'seq') {
                        $sequences[$instance->prefix . $instance->alias[$table_name]] = array(
                            'on' => array(
                                'table' => $instance->prefix . $instance->alias[$table_name],
                                'field' => $field_name,
                            )
                        );

                        $table_indexes[$table_name.'_'.$field_name] = array(
                            'fields' => array(
                                $field_name => true,
                            ),
                            'unique' => true
                        );
                    // Generate indexes
                    } elseif (is_string($required)) {
                        $table_indexes[$table_name.'_'.$required . '_i']['fields'][$field_name] = true;
                        $table_indexes[$table_name.'_'.$required . '_i']['unique'] = true;
                    }
                }
            }
            $tables[$instance->prefix . $instance->alias[$table_name]]['fields'] = $fields;
            $tables[$instance->prefix . $instance->alias[$table_name]]['indexes'] = $table_indexes;
        }

        $definition = array(
            'name' => '<variable>database</variable>',
            'create' => '<variable>create</variable>',
            'tables' => $tables,
            'sequences' => $sequences,
        );

        return LiveUser_Misc_Schema_Install::writeSchema($definition, $file);
    }

    function writeSchema($definition, $file)
    {
        require_once 'MDB2/Schema/Writer.php';
        $writer =& new MDB2_Schema_Writer();
        $arguments = array(
            'output_mode' => 'file',
            'output' => $file,
        );
        return $writer->dumpDatabase($definition, $arguments);
    }

    function installSchema($obj, $file, $variables = array(), $create = true, $options = array())
    {
        $dsn = $obj->dbc->dsn;
        if (is_a($obj->dbc, 'DB_Common')) {
            $options['seqcol_name'] = 'id';
        } else {
            $dsn['database'] = $obj->dbc->database_name;
        }

        $file_old = $file.'.'.$dsn['hostspec'].'.'.$dsn['database'].'.old';
        $variables['create'] = (int)$create;
        $variables['database'] = $dsn['database'];
        unset($dsn['database']);

        $manager =& MDB2_Schema::factory($dsn, $options);
        if (PEAR::isError($manager)) {
            return $manager;
        }

        $result = $manager->updateDatabase($file, $file_old, $variables);

        $debug = $manager->db->getOption('debug');
        if ($debug && !PEAR::isError($debug)) {
            echo('Debug messages<br>');
            echo($manager->db->debugOutput().'<br>');
        }
        $manager->disconnect();
        return $result;
    }
}

?>
