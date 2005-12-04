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

$version = '0.16.8';

$notes = <<<EOT
- clearer status and error messages
- fix a bug with the passed Log object being discarded
- extra debug info when the auth container is instantiated
- more helpful error message when the class cannot be loaded
- make the PEAR::Auth wrapper use the passed handle and password
- fixed phpdoc typo in singleton method (bug #5668)
- fixed ability to call singleton() with only the conf parameter set, even if
  singleton was never called before (bug #5669)
- fixed issue in factoryStorage() that would lead to modifying the config array (bug #5526)
- added ability to disable executing the sql commands on installSchema()
- set status after logging out not before
- tweaked error messages for failed factory method calls
- fix for calling singleton without a signature string (bug #5905)
- attempt at checking if it is safe to start the session, add an error to the stack if not and return
- minor performance tweak in login()
- reordered code inside login() to make onFailedMapping events more powerful
- improved handling of INACTIVE status
- stop using backendArrayIndex infavor of containerName property in the auth instance
- removed loginTimeout feature (disable lastlogin if you are concerned about
  the cost of updating the lastlogin time)
- handle option user data properties in readUserData() in the PEAR::Auth wrapper
- added a few return true's for method that returned void so far
- tons of phpdoc and whitespace fixes and additions
- add missing css file in example5
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
        'sql/Auth_XML.xml'           => 'misc/Auth_XML.xml',
        'sql/Perm_XML.xml'           => 'misc/Perm_XML.xml',
        'sql/README'                 => 'misc/schema/README',
        'sql/install.php'            => 'misc/schema/install.php',
    ),
    'exceptions'         => array(
        'lgpl.txt' => 'doc'
    ),
    'dir_roles'         => array('sql'               => 'data',
                                 'docs'              => 'doc',
                                 'scripts'           => 'script')
));

if (PEAR::isError($result)) {
    echo $result->getMessage();
}

$package->addMaintainer('mw21st',  'lead',      'Markus Wolff',      'mw21st@php.net');
$package->addMaintainer('arnaud',  'lead',      'Arnaud Limbourg',   'arnaud@php.net');
$package->addMaintainer('lsmith',  'lead',      'Lukas Kahwe Smith', 'smith@pooteeweet.org');
$package->addMaintainer('krausbn', 'developer', 'Bjoern Kraus',      'krausbn@php.net');
$package->addMaintainer('dufuz',   'lead',      'Helgi Thormar',     'dufuz@php.net');

$package->addDependency('php',              '4.2.0',      'ge',  'php', false);
$package->addDependency('PEAR',             '1.3.3',      'ge',  'pkg', false);
$package->addDependency('Event_Dispatcher', false,        'has', 'pkg', false);
$package->addDependency('Log',              '1.7.0',      'ge',  'pkg', true);
$package->addDependency('DB',               '1.6.0',      'ge',  'pkg', true);
$package->addDependency('MDB',              '1.1.4',      'ge',  'pkg', true);
$package->addDependency('MDB2',             '2.0.0beta4', 'ge',  'pkg', true);
$package->addDependency('MDB2_Schema',      false,        'has', 'pkg', true);
$package->addDependency('XML_Tree',         false,        'has', 'pkg', true);
$package->addDependency('Crypt_RC4',        false,        'has', 'pkg', true);

if (array_key_exists('make', $_GET) || (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'make')) {
    $result = $package->writePackageFile();
} else {
    $result = $package->debugPackageFile();
}

if (PEAR::isError($result)) {
    echo $result->getMessage();
}

echo "package.xml generated successfully!\n";
