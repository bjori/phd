<?php
namespace phpdotnet\phd;
/* $Id$ */

class Package_Generic_Manpage extends Format_Abstract_Manpage {
    const OPEN_CHUNK    = 0x01;
    const CLOSE_CHUNK   = 0x02;
    private $dchunk      = array(
        "lastWriteHadNewLine" => false,
        "lastWriteHadSpace"   => false,
        "linkCount"           => 0,
        "varlistentryDepth"   => 0,
        "links"               => array(),
        "methodparam"         => NULL,
        "methodparams"        => 0,
    );

     private $elementmap = array( /* {{{ */
        "refentry"              => "format_chunk",
        "refsect1"              => "format_refsect1",
        "warning"              => "format_panels",
        "para"              => "format_para",
        "link"              => "format_link",
        "simpara"              => "format_para",
        "literal"              => "format_literal",
        "emphasis"              => "format_emphasis",
        "refnamediv" => "format_refnamediv",
        "varlistentry" => "format_varlistentry",
        "parameter" => array(
            false,
            "methodparam" => "format_methodparam_parameter",
        ),
        "member" => "format_member",
        "term" => array(
            false,
            "varlistentry" => "format_varlistentry_term",
        ),
        "methodsynopsis" => "format_methodsynopsis",
        "methodparam" => array(
            /* default */ false,
            "methodsynopsis" => "format_methodsynopsis_methodparam",
        ),
    ); /* }}} */
    private $textmap = array(
        "refname" => "format_refname_text",
        "refpurpose" => "format_para_text",
        "para" => "format_para_text",
        "simpara" => "format_para_text",
        "function" => "format_function_text",
        "parameter" => array(
            /* default */ "format_parameter_text",
            "methodparam" => false,
        ),
        "initializer" => array(
            /* default */ false,
            "methodparam" => "format_methodparam_initializer_text",
        ),
        "methodname" => array(
            /* default */ false,
            "methodsynopsis" => "format_methodsynopsis_methodname_text",
        ),
        "constant" => "format_constant_text",
        "type" => array(
            /* default */ "format_type_text",
            "methodsynopsis" => "format_methodsynopsis_type_text",
            "methodparam" => "format_methodparam_type_text",
        ),
        "title" => array(
            /* default */ false,
            "refsect1" => "format_refsect1_title_text",
        ),
    );

    public function format_refpurpose_text($value, $tag) {
        return trim($value);
    }

    public function format_methodparam_parameter_text($value, $tag) {
        return "$value";
    }

