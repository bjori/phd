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

class PhDReader_XHTML extends PhDReader {

    public function __construct( $file, $encoding = 'utf-8', $options = NULL ) {
        parent::__construct( $file, $encoding, $options );
    }
    
    public function __destruct() {
    }
    
    public function getFormatName() {
        
        return 'XHTML 1.0 Transitional';
    
    }

    protected function transformNode( $name, $type, &$output ) {
        
        switch ( $type ) {
            
            case XMLReader::ELEMENT:
            case XMLReader::END_ELEMENT:
                return $this->processElement( $name, $type == XMLReader::ELEMENT, $output );
                break;
                
            case XMLReader::TEXT:
                $output = $this->value;
                return FALSE;
                
            case XMLReader::CDATA:
                $output = $this->processCDATA( $this->value );
                return FALSE;
                
            case XMLReader::ENTITY_REF:
                $output = '<div class="error">WARNING: UNRESOLVED ENTITY '.htmlspecialchars( $name ).'</div>';
                return FALSE;
        
        }
    
    }
    
    protected function processElement( $name, $isOpen, &$output ) {
        static $handlerMap = NULL;
        
        if ( is_null( $handlerMap ) ) {
            $spanName = array( '<span class="%n%">', FALSE, '</span>', FALSE );
            $divName = array( '<div class="%n%">', FALSE, '</div>', FALSE );
            $divNameChunked = array( '<div class="%n%">', FALSE, '</div>', TRUE );
            $oneToOne = array( '<%n%>', FALSE, '</%n%>', FALSE );
            
            $handlerMap = array(
                'application' => $spanName,
                'classname' => $spanName,
                'code' => $oneToOne,
                'collab' => $spanName,
                'collabname' => $spanName,
                'command' => $spanName,
                'computerOutput' => $spanName,
                'constant' => $spanName,
                'emphasis' => $oneToOne,
                'enumname' => $spanName,
                'envar' => $spanName,
                'filename' => $spanName,
                'glossterm' => $spanName,
                'holder' => $spanName,
                'informatlable' => array( '<table>', FALSE, '</table>', FALSE ),
                'itemizedlist' => array( '<ul>', FALSE, '</ul>', FALSE ),
                'listitem' => array( '<li>', FALSE, '</li>', FALSE ),
                'literal' => $spanName,
                'mediaobject' => $divName,
                'methodparam' => $spanName,
                'member' => array( '<li>', FALSE, '</li>', FALSE ),
                'note' => $divName,
                'option' => $spanName,
                'orderedlist' => array( '<ol>', FALSE, '</ol>', FALSE ),
                'para' => array( '<p>', FALSE, '</p>', FALSE ),
                'parameter' => $spanName,
                'partintro' => $divName,
                'productname' => $spanName,
                'propname' => $spanName,
                'property' => $spanName,
                'proptype' => $spanName,
                'section' => $divNameChunked,
                'simplelist' => array( '<ul>', FALSE, '</ul>', FALSE ),
                'simpara' => array( '<p>', FALSE, '</p>', FALSE ),
                'title' => array( 'checkparentname', array( '__default' => 'h1', 'refsect1' => 'h3', 'example' => 'h4' ) ),
                'year' => $spanName,
                'refentry' => array( '<div id="%i%" class="refentry">', FALSE, '</div>', TRUE, TRUE ),
                'reference' => array( $this, 'format_reference' ),
                'function' => array( '<a href="function.%v%.html">', FALSE, '</a>', FALSE ),
                'refsect1' => array( '<div class="refsect_%r%">', FALSE, '</div>', FALSE ),
                '__default' => array( $this, 'unknownElement' ),
            );
        }

        $mapping = isset( $handlerMap[ $name ] ) ? $handlerMap[ $name ] : $handlerMap[ '__default' ];
        if ( is_array( $mapping ) ) {
            if ( is_string( $mapping[ 0 ] ) ) {
                switch ( $mapping[ 0 ] ) {
                    case 'checkparentname':
                        $output = '<div class="warning">NOT IMPLEMENTED YET.</div>';
                        return FALSE;
                    default:
                        $id = $this->getID();
                        $output = $this->formatMappingString( $name, $id, $isOpen ? $mapping[ 0 ] : $mapping[ 2 ] );
                        if ( !empty( $mapping[ 4 ] ) ) {
                            $this->pushStack( $id );
                        }
                        return ( $isOpen ? $mapping[ 1 ] : $mapping[ 3 ] );
                }
            } else if ( is_callable( $mapping ) ) {
                return call_user_func( $mapping, $name, $isOpen, &$output );
            }
        } else if ( is_string( $mapping ) ) {
            if ( $isOpen ) {
                $output = $this->formatMappingString( $name, $this->getID(), $mapping );
            } else {
                $output = '';
            }
            return FALSE;
        }
        $output = '<div class="warning">Bad handler string for '.$name.'!</div>';
        return FALSE;

    }
    
