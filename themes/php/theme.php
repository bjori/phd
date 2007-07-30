<?php

/*  $Id$
    +-------------------------------------------------------------------------+
    | Copyright(c) 2007                                                       |
    | Authors:                                                                |
    |    Gwynne Raskind <gwynne@php.net>                                      |
    |    Hannes Magnusson <bjori@php.net>                                     |
    | This source file is subject to the license that is bundled with this    |
    | package in the file LICENSE, and is available through the               |
    | world-wide-web at the following url:                                    |
    | http://phd.php.net/LICENSE                                              |
    +-------------------------------------------------------------------------+
    | The XHTML output format class. This should not be instantiated          |
    | directly; it is intended for extension by a theme class.                |
    | XXX This is temporarily untrue for "let's get it started" purposes.     |
    +-------------------------------------------------------------------------+
*/

/* Grab the PhDReader parent class. */
require_once 'include/PhDReader.class.php';

class PhD_php_Theme implements PhD_OutputTheme {
    
    protected $reader = NULL;
    
    public function __construct( $reader ) {
        
        $this->reader = $reader;
        
    }
    
    public function __destruct() {
    }
    
    public function getThemeName() {
        
        return 'PHPweb Theme for PhD';
    
    }
    
    public function transformNode( $name, $type, $formatter, &$output ) {
        return $formatter->transformNode( $name, $type, $output );
    }
    
    public function chunkHeader() {
        return '';
    }
    
    public function chunkFooter() {
        return '';
    }

}

?>

