<?php
/**
 * Wrapper class for the dynamic content type data instances
 *
 */
class Dynamic_Content_Handler extends CP_Custom_Content_Handler_Base
{
	private $content_type;
	private $settings;

	public function __construct($content_type = '', $settings = array())
	{
		$this->content_type = sanitize_title_with_dashes(strtolower($content_type));
	 	$default_settings = array(
			'label' => $content_type,
			'label_plural' => $content_type,
			'exclude_from_search' => false,
			'publicly_queryable' => false,
			'public' => true,
			'hierarchical' => false,
			'capability_type' => 'post',
			'icon_url' => '',
			'supports' => array('title', 'editor', 'thumbnail', 'author', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions'),
			'permastructure' => array('identifier' => $this->content_type, 'structure' => '%identifier%'.get_option('permalink_structure'))
		);

		$this->settings = array();
		foreach($default_settings as $name => $default)
		{
			if ( isset($settings[$name]) && $settings[$name] !== null )
				$this->settings[$name] = stripslashes_deep($settings[$name]);
			else
				$this->settings[$name] = $default;
		}
		$this->cleanup_permastructure();
	}

	public function __wakeup()
	{
		$this->cleanup_permastructure();
	}

	public function cleanup_permastructure()
	{
		$permastructure = $this->get_setting('permastructure', parent::get_type_permastructure());
		if ( !is_array($permastructure) )
			$permastructure = array();

		if ( !isset($permastructure['identifier']) )
		{
			$permastructure['identifier'] = $this->get_content_type();
		}
		else
		{
			$permastructure['identifier'] = sanitize_title_with_dashes(trim($permastructure['identifier'], '/'));
		}
		$permastructure['structure'] = str_replace(array('%category%/', '%tag%/'), '', $permastructure['structure']);
		if ( !isset($permastructure['structure']) )
		{
			$permastructure['structure'] = '%identifier%/'.get_option('permalink_structure');
		}
		else
		{
			preg_replace('#/+#', '/', '/' . $permastructure['structure']);
		}

		$this->settings['permastructure'] = $permastructure;
	}

	/**
	 * Returns the content_type for the custom content
	 *
	 * @return string
	 */
	public function get_content_type()
	{
		return $this->content_type;
	}

	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Returns the setting if set/else the default
	 *
	 * @param string $setting_name
	 * @param mixed $default
	 * @return mixed
	 */
	private function get_setting($setting_name, $default = false)
	{
		if(isset($this->settings[$setting_name]))
		{
			return $this->settings[$setting_name];
		}
		return $default;
	}

	/**
	 * Returns the name of the custom content
	 *
	 * @return string
	 */
	public function get_type_label()
	{
		return $this->get_setting('label', $this->get_content_type());
	}

	/**
	 * Returns the plural form of the custom content name
	 *
	 * @return string
	 */
	public function get_type_label_plural()
	{
		return $this->get_setting('label_plural', $this->get_content_type());
	}

	public function get_type_exclude_from_search()
	{
		return (bool) $this->get_setting('exclude_from_search', false);
	}

	/**
	 * returns whether the post_type should be included in search results
	 *
	 * @return bool
	 */
	public function get_type_publicly_queryable()
	{
		return $this->get_setting('publicly_queryable', false);
	}

	public function get_type_is_public()
	{
		return $this->get_setting('public', true);
	}

	public function get_type_is_hierarchical()
	{
		return $this->get_setting('hierarchical', false);
	}

	public function get_type_capability_type()
	{
		return $this->get_setting('capability_type', 'post');
	}

	public function get_type_icon_url()
	{
		return $this->get_setting('icon_url', '');
	}

	public function get_type_permastructure()
	{
		return $this->get_setting('permastructure', parent::get_type_permastructure());
	}

	/**
	 * Updates a setting with the given value.  Will only update settings that currently exist.
	 *
	 * @param string $setting_name
	 * @param mixed $value
	 */
	public function update_setting($setting_name, $value)
	{
		if(isset($this->settings[$setting_name]))
		{
			$this->settings[$setting_name] = $value;
			return true;
		}
		return false;
	}

	/**
	 * Updates settings based on teh given associative array
	 *
	 * @param array $new_settings
	 */
	public function update_settings($new_settings)
	{
		if(!is_array($new_settings))
		{
			return false;
		}
		foreach($new_settings as $setting_name => $value)
		{
			$this->update_setting($setting_name, $value);
		}
		return true;
	}

	public function get_type_supports()
	{
		return (array) $this->settings['supports'];
	}

}

class Dynamic_Content_Builder
{
	const DYNAMIC_CONTENT_TYPES_KEY = 'cms_press_dynamic_content_types';

