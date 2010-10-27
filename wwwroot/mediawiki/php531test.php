<?php
function foo( &$x ) {}

class Proxy {
	function __call( $name, $args ) {
		debug_zval_dump( $args );
		call_user_func_array( 'foo', $args );
	}
}

$arg = 1;
$args = array( &$arg );
$proxy = new Proxy;
call_user_func_array( array( &$proxy, 'bar' ), $args );

?>
