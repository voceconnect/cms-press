<?php
/**
 * Wrapper class for the dynamic taxonomy data instances
 *
 */
class Dynamic_Taxonomy_Handler extends CP_Custom_Taxonomy_Base 
{
	private $taxonomy_name;
	private $object_types;
	private $settings;
		
	public function __construct($taxonomy_name = '', $object_types = array('post'), $settings = array())
	{
		$this->taxonomy_name = sanitize_user(strtolower($taxonomy_name));
		
		$this->object_types = (array) $object_types;
		
	 	$default_settings = array(
	 		'label' => $taxonomy_name,
	 		'label_plural' => $taxonomy_name,
	 		'hierarchical' => false, 
	 		'update_count_callback' => false, 
	 		'rewrite' => true, 
	 		'query_var' => true
		);
		$this->settings = array();
		foreach($default_settings as $name => $default) 
		{
			if ( !empty($settings[$name]) )
				$this->settings[$name] = $settings[$name];
			else
				$this->settings[$name] = $default;
		}
		parent::__construct();
	}
	
	/**
	 * Place holder function to be used in future releases that may need to translate deprecated features.
	 *
	 */
	public function __wakeup()
	{
		parent::__construct();
	}
	
	public function get_object_types()
	{
		return $this->object_types;
	}
	
	public function get_settings()
	{
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
	 * Returns the name of the taxonomy
	 *
	 * @return string
	 */
	public function get_taxonomy_name()
	{
		return $this->taxonomy_name;
	}
	
	/**
	 * Returns the label of the taxonomy
	 *
	 * @return string
	 */
	public function get_taxonomy_label()
	{
		return $this->get_setting('label', $this->get_taxonomy_name());
	}
	
	/**
	 * Returns the plural form of the taxonomy label
	 *
	 * @return string
	 */
	public function get_taxonomy_label_plural()
	{
		return $this->get_setting('label_plural', $this->get_taxonomy_name());
	}

	public function get_taxonomy_is_hierarchical()
	{
		return $this->get_setting('hierarchical', false);
	}

	public function get_taxonomy_rewrite()
	{
		return $this->get_setting('rewrite', true);
	}

	public function get_taxonomy_query_var()
	{
		return $this->get_setting('query_var', true);
	}
	
	public function supports_post_type($post_type)
	{
		return in_array($post_type, $this->object_types);
	}
}

class Dynamic_Taxonomy_Builder
{
	const DYNAMIC_TAXONOMIES_KEY = 'cms_press_dynamic_taxonomies';
	
	/**
	 * Singleton instance of content builder
	 *
	 * @var Dynamic_Taxonomy_Builder
	 */
	private static $instance;
	
	/**
	 * Array of saved taxonomies and settings
	 *
	 * @var array of Dynamic Content Handlers
	 */
	private $taxonomies;
	
	public static function Initialize()
	{
		$instance = self::GetInstance();
		add_action('admin_menu', array($instance, 'add_admin_menu'));
		add_filter('manage_dynamic_taxonomy_columns', array($instance, 'manage_dynamic_taxonomy_columns'));
	}
	