	/**
	 * Singleton instance of content builder
	 *
	 * @var Dynamic_Content_Builder
	 */
	private static $instance;

	/**
	 * Array of saved content types and settings
	 *
	 * @var array of Dynamic Content Handlers
	 */
	private $content_handlers;

	/**
	 * Array of Metabox_Handler_Base classes
	 *
	 * @var array
	 */
	private $meta_handlers;

	public static function Initialize()
	{
		self::GetInstance();
	}

	/**
	 * Returns the singleton instance of the Dynamic_Content_Builder
	 *
	 * @return Dynamic_Content_Builder
	 */
	public static function GetInstance()
	{
		if(!isset(self::$instance))
		{
			self::$instance = new Dynamic_Content_Builder();
		}
		return self::$instance;
	}

	/**
	 * Constructor method set to private so that only one instance
	 * can be created from the Dynamic_Content_Builder::GetInstance() method
	 *
	 */
	private function __construct()
	{
		$this->get_content_handlers();
		$this->meta_handlers = array();

		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('setup_custom_content', array($this, 'on_setup_custom_content'));
		add_filter('manage_dynamic_content_columns', array($this, 'manage_dynamic_content_columns'));
	}

	/**
	 * Returns content handler for the given content type
	 *
	 * @param string $content_type
	 * @return Dynamic_Content_Handler
	 */
	public function get_content_handler($content_type)
	{
		if(isset($this->content_handlers[$content_type]))
		{
			return $this->content_handlers[$content_type];
		}
		return false;
	}

	public function get_standard_features()
	{
		return array(
			'title' => array('label' => __('Title'), 'description' => __('Adds title field.')),
			'author' => array('label' => __('Author'), 'description' => __('Adds author field.')),
			'editor' => array('label' => __('Editor'), 'description' => __('Adds content editor.')),
			'thumbnail' => array('label' => __('Thumbnails'), 'description' => __('Adds ability to select a default image for the content.')),
			'excerpt' => array('label' => __('Excerpts'), 'description' => __('Adds excerpts field to the edit screen.')),
			'trackbacks' => array('label' => __('Send Trackbacks'), 'description' => __('Adds the ability to manage trackbacks the content type.')),
			'custom-fields' => array('label' => __('Custom Fields'), 'description' => __('Adds the ability to add custom fields to the content type.')),
			'comments' => array('label' => __('Comments'), 'description' => __('Adds comment management for the content type.')),
			'revisions' => array('label' => __('Revisions'), 'description' => __('Adds revision management to the content type.'))
		);
	}

	/**
	 * Returns the url to the manage content types page
	 *
	 * @param array $query_args
	 * @return string
	 */
	public function get_manage_content_types_url($query_args = array())
	{
		if(!is_array($query_args))
		{
			$query_args = array();
		}
		$query_args['page'] = 'cms-press/manage-content-types';
		return admin_url('admin.php?'.http_build_query( $query_args ));
	}

	public function get_add_content_type_url($query_args = array())
	{
		if(!is_array($query_args))
		{
			$query_args = array();
		}
		$query_args['page'] = 'cms-press/add-content-type';
		return admin_url('admin.php?'.http_build_query( $query_args ));
	}

