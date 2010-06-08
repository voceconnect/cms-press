<?php
/**
 * DEPRECATED AS OF WP 3.0
 */

global $screen_layout_columns;

// don't load directly
if ( !defined('ABSPATH') )
	die('-1');

if ( ! isset( $post_ID ) )
	$post_ID = 0;
if ( ! isset( $temp_ID ) )
	$temp_ID = 0;


if ( isset($_GET['message']) )
	$_GET['message'] = absint( $_GET['message'] );
$messages[1] = sprintf(__('%s updated. <a href="%s">%s</a>'), $labels['singular_name'], get_permalink($post_ID), $labels['view_item']);
$messages[2] = __('Custom field updated.');
$messages[3] = __('Custom field deleted.');
$messages[5] = sprintf(__('%s published. <a href="%s">%s</a>'), $labels['singular_name'], get_permalink($post_ID), $labels['view_item']);
$messages[6] = sprintf(__('%s submitted. <a href="%s">Preview %s</a>'), $labels['singular_name'], add_query_arg( 'preview', 'true', get_permalink($post_ID) ), $labels['singular_name'] );

if ( isset($_GET['revision']) )
	$messages[5] = sprintf( __($content_title.' restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) );

$notice = false;

if ( 0 == $post_ID)
{
	$form_action = 'post';
	$nonce_action = 'add-post';
	$temp_ID = -1 * time(); // don't change this formula without looking at wp_write_post()
	$form_extra = "<input type='hidden' id='post_ID' name='temp_ID' value='$temp_ID' />";
}
else
{
	$post_ID = (int) $post_ID;
	$form_action = 'editpost';
	$nonce_action = 'update-post_' . $post_ID;
	$form_extra = "<input type='hidden' id='post_ID' name='post_ID' value='$post_ID' />";
	$autosave = wp_get_post_autosave( $post_ID );
	if ( $autosave && mysql2date( 'U', $autosave->post_modified_gmt, false ) > mysql2date( 'U', $post->post_modified_gmt, false ) )
		$notice = sprintf( __( 'There is an autosave of this %s that is more recent than the version below.  <a href="%s">View the autosave</a>.' ), $post_type, get_edit_post_link( $autosave->ID ) );
}

$temp_ID = (int) $temp_ID;
$current_user = wp_get_current_user();
$user_ID = (int) $current_user->ID;

add_meta_box('submitdiv', __('Publish'), 'post_submit_meta_box', $post_type, 'side', 'core');

// all tag-style taxonomies
foreach ( get_object_taxonomies($post_type) as $tax_name ) {
	$taxonomy = get_taxonomy($tax_name);
	$label = isset($taxonomy->label) ? esc_attr($taxonomy->label) : $tax_name;

	if ( !is_taxonomy_hierarchical($tax_name) ) {
		add_meta_box('tagsdiv-' . $tax_name, $label, 'post_tags_meta_box', $post_type, 'side', 'core');
	}
	else {
		add_meta_box($tax_name.'div', $label, 'post_categories_meta_box', 'post', 'side', 'core', array('taxonomy'=>$tax_name));
	}
}

if ( post_type_supports($post_type, 'page-attributes') )
	add_meta_box('pageparentdiv', __('Attributes'), 'page_attributes_meta_box', $post_type, 'side', 'core');

if ( current_theme_supports( 'post-thumbnails', $post_type ) && post_type_supports($post_type, 'post-thumbnails') )
	add_meta_box('postimagediv', __('Post Thumbnail'), 'post_thumbnail_meta_box', $post_type, 'side', 'low');

if ( post_type_supports($post_type, 'excerpts') )
	add_meta_box('postexcerpt', __('Excerpt'), 'post_excerpt_meta_box', $post_type, 'normal', 'core');

if ( post_type_supports($post_type, 'trackbacks') )
	add_meta_box('trackbacksdiv', __('Send Trackbacks'), 'post_trackback_meta_box', $post_type, 'normal', 'core');

if ( post_type_supports($post_type, 'custom-fields') )
	add_meta_box('postcustom', __('Custom Fields'), 'post_custom_meta_box', $post_type, 'normal', 'core');

do_action('dbx_post_advanced');
if ( post_type_supports($post_type, 'comments') )
	add_meta_box('commentstatusdiv', __('Discussion'), 'post_comment_status_meta_box', $post_type, 'normal', 'core');

if ( ('publish' == $post->post_status || 'private' == $post->post_status) && post_type_supports($post_type, 'comments') )
	add_meta_box('commentsdiv', __('Comments'), 'post_comment_meta_box', $post_type, 'normal', 'core');

$authors = get_editable_user_ids( $current_user->id ); // TODO: ROLE SYSTEM
if ( $post->post_author && !in_array($post->post_author, $authors) )
	$authors[] = $post->post_author;
if ( $authors && count( $authors ) > 1 )
	add_meta_box('authordiv', __('Author'), 'post_author_meta_box', $post_type, 'normal', 'core');

if ( post_type_supports($post_type, 'revisions') && 0 < $post_ID && wp_get_post_revisions( $post_ID ) )
	add_meta_box('revisionsdiv', __('Revisions'), 'post_revisions_meta_box', $post_type, 'normal', 'core');

do_action('do_meta_boxes', $post_type, 'normal', $post);
do_action('do_meta_boxes', $post_type, 'advanced', $post);
do_action('do_meta_boxes', $post_type, 'side', $post);

?>
<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php echo esc_html( $title ); ?></h2>
	<form name="post" action="<?php echo admin_url('admin-ajax.php')?>" method="post" id="post">
		<?php if ( $notice ) : ?>
			<div id="notice" class="error"><p><?php echo $notice ?></p></div>
		<?php endif; ?>
		<?php if (isset($_GET['message'])) : ?>
			<div id="message" class="updated fade"><p><?php echo $messages[$_GET['message']]; ?></p></div>
		<?php endif; ?>
		<?php
		wp_nonce_field($nonce_action);
		if (isset($mode) && 'bookmarklet' == $mode)
		{
			echo '<input type="hidden" name="mode" value="bookmarklet" />';
		}
		?>
		<input type="hidden" id="action" name="action" value="submit_custom_content" />
		<input type="hidden" id="user-id" name="user_ID" value="<?php echo $user_ID ?>" />
		<input type="hidden" id="hiddenaction" name="hiddenaction" value='<?php echo esc_attr($form_action) ?>' />
		<input type="hidden" id="originalaction" name="originalaction" value="<?php echo esc_attr($form_action) ?>" />
		<input type="hidden" id="post_author" name="post_author" value="<?php echo esc_attr( $post->post_author ); ?>" />
		<?php echo $form_extra ?>
		<input type="hidden" id="post_type" name="post_type" value="<?php echo esc_attr($post->post_type) ?>" />
		<input type="hidden" id="original_post_status" name="original_post_status" value="<?php echo esc_attr($post->post_status) ?>" />
		<input name="referredby" type="hidden" id="referredby" value="<?php echo esc_url(stripslashes(wp_get_referer())); ?>" />
		<?php if ( 'draft' != $post->post_status ) wp_original_referer_field(true, 'previous'); ?>
		<div id="poststuff" class="metabox-holder<?php echo 2 == $screen_layout_columns ? ' has-right-sidebar' : ''; ?>">
			<div id="side-info-column" class="inner-sidebar">
				<?php
				//do_action('submitpage_box');
				$side_meta_boxes = do_meta_boxes($post_type, 'side', $post);
				?>
			</div>
			<div id="post-body">
				<div id="post-body-content">
					<div id="titlediv">
						<div id="titlewrap">
							<label class="screen-reader-text" for="title"><?php _e('Title') ?></label>
							<input type="text" name="post_title" size="30" tabindex="1" value="<?php echo esc_attr( htmlspecialchars( $post->post_title ) ); ?>" id="title" autocomplete="off" />
						</div>
						<?php if($post->ID > 0) : ?>
							<div class="inside">
								<?php $sample_permalink_html = get_sample_permalink_html($post->ID); ?>
								<div id="edit-slug-box">
									<?php if ( ! empty($post->ID) && ! empty($sample_permalink_html) ){ echo $sample_permalink_html; }?>
								</div>
							</div>
						<?php endif; ?>
					</div>
					<div id="<?php echo user_can_richedit() ? 'postdivrich' : 'postdiv'; ?>" class="postarea">
						<?php the_editor($post->post_content); ?>
						<table id="post-status-info" cellspacing="0">
							<tbody>
								<tr>
									<td id="wp-word-count"></td>
									<td class="autosave-info">
										<span id="autosave">&nbsp;</span>
										<?php
										if ($post_ID)
										{
											if ( $last_id = get_post_meta($post_ID, '_edit_last', true) )
											{
												$last_user = get_userdata($last_id);
												printf(__('Last edited by %1$s on %2$s at %3$s'), esc_html( $last_user->display_name ), mysql2date(get_option('date_format'), $post->post_modified), mysql2date(get_option('time_format'), $post->post_modified));
											}
											else
											{
												printf(__('Last edited on %1$s at %2$s'), mysql2date(get_option('date_format'), $post->post_modified), mysql2date(get_option('time_format'), $post->post_modified));
											}
										}
										?>
									</td>
								</tr>
							</tbody>
						</table>
						<?php
						wp_nonce_field( 'autosave', 'autosavenonce', false );
						wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
						wp_nonce_field( 'getpermalink', 'getpermalinknonce', false );
						wp_nonce_field( 'samplepermalink', 'samplepermalinknonce', false );
						wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
						?>
					</div>
					<?php
					do_meta_boxes($post_type, 'normal', $post);
					do_action('edit_custom_content_form', $post_type);
					do_meta_boxes($post_type, 'advanced', $post);
					?>
				</div>
			</div>
		</div>
	</form>
</div>
<script type="text/javascript">
try{document.post.title.focus();}catch(e){}
</script>
