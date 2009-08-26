<?php
namespace phpdotnet\phd;
/* $Id$ */

class Package_Pear_Factory extends Format_Factory {
    private $formats = array(
        'xhtml'         => 'Package_Pear_ChunkedXHTML',
        'bigxhtml'      => 'Package_Pear_BigXHTML',
        'php'           => 'Package_Pear_Web',
        'chm'           => 'Package_Pear_CHM',
    );
    
    public function __construct() {
        parent::setPackageName("Pear");
        parent::registerOutputFormats($this->formats);
    }
}

/*
* vim600: sw=4 ts=4 syntax=php et
* vim<600: sw=4 ts=4
*/
