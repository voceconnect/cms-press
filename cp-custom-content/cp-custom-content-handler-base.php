<?php
/**
 * Base class for Custom Content Handlers
 *
 * All custom content handlers should extend this class
 *
 */
abstract class CP_Custom_Content_Handler_Base implements iCP_Custom_Content_Handler
{
	/**
	 * Labels object instance
	 *
	 * @var object
	 */
	protected $labels;
	
	public function get_type_labels()
	{
		if(!isset($this->labels)) {
			$labels = array(
				'name' => $this->get_type_label_plural(),
				'singular_name' => $this->get_type_label(),
				'add_new' => __('Add New'),
				'add_new_item' => sprintf( __('Add New %s'), $this->get_type_label() ),
				'edit_item' => sprintf( __('Edit %s'), $this->get_type_label()),
				'new_item' => sprintf( __('New %s'), $this->get_type_label() ),
				'view_item' => sprintf( __('View %s'), $this->get_type_label() ),
				'search_items' => sprintf( __('Search %s'), $this->get_type_label_plural() ),
				'not_found' => sprintf( __('No %s found'), $this->get_type_label_plural() ),
				'not_found_in_trash' => sprintf( __('No %s found in Trash'), $this->get_type_label_plural() ),
				'parent_item_colon' => sprintf( __('Parent %s:'), $this->get_type_label() )
			);
			$this->labels = $labels;
		}
		return $this->labels;
	}
	
	/**
	 * Returns whether the post_type is public/Shows in admin menu
	 *
	 * @return bool
	 */
	public function get_type_is_public()
	{
		return true;
	}
	
	/**
	 * returns whether the post_type is hierarchical
	 *
	 * @return bool
	 */
	public function get_type_is_hierarchical()
	{
		return false;
	}
	
	/**
	 * returns the permission type of the post_type
	 *
	 * @return string
	 */
	public function get_type_capability_type()
	{
		return 'post';
	}
	
	/**
	 * returns whether the post_type should be included in search results
	 *
	 * @return bool
	 */
	public function get_type_exclude_from_search()
	{
		return false;
	}
	
	/**
	 * returns whether the post_type should be allowed as post_type public query_var
	 *
	 * @return bool
	 */
	public function get_type_publicly_queryable()
	{
		return true;
	}
	
	/**
	 * Returns whether the ui for the post type should show.
	 * Defaults to whether the type is public
	 *
	 * @return bool
	 */
	public function get_type_show_ui()
	{
		return $this->get_type_is_public();
	}
	
	/**
	 * returns the edit link for the content type
	 *
	 * @return unknown
	 */
	public function get_type_edit_link()
	{
		if(version_compare(get_wp_version(), '3.0-dev', '<'))
		{
			return 'admin.php?page=cp-custom-content/manage-'.$this->get_content_type().'.php&post=%d';
		}
		return false;
	}
	
	/**
	 * Returns the url to the icon for the content type
	 *
	 * @return string
	 */
	public function get_type_icon_url()
	{
		return '';
	}
	
	/**
	 * Returns an array of features the content type supports
	 *
	 * @return array
	 */
	public function get_type_supports()
	{
		return array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions');
	}
	
	public function get_type_permastructure()
	{
		return array('identifier' => $this->get_type_query_var(), 'structure' => '%identifier%/'.get_option('permalink_structure'));
	}
	
	public function get_type_rewrite()
	{
		return false;
	}
	
	public function get_type_query_var()
	{
		return $this->get_content_type();
	}
	
	/**
	 * Place holder method for adding content_type specific hooks
	 *
	 */
	public function add_custom_hooks()
	{
		//do nothing
	}
	
	/**
	 * Called from CP_Custom_Content_Core::setup_custom_content
	 * This should be used to add any 
	 *
	 */
	public final function add_base_hooks()
	{
		//add permastruct handling
		if(version_compare(get_wp_version(), '3.0-dev', '>='))
		{
			add_filter('post_type_link', array($this, 'post_link'), 10, 3);
		}
		else 
		{
			add_filter('post_link', array($this, 'post_link'), 10, 3);
		}
	}
	
