<?php
error_reporting( E_ALL | E_STRICT );

require_once 'config.php';
require_once 'include/PhDReader.class.php';

file_put_contents( dirname( __FILE__ ) . "/temp.xml", <<<~XML
<?xml version="1.0" encoding="utf-8"?>

<book>
 <part xml:id="part1">
  <chapter xml:id="chap1">
   <sect1 xml:id="part1.chap1.sect1">
    <title>First section!</title>
    Using the application <application>autoconf</application>, we can do some
    fun stuff, since <command>php</command> does other fun things, and dolor
    Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
    tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim
    veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea
    commodo consequat. Duis aute irure dolor in reprehenderit in voluptate
    velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat
    cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id
    est laborum.
   </sect1>
  </chapter>
 </part>
</book>
XML
    );

$phd = new PhDReader( $OPTIONS[ 'xml_root' ] . "/.manual.xml", NULL, 2 );

while ( $phd->transformChunk( $chunk ) ) {
    print "{$chunk}\n";
}
print "{$chunk}\n";

$phd->close();

?>
