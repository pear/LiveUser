<?php
/**
 * Script to generate package.xml file
 *
 * Taken from PEAR::Log, thanks Jon ;)
 *
 * $Id$
 */
require_once 'PEAR/PackageFileManager.php';
require_once 'Console/Getopt.php';

$version = '0.14.0';

$notes = <<<EOT
  - DB containers no longer use CASE in the sql query.
  - BC break! [de]activateGroup was removed in favor of using the optional array for handling that.
  - BC break! getUsersFromGroup removed in favor of people using searchUsers/getUsers with the option where_group_id
  - lazy loading of PEAR::Log
  - getProperty can now handle fooBar fieldnames, on the auth side.
  - removeUsersFromGroup implanted in Admin Perm *_medium containers
  - removeGroup now uses removeUsersFromGroup as well as revokeGroupRight (previously had it's own identical implantion of those functions)
  - Request #2038 implanted, Admin/Perm getUsers can now filter also by auth_user_id in addition to perm_user_id and group_id
  - Fixed Bug #2672, #2713, #2714 Typo fixes
  - Bug #2692 some SQL files go installed under pear_folder/LiveUser/sql/ which are from now on installed in pear_folder/LiveUser/misc/schema/
  - fixed regenid option
  - refactored client part to the new layout (using storage containers) .. fixed example2 to use the new config layout
  - disabled perm caching by default
  - added sessionName param to (un)freeze method calls to the perm container
  - removed options of reading parameters from the superglobals
  - reworked init() method severly
  - remove the callback that catches PEAR_Error errors
  - adding getErrors() method to get the error stack
  - added LiveUser::checkGroup() in order to check for membership in groups
  - added second auth container to example4
  - added 'session_cookie_params' option
  - LiveUser now expects a true from the auth container unfreeze() method
EOT;

$description = <<<EOT
  LiveUser is a set of classes for dealing with user authentication
  and permission management. Basically, there are three main elements that
  make up this package:

  * The LiveUser class
  * The Auth containers
  * The Perm containers

  The LiveUser class takes care of the login process and can be configured
  to use a certain permission container and one or more different auth containers.
  That means, you can have your users' data scattered amongst many data containers
  and have the LiveUser class try each defined container until the user is found.
  For example, you can have all website users who can apply for a new account online
  on the webserver's local database. Also, you want to enable all your company's
  employees to login to the site without the need to create new accounts for all of
  them. To achieve that, a second container can be defined to be used by the LiveUser class.

  You can also define a permission container of your choice that will manage the rights for
  each user. Depending on the container, you can implement any kind of permission schemes
  for your application while having one consistent API.

  Using different permission and auth containers, it's easily possible to integrate
  newly written applications with older ones that have their own ways of storing permissions
  and user data. Just make a new container type and you're ready to go!

  Currently available are containers using:
  PEAR::DB, PEAR::MDB, PEAR::MDB2, PEAR::XML_Tree and PEAR::Auth.
EOT;

$package = new PEAR_PackageFileManager();

$result = $package->setOptions(array(
    'package'           => 'LiveUser',
    'summary'           => 'User authentication and permission management framework',
    'description'       => $description,
    'version'           => $version,
    'state'             => 'beta',
    'license'           => 'LGPL',
    'filelistgenerator' => 'cvs',
    'ignore'            => array('package.php', 'package.xml', 'TODO', 'DefineGenerator'),
    'notes'             => $notes,
    'changelogoldtonew' => false,
    'simpleoutput'      => true,
    'baseinstalldir'    => '/LiveUser',
    'packagedirectory'  => './',
    'installexceptions' => array(
        'LiveUser.php'            => '/',
    ),
    'installas'         => array(
        'sql/Auth_DB.sql'         => 'misc/schema/Auth_DB.sql',
        'sql/Auth_XML.xml'        => 'misc/schema/Auth_XML.xml',
        'sql/perm_db.sql'         => 'misc/schema/perm_db.sql',
        'sql/Perm_XML.xml'        => 'misc/schema/Perm_XML.xml',
        'sql/auth_mdb_schema.xml' => 'misc/schema/auth_mdb_schema.xml',
        'sql/README' => 'misc/schema/README',
        'sql/perm_db_simple.sql' => 'misc/schema/perm_db_simple.sql',
        'sql/perm_db_medium.sql' => 'misc/schema/perm_db_medium.sql',
        'sql/perm_db_complex.sql' => 'misc/schema/perm_db_complex.sql',
    ),
    'exceptions'         => array(
        'lgpl.txt' => 'doc',
    ),
    'dir_roles'         => array('sql'               => 'data',
                                 'docs'              => 'doc',
                                 'scripts'           => 'script')
));

if (PEAR::isError($result)) {
    echo $result->getMessage();
}

$package->addMaintainer('mw21st',  'lead',        'Markus Wolff',      'mw21st@php.net');
$package->addMaintainer('arnaud',  'lead',        'Arnaud Limbourg',   'arnaud@php.net');
$package->addMaintainer('lsmith',  'lead',        'Lukas Kahwe Smith', 'smith@backendmedia.com');
$package->addMaintainer('krausbn', 'developer',   'Bjoern Kraus',      'krausbn@php.net');
$package->addMaintainer('dufuz',   'developer',   'Helgi Şormar',      'dufuz@php.net');

$package->addDependency('php',       '4.2.0', 'ge',  'php', false);
$package->addDependency('PEAR',      '1.3.1',   'ge', 'pkg', false);
$package->addDependency('Log',       '1.7.0',   'ge',  'pkg', true);
$package->addDependency('DB',        '1.6.0',   'ge',  'pkg', true);
$package->addDependency('MDB',       '1.1.4', 'ge',  'pkg', true);
$package->addDependency('MDB2',      '2.0.0beta2', 'ge', 'pkg', true);
$package->addDependency('XML_Tree',  false,   'has', 'pkg', true);
$package->addDependency('Crypt_RC4', false,   'has', 'pkg', true);

if (isset($_GET['make']) || (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'make')) {
    $result = $package->writePackageFile();
} else {
    $result = $package->debugPackageFile();
}

if (PEAR::isError($result)) {
    echo $result->getMessage();
}