    public function format_emphasis($open, $name, $attrs, $props) {
        return "*";
    }
    public function format_literal($open, $name, $attrs, $props) {
        return "_";
    }
    public function format_varlistentry($open, $name, $attrs, $props) {
        if ($open) {
            $this->cchunk["varlistentryDepth"]++;
            return "";
        }
        $this->cchunk["varlistentryDepth"]--;
        return "\n";
    }
    public function format_varlistentry_term($open, $name, $attrs, $props) {
        if ($open) {
            return "- ";
        }
        return "\n";
    }
    public function format_member($open, $name, $attrs, $props) {
        if ($open) {
            return "- ";
        }
        return "\n";
    }
    public function format_refnamediv($open, $name, $attrs, $props) {
        if ($open) {
            return "# SYNOPSIS\n\n";
        }
    }
    public function format_refsect1($open, $name, $attrs, $props) {
        if ($open) {
            return "\n\n\n# " . strtoupper($attrs[Reader::XMLNS_DOCBOOK]["role"]) . "\n\n";
        }
    }
    public function format_link($open, $name, $attrs, $props) {
        if ($open) {
            if (isset($attrs[Reader::XMLNS_DOCBOOK]["linkend"])) {
                $link = $attrs[Reader::XMLNS_DOCBOOK]["linkend"];
            } else if (isset($attrs[Reader::XMLNS_XLINK]["href"])) {
                $link = $attrs[Reader::XMLNS_XLINK]["href"];
            }

            $this->cchunk["links"][++$this->cchunk["linkCount"]] = array("href" => $link, "title" => isset($attrs[Reader::XMLNS_DOCBOOK]["title"]) ? $attrs[Reader::XMLNS_DOCBOOK]["title"] : null);
            return "[";
        }
        return "][{$this->cchunk["linkCount"]}]";
    }
    public function format_methodsynopsis_type_text($value, $tag) {
        $this->cchunk["methodparams"] = 0;
        return "$value function ";
    }
    public function format_constant_text($value, $tag) {
        return "[CONSTANT:$value]";
    }
    public function format_type_text($value, $tag) {
        return "[TYPE:$value]";
    }
    public function format_methodsynopsis_methodname_text($value, $tag) {
        return "$value(";
    }
    public function format_methodparam_parameter($open, $name, $attrs, $props) {
        if ($open) {
            $retval = "";
            if (isset($attrs[Reader::XMLNS_DOCBOOK]["role"]) && $attrs[Reader::XMLNS_DOCBOOK]["role"] == "reference") {
                $retval = "&";
            }
            return "$retval\$";
        }
        return "";
    }
    public function format_parameter_text($value, $tag) {
        $retval = "";
        if (!$this->cchunk["lastWriteHadSpace"]) {
            //$retval = " ";
        }
        // If last write was a new line, don't prepend space
        if ($this->cchunk["lastWriteHadNewLine"]) {
            $retval = "";
        }
        return $retval . "`\$$value`";
    }
    public function format_methodparam_type_text($value, $tag) {
        return "\n    $value ";
    }
    public function format_para_text($value, $tag) {
        $retval = "";
        if (!isset($this->cchunk["lastWriteHadNewLine"])) {
            // I don't know why this happens...
            var_dump($value, $tag);
        }
        if ($this->cchunk["lastWriteHadNewLine"]) {
            $retval = str_repeat("  ", $this->cchunk["varlistentryDepth"]);
            $value = ltrim($value);
        } elseif ($this->cchunk["lastWriteHadSpace"]) {
            $value = ltrim($value);
        }

        $retval .= str_replace(array("\n", "   ", "   ", "  "), array(" ", " ", " ", " "), $value);
        $char = $value[strlen($value)-1];
        if ($char == " " || $char == "\n") {
            $retval = rtrim($retval) . " ";
        }

        return $retval;
    }
    public function format_para($open, $name, $attrs, $props) {
        if ($open) {
            if ($props["sibling"] == $name) {
                return "\n";
            }
        }
        return "";
    }
    public function format_panels($open, $name, $attrs, $props) {
        $border = str_repeat("#", 10);
        return "\n" . $border . " " . strtoupper($name) . " " . $border . "\n";
    }
    public function format_methodparam_initializer_text($value, $tag) {
        $this->cchunk["methodparam"]["initializer"] = true;
        return " = $value";
    }
    public function format_methodsynopsis($open, $name, $attrs, $props) {
        if ($open) {
            return "";
        }
        return "\n)\n\n";
    }
    public function format_methodsynopsis_methodparam($open, $name, $attrs, $props) {
        if ($open) {
            $this->cchunk["methodparam"] = $attrs;

            if ($this->cchunk["methodparams"]) {
                return ",";
            }
            $this->cchunk["methodparams"]++;
            return "";
        }
        if (isset($this->cchunk["methodparam"]["initializer"])) {
            return "";
        }
        if (isset($this->cchunk["methodparam"][Reader::XMLNS_DOCBOOK]["choice"]) && $this->cchunk["methodparam"][Reader::XMLNS_DOCBOOK]["choice"] == "opt") {
            return " = NULL";
        }
    }
    public function format_function_text($value, $tag) {
        return "[FUNCTION:$value]";
    }
    public function format_refname_text($value, $tag) {
        $this->cchunk["funcname"][] = trim($value);
        return "";
    }
    public function format_refsect1_title_text($value, $tag) {
        return "";
    }



    /* If a chunk is being processed */
    protected $chunkOpen = false;

    /* Common properties for all functions pages */
    protected $bookName = "";
    protected $date = "";

