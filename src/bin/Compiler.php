<?php
/**
 * Copyright (c) 2011 Franck Cassedanne, Zenya.com
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Zenya nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package     Zenya\Api
 * @subpackage  Console
 * @author      Franck Cassedanne <fcassedanne@zenya.com>
 * @copyright   2011 Franck Cassedanne, Zenya.com
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://zenya.github.com
 * @version     @@PACKAGE_VERSION@@
 */

#namespace Zenya\bin;

#use Zenya\Api;

class Compiler
{
    const DEFAULT_PHAR_FILE = 'zenya-api-server.phar';

    protected $version;

    /**
     * Compiles the source code into one single Phar file.
     *
     * @param string $pharFile Name of the output Phar file
     */
    public function compile($pharFile = self::DEFAULT_PHAR_FILE)
    {
        if (file_exists($pharFile)) {
            unlink($pharFile);
        }

        if ( $log = exec('git log --pretty="%h %ci" -n1 HEAD') ) {
            $this->version = trim($log);
        } else {
            throw new \RuntimeException('The git binary cannot be found.');
        }

        $phar = new \Phar($pharFile, 0, $pharFile);
        $phar->setSignatureAlgorithm(\Phar::SHA1);

        // start buffering. Mandatory to modify stub.
        $phar->startBuffering();

        // all the files
        $root = __DIR__ . '/../..';
        foreach ( array('src/php', 'vendor/php') as $dir) {
            $it = new \RecursiveDirectoryIterator("$root/$dir");
            foreach (new \RecursiveIteratorIterator($it) as $file) {
                if (
                    $file->getExtension() == 'php'
                    && !preg_match ('@/src/php/Zenya/Api/Util/Compile.php$@', $file->getPathname())
                ) {
                    $path = $file->getPathname();
                    $this->addFile($phar, $path);
                }
            }
        }

        $this->addFile($phar, new \SplFileInfo($root . '/LICENSE.txt'), false);
        $this->addFile($phar, new \SplFileInfo($root . '/README.md'), false);
        $this->addFile($phar, new \SplFileInfo($root . '/src/data/config.dist.php'), false);

        // get the stub
        $stub = preg_replace("@{VERSION}@", $this->version, $this->getStub());
        $stub = preg_replace("@{BUILD}@", gmdate("Ymd\TH:i:s\Z"), $stub);

        $stub = preg_replace("@{PHAR}@", $pharFile, $stub);
        $stub = preg_replace("@{URL}@", 'http://zenya.dev/index3.php/api/v1', $stub);

        // Add the stub
        $phar->setStub($stub);

        $phar->stopBuffering();

        $phar->compressFiles(\Phar::GZ);

        echo 'The new phar has ' . $phar->count() . " entries.\n";
        unset($phar);

        chmod($pharFile, 0777);
        rename($pharFile, __DIR__ . '/../../dist/' . $pharFile);

        echo "Created in " . realpath(__DIR__ . '/../../dist/') . ".\n";
    }

    protected function addFile($phar, $path, $strip = true)
    {
        $path = realpath($path);

        $localPath = str_replace(
            dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR,
            '',
            realpath($path)
        );
        #$localPath = str_replace('src/php'.DIRECTORY_SEPARATOR, '', $localPath);
        #echo $localPath . " ($path)" . PHP_EOL;

        $content = file_get_contents($path);
        if ($strip) {
            $content = self::stripWhitespace($content);
        }

        // TODO: review versioning!!!
        $content = preg_replace("/const VERSION = '.*?';/", "const VERSION = '".$this->version."';", $content);

        #$localPath = strtolower($localPath);
        $phar->addFromString('/' . $localPath, $content);
    }

    /*
     * Returns the stub
     */
    protected function getStub()
    {
        // #!/usr/bin/env php
        return <<<'STUB'
<?php
/**
 * Copyright (c) 2012 Franck Cassedanne, Zenya.com
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Zenya nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package     Zenya\Api
 * @subpackage  Server
 * @author      Franck Cassedanne <fcassedanne@zenya.com>
 * @copyright   2011 Franck Cassedanne, Zenya.com
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://zenya.github.com
 * @version     @@PACKAGE_VERSION@@
 * @version     {VERSION} build: {BUILD}
 */
try {
    Phar::mapPhar('{PHAR}');

    $loc = 'phar://{PHAR}';
    define('APP_LIBDIR', $loc . '/vendor/php');
    define('APP_TOPDIR', $loc . '/src/php');
    //define('APP_TESTDIR', $loc . '/tests/unit-tests/php');

    require APP_LIBDIR . '/psr0.autoloader.php';
    #require_once APP_TOPDIR . '/Zenya/Api/Server.php';

    psr0_autoloader_searchFirst(APP_LIBDIR);
    psr0_autoloader_searchFirst(APP_TOPDIR);
    //psr0_autoloader_searchFirst(APP_TESTDIR);

    spl_autoload_register(function($name){
        #include APP_TOPDIR .'/' . str_replace('\\', DIRECTORY_SEPARATOR, $name) . '.php';
        $file = '/' . str_replace('\\', DIRECTORY_SEPARATOR, $name).'.php';
        $path = APP_TOPDIR . $file;
        if (file_exists($path)) {
            require $path;
        }
    });

} catch (Exception $e) {
    echo $e->getMessage();
    die('Cannot initialize Phar');
}

if ('cli' === php_sapi_name() && basename(__FILE__) === basename($_SERVER['argv'][0])) {
    $cli = new Zenya\Api\Console\Main;
    $cli->setPharName($loc);
    $cli->run();
    exit(0);
}

__HALT_COMPILER();
STUB;
    }

    /**
     * Removes whitespace from a PHP source string while preserving line numbers.
     *
     * Based on Kernel::stripComments(), but keeps line numbers intact.
     *
     * @param string $source A PHP string
     *
     * @return string The PHP string with the whitespace removed
     */
    public static function stripWhitespace($source)
    {
        if (!function_exists('token_get_all')) {
            return $source;
        }

        $output = '';
        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $output .= $token;
            } elseif (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT))) {
                $output .= str_repeat("\n", substr_count($token[1], "\n"));
            } elseif (T_WHITESPACE === $token[0]) {
                // reduce wide spaces
                $whitespace = preg_replace('{[ \t]+}', ' ', $token[1]);
                // normalize newlines to \n
                $whitespace = preg_replace('{(?:\r\n|\r|\n)}', "\n", $whitespace);
                // trim leading spaces
                $whitespace = preg_replace('{\n +}', "\n", $whitespace);
                $output .= $whitespace;
            } else {
                $output .= $token[1];
            }
        }

        return $output;
    }
}
