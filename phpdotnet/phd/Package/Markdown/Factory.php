<?php
namespace phpdotnet\phd;
/* $Id$ */

class Package_Markdown_Factory extends Format_Factory {
    private $formats = array(
        'xhtml'         => 'Package_Markdown_ChunkedXHTML',
        'php'           => 'Package_Markdown_Web',
        'manpage'       => 'Package_Markdown_Manpage',
    );

    /**
     * The package version
     */
    private $version = '@phd_php_version@';

    public function __construct() {
        parent::setPackageName("Markdown");
        parent::setPackageVersion($this->version);
        parent::registerOutputFormats($this->formats);
    }
}

/*
* vim600: sw=4 ts=4 syntax=php et
* vim<600: sw=4 ts=4
*/

