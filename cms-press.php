<?php
/**
 * @package CMS Press
 * @author Michael Pretty
 * @version 0.01
 */
/*
Plugin Name: CMS Press
Plugin URI: http://voceconnect.com
Description: Adds ability to create custom post_types and taxonomies
Author: Michael Pretty (prettyboymp)
Version: 0.1.Beta
Author URI: http://voceconnect.com
*/

if(!function_exists('get_wp_version'))
{
	/**
	 * Returns the current WordPress version.  This is used to avoid the constant use of globals.
	 *
	 * @return string
	 */
	function get_wp_version()
	{
		global $wp_version;
		return $wp_version;
	}
}

if(!version_compare(get_wp_version(), '2.9', '>='))
{
	trigger_error('CMS Press requires WP version 2.9 or higher', E_USER_NOTICE);
	return;
}

define('CP_BASE_DIR', dirname(__FILE__));
define('CP_BASE_URL', str_replace(str_replace('\\', '/',ABSPATH), site_url().'/', str_replace('\\', '/', dirname(__FILE__))));

require_once(CP_BASE_DIR.'/cp-custom-content/legacy/legacy.php');

/**
 * Core files
 */
require_once(CP_BASE_DIR.'/cp-custom-content/cp-custom-content-core.php');
require_once(CP_BASE_DIR.'/cp-custom-taxonomy/cp-custom-taxonomy-core.php');

CP_Custom_Content_Core::Initialize();
CP_Custom_Taxonomy_Core::Initialize();

/**
 * Add dynamic content type handler(s)
 */
require_once(CP_BASE_DIR.'/child-plugins/dynamic/dynamic-content.php');
Dynamic_Content_Builder::Initialize();
require_once(CP_BASE_DIR.'/child-plugins/dynamic/dynamic-taxonomies.php');
Dynamic_Taxonomy_Builder::Initialize();


function on_cmspress_activation()
{
	$role = get_role('administrator');
	if(!$role->has_cap('manage_content_types')) {
		$role->add_cap('manage_content_types');
	}
	if(!$role->has_cap('manage_taxonomies')) {
		$role->add_cap('manage_taxonomies');
	}
}
register_activation_hook(__FILE__, 'on_cmspress_activation');
?>