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
 * @author  Lukas Smith <smith@backendmedia.com>
 * @copyright 2002-2005 Markus Wolff
 * @license http://www.gnu.org/licenses/lgpl.txt
 * @version CVS: $Id$
 * @link http://pear.php.net/LiveUser
 */

require_once 'LiveUser.php';
require_once 'MDB2/Schema.php';

/*
$dsn = 'mysql://root:@localhost/liveuser_test_installer';

$conf = array(
    'authContainers' => array(
        array(
            'type'          => 'MDB2',
            'dsn'           => $dsn,
            'authTable'     => 'liveuser_users',
            'authTableCols' => array(
                'required'  => array(
                    'auth_user_id' => array('name' => 'authUserId', 'type' => 'text'),
                    'handle'       => array('name' => 'handle',     'type' => 'text'),
                    'passwd'       => array('name' => 'passwd',     'type' => 'text'),
                ),
                'optional' => array(
                    'owner_user_id'  => array('name' => 'owner_user_id',    'type' => 'integer'),
                    'owner_group_id' => array('name' => 'owner_group_id',   'type' => 'integer'),
                    'lastlogin'      => array('name' => 'lastLogin',        'type' => 'timestamp'),
                    'is_active'      => array('name' => 'isActive',         'type' => 'boolean')
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

$options = array(
    'debug' => true,
    'log_line_break' => '<br>',
    'portability' => (MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL),
);

$result = LiveUser_Misc_Schema_Install::generateAuthSchema(
    $conf['authContainers'][0],
    'auth_schema.xml'
);
var_dump($result);

$variables = array();
$result = LiveUser_Misc_Schema_Install::installSchema(
    $conf['authContainers'][0]['dsn'],
    'auth_schema.xml',
    $variables,
    true,
    $options
);
var_dump($result);

$result = LiveUser_Misc_Schema_Install::generatePermSchema(
    $conf['permContainer']['storage'],
    'perm_schema.xml'
);
var_dump($result);

$variables = array();
$result = LiveUser_Misc_Schema_Install::installSchema(
    $conf['permContainer']['storage']['MDB2']['dsn'],
    'perm_schema.xml',
    $variables,
    false,
    $options
);
var_dump($result);
*/

class LiveUser_Misc_Schema_Install
{
    function generateAuthSchema($config, $file)
    {
        $auth =& LiveUser::authFactory($config, 'foo');
        if (!$auth) {
            return false;
        }

        $dsn = $auth->dbc->dsn;
        if (is_a($auth->dbc, 'DB_Common')) {
            $options['seqcol_name'] = 'id';
        } else {
            $dsn['database'] = $auth->dbc->database_name;
        }

        // generate xml schema
        $fields = array();

        if (isset($auth->authTableCols['required']) &&
            is_array($auth->authTableCols['required'])
        ) {
            foreach($auth->authTableCols['required'] as $key => $value) {
                $fields[$value['name']] = $value;
                if ($value['type'] == 'text') {
                    $fields[$value['name']]['length'] = 32;
                }
                $fields[$value['name']]['notnull'] = true;
                // todo set proper defaults on a per type basis
                $fields[$value['name']]['default'] = '';
            }
        }

        if (isset($auth->authTableCols['optional']) &&
            is_array($auth->authTableCols['optional'])
        ) {
            foreach($auth->authTableCols['optional'] as $key => $value) {
                $fields[$value['name']] = $value;
                if ($value['type'] == 'text') {
                    $fields[$value['name']]['length'] = 32;
                }
            }
        }

        if (isset($auth->authTableCols['custom']) &&
            is_array($auth->authTableCols['custom'])
        ) {
            foreach($auth->authTableCols['custom'] as $key => $value) {
                $fields[$value['name']] = $value;
                if ($value['type'] == 'text') {
                    $fields[$value['name']]['length'] = 32;
                }
            }
        }

        $definition = array(
            'name' => '<variable>database</variable>',
            'create' => '<variable>create</variable>',
            'tables' => array(
                $auth->authTable => array(
                    'fields' => $fields,
                    'indexes' => array(
                        $auth->authTableCols['required']['auth_user_id']['name'] => array(
                            'fields' => array(
                                $auth->authTableCols['required']['auth_user_id']['name'] => true,
                            ),
                           'unique' => true,
                        ),
                    ),
                ),
            ),
            'sequences' => array(
                $auth->authTable => array(
                    'on' => array(
                        'table' => $auth->authTable,
                        'field' => $auth->authTableCols['required']['auth_user_id']['name'],
                    )
                ),
            ),
        );

        if (!LiveUser_Misc_Schema_Install::writeSchema($definition, $file)) {
            return false;
        }
        return true;
    }

