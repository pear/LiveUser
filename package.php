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

$version = '0.15.0';

$notes = <<<EOT
general notes
  - moved all config parameter handling inside the containers into init() methods
  - reworked RC4 handling into cryptRC4() method
  - fixed bug in LiveUser_Perm_Storage_XML::mapUser() method which would result
    in read issues if the user is not the first user in the xml file
  - moved authTableCols from the database containers into common
  - added conversion of PEAR errors to error stack in several places
  - disable password checks in the auth containters if password is set to false
    in the authTableCols config option
  - some cleanups and refactoring to add support for the authTableCols fields
    in the XML container like in the database containers
  - added Session auth container that checks a password as set inside the
    session. this could be useful in combination with a CAPTCHA
  - use LIVEUSER_ERROR constant instead of LIVEUSER_ADMIN_ERROR_QUERY_BUILDER
  - added allowEmptyPasswords auth container option
  - added install.php class to handle database schema installation via the MDB2
    schema manager to provide support for other RDBMS than only MySQL
  - removed unused userExists() auth container method (use admin interface instead)
  - accept all config parameters by reference inside the containers
  - move readAreaAdminsAreas over to Complex where it should be.

perm schema structure
  - moved default database structure into separate file (using the GLOBALS super globals)
  - default datatype for auth_user_id should be 'text' (thx Matthias aka Nomatt for spotting)
  - table rights_implied should have been right_implied in the perm schema structure
  - removed has_level from the database schema
  - added area_admin_areas and all it's joins
  - fixed remember me feature in example2
  - remove empty placeholders and block in loadTemplate() calls in example4

examples
  - fixed bug in example1 onLogout -> postLogout (bug #3135)
  - fixed php5 issues in example4
  - all examples now have a unique database name by default
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
        'sql/Auth_DB.sql'           => 'misc/schema/Auth_DB.sql',
        'sql/Auth_XML.xml'          => 'misc/schema/Auth_XML.xml',
        'sql/perm_db.sql'           => 'misc/schema/perm_db.sql',
        'sql/Perm_XML.xml'          => 'misc/schema/Perm_XML.xml',
        'sql/auth_mdb_schema.xml'   => 'misc/schema/auth_mdb_schema.xml',
        'sql/README'                => 'misc/schema/README',
        'sql/perm_db_simple.sql'    => 'misc/schema/perm_db_simple.sql',
        'sql/perm_db_medium.sql'    => 'misc/schema/perm_db_medium.sql',
        'sql/perm_db_complex.sql'   => 'misc/schema/perm_db_complex.sql',
        'sql/install.php'           => 'misc/schema/install.php',
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
$package->addMaintainer('dufuz',   'developer',   'Helgi Þormar',      'dufuz@php.net');

$package->addDependency('php',       '4.2.0', 'ge',  'php', false);
$package->addDependency('PEAR',      '1.3.3',   'ge', 'pkg', false);
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
