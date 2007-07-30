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
    | The base class for reading the giant XML blob. This is intended to have |
    | a format and theme plugged into it.                                     |
    +-------------------------------------------------------------------------+
*/

// ***
// Output format classes must implement this interface.
//  THIS IS THE OFFICIAL OUTPUT FORMAT INTERFACE.
interface PhD_OutputFormat {

    // proto string getFormatName( void )
    //  Return the name of the format.
    public function getFormatName();
    
    // proto string transformNode( string name, int type, string &output )
    //  Transform a given node, returning the string output. Binary strings ARE
    //  handled safely. This function will be called for all element, text,
    //  cdata, entity reference, and end element nodes. It is NOT valid for
    //  this method to advance the parser's input. Return TRUE to create a
    //  chunk boundary, FALSE otherwise.
    public function transformNode( $name, $type, &$output );

}

// ***
// Output theme classes must implement this interface.
//  THIS IS THE OFFICIAL OUTPUT THEME INTERFACE.
interface PhD_OutputTheme {
    
    // proto string getThemeName( void )
    //  Return the name of the theme.
    public function getThemeName();
    
    // proto string transformNode( string name, int type, PhD_OutputFormat formatter, string &output )
    //  Transform a given node, returning the string output. Call the given
    //  formatter for all inputs, altering its output as appropriate, unless
    //  the theme handles the entire transformation by itself.
    public function transformNode( $name, $type, $formatter, &$output );
    
    // proto string chunkHeader( void )
    //  Return a header for a chunk. The current node will be the first node in
    //  the chunk.
    public function chunkHeader();
    
    // proto string chunkFooter( void )
    //  Return a footer for a chunk. The current node will be the END_ELEMENT
    //  of the chunk's first node.
    public function chunkFooter();

}


class PhDReader extends XMLReader {

	const XMLNS_XML   = "http://www.w3.org/XML/1998/namespace";
	const XMLNS_XLINK = "http://www.w3.org/1999/xlink";
	const XMLNS_PHD   = "http://www.php.net/ns/phd";

    protected $outputFormat = NULL;
    protected $outputTheme = NULL;
    
    protected $stack = array();
    protected $state = array();
    
    // ***
    // Debugging functions
    public function debuggingOn() { return $GLOBALS[ 'OPTIONS' ][ 'debug' ]; }
    public function debugMessage( $msg ) {
        if ( $this->debuggingOn() ) {
            fwrite( STDERR, date( DATE_RFC2822 ) . ": {$msg}\n" );
        }
    }
    public function debugAssert( $condition, $desc ) {
        if ( $this->debuggingOn() && $condition == FALSE ) {
            $this->debugMessage( "ASSERTION FAILURE for '{$desc}'.\nBacktrace:" );
            $this->debugMessage( print_r( array_slice( debug_backtrace(), 1 ), 1 ) );
            exit( 1 );
        }
    }
    
    // ***
    // Constructor and destructor
	public function __construct( $file ) {
	    global $OPTIONS;

		if ( !parent::open( $file, NULL, 0 ) ) {
			throw new Exception();
		}
		// Position the reader at the first node.
		$this->read();
		
		// Grab the output format.
		require dirname( __FILE__ ) . "/../formats/{$OPTIONS[ 'output_format' ]}.php";
		$formatClassName = "PhD_" . str_replace( '-', '_', $OPTIONS[ 'output_format' ] ) . '_Format';
		$this->outputFormat = new $formatClassName( $this );
		$this->debugMessage( "Loaded output format '{$OPTIONS[ 'output_format' ]}' using class '{$formatClassName}'." );
        
        // Grab the output theme.
		require dirname( __FILE__ ) . "/../themes/{$OPTIONS[ 'output_theme' ]}/theme.php";
		$themeClassName = "PhD_" . str_replace( '-', '_', $OPTIONS[ 'output_theme' ] ) . '_Theme';
		$this->outputTheme = new $themeClassName( $this );
		$this->debugMessage( "Loaded output theme '{$OPTIONS[ 'output_theme' ]}' using class '{$themeClassName}'." );
		
		// TODO: Set up the output encoding if necessary.

	}
	
