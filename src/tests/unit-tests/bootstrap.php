<?php

// =========================================================================
//
// tests/bootstrap.php
//		A helping hand for running our unit tests
//
// Author	Stuart Herbert
//		(stuart@stuartherbert.com)
//
// Copyright	(c) 2011 Stuart Herbert
//		Released under the New BSD license
//
// =========================================================================

// step 1: create the APP_TOPDIR constant that all components require
define('APP_TOPDIR', realpath(__DIR__ . '/../../php'));
define('APP_LIBDIR', realpath(__DIR__ . '/../../../vendor/php'));
define('APP_TESTDIR', realpath(__DIR__ . '/php'));

$app_libdir = realpath(__DIR__ . '/../../../vendor/php');

require_once($app_libdir . '/psr0.autoloader.php');

// step 2: find the autoloader, and install it
#require_once(APP_LIBDIR . '/psr0.autoloader.php');

// step 3: add the additional paths to the include path
psr0_autoloader_searchFirst(APP_LIBDIR);
psr0_autoloader_searchFirst(APP_TESTDIR);
psr0_autoloader_searchFirst(APP_TOPDIR);

// step 4: enable ContractLib if it is available
if (class_exists('Phix_Project\ContractLib\Contract')) {
        \Phix_Project\ContractLib\Contract::EnforceWrappedContracts();
}
