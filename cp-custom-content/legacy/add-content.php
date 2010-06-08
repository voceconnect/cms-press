<?php
/**
 * DEPRECATED AS OF WP 3.0
 */


/** setup content specific variables **/
$labels = $this->get_type_labels();
$post_type = $this->get_content_type();
/** END setup content specific variables **/

if ( empty($title) )
	$title = $labels['add_new_item'];

if ( current_user_can('edit_pages') ) {
	$action = 'post';
	$post = get_default_post_to_edit();
	$post->post_type = $post_type;
	include(dirname(__FILE__).'/edit-content-form.php');
}