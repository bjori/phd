<?php
class PHPNETChunkedReader extends PhDXHTMLReader {
	protected $CURRENT_FUNCTION = "";

	public function format_refentry( $open ) {
		if  ( $open ) {
			$retval = parent::format_refentry( $open );
			$this->CURRENT_FUNCTION = str_replace( array( "function.", "_" ), array( "", "-" ), $this->CURRENT_FUNCTION_ID );
			ob_start();

			return $retval;
		}
		$funcname = $this->CURRENT_FUNCTION;

		echo parent::format_refentry( $open );
		$content = ob_get_contents();
		ob_end_clean();

		file_put_contents( "cache/function.{$funcname}.html", $content );

		$this->CURRENT_FUNCTION = "";

		return "";
	}
	public function format_reference( $open ) {
		if ( $open ) {
			ob_start();
			return parent::format_reference( $open );
		}
		$refid = $this->CURRENT_REFERENCE_ID;
		echo parent::format_reference( $open );
		$content = ob_get_contents();
		ob_end_clean();

		file_put_contents( "cache/{$refid}.html", $content );

		return "";
	}
	public function format_function( $open ) {
		$func = $this->readContent();
		$func = str_replace( "_", "-", $func );

		if ( $func == $this->CURRENT_FUNCTION ) {
			return sprintf( "<b>%s()</b>", $func );
		}

		return sprintf( '<a href="function.%1$s.html">%1$s()</a>', $func );
	}
}