	/**
	 * Registers the rewrite rules for the content_type with the system.
	 *
	 */
	public function add_rewrite_rules()
	{
		if($this->get_type_publicly_queryable())
		{
			global $wp_rewrite;
			$permastructure = $this->get_type_permastructure();
			$structure = $permastructure['structure'];
			$front = substr($structure, 0, strpos($structure, '%'));
			$type_query_var = $this->get_type_query_var();
			$structure = str_replace('%identifier%', $permastructure['identifier'], $structure);
			$rewrite_rules = $wp_rewrite->generate_rewrite_rules($structure, EP_NONE, true, true, true, true, true);
			
			//build a rewrite rule from just the identifier if it is the first token		
			preg_match('/%.+?%/', $permastructure['structure'], $tokens);
			if($tokens[0] == '%identifier%')
			{
				$rewrite_rules = array_merge($wp_rewrite->generate_rewrite_rules($front.$permastructure['identifier'].'/'), $rewrite_rules);
				$rewrite_rules[$front.$permastructure['identifier'].'/?$'] = 'index.php?paged=1';
			}

			foreach($rewrite_rules as $regex => $redirect)
			{
				if(strpos($redirect, 'attachment=') === false)
				{
					//don't set the post_type for attachments
					$redirect .= '&post_type='.$this->get_content_type();
				}
				if(0 < preg_match_all('@\$([0-9])@', $redirect, $matches))
				{
					for($i = 0; $i < count($matches[0]); $i++)
					{
						$redirect = str_replace($matches[0][$i], '$matches['.$matches[1][$i].']', $redirect);
					}
				}
				if(version_compare(get_wp_version(), '3.0-dev', '>=') && $type_query_var)
				{
					$redirect = str_replace('name=', $type_query_var.'=', $redirect);
				}
				add_rewrite_rule($regex, $redirect, 'top');
			}
		}
	}
	
	/**
	 * Permalink handling for post_type
	 *
	 * @param string $permalink
	 * @param objecy $post
	 * @param bool $leavename
	 * @return string
	 */
	public function post_link($permalink, $id, $leavename = false)
	{
		if ( is_object($id) && isset($id->filter) && 'sample' == $id->filter ) {
			$post = $id;
		} else {
			$post = &get_post($id);
		}
	
		if ( empty($post->ID) || $this->get_content_type() != $post->post_type || $this->get_type_is_hierarchical() ) return $permalink;
		
		$rewritecode = array(
			'%identifier%',
			'%year%',
			'%monthnum%',
			'%day%',
			'%hour%',
			'%minute%',
			'%second%',
			$leavename? '' : '%postname%',
			'%post_id%',
			'%category%',
			'%author%',
			$leavename? '' : '%pagename%',
		);
	
		$permastructure = $this->get_type_permastructure();
		$identifier = $permastructure['identifier'];
		$permalink = $permastructure['structure'];
		if ( '' != $permalink && !in_array($post->post_status, array('draft', 'pending', 'auto-draft')) ) 
		{
			$unixtime = strtotime($post->post_date);
	
			$category = '';
			if ( strpos($permalink, '%category%') !== false ) {
				$cats = get_the_category($post->ID);
				if ( $cats ) {
					usort($cats, '_usort_terms_by_ID'); // order by ID
					$category = $cats[0]->slug;
					if ( $parent = $cats[0]->parent )
						$category = get_category_parents($parent, false, '/', true) . $category;
				}
				// show default category in permalinks, without
				// having to assign it explicitly
				if ( empty($category) ) {
					$default_category = get_category( get_option( 'default_category' ) );
					$category = is_wp_error( $default_category ) ? '' : $default_category->slug;
				}
			}
	
			$author = '';
			if ( strpos($permalink, '%author%') !== false ) {
				$authordata = get_userdata($post->post_author);
				$author = $authordata->user_nicename;
			}
	
			$date = explode(" ",date('Y m d H i s', $unixtime));
			$rewritereplace =
			array(
				$identifier,
				$date[0],
				$date[1],
				$date[2],
				$date[3],
				$date[4],
				$date[5],
				$post->post_name,
				$post->ID,
				$category,
				$author,
				$post->post_name,
			);
			$permalink = home_url( str_replace($rewritecode, $rewritereplace, $permalink) );
			$permalink = user_trailingslashit($permalink, 'single');
		}
		else 
		{
			$permalink = home_url('?p=' . $post->ID . '&post_type=' . urlencode($this->get_content_type() ));
		}
		return $permalink;
	}
	
