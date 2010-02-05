<?php
/**
 * DEPRECATED AS OF WP 3.0
 */


/** setup content specific variables **/
$content_title = $this->get_type_label();
$content_title_plural = $this->get_type_label_plural();
$post_type = $this->get_content_type();
global $post;
/** END setup content specific variables **/

if ( empty($title) )
	$title = "Add New $content_title";

if ( current_user_can('edit_pages') ) {
	$action = 'post';
	$post = get_default_post_to_edit();
	$post->post_type = $post_type;
	include(dirname(__FILE__).'/edit-content-form.php');
}