    protected function processCDATA( $content ) {
        
        return '<div class="phpcode">' . highlight_string( $content ) . '</div>';
        
    }
    
    protected function formatMappingString( $name, $id, $string ) {
        
        // XXX Yes, this needs heavy optimization, it's example for now.
        return str_replace( array( '%n%', '%i%', '%v%', '%r' ),
                            array( $name, $id, $this->readInnerXML(), $this->getAttribute( 'role' ) ),
                            $string );
    
    }
    
    protected  function format_reference( $name, $isOpen, $output ) {
        if ( $isOpen ) {
            $output = sprintf( '<div id="%s" class="reference">', $this->getID() );
            return FALSE;
        }
        $output = '</div>' .
                  '<ul class="funclist">';
        foreach ( $this->popStack() as $func => $desc ) {
            $output .= sptrinf( '<li><a href="function.%1$s.html" class="refentry">%1$s</a></li>', $func );
        }
        $output .= '</ul>';
        return TRUE;
    }
    
    protected function unknownElement( $name, $isOpen, $output ) {

        $output = "Can't handle a {$name}.\n";
        return FALSE;
    
    }
/*
	public function format_link( $open ) {

		$this->moveToNextAttribute();
		$href = $this->value;
		$class = $this->name;
		$content = $this->readContent( "link" );
		return sprintf( '<a href="%s" class="%s">%s</a>', $href, $class, $content );

	}

	public function format_methodsynopsis( $open ) {

		/* We read this element to END_ELEMENT so $open is useless *
		$content = '<div class="methodsynopsis">';
		$root = $this->name;

		while( $this->readNode( $root ) ) {
			if ( $this->nodeType == XMLReader::END_ELEMENT ) {
				$content .= "</span>\n";
				continue;
			}
			$name = $this->name;
			switch($name) {
    			case "type":
    			case "parameter":
    			case "methodname":
    				$content .= sprintf( '<span class="%s">%s</span>', $name, $this->readContent( $name ) );
    				break;

    			case "methodparam":
    				$content .= '<span class="methodparam">';
    				break;
			}
		}
		$content .= "</div>";
		return $content;

	}
	public function format_refnamediv( $open ) {
		$root = $this->name;

		while ( $this->readNode( $root ) ) {
			$name = $this->name;
			switch( $name ) {
    			case "refname":
	    			$refname = $this->readContent( $name );
		    		break;
			    case "refpurpose":
				    $refpurpose = $this->readContent( $name );
			    	break;
			}
		}
		
		$this->functionList[ $refname ] = $refpurpose;
		return sprintf( '<div class="refnamediv"><span class="refname">%s</span><span class="refpurpose">%s</span></div>', $refname, $refpurpose );
	}

*/
}

/*
* vim600: sw=4 ts=4 fdm=syntax syntax=php et
* vim<600: sw=4 ts=4
*/
?>
