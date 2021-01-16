<?php
/**
 * Plugin Name: WP Load Later
 * Plugin URI: https://github.com/Automattic/wp-load-later
 * Version: 00.01.00
 * License: GPLv2 or later
 */

class WP_Load_Later {
	private static $deferred_js = [];
	private static $after_load_js = [];

	/**
	 * Call this to load a JS resource after the load event fires
	 *
	 * @param string $url
	 * @param array $attributes key/value pairs
	 */
	public static function after_load_js( $url, $attributes = [] ) {
		self::$after_load_js[ $url ] = $attributes;
	}

	/**
	 * Call this to add another JS resource that uses the defer attribute.
	 *
	 * @param string $url
	 * @param array $attributes key/value pairs
	 */
	public static function defer_js( $url, $attributes = [] ) {
		self::$deferred_js[ $url ] = $attributes;
	}

	/***********************/
	/*** Startup  Method ***/
	/***********************/

	/**
	 * Add resources as part of wp_footer.
	 */
	public static function init() {
		add_action( 'wp_footer', [ 'WP_Load_Later', '_inject_deferred_js' ], 99999 );
		add_action( 'wp_footer', [ 'WP_Load_Later', '_inject_after_load' ], 99999 );
    }

	/************************/
	/*** Internal Methods ***/
	/************************/

	/**
	 * Inject the deferred JS into the HTML.
	 */
	public static function _inject_deferred_js() {
		$js_files = self::_escape_all( self::$deferred_js );
		foreach ( $js_files as $js => $attr ) {
			echo '<script defer ';
			foreach ( $attr as $k => $v ) {
				echo "$k='$v' "; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo "src='$js'></script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Inject resources to be loaded after the load event fires
	 */
	public static function _inject_after_load() {
		if ( count( self::$after_load_js ) < 1 ) {
			return;
		}

		$js = wp_json_encode( self::_escape_all( self::$after_load_js ) );

		$out = <<<HTML
<script>
window.addEventListener( 'load', function( event ) {
	function appendToDoc( element, attributes ) {
		for ( var attr in attributes ) {
			try {
				element.setAttribute( attr, attributes[attr] );
			} catch ( e ) {
				console.log( "WP_Load_Later: Invalid attribute", e );
			}
		}
		document.body.appendChild( element );
	}

	try {
		var js = JSON.parse( '$js' );
		for ( var url in js ) {
			var script = document.createElement( 'script' );
			script.src = url;

			appendToDoc( script, js[url] );
		}
	} catch( e ) {
		console.log( 'WP_Load_Later: add_after_load failure', e );
	}
} );
</script>
HTML;

		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Escape all of the attributes included with the resources.
	 * array ( resources => array of attributes )
	 *
	 * @param array $resources
	 * @return array $escaped
	 */
	public static function _escape_all( $resources ) {
		// Each top level item is a URL, the array values below that are
		// attributes.
		$escaped = [];

		foreach ( $resources as $url => $attributes ) {
			$url = esc_url( $url );
			$escaped[ $url ] = [];
			foreach ( $attributes as $k => $v ) {
				$escaped[ $url ][ esc_attr( $k ) ] = esc_attr( $v );
			}
		}

		return $escaped;
	}
}

WP_Load_Later::init();
