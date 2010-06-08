<?php
/**
 * DEPRECATED AS OF WP 3.0
 */

/** setup content specific variables **/
$labels = $this->get_type_labels();
$post_type = $this->get_content_type();
/** END setup content specific variables **/

global $post, $post_ID, $temp_ID; //I hate globals by the way
$title = $labels['edit_item'];
$editing = true;
$page_ID = $post_ID = $p = (int) $_GET['post'];
$post = get_post_to_edit($post_ID);

if (empty($post->ID))
{
	wp_die( __('You attempted to edit a '.$labels['singular_name'].' that doesn&#8217;t exist. Perhaps it was deleted?') );
}
if ( !current_user_can('edit_page', $page_ID) )
{
	wp_die( __('You are not allowed to edit this '.$labels['singular_name'].'.') );
}
if ( 'trash' == $post->post_status )
{
	wp_die( __('You can&#8217;t edit this page because it is in the Trash. Please move it out of the Trash and try again.') );
}

if ( $last = wp_check_post_lock( $post->ID ) )
{
	$last_user = get_userdata( $last );
	$last_user_name = $last_user ? $last_user->display_name : __('Somebody');
	$message = sprintf( __( 'Warning: %s is currently editing this page' ), esc_html( $last_user_name ) );
	$message = str_replace( "'", "\'", "<div class='error'><p>$message</p></div>" );
	add_action('admin_notices', create_function( '', "echo '$message';" ) );
}
else
{
	wp_set_post_lock( $post->ID );
	wp_enqueue_script('autosave');
}

include(dirname(__FILE__).'/edit-content-form.php');