    public function __destruct() {
    }
    
    // ***
    // Protected methods (intended for internal and subclass use only)

    // ***
    // Public methods
    
    // proto array getAllAttributes( void )
    //  Return all the attributes in the current element node as name:ns =>
    //  value pairs. Prefer the getAttribute*() methods defined by XMLReader
    //  when possible; use this only when you really do need all the
    //  attributes. An element without any attributes will result in an empty
    //  array, while a non-element node will result in a return of FALSE.
    public function getAttributes() {
        
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
    public function getID() {

		if ( $this->hasAttributes && $this->moveToAttributeNs( "id", self::XMLNS_XML ) ) {
			$id = $this->value;
			$this->moveToElement();
			return $id;
		}
		return "";

	}
		
    // proto bool seek( string id )
    //  Seek to an ID. This is used to start the parser somewhere that isn't at
    //  the beginning (duh). Be careful; this does not cause the parser to halt
    //  at the closing element of a successful seek. Don't forget to check the
    //  return value.
	public function seek( $id ) {
        
        $this->debugMessage( "Starting seek to {$id}..." );
		while( parent::read() ) {
			if ( $this->nodeType === XMLREADER::ELEMENT && $this->hasAttributes &&
			        $this->moveToAttributeNs( "id", self::XMLNS_XML ) && $this->value === $id ) {
                $this->debugMessage( "Seek complete." );
				return $this->moveToElement();
			}
		}
		$this->debugMessage( "Seek failed." );
		return FALSE;

	}
	
	// proto string transform( void )
	//  Transform the whole tree as one giant chunk, IGNORING the output
	//  format's chunker. Returns the tree, or FALSE on error.
	public function transform() {
	    
	    $allData = $this->outputTheme->chunkHeader();
	    while ( $this->transformChunk( $data, FALSE ) ) {
            $allData .= $data;
	    }
	    return $allData . $this->outputTheme->chunkFooter();
	
	}
	
	// proto bool transformChunk( string &outData )
	//  Transform nodes until the output format says it's time to output a
	//  chunking boundary or the parser runs out of data. Returns TRUE on
	//  success, FALSE on EOF. $data contains the transformed data, if any.
	public function transformChunk( &$outData, $applyHeaderFooter = TRUE ) {
	    global $OPTIONS;

        $hasMore = TRUE;
	    $data = fopen( "php://temp/maxmemory:{$OPTIONS[ 'chunking_memory_limit' ]}", "r+" );
	    fwrite( $data, $this->outputTheme->chunkHeader() );
	    $isChunk = FALSE;
	    do {
	        $nodeName = $this->name;
	        $nodeType = $this->nodeType;
	        switch ( $nodeType ) {
                case XMLReader::ELEMENT:
                case XMLReader::END_ELEMENT:
                case XMLReader::TEXT:
                case XMLReader::CDATA:
                case XMLReader::ENTITY_REF:
                    $isChunk = $this->outputTheme->transformNode( $nodeName, $nodeType, $this->outputFormat, $output );
                    fwrite( $data, $output );
                    if ( $isChunk ) {
                        fwrite( $data, $this->outputTheme->chunkFooter() );
                    }
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
                case XMLReader::COMMENT:
                    // Eat it for lunch.
                    break;
                
                default:
                    die( "Unknown node type {$nodeType} while transforming. Can not continue." );
            }
            $hasMore = $this->read();
        } while ( !$isChunk && $hasMore );
        
        rewind( $data );
        $outData = stream_get_contents( $data );
        fclose( $data );
        return $hasMore;

    }

/*
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
*/
}

/*
* vim600: sw=4 ts=4 fdm=syntax syntax=php et
* vim<600: sw=4 ts=4
*/
?>
