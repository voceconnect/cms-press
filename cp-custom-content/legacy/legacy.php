<?php
/**
 * This file contains functions that have been replaced by core functions, but are
 * still needed in older versions of WP
 * 
 */

if(!function_exists('post_type_supports'))
{
	/**
	 * implementation of post_type_supports for WP 2.9
	 */
	function post_type_supports( $post_type, $feature ) {
		global $wp_post_types;
		if ( isset($wp_post_types[$post_type]) && isset( $wp_post_types[$post_type]->supports) && in_array($feature, $wp_post_types[$post_type]->supports ) )
			return true;
		return false;
	}
}

if(!function_exists('home_url'))
{
	function home_url( $path = '', $scheme = null ) 
	{
		$orig_scheme = $scheme;
		$scheme      = is_ssl() && !is_admin() ? 'https' : 'http';
		$url = str_replace( 'http://', "$scheme://", get_option('home') );
	
		if ( !empty( $path ) && is_string( $path ) && strpos( $path, '..' ) === false )
			$url .= '/' . ltrim( $path, '/' );
	
		return apply_filters( 'home_url', $url, $path, $orig_scheme );
	}
}