    function generatePermSchema($config, $file)
    {
        $perm =& LiveUser::storageFactory($config);
        if (!$perm) {
            return false;
        }

        $dsn = $perm->dbc->dsn;
        if (is_a($perm->dbc, 'DB_Common')) {
            $options['seqcol_name'] = 'id';
        } else {
            $dsn['database'] = $perm->dbc->database_name;
        }

        // generate xml schema
        $tables = array();
        $sequences = array();
        foreach ($perm->tables as $table_name => $table) {
            $fields = array();
            $table_indexes = array();
            foreach($table['fields'] as $field_name => $required) {
                $fields[$field_name]['name'] = $perm->alias[$field_name];
                $fields[$field_name]['type'] = $perm->fields[$field_name];
                if ($fields[$field_name]['type'] == 'text') {
                    $fields[$field_name]['length'] = 32;
                }

                // check if not null
                if ($required) {
                    $fields[$field_name]['notnull'] = true;
                    // todo set proper defaults on a per type basis .. especially for '*_level'
                    $fields[$field_name]['default'] = '';
                    // Sequences
                    if ($required == 'seq') {
                        $sequences[$perm->prefix . $table_name] = array(
                            'on' => array(
                                'table' => $perm->prefix . $table_name,
                                'field' => $perm->alias[$field_name],
                            )
                        );

                        $table_indexes[$perm->alias[$field_name]] = array(
                            'fields' => array(
                                $perm->alias[$field_name] => true,
                            ),
                            'unique' => true
                        );
                    // Generate indexes
                    } elseif (is_string($required)) {
                        $table_indexes[$required . '_i']['fields'][$perm->alias[$field_name]] = true;
                        $table_indexes[$required . '_i']['unique'] = true;
                    }
                }
            }
            $tables[$perm->prefix . $table_name]['fields'] = $fields;
            $tables[$perm->prefix . $table_name]['indexes'] = $table_indexes;
        }

        $definition = array(
            'name' => '<variable>database</variable>',
            'create' => '<variable>create</variable>',
            'tables' => $tables,
            'sequences' => $sequences,
        );

        if (!LiveUser_Misc_Schema_Install::writeSchema($definition, $file)) {
            return false;
        }
        return true;
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

    function installSchema($dsn, $file, $variables, $create = true, $options = array())
    {
        $manager =& new MDB2_Schema;
        $dsn = MDB2::parseDSN($dsn);
        $file_old = $file.'.'.$dsn['hostspec'].'.'.$dsn['database'].'.old';
        $variables['create'] = (int)$create;
        $variables['database'] = $dsn['database'];
        unset($dsn['database']);

        $err = $manager->connect($dsn, $options);
        if (MDB2::isError($err)) {
            return $err;
        }

        $result = $manager->updateDatabase($file, $file_old, $variables);

        $debug = $manager->getOption('debug');
        if ($debug && !PEAR::isError($debug)) {
            echo('Debug messages<br>');
            echo($manager->db->debugOutput().'<br>');
        }
        $manager->disconnect();
        return $result;
    }
}

?>
