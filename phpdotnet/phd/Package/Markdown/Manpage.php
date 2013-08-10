<?php
namespace phpdotnet\phd;
/* $Id$ */

class Package_Markdown_Manpage extends Package_Generic_Manpage {

    protected $elementmap = array(
        'phpdoc:varentry'               => 'format_chunk',
        'phpdoc:classref'               => 'format_chunk',
        'phpdoc:exception'              => 'format_chunk',
        'phpdoc:exceptionref'           => 'format_chunk',
        'phpdoc:varentry'               => 'format_chunk',
    );

    protected $textmap =        array(
    );

    protected $CURRENT_ID = "";

    /* Current Chunk settings */
    protected $cchunk          = array();
    /* Default Chunk settings */

    public function __construct() {
        parent::__construct();

        $this->registerFormatName("Markdown-Functions");
        $this->setTitle("PHP Manual");
        $this->dchunk = array_merge(parent::getDefaultChunkInfo(), static::getDefaultChunkInfo());
    }

    public function __destruct() {
        $this->close();
    }

    public function update($event, $val = null) {
        switch($event) {
        case Render::STANDALONE:
            if ($val) {
                $this->registerElementMap(array_merge(parent::getDefaultElementMap(), $this->elementmap));
                $this->registerTextMap(array_merge(parent::getDefaultTextMap(), $this->textmap));
            } else {
                $this->registerElementMap(static::getDefaultElementMap());
                $this->registerTextMap(static::getDefaultTextMap());
            }
            break;
        default:
            return parent::update($event, $val);
            break;
        }
    }


    public function header($index) {
return "";
        return ".TH " . strtoupper($this->cchunk["funcname"][$index]) . " 3 \"" . $this->date . "\" \"PHP Documentation Group\" \"" . $this->bookName . "\"" . "\n";
    }
    public function format_class_title_text($value, $tag) {
        $this->cchunk["funcname"][] = $this->toValidName(trim($value));
    }



    public function close() {
        foreach ($this->getFileStream() as $fp) {
            fclose($fp);
        }
    }

}

/*
* vim600: sw=4 ts=4 syntax=php et
* vim<600: sw=4 ts=4
*/

