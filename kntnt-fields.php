<?php

/**
 * Kntnt Fields
 *
 * @package           PluginPackage
 * @author            Thomas Barregren
 * @copyright         2021 Thomas Barregren
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Kntnt Fields
 * Plugin URI:        https://github.com/Kntnt/kntnt-fields
 * Description:       Provides API and shortcode for getting value of bloginfo, options, users, posts and ACF fields.
 * Version:           2.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.3
 * Author:            Thomas Barregren
 * Author URI:        https://www.kntnt.se/thomas-barregren
 * Text Domain:       kntnt-field
 * License:           GPL v3 or later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Update URI:        https://github.com/Kntnt/kntnt-fields
 */

namespace {

	/**
	 * Returns the value of the filed described by $path.
	 *
	 * @param string $field Path describing the field to get.
	 * @param string $sep Separator used in the path. Default: '/'.
	 *
	 * @return string
	 */
	function kntnt_get_field( string $field, string $sep = '/' ) {
		list( $type, $object, $field ) = explode( '/', $field, 3 );
		return ( $field ? Kntnt\Fields\Plugin::get( $field, $type, $object, $sep ) : null ) ?: '';
	}

	/**
	 * Echoes the text field content of the field described by $path.
	 *
	 * @param string $field Path describing the field to get.
	 * @param string $sep Separator used in the path. Default: '/'.
	 */
	function kntnt_echo_text_field( string $field, string $sep = '/' ) {
		echo esc_html( kntnt_get_field( $field, $sep ) );
	}

	/**
	 * Echoes the HTML content of the field described by $path.
	 *
	 * @param string         $field Path describing the field to get.
	 * @param array[]|string $allowed_html An array of allowed HTML elements and attributes, or a context name such as
	 *     'post'. See wp_kses_allowed_html() for the list of accepted context names.
	 * @param string         $sep Separator used in the path. Default: '/'.
	 */
	function kntnt_echo_html_field( string $field, $allowed_html = 'post', string $sep = '/' ) {
		echo wp_kses( kntnt_get_field( $field, $sep ), $allowed_html );
	}

	/**
	 * Echoes the HTML attribute of the field described by $path.
	 *
	 * @param string $field Path describing the field to get.
	 * @param string $sep Separator used in the path. Default: '/'.
	 */
	function kntnt_echo_attr_field( string $field, string $sep = '/' ) {
		echo esc_attr( kntnt_get_field( $field, $sep ) );
	}

	/**
	 * Echoes the URL of the field described by $path.
	 *
	 * @param string $field Path describing the field to get.
	 * @param string $sep Separator used in the path. Default: '/'.
	 */
	function kntnt_echo_url_field( string $field, string $sep = '/' ) {
		echo esc_url( kntnt_get_field( $field, $sep ) );
	}

	/**
	 * Echoes the textarea content of the field described by $path.
	 *
	 * @param string $field Path describing the field to get.
	 * @param string $sep Separator used in the path. Default: '/'.
	 */
	function kntnt_echo_textarea_field( string $field, string $sep = '/' ) {
		echo esc_textarea( kntnt_get_field( $field, $sep ) );
	}
}

namespace Kntnt\Fields {

	/**
	 * The plugin itself.
	 */
	class Plugin {

		/**
		 * The default values of the shortcode arguments.
		 *
		 * @var string[] Shortcode argument => default value.
		 */
		private static $defaults = array(
			'field' => '',
			'sep'   => '/',
		);

		/**
		 * Creates the plugin.
		 */
		public function __construct() {
			add_shortcode( 'field', array( $this, 'field_shortcode' ) );
		}

		/**
		 * Returns the value of the field $field of the object $object which is
		 * of type $type.
		 *
		 * @param string $field Name of the field.
		 * @param string $type Type of object. Should be one of following:
		 *                       'post', 'user', 'acf', 'option', and 'bloginfo'.
		 * @param mixed  $object Object from which the field shall be retrieved.
		 * @param string $sep Separator used in the path. Default: '/'.
		 *
		 * @return mixed|null    Value of the retrieved field, or null iof it doesn't exist.
		 */
		public static function get( $field, $type = 'post', $object = null, $sep = '/' ) {
			if ( empty( $object ) ) {
				$object = null;
			}
			if ( in_array( $type, array( 'post', 'user', 'acf', 'option', 'bloginfo' ), true ) ) {
				return self::$type( explode( $sep, $field ), $object );
			} else {
				return null;
			}
		}

		/**
		 * Implements the shortcode [field …].
		 *
		 * @param array  $atts Attributes of the shortcode.
		 * @param string $content Not used.
		 * @param string $tag The string `field`.
		 *
		 * @return string The value of the field as a string.
		 */
		public function field_shortcode( array $atts, string $content, string $tag ) {

			$atts  = self::shortcode_atts( self::$defaults, $atts );
			$field = $atts['field']; // The field path.
			$sep   = $atts['sep']; // The field path separator.

			list( $type, $object, $field ) = explode( '/', $field, 3 );
			return self::get( $field, $type, $object, $sep ) ?? '';

		}