	/**
	 * Returns the singleton instance of the Dynamic_Taxonomy_Builder
	 *
	 * @return Dynamic_Taxonomy_Builder
	 */
	public static function GetInstance()
	{
		if(!isset(self::$instance))
		{
			self::$instance = new Dynamic_Taxonomy_Builder();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor method set to private so that only one instance
	 * can be created from the Dynamic_Taxonomy_Builder::GetInstance() method
	 *
	 */
	private function __construct() 
	{
		$this->taxonomies = get_option(self::DYNAMIC_TAXONOMIES_KEY );
		if(!$this->taxonomies) $this->taxonomies = array();
	}
	
	/**
	 * Returns taxonomy for the given taxonomy
	 *
	 * @param string $taxonomy_name
	 * @return Dynamic_Taxonomy_Handler|Bool False if it doesn't exist
	 */
	public function get_taxonomy($taxonomy_name)
	{
		if(isset($this->taxonomies[$taxonomy_name]))
		{
			return $this->taxonomies[$taxonomy_name];
		}
		return false;
	}
	
	/**
	 * Returns the url to the manage taxonomies page
	 *
	 * @param array $query_args
	 * @return string
	 */
	public function get_manage_taxonomies_url($query_args = array())
	{
		if(!is_array($query_args))
		{
			$query_args = array();
		}
		$query_args['page'] = 'cms-press/manage-taxonomies';
		return admin_url('admin.php?'.http_build_query( $query_args ));
	}
	
	/**
	 * Returns the url to the add taxonomy page
	 *
	 * @param array $query_args
	 * @return string
	 */
	public function get_add_taxonomy_url($query_args = array())
	{
		if(!is_array($query_args))
		{
			$query_args = array();
		}
		$query_args['page'] = 'cms-press/add-taxonomy';
		return admin_url('admin.php?'.http_build_query( $query_args ));
	}
	
	/**
	 * Returns the edit taxonomy page url
	 *
	 * @param string $taxonomy_name
	 * @param array $query_args
	 * @return string
	 */
	public function get_edit_taxonomy_url($taxonomy_name, $query_args = array())
	{
		if(!is_array($query_args))
		{
			$query_args = array();
		}
		$query_args['taxonomy'] = $taxonomy_name;
		return($this->get_manage_taxonomies_url($query_args));
	}
	
	/**
	 * Updates the passed in taxonomy, adds it if a taxonomy with that name does not exist
	 *
	 * @param Dynamic_Taxonomy_Handler $updated_taxonomy
	 * @param bool $save
	 * @return string|WP_Error taxonomy name|Error on error
	 */
	public function update_taxonomy($updated_taxonomy, $save = true)
	{
		if(!is_a($updated_taxonomy, 'Dynamic_Taxonomy_Handler'))
		{
			return new WP_Error('invalid_taxonomy_class', 'The new taxonomy must extend Dynamic_Taxonomy_Handler in order to be a Dynamic Taxonomy.');
		}
		if(!isset($this->taxonomies[$updated_taxonomy->get_taxonomy_name()]))
		{
			return $this->add_taxonomy($updated_taxonomy, $save);
		}
		$this->taxonomies[$updated_taxonomy->get_taxonomy_name()] = $updated_taxonomy;
		if($save)	$this->save_taxonomies();
		return $updated_taxonomy->get_taxonomy_name();
	}
	
	/**
	 * Saves the dynamic taxonomies to the database
	 *
	 */
	public function save_taxonomies()
	{
		global $wp_rewrite;
		update_option(self::DYNAMIC_TAXONOMIES_KEY, $this->taxonomies);
		$wp_rewrite->flush_rules();
	}
	
	/**
	 * Adds the taxonomy to the dynamic taxonomy system.
	 *
	 * @param Dynamic_Taxonomy_Handler $new_taxonomy
	 * @param bool $save whether to save the option after adding
	 * @return bool|WP_Error on erorr
	 */
	public function add_taxonomy($new_taxonomy, $save = true)
	{
		if(!is_a($new_taxonomy, 'Dynamic_Taxonomy_Handler'))
		{
			return new WP_Error('invalid_taxonomy_class', 'The new taxonomy must extend Dynamic_Taxonomy_Handler in order to be a Dynamic Taxonomy.');
		}
		if(strlen($new_taxonomy->get_taxonomy_name()) < 1)
		{
			return new WP_Error('empty_taxonomy', "The taxonomy cannot be empty.");
		}
		if(isset($this->taxonomies[$new_taxonomy->get_taxonomy_name()]))
		{
			return new WP_Error('duplicate_taxonomy', "A taxonomy of '{$new_taxonomy->get_taxonomy_name()}' already exists");
		}
		$this->taxonomies[$new_taxonomy->get_taxonomy_name()] = $new_taxonomy;
		if($save)	$this->save_taxonomies();
		return $new_taxonomy->get_taxonomy_name();
	}
	
	/**
	 * Deletes the taxonomy from the system
	 *
	 * @param string $taxonomy_name
	 * @param bool $save
	 * @return bool
	 */
	public function remove_taxonomy($taxonomy_name, $save = true)
	{
		if(isset($this->taxonomies[$taxonomy_name]))
		{
			unset($this->taxonomies[$taxonomy_name]);
			if($save)	$this->save_taxonomies();
			return true;
		}
		return false;
	}
	
	/**
	 * Returns saved taxonomies value
	 * 
	 * @return array
	 *
	 */
	public function get_taxonomies()
	{
		return $this->taxonomies;
	}
	
	/**
	 * Adds items to menu
	 *
	 */
	public function add_admin_menu()
	{
		$hook = add_submenu_page('cms-press/manage-content-types', __('Edit Taxonomies'), __('Edit Taxonomies'), 'manage_taxonomies', 'cms-press/manage-taxonomies', array($this, 'manage_taxonomies_page'));
		add_action('load-'.$hook, array($this, 'on_load_manage_taxonomies_page'));
		
		$hook = add_submenu_page('cms-press/manage-content-types', 'Add Taxonomy', 'Add Taxonomy', 'manage_taxonomies', 'cms-press/add-taxonomy', array($this, 'add_taxonomy_page'));
		add_action('load-'.$hook, array($this, 'on_load_add_taxonomy_page'));
	}
	
	public function manage_dynamic_taxonomy_columns($column_headers)
	{
		$column_headers = array(
			'cb' => '<input type="checkbox" />',
			'taxonomy' => __('Taxonomy'),
			'label' => __('Label'),
			'object_types' => ('Object Types'),			
		);
		return $column_headers;
	}
	
	public function on_load_add_taxonomy_page()
	{
		if(!current_user_can('manage_taxonomies'))
		{
			wp_die("You do not have sufficient permissions to access this page");
		}
		$action = !empty($_POST['action']) ? $_POST['action'] : '';
		$nonce = !empty($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '';
		switch($action)
		{
			case 'add_taxonomy':
				if(wp_verify_nonce($nonce, 'add_taxonomy'))
				{
					if(!empty($_POST['taxonomy_name']))
					{
						$object_types = isset($_POST['object_types']) ? $_POST['object_types'] : array();
						$settings = isset($_POST['settings']) ? $_POST['settings'] : array();
						$taxonomy_name = $_POST['taxonomy_name'];
						$taxonomy = new Dynamic_Taxonomy_Handler($taxonomy_name, $object_types, $settings);
						$taxonomy_name = $this->add_taxonomy($taxonomy);
						if(!is_wp_error($taxonomy_name))
						{
							wp_redirect($this->get_edit_taxonomy_url($taxonomy_name, array('notice'=> "The taxonomomy '$taxonomy_name' has been created")));
							exit();
						}
						else 
						{
							$_REQUEST['notice'] = $taxonomy_name->get_error_message();
						}
					}
				}
				break;
		}
	}
	
	public function on_load_manage_taxonomies_page()
	{
		if(!current_user_can('manage_taxonomies'))
		{
			wp_die("You do not have sufficient permissions to access this page");
		}
		$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : '';
		if(isset($_REQUEST['doaction2'])) $action = $_REQUEST['action2'];
		$nonce = !empty($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : '';
		switch($action)
		{
			case 'edit_taxonomy':
				if(empty($_REQUEST['orig_taxonomy']) || !($this->get_taxonomy($_REQUEST['orig_taxonomy'])))
				{
					wp_redirect($this->get_manage_taxonomies_url());
					exit;
				}
				if(wp_verify_nonce($nonce, 'edit_taxonomy'))
				{
					$object_types = isset($_REQUEST['object_types']) ? $_REQUEST['object_types'] : array();
					$settings = isset($_REQUEST['settings']) ? $_REQUEST['settings'] : array();

					//overwrite the old taxonomy with the new one
					$taxonomy = new Dynamic_Taxonomy_Handler($_REQUEST['orig_taxonomy'], $object_types, $settings);
					$taxonomy_name = $this->update_taxonomy($taxonomy);
					if(!is_wp_error($taxonomy_name))
					{
						wp_redirect($this->get_edit_taxonomy_url($taxonomy_name, array('notice'=>__('Your changes have been saved'))));
						exit();
					}
				}
				break;
			case 'bulk-delete':
				if(wp_verify_nonce($nonce, 'bulk-action') && isset($_REQUEST['taxonomies']) && is_array($_REQUEST['taxonomies']))
				{
					foreach($_REQUEST['taxonomies'] as $taxonomy_name)
					{
						$this->remove_taxonomy($taxonomy_name, false);
					}
					$this->save_taxonomies();
					wp_redirect($this->get_manage_taxonomies_url(array('notice'=> 'The taxonomies have been deleted')));
					exit;
				}
				break;
			case 'delete':
				if(wp_verify_nonce($nonce, 'delete_taxonomy') && !empty($_REQUEST['taxonomy']))
				{
					$taxonomy_name = $_REQUEST['taxonomy'];
					if($this->get_taxonomy($taxonomy_name))
					{
						$this->remove_taxonomy($taxonomy_name);
						$notice = sprintf("The taxonomy '{$taxonomy_name}' has been deleted");
					}
					else 
					{
						$notice = "Invalid taxonomy";
					}
					wp_redirect($this->get_manage_taxonomies_url(array('notice'=> $notice)));
					exit;
				}
				break;
		}
	}
	
	/**
	 * Renders page for adding new taxonomies
	 * @todo integrate error messages
	 *
	 */
	public function add_taxonomy_page()
	{
		if(!current_user_can('manage_content_types'))
		{
			wp_die("You do not have sufficient permissions to access this page");
		}
		?>
		<div class="wrap">
			<?php screen_icon('taxonomy'); ?>
			<h2>
				<?php _e("Add New Taxonomy"); ?> 
			</h2>
			<?php
			$taxonomy_name = '';
			if(!empty($_POST['taxonomy']))
			{
				$taxonomy_name = $_POST['taxonomy'];
			}
			$taxonomy = new Dynamic_Taxonomy_Handler($taxonomy_name, $_POST);
			$this->edit_taxonomy_form($taxonomy);
			?>
		</div>
		<?php
	}
	
	/**
	 * Form for editing a taxonomy
	 *
	 * @param Dynamic_Taxonomy_Handler $taxonomy
	 */
	private function edit_taxonomy_form($taxonomy, $add = true)
	{
		?>
		<?php if(!empty($_REQUEST['notice'])): ?>
			<div id="message" class="updated fade"><p><strong><?php echo stripslashes($_REQUEST['notice'])?></strong></div>
		<?php endif; ?>		
		<form method="post" action="<?php $this->get_edit_taxonomy_url($taxonomy->get_taxonomy_name())?>">
			<?php if($add) : ?>
				<input type="hidden" name="action" value="add_taxonomy" />
				<?php wp_nonce_field('add_taxonomy', '_wpnonce')?>
			<?php else: ?>
				<input type="hidden" name="action" value="edit_taxonomy" />
				<?php wp_nonce_field('edit_taxonomy', '_wpnonce')?>
				<input type="hidden" name="orig_taxonomy" value="<?php echo esc_attr($taxonomy->get_taxonomy_name())?>" />
			<?php endif; ?>
			<h3><?php _e('General Settings')?></h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><label for="taxonomy_name"><?php _e('Taxonomy (required)'); ?></label></th>
					<td>
						<input type="text" class="regular-text code" id="taxonomy_name" name="taxonomy_name" value="<?php echo esc_attr($taxonomy->get_taxonomy_name()); ?>"<?php echo $add ? '' : ' readonly="readonly"'?>/>
						<span class="description"><?php _e('This will be used to identify this taxonomy in the database.  This must be unique.')?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="taxonomy"><?php _e('Label'); ?></label></th>
					<td>
						<input type="text" class="regular-text code" id="label" name="settings[label]" value="<?php echo esc_attr($taxonomy->get_taxonomy_label()); ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="taxonomy"><?php _e('Label Plural'); ?></label></th>
					<td>
						<input type="text" class="regular-text code" id="label_plural" name="settings[label_plural]" value="<?php echo esc_attr($taxonomy->get_taxonomy_label_plural()); ?>" />
					</td>
				</tr>
				<?php if(version_compare(get_wp_version(), '3.0-dev', '>=')): ?>
				<tr valign="top">
					<th scope="row"><?php _e('Is Hierarchical?'); ?></th>
					<td>
						<label for="hierarchical_yes"><?php echo ('Yes') ?></label>
						<input type="radio" id="hierarchical" name="settings[hierarchical]" value="1"<?php echo $taxonomy->get_taxonomy_is_hierarchical() ? ' checked="checked"' : ''?> />
						&nbsp; &nbsp;
						<label for="hierarchical_no"><?php echo ('No') ?></label>
						<input type="radio" id="hierarchical" name="settings[hierarchical]" value="0"<?php echo !$taxonomy->get_taxonomy_is_hierarchical() ? ' checked="checked"' : ''?> />
						&nbsp; &nbsp;
						<span class="description"><?php _e('Will terms in this taxonomy have categorical structure?')?></span>
					</td>
				</tr>
				<?php endif; ?>
			</table>
			<h3><?php _e('General Settings')?></h3>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e('Related Object Types')?></th>
					<td>
						<?php foreach(get_post_types(null, 'objects') as $post_type): ?>
							<?php if((isset($post_type->public) && $post_type->public) || in_array($post_type->name, array('post', 'page', 'attachment'))):?>
								<input type="checkbox" id="object_types_<?php echo esc_attr($post_type->name)?>" name="object_types[]" value="<?php echo esc_attr($post_type->name)?>"<?php echo $taxonomy->supports_post_type($post_type->name) ? ' checked="checked"' : ''?> />
								<label for="object_types_<?php echo esc_attr($post_type->name)?>"><?php echo $post_type->name?></label>
								<br />
							<?php endif; ?>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>
			<p class="submit"><input type="submit" name="submit" value="<?php esc_attr_e('Submit'); ?>" class="button-secondary" /></p>
		</form>
		<?php
	}
	
	/**
	 * Prints manage row for dynamic taxonomy
	 *
	 * @param Dynamic_Taxonomy_Handler $taxonomy_obj
	 * @param string $style
	 * @return unknown
	 */
	public function dynamic_taxonomy_row($taxonomy_obj, $style)
	{
		$checkbox = "<input type='checkbox' name='taxonomies[]' id='taxonomy_{$taxonomy_obj->get_taxonomy_name()}' value='{$taxonomy_obj->get_taxonomy_name()}' />";
		
		$r = "<tr id='taxonomy-{$taxonomy_obj->get_taxonomy_name()}'$style>";
		$columns = get_column_headers('dynamic_taxonomy');
		$hidden = get_hidden_columns('dynamic_taxonomy');
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
				case 'taxonomy':
					$r .= sprintf('<td %s>%s<br /><div class="row-actions"><span class="edit"><a href="%s">Edit</a> | </span><span class="delete"><a href="%s" class="submitdelete">Delete</a></span></div></td>',
						$attributes, 
						$taxonomy_obj->get_taxonomy_name(), 
						$this->get_edit_taxonomy_url($taxonomy_obj->get_taxonomy_name()),
						wp_nonce_url($this->get_manage_taxonomies_url(array('action'=>'delete', 'taxonomy'=>$taxonomy_obj->get_taxonomy_name())), 'delete_taxonomy'));
					break;
				case 'label':
					$r .= "<td $attributes>{$taxonomy_obj->get_taxonomy_label()}</td>";
					break;
				case 'object_types':
					$obect_types = count($taxonomy_obj->get_object_types()) > 0 ? join(', ', $taxonomy_obj->get_object_types()) : "none";
					$r .= "<td $attributes>$obect_types</td>";
					break;
				default:
					$r .= "<td $attributes>";
					$r .= apply_filters('manage_users_custom_column', '', $column_name, $taxonomy_obj->get_taxonomy_name());
					$r .= "</td>";
			}
		}
		$r .= '</tr>';
		return $r;
	}
	
	public function manage_taxonomies_page()
	{
		if(isset($_REQUEST['taxonomy']))
		{
			$this->do_edit_taxonomy_page();
		}
		else 
		{
			$this->do_manage_taxonomies_page();
		}
	}
	
	private function do_edit_taxonomy_page()
	{
		$taxonomy = false;
		if(!empty($_REQUEST['taxonomy']))
		{
			$taxonomy_name = $_REQUEST['taxonomy'];
			$taxonomy = $this->get_taxonomy($taxonomy_name);
		}
		else 
		{
			throw new Exception("An error has occurred.");
		}
		?>
		<div class="wrap">
			<?php screen_icon('taxonomy'); ?>
			<h2>
				<?php printf(__("Edit Taxonomy '%s'"), $taxonomy->get_taxonomy_label()); ?>  
			</h2>
			<?php	$this->edit_taxonomy_form($taxonomy, false); ?>
		</div>
		<?php
	}
	
	private function do_manage_taxonomies_page()
	{
		$taxonomy_names = $this->get_taxonomies();
		?>
		<div class="wrap">
			<?php screen_icon('taxonomy'); ?>
			<h2>
				<?php _e("Edit Taxonomies"); ?>  
				<a href="<?php echo $this->get_add_taxonomy_url();?>" class="button add-new-h2"><?php _e('Add New'); ?></a> 
			</h2>
			<?php if(!empty($_REQUEST['notice'])): ?>
				<div id="message" class="updated fade"><p><strong><?php echo stripslashes($_REQUEST['notice'])?></strong></div>
			<?php endif; ?>		
			<?php if(count($taxonomy_names)) : ?>	
				<form id="posts-filter" action="<?php $this->get_manage_taxonomies_url()?>" method="post">
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
								<?php print_column_headers('dynamic_taxonomy') ?>
							</tr>
						</thead>
						<tfoot>
							<tr class="thead">
								<?php print_column_headers('dynamic_taxonomy', false) ?>
							</tr>
						</tfoot>
						<tbody id="taxonomies" class="list:taxonomies taxonomy-list">
							<?php	$style = '';?>
							<?php	foreach ( $taxonomy_names as $taxonomy_name ) : ?>
								<?php $style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';?>
								<?php echo "\n\t" . $this->dynamic_taxonomy_row($taxonomy_name, $style); ?>
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
				<p><?php printf(__('There are currently no custom taxonomies created.  <a href="%s">Create One</a></p>'), $this->get_add_taxonomy_url())?></p>
			<?php endif; ?>
		</div>
		<br class="clear" />
		<?php
	}

}