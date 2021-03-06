<?php

/**
 *
 * This file is part of the Apix Project.
 *
 * (c) Franck Cassedanne <franck at ouarz.net>
 *
 * @license     http://opensource.org/licenses/BSD-3-Clause  New BSD License
 *
 */

namespace Apix;

class Reflection
{
    /**
     * @var string
     */
    protected $prefix;

    /**
     * Constructor
     *
     * @param string|null $prefix [optional default:null]
     */
    public function __construct($prefix='null')
    {
        $this->prefix = $prefix;
    }

    /**
     * Returns prefix
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Returns the PHPDoc string
     *
     * @param  \Reflection|string $mix A reflection object or a PHPDoc string
     * @return array
     */
    // public function getPhpDocString($mix)
    // {
    //     return $mix instanceOf \Reflector ? $mix->getDocComment() : $mix;
    // }

    /**
     * Extract PHPDOCs
     *
     * @param  \Reflection|string $mix       A reflection object or a PHPDoc string
     * @param  array|null         $requireds An array of param name that are required.
     * @return array
     */
    public static function parsePhpDoc($mix, array $requireds=null)
    {
        if ($mix instanceof \Reflector) {
            $doc = $mix->getDocComment();
            $requireds = self::getRequiredParams($mix);
        } else {
            $doc = $mix;
        }

        $docs = array();

        // 1st - remove /*, *, */ from all the lines
        $doc = substr($doc, 3, -2);

        // 2. remove the carrier returns
        #$pattern = '/\r?\n *\* */';

        // does 1. + 2. BUT not too efficiently!
        #$pattern = '%(\r?\n(?! \* ?@))?^(/\*\*\r?\n \* | \*/| \* ?)%m';

        // same as 2. BUT keep the carrier returns in.
        // $pattern = '@(\r+|\t+)? *\* *@';
        $pattern = '@(\r+|\t+)? +\*\s?@';

        $str = preg_replace($pattern, '', $doc);

        #$lines =array_map('trim',explode(PHP_EOL, $str));
        $lines = preg_split('@\r?\n|\r@', $str, null, PREG_SPLIT_NO_EMPTY);

        // extract the short decription (title)
        $docs['title'] = array_shift($lines);

        // extract the long description
        $docs['description'] = '';
        foreach ($lines as $i => $line) {
            if (strlen(trim($line)) && strpos($line, '@') !== 0) {
                $docs['description'] .= $docs['description'] ? PHP_EOL . $line : $line;
                unset($lines[$i]);
            } else break;
        }

        // do all the "@entries"
        preg_match_all(
            '/@(?P<key>[\w_]+)\s+(?P<value>.*?)\s*(?=$|@[\w_]+\s)/s',
            $str,
            $lines
        );

        foreach ($lines['value'] as $i => $v) {
            $grp = $lines['key'][$i];

            if ($grp == 'param' || $grp == 'global') {
                // "@param string $param description of param"
                preg_match('/(?P<t>\S+)\s+\$(?P<name>\S+)(?P<d>\s+(?:[\w\W_\s]*))?/', $v, $m);

                $t = $grp == 'param' ? 'params' : 'globals';
                $docs[$t][$m['name']] = array(
                    'type'        => $m['t'],
                    'name'        => $m['name'],
                    'description' => isset($m['d'])
                                        ? trim($m['d'])
                                        : null,
                    'required'    => isset($requireds)
                                        && in_array($m['name'], $requireds)
                );
            } else {
                // other @entries as group
                $docs[$grp][] = $v;
            }
        }

        if (isset($docs['return'])) {
            $returns = array();
            foreach ($docs['return'] as $v) {
                // preg_match('/(?P<t>\S+)(?P<d>\s+(?:.+))?(?P<x>(?:.|\s)*)/', $v, $m); // with extar
                preg_match('/(?P<t>\S+)(?P<d>\s+(?:.|\s)*)/', $v, $m);
                if ( isset($m['t']) ) {
                    $returns[] = array(
                        'type'        => $m['t'],
                        'description' => trim($m['d']),
                        // 'extra'       => trim($m['x']),
                    );
                }
            }
            $docs['return'] = array( $returns );
        }

        // reduce group
        foreach ($docs as $key => $value) {
            if (
                $key !== 'params' && $key !== 'globals' && $key !== 'methods'
            ) {
                if (is_array($value) && count($value) == 1) {
                    $docs[$key] = reset( $docs[$key] );
                }
            }
        }

        return $docs;
    }

    /**
     * Returns the required parameters
     *
     * @param  \ReflectionFunctionAbstract $ref A reflected method/function to introspect
     * @return array                       The array of required parameters
     */
    public static function getRequiredParams(\ReflectionFunctionAbstract $ref)
    {
        $params = array();
        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();
            if ( !$param->isOptional() ) {
                $params[] = $name;
            }
        }

        return $params;
    }

    /**
     * Extract source code
     *
     * @param  \Reflector $ref
     * @return array
     */
    public static function getSource(\Reflector $ref)
    {
        if( !file_exists( $ref->getFileName() ) ) return false;

        $start_offset = $ref->getStartLine();
        $end_offset   = $ref->getEndLine()-$start_offset;

        return join('',
            array_slice(
                file($ref->getFileName()),
                $start_offset-1,
                $end_offset+1
            )
        );
    }

}
