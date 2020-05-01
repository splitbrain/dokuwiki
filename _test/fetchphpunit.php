#!/usr/bin/php
<?php
/**
 * Simple command line script to fetch the correct PHPUnit release
 */

$phpVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
print "Running PHP $phpVersion\n";

switch ($phpVersion) {
    case '5.6':
        $phpunit = 'phpunit-5.phar';
        break;
    case '7.0':
        $phpunit = 'phpunit-6.phar';
        break;
    case '7.1':
    case '7.2':
    case '7.3':
        $phpunit = 'phpunit-7.phar';
        break;
    case '7.4':
    case '7.4snapshot':
        $phpunit = 'phpunit-7.phar';
        // PHP 5 backward compatibility lock to PHPUnit 7 (type hinting)
        break;
    default:
        $phpunit = 'phpunit-7.phar';

}

$url = "https://phar.phpunit.de/$phpunit";
$out = __DIR__ . '/phpunit.phar';

$return = 0;
system("wget '$url' -O '$out'", $return);
if ($return !== 0) exit($return);

chmod($out, 0755);
print "Downloaded $phpunit\n";
exit(0);