    /* Current Chunk variables */
    protected $cchunk      = array();
    /* Default Chunk variables */

    public function __construct() {
        parent::__construct();

        $this->registerFormatName("New doc format");
        $this->setExt(Config::ext() === null ? ".md" : Config::ext());
        $this->setChunked(true);
        $this->cchunk = $this->dchunk;
    }

    public function update($event, $val = null) {
        switch($event) {
        case Render::CHUNK:
            switch($val) {
            case self::OPEN_CHUNK:
                if ($this->getFileStream()) {
                    /* I have an already open stream, back it up */
                    $this->pChunk = $this->cchunk;
                }
                $this->pushFileStream(fopen("php://temp/maxmemory", "r+"));
                $this->cchunk    = $this->dchunk;
                $this->chunkOpen = true;
                break;

            case self::CLOSE_CHUNK:
                if ($this->cchunk["links"]) {
                    $this->appendData("\n");
                    foreach($this->cchunk["links"] as $n => $link) {
                        $this->appendData("\n[$n]: {$link["href"]} ");
                    }
                }
                $stream = $this->popFileStream();
                $this->writeChunk($stream);
                fclose($stream);
                /* Do I have a parent stream I need to resume? */
                if ($this->getFileStream()) {
                    $this->cchunk    = $this->pChunk;
                    $this->chunkOpen = true;
                } else {
                    $this->cchunk    = array();
                    $this->chunkOpen = false;
                }
                break;

            default:
                var_dump("Unknown action");
            }
            break;

        case Render::STANDALONE:
            if ($val) {
                $this->registerElementMap(self::getDefaultElementMap());
                $this->registerTextMap(self::getDefaultTextMap());
            } else {
                $this->registerElementMap(static::getDefaultElementMap());
                $this->registerTextMap(static::getDefaultTextMap());
            }
            break;

        case Render::INIT:
            $this->setOutputDir(Config::output_dir() . '/md/');
            if (file_exists($this->getOutputDir())) {
                if (!is_dir($this->getOutputDir())) {
                    v("Output directory is a file?", E_USER_ERROR);
                }
            } else {
                if (!mkdir($this->getOutputDir(), 0777, true)) {
                    v("Can't create output directory", E_USER_ERROR);
                }
            }
            break;
        case Render::VERBOSE:
        	v("Starting %s rendering", $this->getFormatName(), VERBOSE_FORMAT_RENDERING);
        	break;
        }
    }

    public function appendData($data) {
        if ($this->chunkOpen) {
            if (trim($data) === "" && $data != "\n") {
                return 0;
            }
            $this->cchunk["lastWriteHadNewLine"] = rtrim($data, "\n") != $data;
            $this->cchunk["lastWriteHadSpace"] = rtrim($data, " ") != $data;

            $streams = $this->getFileStream();
            $stream = end($streams);
            return fwrite($stream, $data);
        }

        return 0;
    }

    public function writeChunk($stream) {
        $index = 0;

        rewind($stream);

        $filename = $this->cchunk["funcname"][$index] . ".md";
        $file = fopen($this->getOutputDir() . $filename, "wb");

        fwrite($file, $this->header($index));
        fwrite($file, stream_get_contents($stream));
        fclose($file);
        v("Wrote %s", $this->getOutputDir() . $filename, VERBOSE_CHUNK_WRITING);
    }

    public function getChunkInfo() {
        return $this->cchunk;
    }

    public function getDefaultChunkInfo() {
        return $this->dchunk;
    }

    public function getDefaultElementMap() {
        return $this->elementmap;
    }

    public function getDefaultTextMap() {
        return $this->textmap;
    }


    public function format_chunk($open, $name, $attrs, $props) {
        if ($open) {
            $this->notify(Render::CHUNK, self::OPEN_CHUNK);
        } else {
            $this->notify(Render::CHUNK, self::CLOSE_CHUNK);
        }

        return false;
    }

}

/*
* vim600: sw=4 ts=4 syntax=php et
* vim<600: sw=4 ts=4
*/