	public function get_edit_content_type_url($content_type, $query_args = array())
	{
		if(!is_array($query_args))
		{
			$query_args = array();
		}
		$query_args['content_type'] = $content_type;
		return($this->get_manage_content_types_url($query_args));
	}

	public function update_content_handler($updated_content_handler, $save = true)
	{
		if(!is_a($updated_content_handler, 'Dynamic_Content_Handler'))
		{
			return new WP_Error('invalid_content_type_class', 'The new content handler must extend Dynamic_Content_Handler in order to be a Dynamic Content Type.');
		}
		if(!isset($this->content_handlers[$updated_content_handler->get_content_type()]))
		{
			return $this->add_content_handler($updated_content_handler, $save);
		}
		$this->content_handlers[$updated_content_handler->get_content_type()] = $updated_content_handler;
		if($save)	$this->save_content_types();
		return $updated_content_handler->get_content_type();
	}

	public function save_content_types()
	{
		$content_handlers = array();
		foreach($this->content_handlers as $post_type => $content_handler) {
			$content_handlers[$post_type] = $content_handler->get_settings();
		}
		update_option(self::DYNAMIC_CONTENT_TYPES_KEY, $content_handlers);
		//deleting the option so that rewrite rules get flushed on next request
		//flushing them here would include the old rules
		delete_option('installed_post_types');
	}

	/**
	 * Adds the content handler to the dynamic content type system.
	 * @todo look ito potential issues with multiple users updating this at once
	 *
	 * @param Dynamic_Content_Handler $new_content_handler
	 * @param bool $save whether to save the option after adding
	 * @return bool|WP_Error on erorr
	 */
	public function add_content_handler($new_content_handler, $save = true)
	{
		if(!is_a($new_content_handler, 'Dynamic_Content_Handler'))
		{
			return new WP_Error('invalid_content_type_class', 'The new content handler must extend Dynamic_Content_Handler in order to be a Dynamic Content Type.');
		}
		if(strlen($new_content_handler->get_content_type()) < 1)
		{
			return new WP_Error('empty_content_type', "The content type cannot be empty.");
		}
		if(isset($this->content_handlers[$new_content_handler->get_content_type()]))
		{
			return new WP_Error('duplicate_content_type', "A content_type of '{$new_content_handler->get_content_type()}' already exists");
		}
		$this->content_handlers[$new_content_handler->get_content_type()] = $new_content_handler;
		if($save)	$this->save_content_types();
		return $new_content_handler->get_content_type();
	}

	public function remove_content_handler($content_type, $save = true)
	{
		if(isset($this->content_handlers[$content_type]))
		{
			unset($this->content_handlers[$content_type]);
			if($save)	$this->save_content_types();
			return true;
		}
		return false;
	}

	/**
	 * Initializes all dynamic content handlers and runs
	 * each handlers on_setup_custom_content() method
	 *
	 */
	public function on_setup_custom_content()
	{
		$content_types = $this->get_content_handlers();
		foreach ($content_types as $dc_handler)
		{
			if(is_a($dc_handler, 'Dynamic_Content_Handler'))
			{
				$dc_handler->on_setup_custom_content();
			}
		}

		do_action('register_metabox_handlers');
	}

	/**
	 * Returns saved content types value
	 *
	 * @return array
	 *
	 */
	public function get_content_handlers()
	{
		if(!isset($this->content_handlers))
		{
			$content_handlers = get_option(self::DYNAMIC_CONTENT_TYPES_KEY );
			if(!$content_handlers) $content_handlers = array();
			$this->content_handlers = array();

			foreach($content_handlers as $post_type => $handler) {
				$handler = (array) $handler;
				if(isset($handler['' . "\0" . 'Dynamic_Content_Handler' . "\0" . 'settings'])) {
					//import old saved format
					$this->content_handlers[$post_type] = new Dynamic_Content_Handler($post_type, $handler['' . "\0" . 'Dynamic_Content_Handler' . "\0" . 'settings']);
				} else {
					$this->content_handlers[$post_type] = new Dynamic_Content_Handler($post_type, $handler);
				}
			}
		}
		return $this->content_handlers;
	}