		/**
		 * A more forgiving version of WordPress' shortcode_atts(). In
		 * particular, it allows positional attributes.
		 *
		 * @param array  $pairs Entire list of supported attributes and their defaults.
		 * @param array  $atts User defined attributes in shortcode tag.
		 * @param string $shortcode Optional. The name of the shortcode, provided for context to enable filtering.
		 *
		 * @return array Combined and filtered attribute list.
		 *
		 * @see \shortcode_atts
		 */
		public static function shortcode_atts( $pairs, $atts, string $shortcode = '' ): array {

			// $atts can be a string which is cast to an array. An empty string should
			// be an empty array (not an array with an empty element as by casting).
			$atts = $atts ? (array) $atts : array();

			$out = array();
			$pos = 0;

			while ( $name = key( $pairs ) ) { // phpcs:ignore
				$default = array_shift( $pairs );
				if ( array_key_exists( $name, $atts ) ) {
					$out[ $name ] = $atts[ $name ];
				} elseif ( array_key_exists( $pos, $atts ) ) {
					$out[ $name ] = $atts[ $pos ];
					++ $pos;
				} else {
					$out[ $name ] = $default;
				}
			}

			if ( $shortcode ) {
				$out = apply_filters( "shortcode_atts_{$shortcode}", $out, $pairs, $atts, $shortcode );
			}

			return $out;

		}

		/**
		 * Returns the value of a post field including custom fields, featured
		 * image and post attributes.
		 *
		 * @param string[]  $fields An array representing the path to the field.
		 * @param int|false $object Optional. Post ID.
		 *
		 * @return string The value of the string.
		 *
		 * @see \get_metadata
		 */
		private static function post( $fields, $object ) {
			if ( in_array(
				$fields[0],
				array(
					'ID',
					'post_author',
					'post_content',
					'post_date',
					'post_excerpt',
					'post_modified',
					'post_name',
					'post_parent',
					'post_status',
					'post_title',
					'comment_count',
					'comment_status',
				),
				true
			) ) {
				$post = get_post( $object, 'OBJECT', 'display' );
				if ( $post ) {
					$field = array_shift( $fields );
					$data  = $post->$field;
					if ( 'post_parent' === $field && count( $fields ) && $data ) {
						$data = self::post( $fields, $data );
					}
					if ( 'post_author' === $field && count( $fields ) ) {
						$data = self::user( $fields, $data );
					}
					return $data;
				}
			} elseif ( 'featured_image' === $fields[0] ) {
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
			} else {
				$metadata = get_metadata( 'post', $object, array_shift( $fields ), true );
				return self::subfield( $fields, $metadata );
			}
		}

		/**
		 * Returns the value of a user field.
		 *
		 * @param string[]  $fields An array representing the path to the field.
		 * @param int|false $object Optional. User ID.
		 *
		 * @return string The value of the string.
		 *
		 * @see \get_the_author_meta
		 */
		private static function user( $fields, $object ) {
			return get_the_author_meta( $fields[0], $object );
		}

		/**
		 * Returns the value of a field stored by Advanced Custom Fields (ACF).
		 *
		 * @param string[]        $fields An array representing the path to the field.
		 * @param int|string|null $object Optional. The ID of the post from which the field is retrieved.
		 *
		 * @return string The value of the string.
		 *
		 * @see \get_field of Advanced Custom Field ACF)
		 */
		private static function acf( $fields, $object ) {
			return function_exists( 'get_field' ) ? get_field( $fields[0], $object ) : null;
		}

		/**
		 * Returns the value of an option.
		 *
		 * @param string[] $fields An array representing the path to the option.
		 * @param mixed    $object Ignored.
		 *
		 * @return string The value of the string.
		 */
		private static function option( $fields, $object ) {
			$option = get_option( array_shift( $fields ), null );
			return self::subfield( $fields, $option );
		}

		/**
		 * Returns the value of a bloginfo field.
		 *
		 * @param string[] $fields An array representing the path to the field.
		 * @param mixed    $object Ignored.
		 *
		 * @return string The value of the string.
		 *
		 * @see \get_bloginfo
		 */
		private static function bloginfo( $fields, $object ) {
			return get_bloginfo( $fields[0] );
		}

		/**
		 * Recursively wlk down the path $fields.
		 *
		 * @param string[] $fields An array representing the path to the field.
		 * @param mixed    $data An array of data collected.
		 *
		 * @return mixed|null
		 */
		private static function subfield( $fields, $data ) {
			$field = array_shift( $fields );
			if ( $field ) {
				$data = $data[ $field ] ?? null;
				if ( $data ) {
					$data = self::subfield( $fields, $data );
				}
			}
			return $data;
		}

	}

	new Plugin();

}