	/**
	 * registers the current content handler.  Child plugins should
	 * register this function to fire on the 'setup_custom_content' action
	 *
	 * @example add_action('setup_custom_content', array($handler, 'on_setup_custom_content'));
	 *
	 */
	public function on_setup_custom_content()
	{
		if(strlen($this->get_content_type()) == 0)
		{
			trigger_error("Custom_Content_Handler must have a set content_type.  Content Type was not loaded.", E_WARNING);
			return;
		}
		CP_Custom_Content_Core::GetInstance()->register_custom_content_type($this);
	}

	/**
	 * Adds metaboxes for the given post_type
	 * @todo change this to run off of a setting driven dataset
	 *
	 */
	protected function add_meta_boxes()
	{
		add_meta_box('submitdiv', __('Publish'), 'post_submit_meta_box', $this->get_content_type(), 'side', 'core');
		add_meta_box( $this->get_content_type().'_slugdiv', __('Post Slug'), 'post_slug_meta_box', $this->get_content_type(), 'normal', 'core');

		/**
		 * @todo pending patch 'hidden_meta_boxes.patch' submitted to http://core.trac.wordpress.org/ticket/10437
		 */
		add_filter('get_hidden_meta_boxes', array($this, 'filter_hidden_meta_boxes'), 10, 2);

		// add taxonomies
		foreach ( get_object_taxonomies($this->get_content_type()) as $tax_name ) {
			if ( !is_taxonomy_hierarchical($tax_name) ) {
				$taxonomy = get_taxonomy($tax_name);
				$label = isset($taxonomy->label) ? esc_attr($taxonomy->label) : $tax_name;
				add_meta_box('tagsdiv-' . $tax_name, $label, 'post_tags_meta_box', $this->get_content_type(), 'side', 'core');
			}
		}
	}

	/**
	 * BEGIN WP 2.9 ONLY METHODS
	 */

	/**
	 * hides the slugs meta box
	 * @todo pending patch 'hidden_meta_boxes.patch' submitted to http://core.trac.wordpress.org/ticket/10437
	 *
	 * @param array $hidden_meta_boxes
	 * @param string $post_type
	 * @return array
	 */
	public function filter_hidden_meta_boxes($hidden_meta_boxes, $post_type)
	{
		return array_merge($hidden_meta_boxes, array($this->get_content_type().'_slugdiv'));
	}

	/**
	 * Setup handling for the manage page for the content type.
	 *
	 * @todo this should be changed to share more code between the add
	 * and edit custom type setup
	 */
	public function setup_manage_page()
	{
		if(isset($_REQUEST['post']))
		{
			$this->setup_edit_page('edit');
		}
		else
		{
			add_filter('manage_' . $this->get_content_type() . '_columns', array($this, 'manage_columns'));
		}
	}
	
	/**
	 * Sets the columns for the edit/add page for the content type.  Default is 2
	 *
	 * @param array $columns
	 * @param string $screen
	 * @return array
	 */
	public function filter_screen_layout_columns($columns, $screen)
	{
		$columns[$screen] = 2;
		return $columns;
	}

