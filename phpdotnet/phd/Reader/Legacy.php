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
    | The base class for reading the giant XML blob. This is intended for     |
    | extension by output formats, and then for further extension by output   |
    | themes. This class should not be instantiated directly.                 |
    +-------------------------------------------------------------------------+
*/

abstract class PhDReader extends XMLReader {
	const XMLNS_XML   = "http://www.w3.org/XML/1998/namespace";
	const XMLNS_XLINK = "http://www.w3.org/1999/xlinK";
	const XMLNS_PHD   = "http://www.php.net/ns/phd";

	protected $map = array();
    protected $STACK = array();

	public function __construct( $file, $encoding = "utf-8", $options = NULL ) {

		if ( !parent::open( $file, $encoding, $options ) ) {
			throw new Exception();
		}

	}
    
    public function __destruct() {
    }
    
    /* Format subclasses must implement these to make them real formats. */
    abstract public function getFormatName();
    abstract protected function transformFromMap( $open, $name );
    
    /* These are new functions, extending XMLReader. */
    
    /* Seek to an ID within the file. */
	public function seek( $id ) {

		while( parent::read() ) {
			if ( $this->nodeType === XMLREADER::ELEMENT && $this->hasAttributes &&
			        $this->moveToAttributeNs( "id", self::XMLNS_XML ) && $this->value === $id ) {
				return $this->moveToElement();
			}
		}
		return FALSE;

	}
   	public function getID() {
		if ( $this->hasAttributes && $this->moveToAttributeNs("id", self::XMLNS_XML) ) {
			$id = $this->value;
			$this->moveToElement();
			return $id;
		}
		return "";
	}

    /* Go to the next useful node in the file. */
	public function nextNode() {

		while( $this->read() ) {
			switch( $this->nodeType ) {

    			case XMLReader::ELEMENT:
    				if ( $this->isEmptyElement ) {
    				    continue;
    				}

    			case XMLReader::TEXT:
    			case XMLReader::CDATA:
    			case XMLReader::END_ELEMENT:
    				return TRUE;
			}
		}
		return FALSE;

	}
    
    /* Read a node with the right name? */
	public function readNode( $nodeName ) {

		return $this->read() && !( $this->nodeType == XMLReader::END_ELEMENT && $this->name == $nodeName );

	}

    /* Get the content of a named node, or the current node. */
	public function readContent( $node = NULL ) {

		$retval = "";
		if ( !$node ) {
			$node = $this->name;
		}
		if ( $this->readNode( $node ) ) {
			$retval = $this->value;
			$this->read(); // Jump over END_ELEMENT too
		}
		return $retval;

	}
    
    /* Get the attribute value by name, if exists. */
	public function readAttribute( $attr ) {

		return $this->moveToAttribute( $attr ) ? $this->value : "";

	}

    /* Handle unmapped nodes. */
	public function __call( $func, $args ) {

		if ( $this->nodeType == XMLReader::END_ELEMENT ) {
		    /* ignore */ return;
		}
		trigger_error( "No mapper for $func", E_USER_WARNING );

		/* NOTE:
		 *  The _content_ of the element will get processed even though we dont 
		 *  know how to handle the elment itself
		*/
		return "";

	}
	public function notXPath( $tag ) {
		$depth = $this->depth;
		do {
			if ( isset( $tag[ $this->STACK[ --$depth ] ] ) ) {
				$tag = $tag[ $this->STACK[ $depth ] ];
			} else {
				$tag = $tag[0];
			}
		} while ( is_array( $tag ) );

		return $tag;
	}
 
    /* Perform a transformation. */
	public function transform() {

		$type = $this->nodeType;
		$name = $this->name;

		switch( $type ) {

    		case XMLReader::ELEMENT:
    			$this->STACK[ $this->depth ] = $name;

    		case XMLReader::END_ELEMENT:
    			$funcname = "format_$name";
 	    		if ( isset( $this->map[ $name ] ) ) {
		    		$tag = $this->map[ $name ];
			    	if ( is_array( $tag ) ) {
				    	$tag = $this->notXPath( $tag );
    				}
	    			if ( strncmp( $tag, "format_", 7 ) ) {
		    			return $this->transormFromMap( $type == XMLReader::ELEMENT, $tag, $name );
			    	}
				    $funcname = $tag;
     			}
			    return call_user_func( array( $this, $funcname ), $type == XMLReader::ELEMENT );
    			break;

    		case XMLReader::TEXT:
    			return $this->value;
    			break;

    		case XMLReader::CDATA:
    			return $this->highlight_php_code( $this->value );
    			break;

    		case XMLReader::COMMENT:
    		case XMLReader::WHITESPACE:
    		case XMLReader::SIGNIFICANT_WHITESPACE:
    			/* swallow it */
    			/* XXX This could lead to a recursion overflow if a lot of comment nodes get strung together. */
    			$this->read();
    			return $this->transform();

    		default:
    			trigger_error( "Dunno what to do with {$this->name} {$this->nodeType}", E_USER_ERROR );
    			return "";
		}

    }

}

/*
* vim600: sw=4 ts=4 fdm=syntax syntax=php et
* vim<600: sw=4 ts=4
*/
?>
