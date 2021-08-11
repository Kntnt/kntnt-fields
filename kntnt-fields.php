<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Kntnt Fields
 * Plugin URI:        https://github.com/Kntnt/kntnt-fields
 * Description:       Provides API and shortcode for getting value of bloginfo, options, users, posts and ACF fields.
 * Version:           1.0.0
 * Author:            Thomas Barregren
 * Author URI:        https://www.kntnt.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */


namespace {

	function kntnt_get_field( $field, $sep = '/' ) {
		[ $type, $object, $field ] = explode( '/', $field, 3 );
		return ( $field ? \Kntnt\Fields\Plugin::get( $field, $type, $object, $sep ) : null ) ?: '';
	}

	function kntnt_field_as_text( $field, $echo = true, $sep = '/' ) {
		$text = esc_html( kntnt_get_field( $field, $sep ) );
		if ( $echo ) {
			echo $text;
		}
		return $text;
	}

	function kntnt_field_as_html( $field, $echo = true, $allowed_html = 'post', $sep = '/' ) {
		$html = wp_kses( kntnt_get_field( $field, $sep ), $allowed_html );
		if ( $echo ) {
			echo $html;
		}
		return $html;
	}

	function kntnt_field_as_attr( $field, $echo = true, $sep = '/' ) {
		$attr = esc_attr( kntnt_get_field( $field, $sep ) );
		if ( $echo ) {
			echo $attr;
		}
		return $attr;
	}

	function kntnt_field_as_url( $field, $echo = true, $sep = '/' ) {
		$url = esc_url( kntnt_get_field( $field, $sep ) );
		if ( $echo ) {
			echo $url;
		}
		return $url;
	}

	function kntnt_field_as_textarea( $field, $echo = true, $sep = '/' ) {
		$textarea = esc_textarea( kntnt_get_field( $field, $sep ) );
		if ( $echo ) {
			echo $textarea;
		}
		return $textarea;
	}

}


namespace Kntnt\Fields {

	class Plugin {

		private static $defaults = [
			'field' => '',
			'sep' => '/',
		];

		public function __construct() {
			add_shortcode( 'field', [ $this, 'field_shortcode' ] );
		}

		public static function get( $field, $type = 'post', $object = null, $sep = '/' ) {
			if ( empty( $object ) ) {
				$object = null;
			}
			if ( in_array( $type, [ 'post', 'user', 'acf', 'option', 'bloginfo' ] ) ) {
				return self::$type( explode( $sep, $field ), $object );
			}
			else {
				return null;
			}
		}

		public function field_shortcode( $atts, $template, $tag ) {

			/** @var string $field The field path. */
			/** @var string $sep The field path separator. */
			extract( self::shortcode_atts( self::$defaults, $atts ) );

			[ $type, $object, $field ] = explode( '/', $field, 3 );
			return self::get( $field, $type, $object, $sep );

		}

		// A more forgiving version of WordPress' shortcode_atts().
		public static function shortcode_atts( $pairs, $atts, $shortcode = '' ): array {

			// $atts can be a string which is cast to an array. An empty string should
			// be an empty array (not an array with an empty element as by casting).
			$atts = $atts ? (array) $atts : [];

			$out = [];
			$pos = 0;

			while ( $name = key( $pairs ) ) {
				$default = array_shift( $pairs );
				if ( array_key_exists( $name, $atts ) ) {
					$out[ $name ] = $atts[ $name ];
				}
				else if ( array_key_exists( $pos, $atts ) ) {
					$out[ $name ] = $atts[ $pos ];
					++ $pos;
				}
				else {
					$out[ $name ] = $default;
				}
			}

			if ( $shortcode ) {
				$out = apply_filters( "shortcode_atts_{$shortcode}", $out, $pairs, $atts, $shortcode );
			}

			return $out;

		}

		private static function post( $fields, $object ) {
			if ( in_array( $fields[0], [ 'ID', 'post_author', 'post_content', 'post_date', 'post_excerpt', 'post_modified', 'post_name', 'post_parent', 'post_status', 'post_title', 'comment_count', 'comment_status' ] ) ) {
				if ( $post = get_post( $object, 'OBJECT', 'display' ) ) {
					$field = array_shift( $fields );
					$data = $post->$field;
					if ( 'post_parent' == $field && count( $fields ) && $data ) {
						$data = self::post( $fields, $data );
					}
					if ( 'post_author' == $field && count( $fields ) ) {
						$data = self::user( $fields, $data );
					}
					return $data;
				}
			}
			else if ( 'featured_image' == $fields[0] ) {
				$id = get_metadata( 'post', $object, '_thumbnail_id', true );
				switch ( $fields[1] ?? null ) {
					case 'id':
						return $id;
					case 'alt':
						return get_post_meta( $id, '_wp_attachment_image_alt', true );
					case 'caption':
						return get_post( $id )->post_excerpt;
					case 'title':
						return get_post( $id )->post_title;
					case 'description':
						return get_post( $id )->post_content;
					case 'url':
						return get_permalink( $id );
					case 'html':
						return wp_get_attachment_image( $id, $fields[2] ?? 'thumbnail' );
					default:
						return null;
				}
			}
			else {
				$metadata = get_metadata( 'post', $object, array_shift( $fields ), true );
				return self::subfield( $fields, $metadata );
			}
		}

		private static function user( $fields, $object ) {
			return get_the_author_meta( $fields[0], $object );
		}

		private static function acf( $fields, $object ) {
			return function_exists( 'get_field' ) ? get_field( $fields[0], $object ) : null;
		}

		private static function option( $fields, $object ) {
			$option = get_option( array_shift( $fields ), null );
			return self::subfield( $fields, $option );
		}

		private static function bloginfo( $fields, $object ) {
			return get_bloginfo( $fields[0] );
		}

		private static function subfield( $fields, $data ) {
			if ( $field = array_shift( $fields ) ) {
				if ( $data = $data[ $field ] ?? null ) {
					$data = self::subfield( $fields, $data );
				}
			}
			return $data;
		}

	}

	new Plugin;

}