	/**
	 * Default function for handling manage page post backs.
	 *
	 */
	public function handle_manage_page_postback()
	{
		global $wpdb;

		if (! current_user_can ( 'edit_pages' ))
		{
			wp_die ( __ ( 'Cheatin&#8217; uh?' ) );
		}
		// Handle bulk actions
		if (isset ( $_POST ['doaction'] ) || isset ( $_POST ['doaction2'] ) || isset ( $_POST ['delete_all'] ) || isset ( $_POST ['delete_all2'] ) || isset ( $_POST ['bulk_edit'] ))
		{
			check_admin_referer ( 'bulk-'.$this->get_content_type() );
			$sendback = wp_get_referer ();

			if (isset ( $_POST ['delete_all'] ) || isset ( $_POST ['delete_all2'] ))
			{
				$post_status = preg_replace ( '/[^a-z0-9_-]+/i', '', $_POST ['post_status'] );
				$post_ids = $wpdb->get_col ( $wpdb->prepare ( "SELECT ID FROM $wpdb->posts WHERE post_type=%s AND post_status = %s", $this->get_content_type(), $post_status ) );
				$doaction = 'delete';
			}
			elseif (($_POST ['action'] != - 1 || $_POST ['action2'] != - 1) && isset ( $_POST ['post'] ))
			{
				$post_ids = array_map ( 'intval', ( array ) $_POST ['post'] );
				$doaction = ($_POST ['action'] != - 1) ? $_POST ['action'] : $_POST ['action2'];
			}
			else
			{
				wp_redirect ( $sendback );
			}

			//handle case where trash isn't available yet on VIP
			if($doaction == 'trash' && !function_exists('wp_trash_post'))
			{
				$doaction = 'delete';
			}

			switch ($doaction)
			{
				case 'trash' :
					$trashed = 0;
					foreach ( ( array ) $post_ids as $post_id )
					{
						if (! current_user_can ( 'delete_page', $post_id ))
						wp_die ( __ ( 'You are not allowed to move this page to the trash.' ) );
						if (! wp_trash_post ( $post_id ))
						{
							wp_die ( __ ( 'Error in moving to trash...' ) );
						}
						$trashed ++;
					}
					$sendback = add_query_arg ( 'trashed', $trashed, $sendback );
					break;
				case 'untrash' :
					$untrashed = 0;
					foreach ( ( array ) $post_ids as $post_id )
					{
						if (! current_user_can ( 'delete_page', $post_id ))
						wp_die ( __ ( 'You are not allowed to restore this page from the trash.' ) );

						if (! wp_untrash_post ( $post_id ))
						wp_die ( __ ( 'Error in restoring from trash...' ) );

						$untrashed ++;
					}
					$sendback = add_query_arg ( 'untrashed', $untrashed, $sendback );
					break;
				case 'delete' :
					$deleted = 0;
					foreach ( ( array ) $post_ids as $post_id )
					{
						$post_del = & get_post ( $post_id );

						if (! current_user_can ( 'delete_page', $post_id ))
						wp_die ( __ ( 'You are not allowed to delete this page.' ) );

						if ($post_del->post_type == 'attachment')
						{
							if (! wp_delete_attachment ( $post_id ))
							wp_die ( __ ( 'Error in deleting...' ) );
						}
						else
						{
							if (! wp_delete_post ( $post_id ))
							wp_die ( __ ( 'Error in deleting...' ) );
						}
						$deleted ++;
					}
					$sendback = add_query_arg ( 'deleted', $deleted, $sendback );
					break;
				case 'edit' :
					$_POST ['post_type'] = $this->get_content_type();
					$done = bulk_edit_posts ( $_POST );

					if (is_array ( $done ))
					{
						$done ['updated'] = count ( $done ['updated'] );
						$done ['skipped'] = count ( $done ['skipped'] );
						$done ['locked'] = count ( $done ['locked'] );
						$sendback = add_query_arg ( $done, $sendback );
					}
					break;
			}

			if (isset ( $_POST ['action'] ))
			$sendback = remove_query_arg ( array ('action', 'action2', 'post_parent', 'page_template', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view', 'post_type' ), $sendback );

			wp_redirect ( $sendback );
			exit ();
		}
		elseif (isset ( $_POST ['_wp_http_referer'] ) && ! empty ( $_POST ['_wp_http_referer'] ))
		{
			wp_redirect ( remove_query_arg ( array ('_wp_http_referer', '_wpnonce' ), stripslashes ( $_SERVER ['REQUEST_URI'] ) ) );
			exit ();
		}
	}
	
	/**
	 * Enqueues the needed scripts and hooks for the add page for the custom
	 * content type. Calls $this->add_meta_boxes for adding custom content
	 * specific metaboxes
	 *
	 */
	public function setup_add_page()
	{
		$this->setup_edit_page();
	}

	/**
	 * Enqueues the needed scripts and files for the edit page
	 *
	 * @param string $mode 'add'|'edit' modes allow turning on and off of specific scripts depending on the mode.
	 */
	public function setup_edit_page($mode = 'add')
	{
		wp_enqueue_script('autosave');
		wp_enqueue_script('page');
		if ( user_can_richedit() )
		{
			wp_enqueue_script('editor');
		}
		add_thickbox();
		wp_enqueue_script('media-upload');
		wp_enqueue_script('word-count');

		add_action( 'admin_print_footer_scripts', 'wp_tiny_mce', 25 );
		wp_enqueue_script('quicktags');
		wp_enqueue_script('post');

		require_once(ABSPATH .'/wp-admin/includes/meta-boxes.php');
		$this->add_meta_boxes();

		add_filter('screen_layout_columns', array($this, 'filter_screen_layout_columns'), 10, 2);
	}

	public function manage_columns($columns)
	{
		return wp_manage_pages_columns();
	}

	/**
	 * Default handling for updating content types.  This should be overridden if special handling is needed
	 *
	 * @param int $post_ID
	 * @param array $post_data
	 */
	public function update_content($post_ID, $post_data)
	{
		$page_ID = edit_post($post_data);
		if(isset($_POST['wp-preview']) && $_POST['wp-preview'] == 'dopreview')
		{
			wp_redirect(get_permalink($post_ID));
			exit();
		}
		$this->redirect_content($page_ID);
	}

	/**
	 * Redirects to the edit url for the given post_ID
	 *
	 * @param int $post_ID
	 */
	public function redirect_content($post_ID)
	{
		wp_redirect(get_edit_post_link($post_ID));
		exit();
	}

	/**
	 * Public function for printing the management rows of the content type
	 *
	 * @param array $posts
	 * @param int $pagenum
	 * @param int $per_page
	 */
	public function manage_rows($posts, $pagenum, $per_page)
	{
		global $wp_query, $post, $mode;
		add_filter('the_title','esc_html');

		// Create array of post IDs.
		$post_ids = array();

		if ( empty($posts) )
		{
			$posts = &$wp_query->posts;
		}
		foreach ( $posts as $a_post )
		{
			$post_ids[] = $a_post->ID;
		}
		$comment_pending_count = get_pending_comments_num($post_ids);
		if ( empty($comment_pending_count) )
		{
			$comment_pending_count = array();
		}
		foreach ( $posts as $post )
		{
			if ( empty($comment_pending_count[$post->ID]) )
			{
				$comment_pending_count[$post->ID] = 0;
			}
			$this->_manage_row($post, $comment_pending_count[$post->ID], $mode);
		}
	}

	/**
	 * prints the individual row
	 *
	 * @param object $post
	 * @param int $comment_pending_count
	 * @param string $mode
	 */
	protected function _manage_row($post, $comment_pending_count, $mode)
	{
		static $rowclass;
		$global_post = $post;
		setup_postdata($post);
		$rowclass = 'alternate' == $rowclass ? '' : 'alternate';
		$current_user = wp_get_current_user();
		$post_owner = ( $current_user->ID == $post->post_author ? 'self' : 'other' );
		$edit_link = get_edit_post_link( $post->ID );
		$title = _draft_or_post_title($post->ID);
		?>
		<tr id='post-<?php echo $post->ID; ?>'	class='<?php echo trim( $rowclass . ' author-' . $post_owner . ' status-' . $post->post_status ); ?> iedit'	valign="top">
			<?php
			$posts_columns = get_column_headers($this->get_content_type());
			$hidden = get_hidden_columns($this->get_content_type());
			foreach ( $posts_columns as $column_name=>$column_display_name )
			{
				$class = "class=\"$column_name column-$column_name\"";
				$style = '';
				if ( in_array($column_name, $hidden) )
				{
					$style = ' style="display:none;"';
				}
				$attributes = "$class$style";

				switch ($column_name)
				{
					case 'cb':
						?>
						<th scope="row" class="check-column"><?php if ( current_user_can( 'edit_post', $post->ID ) ) { ?><input	type="checkbox" name="post[]" value="<?php the_ID(); ?>" /><?php } ?></th>
						<?php
						break;
						//end case 'cb'

					case 'date':
						if ( '0000-00-00 00:00:00' == $post->post_date && 'date' == $column_name )
						{
							$t_time = $h_time = __('Unpublished');
							$time_diff = 0;
						} else
						{
							$t_time = get_the_time(__('Y/m/d g:i:s A'));
							$m_time = $post->post_date;
							$time = get_post_time('G', true, $post);

							$time_diff = time() - $time;

							if ( $time_diff > 0 && $time_diff < 24*60*60 )
							$h_time = sprintf( __('%s ago'), human_time_diff( $time ) );
							else
							$h_time = mysql2date(__('Y/m/d'), $m_time);
						}
						echo '<td ' . $attributes . '>';
						if ( 'excerpt' == $mode )
						{
							echo apply_filters('post_date_column_time', $t_time, $post, $column_name, $mode);
						}
						else
						{
							echo '<abbr title="' . $t_time . '">' . apply_filters('post_date_column_time', $h_time, $post, $column_name, $mode) . '</abbr>';
						}
						echo '<br />';
						if ( 'publish' == $post->post_status )
						{
							_e('Published');
						}
						elseif ( 'future' == $post->post_status )
						{
							if ( $time_diff > 0 )
							{
								echo '<strong class="attention">' . __('Missed schedule') . '</strong>';
							}
							else
							{
								_e('Scheduled');
							}
						}
						else
						{
							_e('Last Modified');
						}
						echo '</td>';
						break;
						//end case 'date'

					case 'title':
						$attributes = 'class="post-title column-title"' . $style;
						?>
						<td <?php echo $attributes ?>><strong><?php if ( current_user_can('edit_post', $post->ID) && $post->post_status != 'trash' ) { ?><a	class="row-title" href="<?php echo $edit_link; ?>" title="<?php echo esc_attr(sprintf(__('Edit &#8220;%s&#8221;'), $title)); ?>"><?php echo $title ?></a><?php } else { echo $title; }; _post_states($post); ?></strong>
						<?php
						if ( 'excerpt' == $mode )
						{
							the_excerpt();
						}
						$actions = array();
						if ( 'trash' == $post->post_status && current_user_can('delete_post', $post->ID) )
						{
							$actions['untrash'] = "<a title='" . esc_attr(__('Remove this post from the Trash')) . "' href='" . wp_nonce_url("post.php?action=untrash&amp;post=$post->ID", 'untrash-post_' . $post->ID) . "'>" . __('Restore') . "</a>";
							$actions['delete'] = "<a class='submitdelete' title='" . esc_attr(__('Delete this post permanently')) . "' href='" . wp_nonce_url("post.php?action=delete&amp;post=$post->ID", 'delete-post_' . $post->ID) . "'>" . __('Delete Permanently') . "</a>";
						}
						else
						{
							if ( current_user_can('edit_post', $post->ID) )
							{
								$actions['edit'] = '<a href="' . get_edit_post_link($post->ID, true) . '" title="' . esc_attr(__('Edit this post')) . '">' . __('Edit') . '</a>';
								//removing quickedit for now
								//$actions['inline hide-if-no-js'] = '<a href="#" class="editinline" title="' . esc_attr(__('Edit this post inline')) . '">' . __('Quick&nbsp;Edit') . '</a>';
							}
							if ( current_user_can('delete_post', $post->ID) && function_exists('wp_trash_post'))
							{
								$actions['trash'] = "<a class='submitdelete' title='" . esc_attr(__('Move this post to the Trash')) . "' href='" . wp_nonce_url("post.php?action=trash&amp;post=$post->ID", 'trash-post_' . $post->ID) . "'>" . __('Trash') . "</a>";
							}
							else
							{
								$actions['delete'] = "<a class='submitdelete' title='" . esc_attr(__('Delete this post permanently')) . "' href='" . wp_nonce_url("post.php?action=delete&amp;post=$post->ID", 'delete-post_' . $post->ID) . "'>" . __('Delete Permanently') . "</a>";
							}
							if ( in_array($post->post_status, array('pending', 'draft')) )
							{
								if ( current_user_can('edit_post', $post->ID) )
								$actions['view'] = '<a href="' . get_permalink($post->ID) . '" title="' . esc_attr(sprintf(__('Preview &#8220;%s&#8221;'), $title)) . '" rel="permalink">' . __('Preview') . '</a>';
							}
							else
							{
								$actions['view'] = '<a href="' . get_permalink($post->ID) . '" title="' . esc_attr(sprintf(__('View &#8220;%s&#8221;'), $title)) . '" rel="permalink">' . __('View') . '</a>';
							}
						}
						$actions = apply_filters('post_row_actions', $actions, $post);
						$action_count = count($actions);
						$i = 0;
						echo '<div class="row-actions">';
						foreach ( $actions as $action => $link )
						{
							++$i;
							( $i == $action_count ) ? $sep = '' : $sep = ' | ';
							echo "<span class='$action'>$link$sep</span>";
						}
						echo '</div>';
						get_inline_data($post);
						?>
						</td>
						<?php
						break;
						//end case 'title'

					case 'categories':
						?>
						<td <?php echo $attributes ?>><?php
						$categories = get_the_category();
						if ( !empty( $categories ) ) {
							$out = array();
							foreach ( $categories as $c )
							$out[] = "<a href='edit.php?category_name=$c->slug'> " . esc_html(sanitize_term_field('name', $c->name, $c->term_id, 'category', 'display')) . "</a>";
							echo join( ', ', $out );
						} else {
							_e('Uncategorized');
						}
						?></td>
						<?php
						break;
						//end case 'categories'

					case 'tags':
						?>
						<td <?php echo $attributes ?>><?php
						$tags = get_the_tags($post->ID);
						if ( !empty( $tags ) )
						{
							$out = array();
							foreach ( $tags as $c )
							{
								$out[] = "<a href='edit.php?tag=$c->slug'> " . esc_html(sanitize_term_field('name', $c->name, $c->term_id, 'post_tag', 'display')) . "</a>";
							}
							echo join( ', ', $out );
						} else {
							_e('No Tags');
						}
							?>
						</td>
						<?php
						break;
						//end case 'tags'

					case 'comments':
						?>
						<td <?php echo $attributes ?>>
						<div class="post-com-count-wrapper">
						<?php
						if(!isset($pending_comments))
						{
							$pending_comments = 0;
						}
						$pending_phrase = sprintf( __('%s pending'), number_format( $pending_comments ) );
						if ( $pending_comments )
						echo '<strong>';
						comments_number("<a href='edit-comments.php?p=$post->ID' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . /* translators: comment count link */ _x('0', 'comment count') . '</span></a>', "<a href='edit-comments.php?p=$post->ID' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . /* translators: comment count link */ _x('1', 'comment count') . '</span></a>', "<a href='edit-comments.php?p=$post->ID' title='$pending_phrase' class='post-com-count'><span class='comment-count'>" . /* translators: comment count link: % will be substituted by comment count */ _x('%', 'comment count') . '</span></a>');
						if ( $pending_comments )
						echo '</strong>';
						?>
						</div>
						</td>
						<?php
						break;
						//end case 'comments'

					case 'author':
						?>
						<td <?php echo $attributes ?>><a href="edit.php?author=<?php the_author_meta('ID'); ?>"><?php the_author() ?></a></td>
						<?php
						break;
						//end case 'author'

					case 'control_view':
						?>
						<td><a href="<?php the_permalink(); ?>" rel="permalink"	class="view"><?php _e('View'); ?></a></td>
						<?php
						break;
						//end case 'control_view'

					case 'control_edit':
						?>
						<td><?php if ( current_user_can('edit_post', $post->ID) ) { echo "<a href='$edit_link' class='edit'>" . __('Edit') . "</a>"; } ?></td>
						<?php
						break;
						//end case 'control_edit'

					case 'control_delete':
						?>
						<td><?php if ( current_user_can('delete_post', $post->ID) ) { echo "<a href='" . wp_nonce_url("post.php?action=delete&amp;post=$post->ID", 'delete-post_' . $post->ID) . "' class='delete'>" . __('Delete') . "</a>"; } ?></td>
						<?php
						break;
						//end case 'control_delete

					default:
						?>
						<td <?php echo $attributes ?>><?php do_action('manage_posts_custom_column', $column_name, $post->ID); ?></td>
						<?php
						break;
				}
			}
			?>
		</tr>
		<?php
		$post = $global_post;
	}

	public function manage_content_page()
	{
		//do management page
		if(isset($_REQUEST['post']))
		{
			include(dirname(__FILE__).'/legacy/edit-content.php');
			return;
		}
		include(dirname(__FILE__).'/legacy/manage-content.php');
		return;
	}

	public function add_content_page()
	{
		include(apply_filters('add_custom_content_handler', dirname(__FILE__).'/legacy/add-content.php',  $this->get_content_type()));
		return;
	}

}

interface iCP_Custom_Content_Handler
{
	
	/**
	 * Returns the post_type for the custom content
	 *
	 * @return string
	 */
	public function get_content_type();
	
	/**
	 * Returns the name of the custom content
	 *
	 * @return string
	 */
	public function get_type_label();
	
	/**
	 * Returns the plural form of the custom content name
	 *
	 * @return string
	 */
	public function get_type_label_plural();
	
	/**
	 * Returns the labels array for the custom content type
	 *
	 * @return array
	 */
	public function get_type_labels();
	
	/**
	 * Returns whether the post_type is public/Shows in admin menu
	 *
	 * @return bool
	 */
	function get_type_is_public();
	
	/**
	 * returns whether the post_type is hierarchical
	 *
	 * @return bool
	 */
	public function get_type_is_hierarchical();
	
	/**
	 * returns the permission type of the post_type
	 *
	 * @return string
	 */
	public function get_type_capability_type();
	
	/**
	 * returns whether the post_type should be included in search results
	 *
	 * @return bool
	 */
	public function get_type_exclude_from_search();
	
	/**
	 * returns whether the post_type should be allowed as post_type public query_var
	 *
	 * @return bool
	 */
	public function get_type_publicly_queryable();
	
	public function get_type_show_ui();
	
	/**
	 * returns the edit link for the content type
	 *
	 * @return unknown
	 */
	public function get_type_edit_link();
	
	/**
	 * Returns the url to the icon for the content type
	 *
	 * @return string
	 */
	public function get_type_icon_url();
	
	/**
	 * Returns an array of features the content type supports
	 *
	 * @return array
	 */
	public function get_type_supports();
	
	public function get_type_permastructure();
	
	/**
	 * Place holder method for adding content_type specific hooks
	 *
	 */
	public function add_custom_hooks();
	
	/**
	 * Registers the rewrite rules for the content_type with the system.
	 *
	 */
	public function add_rewrite_rules();
	
	/**
	 * Permalink handling for post_type
	 *
	 * @param string $permalink
	 * @param objecy $post
	 * @param bool $leavename
	 * @return string
	 */
	public function post_link($permalink, $id, $leavename = false);
	
	/**
	 * registers the current content handler.  Child plugins should
	 * register this function to fire on the 'setup_custom_content' action
	 *
	 * @example add_action('setup_custom_content', array($handler, 'on_setup_custom_content'));
	 *
	 */
	public function on_setup_custom_content();
	
}
