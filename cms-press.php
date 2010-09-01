<?php
/**
 * @package CMS Press
 * @author Michael Pretty
 * @version 0.1.6
 */
/*
Plugin Name: CMS Press
Plugin URI: http://vocecommunications.com/services/web-development/wordpress/plugins/cms-press/
Description: Adds ability to create custom post_types and taxonomies
Author: Michael Pretty (prettyboymp)
Version: 0.1.8
Author URI: http://voceconnect.com
*/

/*  Copyright 2010 Voce Communications
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
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
register_activation_hook(basename(dirname(__FILE__)).'/'.basename(__FILE__), 'on_cmspress_activation');
?>