	/**
	 * Adds menu items to admin
	 *
	 */
	public function add_admin_menu()
	{
		add_menu_page(__('CMS Press'), __('CMS Press'), 'manage_content_types', 'cms-press/manage-content-types', array($this, 'manage_content_types_page'));
		$hook = add_submenu_page('cms-press/manage-content-types', __('Edit Content Types'), 'Edit Content Types', 'manage_content_types', 'cms-press/manage-content-types', array($this, 'manage_content_types_page'));
		add_action('load-'.$hook, array($this, 'on_load_manage_content_types_page'));

		$hook = add_submenu_page('cms-press/manage-content-types', 'Add Content Type', 'Add Content Type', 'manage_content_types', 'cms-press/add-content-type', array($this, 'add_content_type_page'));
		add_action('load-'.$hook, array($this, 'on_load_add_content_type_page'));
	}

	/**
	 * Returns the column headers for the dynamic content types
	 *
	 * @param array $column_headers
	 * @return array
	 */
	public function manage_dynamic_content_columns($column_headers)
	{
		$column_headers = array(
			'cb' => '<input type="checkbox" />',
			'content_type' => __('Content Type'),
			'label' => __('Label'),
		);
		return $column_headers;
	}

	/**
	 * Handles adding of content_types before any content is rendered.
	 *
	 */
	public function on_load_add_content_type_page()
	{
		if(!current_user_can('manage_content_types'))
		{
			wp_die("You do not have sufficient permissions to access this page");
		}
		$action = !empty($_POST['action']) ? $_POST['action'] : '';
		$nonce = !empty($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';
		switch($action)
		{
			case 'add_content_type':
				if(wp_verify_nonce($nonce, 'add_content_type'))
				{
					if(!empty($_POST['content_type']))
					{
						$content_type = $_POST['content_type'];
						$content_handler = new Dynamic_Content_Handler($content_type, $_POST);
						$content_type = $this->add_content_handler($content_handler);
						if(!is_wp_error($content_type))
						{
							$add_url = admin_url('post-new.php?post_type='.$content_type);
							if(version_compare(get_wp_version(), '3.0-dev', '<'))
							{
								$add_url = CP_Custom_Content_Core::GetInstance()->get_add_custom_content_url($content_type);
							}
							$notice = sprintf(__("The content type of '{$content_type}' has been created.  <a href=\"%s\">Add one now.</a>"), esc_attr($add_url));
							wp_redirect($this->get_edit_content_type_url($content_type, array('notice'=>$notice)));
							exit();
						}
						else
						{
							$_REQUEST['notice'] = $content_type->get_error_message();
						}
					}
				}
				break;
		}
	}

	/**
	 * Renders page for adding new content types
	 * @todo integrate error messages
	 *
	 */
	public function add_content_type_page()
	{
		if(!current_user_can('manage_content_types'))
		{
			wp_die("You do not have sufficient permissions to access this page");
		}
		?>
		<div class="wrap">
			<?php screen_icon('content_type'); ?>
			<h2>
				<?php _e("Add New Content Type"); ?>
			</h2>
			<?php
			$content_type = '';
			if(!empty($_POST['content_type']))
			{
				$content_type = $_POST['content_type'];
			}
			$content_handler = new Dynamic_Content_Handler($content_type, $_POST);
			$this->edit_content_type_form($content_handler);
			?>
		</div>
		<?php
	}

	/**
	 * Handles updates to content types before page is rendered
	 *
	 */
	public function on_load_manage_content_types_page()
	{
		if(!current_user_can('manage_content_types'))
		{
			wp_die("You do not have sufficient permissions to access this page");
		}
		$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : '';
		if(isset($_REQUEST['doaction2'])) $action = $_REQUEST['action2'];
		$nonce = !empty($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : '';
		switch($action)
		{
			case 'edit_content_type':
				if(empty($_REQUEST['orig_content_type']) || !($this->get_content_handler($_REQUEST['orig_content_type'])))
				{
					wp_redirect($this->get_manage_content_types_url());
					exit;
				}
				if(wp_verify_nonce($nonce, 'edit_content_type'))
				{
					//overwrite the old content handler with the new one
					$content_handler = new Dynamic_Content_Handler($_REQUEST['orig_content_type'], $_REQUEST);
					$content_type = $this->update_content_handler($content_handler);
					if(!is_wp_error($content_type))
					{
						wp_redirect($this->get_edit_content_type_url($content_type, array('notice'=>__('Your changes have been saved'))));
						exit();
					}
				}
				break;
			case 'bulk-delete':
				if(wp_verify_nonce($nonce, 'bulk-action') && isset($_REQUEST['content_handlers']) && is_array($_REQUEST['content_handlers']))
				{
					foreach($_REQUEST['content_handlers'] as $content_type)
					{
						$this->remove_content_handler($content_type, false);
					}
					$this->save_content_types();
					wp_redirect($this->get_manage_content_types_url(array('notice'=> 'The content types have been deleted')));
					exit;
				}
				break;
			case 'delete':
				if(wp_verify_nonce($nonce, 'delete_content_type') && !empty($_REQUEST['content_type']))
				{
					$content_type = $_REQUEST['content_type'];
					if($this->get_content_handler($content_type))
					{
						$this->remove_content_handler($content_type);
						$notice = sprintf("The content type '{$content_type}' has been deleted");
					}
					else
					{
						$notice = "Invalid content type";
					}
					wp_redirect($this->get_manage_content_types_url(array('notice'=> $notice)));
					exit;
				}
				break;
		}
	}

	/**
	 * Renders the form for editing a content type
	 *
	 * @param Dynamic_Content_Handler $content_handler
	 */
	private function edit_content_type_form($content_handler, $add = true)
	{
		if(!empty($_REQUEST['notice']))
		{
			?><div id="message" class="updated fade"><p><strong><?php echo stripslashes($_REQUEST['notice'])?></strong></div><?php
		}
		$permastructure = $content_handler->get_type_permastructure();
		if( empty($permastructure['identifier']) )
		{
			$perma_identifier = $content_handler->get_content_type();
			if(!$add) $permalink_warnings[] = __("Warning: Content Type Identifier should not be empty.");
		}
		else
		{
			$perma_identifier = $permastructure['identifier'];
		}
		if( empty($permastructure['structure']) )
		{
			$perma_structure = '%identifier%'.get_option('permalink_structure');
		}
		else
		{
			$perma_structure = $permastructure['structure'];
			if(!$add && false === strpos($perma_structure, '%identifier%'))
			{
				$permalink_warnings[] = __("Warning: The permalink structure must contain the %identifier% term.");
			}
			if(false !== strpos($perma_structure, array('%tag%', '%category%')))
			{
				$permalink_warnings[] = __("Warning: The user of the %tag% or %category% tags is not currently supported by cms-press.");
			}
		}
		?>
		<?php if(isset($permalink_warnings) && count($permalink_warnings)) : ?>
			<?php foreach($permalink_warnings as $permalink_warning) :?>
				<?php if(!empty($_REQUEST['notice'])): ?>
				<div class="updated"><p><strong><?php echo stripslashes($permalink_warning)?></strong></div>
			<?php endif; ?>
			<?php endforeach; ?>
		<?php endif;?>
		<form method="post" action="<?php $this->get_edit_content_type_url($content_handler->get_content_type())?>">
			<?php if($add) : ?>
				<input type="hidden" name="action" value="add_content_type" />
				<?php wp_nonce_field('add_content_type', '_wpnonce')?>
			<?php else: ?>
				<input type="hidden" name="action" value="edit_content_type" />
				<?php wp_nonce_field('edit_content_type', '_wpnonce')?>
				<input type="hidden" name="orig_content_type" value="<?php echo esc_attr($content_handler->get_content_type())?>" />
			<?php endif; ?>
			<h3><?php _e('General Settings')?></h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="content_type"><?php _e('Content Type (required)'); ?></label></th>
					<td>
						<input type="text" class="regular-text code" id="content_type" name="content_type" value="<?php echo esc_attr($content_handler->get_content_type()); ?>"<?php echo $add ? '' : ' readonly="readonly"'?>/>
						<span class="description"><?php _e('This will be used to identify this content type in the database.  This must be unique.')?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="content_type"><?php _e('Label'); ?></label></th>
					<td>
						<input type="text" class="regular-text code" id="label" name="label" value="<?php echo esc_attr($content_handler->get_type_label()); ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="content_type"><?php _e('Label Plural'); ?></label></th>
					<td>
						<input type="text" class="regular-text code" id="label_plural" name="label_plural" value="<?php echo esc_attr($content_handler->get_type_label_plural()); ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Display in admin menu?'); ?></th>
					<td>
						<label for="public_yes"><?php echo ('Yes') ?></label>
						<input type="radio" id="public" name="public" value="1"<?php echo $content_handler->get_type_is_public() ? ' checked="checked"' : ''?> />
						&nbsp; &nbsp;
						<label for="public_no"><?php echo ('No') ?></label>
						<input type="radio" id="public" name="public" value="0"<?php echo !$content_handler->get_type_is_public() ? ' checked="checked"' : ''?> />
						&nbsp; &nbsp;
						<span class="description"><?php _e('This should almost always be Yes.')?></span>
					</td>
				</tr>
				<?php /* @todo leaving these out for now
				<tr valign="top">
					<th scope="row"><?php _e('Is Hierarchical?'); ?></th>
					<td>
						<label for="hierarchical_yes"><?php echo ('Yes') ?></label>
						<input type="radio" id="hierarchical" name="hierarchical" value="1"<?php echo $content_handler->get_type_is_hierarchical() ? ' checked="checked"' : ''?> />
						&nbsp; &nbsp;
						<label for="hierarchical_no"><?php echo ('No') ?></label>
						<input type="radio" id="hierarchical" name="hierarchical" value="0"<?php echo !$content_handler->get_type_is_hierarchical() ? ' checked="checked"' : ''?> />
						&nbsp; &nbsp;
						<span class="description"><?php _e('Will the URL to the content be based on parent child relationships?.')?></span>
					</td>
				</tr>
				*/
				?>
				<?php if( version_compare(get_wp_version(), '3.0-dev', '>=')) : ?>
					<tr valign="top">
						<th scope="row"><label for="capability_type"><?php _e('Capability Type'); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="capability_type" name="capability_type" value="<?php echo esc_attr($content_handler->get_type_capability_type()); ?>" />
						</td>
					</tr>
				<?php endif; ?>
				<tr valign="top">
					<th scope="row"><?php _e('Is Publicly Queryable?'); ?></th>
					<td>
						<label for="publicly_queryable_yes"><?php echo ('Yes') ?></label>
						<input type="radio" id="publicly_queryable" name="publicly_queryable" value="1"<?php echo $content_handler->get_type_publicly_queryable() ? ' checked="checked"' : ''?> />
						&nbsp; &nbsp;
						<label for="publicly_queryable_no"><?php echo ('No') ?></label>
						<input type="radio" id="publicly_queryable" name="publicly_queryable" value="0"<?php echo !$content_handler->get_type_publicly_queryable() ? ' checked="checked"' : ''?> />
						&nbsp; &nbsp;
						<span class="description"><?php _e('Can this post_type be accessed via URL?')?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Exclude from search results?'); ?></th>
					<td>
						<label for="exclude_from_search_yes"><?php echo ('Yes') ?></label>
						<input type="radio" id="exclude_from_search" name="exclude_from_search" value="1"<?php echo $content_handler->get_type_exclude_from_search() ? ' checked="checked"' : ''?> />
						&nbsp; &nbsp;
						<label for="exclude_from_search_no"><?php echo ('No') ?></label>
						<input type="radio" id="exclude_from_search" name="exclude_from_search" value="0"<?php echo !$content_handler->get_type_exclude_from_search() ? ' checked="checked"' : ''?> />
						&nbsp; &nbsp;
						<span class="description"><?php _e('Should this content be excluded in search results?')?></span>
					</td>
				</tr>
			</table>
			<br />
			<h3><?php _e('Permalink Structure')?></h3>
			<p><span class="description">This only applies to Content Types that are Publicly Queryable.</span></p>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="permastructure_identifier"><?php _e('Content Type Identifier');?></label></th>
					<td>
						<input type="text" class="regular-text code" id="permastructure_identifier" name="permastructure[identifier]" value="<?php echo esc_attr($perma_identifier); ?>"/>
						<span class="description"><?php _e('This will be used in the permalink structure to identify this content type.  This should be unique per content type.')?></span>
					</td>
				<tr valign="top">
					<th scope="row"><label for="permastructure_structure"><?php _e('Permalink Structure');?></label></th>
					<td>
						<input type="text" class="regular-text code" id="permastructure_structure" name="permastructure[structure]" value="<?php echo esc_attr($perma_structure); ?>"/>
						<span class="description"><?php _e('This will be custom URL structure for this content type. These follow WP\'s normal <a href="http://codex.wordpress.org/Using_Permalinks">permalink tags</a>, but must also include the content type \'%identifier%\'.')?></span>
						<br />
						<span class="description">
						Allowed tags: %year%, %monthnum%, %day%, %hour%, %minute%, %second%, %postname%, %post_id%,
						</span>
					</td>
				</tr>
				</tr>
			</table>
			<br />
			<h3><?php _e('Supported Features')?></h3>
			<table class="form-table">
				<?php	$standard_features = $this->get_standard_features()?>
				<?php $supported_features = $content_handler->get_type_supports(); ?>
				<?php foreach($standard_features as $feature_key => $feature): ?>
					<tr valign="top">
						<th scope="row"><label for="supports_<?php echo $feature_key?>"><?php echo $feature['label'];?></label></th>
						<td>
						<input type="checkbox" id="supports_<?php echo $feature_key?>" name="supports[]" value="<?php echo $feature_key?>"<?php echo in_array($feature_key, $supported_features) ? ' checked="checked"' : ''?> />
						<?php if(!empty($feature['description'])): ?>
							<span class="description"><?php echo $feature['description']?></span>
						<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<p class="submit"><input type="submit" name="submit" value="<?php esc_attr_e('Submit'); ?>" class="button-secondary" /></p>
		</form>
		<?php
	}

	/**
	 * Prints manage row for dynamic content handler
	 *
	 * @param Dynamic_Content_Handler $content_type_obj
	 * @param string $style
	 * @return unknown
	 */
	public function dynamic_content_row($content_type_obj, $style)
	{
		$checkbox = "<input type='checkbox' name='content_handlers[]' id='content_handler_{$content_type_obj->get_content_type()}' value='{$content_type_obj->get_content_type()}' />";

		$r = "<tr id='content_type-{$content_type_obj->get_content_type()}'$style>";
		$columns = get_column_headers('dynamic_content');
		$hidden = get_hidden_columns('dynamic_content');
		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class=\"$column_name column-$column_name\"";
			$style = '';
			if ( in_array($column_name, $hidden) )
				$style = ' style="display:none;"';
			$attributes = "$class$style";

			switch ($column_name) {
				case 'cb':
					$r .= "<th scope='row' class='check-column'>$checkbox</th>";
					break;
				case 'content_type':
					$r .= sprintf('<td %s>%s<br /><div class="row-actions"><span class="edit"><a href="%s">Edit</a> | </span><span class="delete"><a href="%s" class="submitdelete">Delete</a></span></div></td>',
						$attributes,
						$content_type_obj->get_content_type(),
						$this->get_edit_content_type_url($content_type_obj->get_content_type()),
						wp_nonce_url($this->get_manage_content_types_url(array('action'=>'delete', 'content_type'=>$content_type_obj->get_content_type())), 'delete_content_type'));
					break;
				case 'label':
					$r .= "<td $attributes>{$content_type_obj->get_type_label()}</td>";
					break;
				default:
					$r .= "<td $attributes>";
					$r .= apply_filters('manage_users_custom_column', '', $column_name, $content_type_obj->get_content_type());
					$r .= "</td>";
			}
		}
		$r .= '</tr>';
		return $r;
	}

	public function manage_content_types_page()
	{
		if(isset($_REQUEST['content_type']))
		{
			$this->do_edit_content_type_page();
		}
		else
		{
			$this->do_manage_content_types_page();
		}
	}

	private function do_edit_content_type_page()
	{
		$content_handler = false;
		if(!empty($_REQUEST['content_type']))
		{
			$content_type = $_REQUEST['content_type'];
			$content_handler = $this->get_content_handler($content_type);
		}
		else
		{
			throw new Exception("An error has occurred.");
		}
		?>
		<div class="wrap">
			<?php screen_icon('content_type'); ?>
			<h2>
				<?php printf(__("Edit Content Type '%s'"), $content_handler->get_type_label()); ?>
			</h2>
			<?php	$this->edit_content_type_form($content_handler, false); ?>
		</div>
		<?php
	}

	private function do_manage_content_types_page()
	{
		$content_types = $this->get_content_handlers();
		?>
		<div class="wrap">
			<?php screen_icon('content_type'); ?>
			<h2>
				<?php _e("Edit Content Types"); ?>
				<a href="<?php echo $this->get_add_content_type_url();?>" class="button add-new-h2"><?php _e('Add New'); ?></a>
			</h2>
			<?php if(!empty($_REQUEST['notice'])): ?>
				<div id="message" class="updated fade"><p><strong><?php echo stripslashes($_REQUEST['notice'])?></strong></div>
			<?php endif; ?>
			<?php if(count($content_types)) :?>
				<form id="posts-filter" action="<?php $this->get_manage_content_types_url()?>" method="post">
					<div class="tablenav">
						<div class="alignleft actions">
							<select name="action">
								<option value="" selected="selected"><?php _e('Bulk Actions'); ?></option>
								<option value="bulk-delete"><?php _e('Delete'); ?></option>
							</select>
							<input type="submit" value="<?php esc_attr_e('Apply'); ?>" name="doaction" id="doaction" class="button-secondary action" />
							<?php wp_nonce_field('bulk-action'); ?>
						</div>
						<br class="clear" />
					</div>
					<table class="widefat fixed" cellspacing="0">
						<thead>
							<tr class="thead">
								<?php print_column_headers('dynamic_content') ?>
							</tr>
						</thead>
						<tfoot>
							<tr class="thead">
								<?php print_column_headers('dynamic_content', false) ?>
							</tr>
						</tfoot>
						<tbody id="content-types" class="list:content-types content-type-list">
							<?php	$style = '';?>
							<?php	foreach ( $content_types as $content_type ) : ?>
								<?php $style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';?>
								<?php echo "\n\t" . $this->dynamic_content_row($content_type, $style); ?>
							<?php endforeach; ?>
						</tbody>
					</table>
					<div class="tablenav">
						<div class="alignleft actions">
							<select name="action2">
								<option value="" selected="selected"><?php _e('Bulk Actions'); ?></option>
								<option value="delete"><?php _e('Delete'); ?></option>
							</select>
							<input type="submit" value="<?php esc_attr_e('Apply'); ?>" name="doaction2" id="doaction2" class="button-secondary action" />
						</div>
						<br class="clear" />
					</div>
				</form>
			<?php else: ?>
				<p><?php printf(__('There are currently no custom content types created.  <a href="%s">Create One</a></p>'), $this->get_add_content_type_url())?></p>
			<?php endif; ?>
		</div>
		<br class="clear" />
		<?php
	}

}