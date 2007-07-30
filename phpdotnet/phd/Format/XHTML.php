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
    | directly; it is intended for use only by PHDReader.                     |
    +-------------------------------------------------------------------------+
*/

/* Grab the PhDReader parent class. */
require_once 'include/PhDReader.class.php';

class PhD_xhtml_Format implements PhD_OutputFormat {

    protected $reader = NULL;
    protected $handlerMap = array();
    protected $IDStack = array();
    protected $nameStack = array();
    
    protected function translationSpec( $name ) {
        return array( "<{$name} class=\"%n%\">", "</{$name}>", FALSE );
    }
    
    public function __construct( $reader ) {
        
        $this->reader = $reader;
        
        $span = $this->translationSpec( 'span' );
        $div = $this->translationSpec( 'div' );
        
        $this->handlerMap = array(
            'application' => $span,
            'classname' => $span,
            'code' => $this->translationSpec( 'code' ),
            'collab' => $span,
            'collabname' => $span,
            'command' => $span,
            'computerOutput' => $span,
            'constant' => $span,
            'emphasis' => $this->translationSpec( 'em' ),
            'enumname' => $span,
            'envar' => $span,
            'filename' => $span,
            'glossterm' => $span,
            'holder' => $span,
            'informaltable' => $this->translationSpec( 'table' ),
            'itemizedlist' => $this->translationSpec( 'ul' ),
            'listitem' => $this->translationSpec( 'li' ),
            'literal' => $span,
            'mediaobject' => $div,
            'methodparam' => $span,
            'member' => $this->translationSpec( 'li' ),
            'note' => $div,
            'option' => $span,
            'orderedlist' => $this->translationSpec( 'ol' ),
            'para' => $this->translationSpec( 'p' ),
            'parameter' => $span,
            'partintro' => $div,
            'productname' => $span,
            'propname' => $span,
            'property' => $span,
            'proptype' => $span,
            'section' => array( '<div class="%n%" id="%i%" xml:id="%i%">', '</div>', TRUE ),
            'sect1' => array( '%push=i%%push=n%<div class="%n%" id="%i%" xml:id="%i%">', '</div>%pop=n%%pop=i%', TRUE ),
            'title' => array( '<h1 class="%top=n%"><a name="%top=i%">', '</a></h1>', FALSE ),
            'simplelist' => $this->translationSpec( 'ul' ),
            'simpara' => $this->translationSpec( 'p' ),
            'year' => $span,
            'refentry' => array( '<div id="%i%">', '</div>', TRUE ),
            'reference' => $div,
            'function' => array( '<b>', '</b>', FALSE ),
            'refsect1' => $div,
            '__default' => array( $this, 'unknownElement' ),
        );

    }
    
    public function __destruct() {
    }
    
    public function getFormatName() {
        
        return 'XHTML 1.0 Transitional';
    
    }

    public function transformNode( $name, $type, &$output ) {
        
        switch ( $type ) {
            
            case XMLReader::ELEMENT:
            case XMLReader::END_ELEMENT:
                return $this->processElement( $name, $type == XMLReader::ELEMENT, $output );
                break;
                
            case XMLReader::TEXT:
                $output = $this->reader->value;
                return FALSE;
                
            case XMLReader::CDATA:
                $output = $this->processCDATA( $this->reader->value );
                return FALSE;
                
            case XMLReader::ENTITY_REF:
                $output = '<div class="error">WARNING: UNRESOLVED ENTITY '.htmlspecialchars( $name ).'</div>';
                return FALSE;
        
        }
    
    }
    
    protected function processElement( $name, $isOpen, &$output ) {
        
        $mapping = isset( $this->handlerMap[ $name ] ) ? $this->handlerMap[ $name ] : $this->handlerMap[ '__default' ];

        if ( is_callable( $mapping ) ) {
            return call_user_func( $mapping, $name, $isOpen, &$output );

        } else if ( is_array( $mapping ) ) {
            $output = $this->formatMappingString( $name, $this->reader->getID(), $isOpen ? $mapping[ 0 ] : $mapping[ 1 ] );
            return ( $isOpen ? FALSE : $mapping[ 2 ] );

        }
        $output = '<div class="warning">Bad handler string for '.$name.'!</div>';
        return FALSE;

    }
    
    protected function processCDATA( $content ) {
        
        return "<div>{$content}</div>";
        
    }
    
    protected function mappingFormatter( $matches ) {

        if ( empty( $matches[ 1 ] ) ) {
            if ( $matches[ 4 ] == 'n' ) {
                return $this->_mapping_name;
            } else if ( $matches[ 4 ] == 'i' ) {
                return $this->_mapping_id;
            }
        } else {
            if ( $matches[ 3 ] == 'n' ) {
                $a = &$this->nameStack;
                $v = $this->_mapping_name;
            } else if ( $matches[ 3 ] == 'i' ) {
                $a = &$this->IDStack;
                $v = $this->_mapping_id;
            }
            
            if ( $matches[ 2 ] == 'push' ) {
                array_push( $a, $v );
                return '';
            } else if ( $matches[ 2 ] == 'pop' ) {
                array_pop( $a );
                return '';
            } else if ( $matches[ 2 ] == 'top' ) {
                return $a[ 0 ];
            }
        }
        return 'INVALID MAPPING DATA:' . var_export($matches,1);

    }
        
    
    protected function formatMappingString( $name, $id, $string ) {
        
        $sc = 'ni'; // sc == stack chars
        $fc = 'ni'; // fc == formatted chars
        $this->_mapping_name = $name;
        $this->_mapping_id = $id;
        $s = preg_replace_callback( "/%(?:((push|pop|top)=([{$sc}]))|([{$fc}]))%/", array( $this, 'mappingFormatter' ), $string );
        unset( $this->_mapping_name );
        unset( $this->_mapping_id );
        return $s;
    
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
