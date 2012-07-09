<?php
#echo $_SERVER["SCRIPT_FILENAME"];
#exit;

define('APP_TOPDIR', realpath(__DIR__ . '/../php'));
define('APP_LIBDIR', realpath(__DIR__ . '/../../vendor/php'));
define('APP_TESTDIR', realpath(__DIR__ . '/../tests/unit-tests/php'));

require_once APP_LIBDIR . '/psr0.autoloader.php';

psr0_autoloader_searchFirst(APP_LIBDIR);
psr0_autoloader_searchFirst(APP_TESTDIR);
psr0_autoloader_searchFirst(APP_TOPDIR);

# Test server
try {
    $api = new Zenya\Api\Server;

// // Test server
// require_once __DIR__ . '../../../dist/zenya-api-server.phar';
// try {
//     $api = new Zenya\Api\Server(require "../../src/data/config.dist.php");

    $api->onRead('/version/:software',
        /**
         * Returns the last version of the :software
         *
         * Blahh blahh...
         *
         * @param string    $software
         * @return array    The array to return to the client
         * @api_role        public
         * @api_cache 10h   julien
         */
        function($software) use ($api) {
            return array(
                'zazz' =>' is pretty',
                $software => exec('git log --pretty="%h %ci" -n1 HEAD')
            );
        }
    )->group('software');

    $api->onRead('/download/:software',
        /**
         * Download the :software
         *
         * @param string    $software
         * @return array    The array to return to the client
         * @api_role        public
         * @api_cahce 3w    julien
         */
        function($software) use ($api) {
            $file = "../../dist/$software";
            if(file_exists($file)) {
                echo file_get_contents($file);
                exit;
            }
            throw new Exception("'$software' doesn't not exist.");
        }
    )->group('software');

    $api->onCreate('/upload/:software',
        /**
         * Upload a new software :software
         *
         * @param string    $software
         * @return array    The array to return to the client
         * @api_role admin
         * @api_purge_cache julien
         */
        function($software) use ($api) {
            throw new Exception("Todo");
        }
    )->group('software');

    $api->onUpdate('/upload/:software',
        /**
         * Update an existing software :software
         *
         * @param string    $software
         * @return array    The array to return to the client
         * @api_role admin
         * @api_purge_cache julien
         */
        function($software) use ($api) {
            throw new Exception("Todo");
        }
    )->group('software');

    echo $api->run();

} catch (\Exception $e) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    die("<h1>500 Internal Server Error</h1>" . $e->getMessage());
}
exit;