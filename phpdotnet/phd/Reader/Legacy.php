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

    protected $stack = array();

	public function __construct( $file, $encoding = "utf-8", $options = NULL ) {

		if ( !parent::open( $file, $encoding, $options ) ) {
			throw new Exception();
		}
		$this->read();

	}
	
    public function __destruct() {
    }
    
    // ***
    // Format subclasses must implement these to make them real formats.
    //  THIS IS THE OFFICIAL OUTPUT FORMAT INTERFACE.

    //  proto string getFormatName( void )
    //      Return the name of the format.
    abstract public function getFormatName();

    //  proto string transformNode( string name, int type, string &output )
    //      Transform a given node, returning the binary string output. Binary
    //      strings ARE handled safely. This function will be called for all
    //      element, text, cdata, entity reference, and end element nodes. It
    //      is always valid for this method to make the parser move around in
    //      the file. Return TRUE to create a chunk boundary, FALSE otherwise.
    abstract protected function transformNode( $name, $type, &$output );
    
    // ***
    // Protected methods (intended for internal and subclass use only)

    // proto array getAllAttributes( void )
    //  Return all the attributes in the current element node as name:ns =>
    //  value pairs. Prefer the getAttribute*() methods defined by XMLReader
    //  when possible; use this only when you really do need all the
    //  attributes. An element without any attributes will result in an empty
    //  array, while a non-element node will result in a return of FALSE.
    protected function getAttributes() {
        
        $type = $this->nodeType;
        if ( $type != XMLReader::ELEMENT && $type != XMLReader::END_ELEMENT ) {
            return FALSE;
        }
        $attrCount = $this->attributeCount;
        $attrs = array();
        if ( $attrCount > 0 ) {
            for ( $i = 0; $i < $attrCount; ++$i ) {
                $this->moveToAttributeNo( $i );
                $attrs[ $this->name ] = $this->value;
            }
            $this->moveToElement();
        }
        return $attrs;

    }
    
    // proto string getID( void )
    //  Get the ID of the current element. Works on element and end element
    //  nodes only, returning an empty string in all other cases.
    protected function getID() {

		if ( $this->hasAttributes && $this->moveToAttributeNs( "id", self::XMLNS_XML ) ) {
			$id = $this->value;
			$this->moveToElement();
			return $id;
		}
		return "";

	}
	
	// protected void pushStack( mixed value )
	//  Push a value of any kind onto the parser stack. The stack is not used
	//  by the parser; it is intended as a cheap data store for formats and
	//  themes.
	protected function pushStack( $value ) {
	    
	    array_push( $this->stack, $value );
	
	}
	
	// protected mixed stackTop( void )
	//  Return the top value on the stack.
	protected function stackTop() {
	    
	    return count( $this->stack ) ? $this->stack[ 0 ] : NULL;
	
	}
	
	// protected mixed popStack( void )
	//  Pop the top value off the stack and return it.
	protected function popStack() {
	    
	    return array_pop( $this->stack );
	
	}
    
    // ***
    // Public methods
    
    // proto bool seek( string id )
    //  Seek to an ID. This is used to start the parser somewhere that isn't at
    //  the beginning (duh). Be careful; this does not cause the parser to halt
    //  at the closing element of a successful seek. Don't forget to check the
    //  return value.
	public function seek( $id ) {

		while( parent::read() ) {
			if ( $this->nodeType === XMLREADER::ELEMENT && $this->hasAttributes &&
			        $this->moveToAttributeNs( "id", self::XMLNS_XML ) && $this->value === $id ) {
				return $this->moveToElement();
			}
		}
		return FALSE;

	}
	
	// proto string transform( void )
	//  Transform the whole tree as one giant chunk, IGNORING the output
	//  format's chunker. Returns the tree, or FALSE on error.
	public function transform() {
	    
	    $allData = '';
	    while ( ( $data = $this->transformChunk() ) !== FALSE ) {
            $allData .= $data;
	    }
	    return $allData;
	
	}
	
	// proto bool transformChunk( string &outData )
	//  Transform nodes until the output format says it's time to output a
	//  chunking boundary or the parser runs out of data. Returns TRUE on
	//  success, FALSE on EOF. $data contains the transformed data, if any.
	public function transformChunk( &$outData ) {
	    global $OPTIONS;

        $hasMore = TRUE;
	    $data = fopen( "php://temp/maxmemory:{$OPTIONS[ 'chunking_memory_limit' ]}", "r+" );
	    $isChunk = FALSE;
	    do {
	        $nodeName = $this->name;
	        $nodeType = $this->nodeType;
	        switch ( $nodeType ) {
	            case XMLReader::NONE:
	                break;
	                
                case XMLReader::ELEMENT:
                case XMLReader::END_ELEMENT:
                case XMLReader::TEXT:
                case XMLReader::CDATA:
                case XMLReader::ENTITY_REF:
                    $isChunk = $this->transformNode( $nodeName, $nodeType, $output );
                    fwrite( $data, $output );
                    break;

                case XMLReader::ENTITY:
                case XMLReader::PI:
                case XMLReader::DOC_TYPE:
                case XMLReader::DOC:
                case XMLReader::DOC_FRAGMENT:
                case XMLReader::NOTATION:
                case XMLReader::WHITESPACE:
                case XMLReader::SIGNIFICANT_WHITESPACE:
                case XMLReader::END_ENTITY:
                case XMLReader::XML_DECLARATION:
                    // Eat it for lunch.
                    break;
            }
            $hasMore = $this->read();
        } while ( !$isChunk && $hasMore );
        
        rewind( $data );
        $outData = stream_get_contents( $data );
        fclose( $data );
        return $hasMore;

    }

/*
   	public function getID() {
		if ( $this->hasAttributes && $this->moveToAttributeNs("id", self::XMLNS_XML) ) {
			$id = $this->value;
			$this->moveToElement();
			return $id;
		}
		return "";
	}

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
    
	public function readNode( $nodeName ) {

		return $this->read() && !( $this->nodeType == XMLReader::END_ELEMENT && $this->name == $nodeName );

	}

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
    
	public function readAttribute( $attr ) {

		return $this->moveToAttribute( $attr ) ? $this->value : "";

	}

	public function __call( $func, $args ) {

		if ( $this->nodeType == XMLReader::END_ELEMENT ) {
		    /* ignore * return;
		}
		trigger_error( "No mapper for $func", E_USER_WARNING );

		/* NOTE:
		 *  The _content_ of the element will get processed even though we dont 
		 *  know how to handle the elment itself
		*
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
    			// swallow it
    			// XXX This could lead to a recursion overflow if a lot of comment nodes get strung together.
    			$this->read();
    			return $this->transform();

    		default:
    			trigger_error( "Dunno what to do with {$this->name} {$this->nodeType}", E_USER_ERROR );
    			return "";
		}

    }
*/
}

/*
* vim600: sw=4 ts=4 fdm=syntax syntax=php et
* vim<600: sw=4 ts=4
*/
?>
