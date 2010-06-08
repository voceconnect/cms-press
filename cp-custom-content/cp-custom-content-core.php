<?php
/**
 * Main Class for handling custom content types
 *
 */
class CP_Custom_Content_Core
{
	/**
	 * Singleton Instance
	 *
	 * @var CP_Custom_Content_Core
	 */
	private static $instance;

	/**
	 * Array of custom content handlers
	 *
	 * @var array
	 */
	private $content_handlers;

	/**
	 * Returns singleton instance of CP_Custom_Content_Core
	 *
	 * @return CP_Custom_Content_Core
	 */
	public static function GetInstance()
	{
		if (is_null ( self::$instance ))
		{
			self::$instance = new CP_Custom_Content_Core ( );
		}
		return self::$instance;
	}
	
	/**
	 * Initializes the plugin
	 * calls 'setup_custom_content' //used for registering content types
	 *
	 */
	public static function Initialize()
	{
		$instance = self::GetInstance();
		add_action ( 'init', array ($instance, 'setup_custom_content' ), 1 );
		if(version_compare(get_wp_version(), '3.0-dev', '<'))
		{
			add_action ( 'admin_menu', array ($instance, 'add_menu_items' ) );
			add_filter('screen_meta_screen', array($instance, 'filter_screen_meta_screen'));
			add_action('wp_ajax_submit_custom_content', array($instance, 'ajax_submit_custom_content'));
			add_action('autosave_generate_nonces', array($instance, 'autosave_generate_nonces'));
			add_filter('get_edit_post_link', array($instance, 'get_edit_post_link'), 10, 3);
			add_filter('map_meta_cap', array($instance, 'map_meta_cap'), 10, 4);
		}
		
	}
	
	/**
	 * Constructor method
	 *
	 */
	private function __construct()
	{
		$this->content_handlers = array ();
	}

	/**
	 * This calls the stetup_custom_content action which should be
	 * used to registers any child plugins via register_custom_content_type()
	 *
	 * Runs on 'init' action
	 */
	public function setup_custom_content()
	{
		global $wp_rewrite;

		//child plugins should hook into this action to register their handler
		do_action ( 'setup_custom_content' );

		if(!count($this->content_handlers))
			return;
		
		//check if new post_types were added.
		$prev_installed_post_types = get_option('installed_post_types');
		$installed_post_types = array_keys($this->content_handlers);
		$has_new_types = false;
		if(!is_array($prev_installed_post_types) || count(array_diff($installed_post_types, $prev_installed_post_types)) > 0)
		{
			$has_new_types = true;
		}
		
		foreach($this->content_handlers as $handler)
		{
			$handler->add_base_hooks();
			$handler->add_custom_hooks();
			
			$args = array(
				'labels' => $handler->get_type_labels(),
				'publicly_queryable' => $handler->get_type_publicly_queryable(),
				'exclude_from_search' => $handler->get_type_exclude_from_search(), 
				'public' => $handler->get_type_is_public(), 
				'hierarchical'=> $handler->get_type_is_hierarchical(), 
				'capability_type' => $handler->get_type_capability_type(), 
				'supports'=>$handler->get_type_supports(),
				'rewrite' => $handler->get_type_rewrite(),
				'query_var' => $handler->get_type_query_var(),
				'show_ui' => $handler->get_type_show_ui()
			);
				
			if($edit_link = $handler->get_type_edit_link())
			{
				$args['_edit_link'] = $edit_link;
			}
			register_post_type($handler->get_content_type(), $args);
			if(!function_exists('wpcom_is_vip'))
			{
				$handler->add_rewrite_rules();
			}
		}
		
		if(version_compare(get_wp_version(), '3.0-dev', '<'))
		{
			add_filter('query_vars', array($this, 'query_vars'), 10, 1);
			add_action('parse_request', array($this, 'parse_request'), 10, 1);
		}
		
		//template handling
		/**
		 * @todo remove if http://core.trac.wordpress.org/attachment/ticket/9674/9674.18.diff is merged
		 */
		add_filter("single_template", array($this, 'single_template'), 10, 1);
		
		add_filter("date_template", array($this, 'date_template'), 10, 1);
		add_filter("search_template", array($this, 'search_template'), 10, 1);

		add_filter("index_template", array($this, 'index_template'), 10, 1);
		add_filter("home_template", array($this, 'index_template'), 10, 1);

		//flush the rewrite rules if new content_types were added
		if(($has_new_types) && !function_exists('wpcom_is_vip'))
		{
			$wp_rewrite->flush_rules();
			update_option('installed_post_types', $installed_post_types);
		}
	}

	/**
	 * Registers the custom content type into the system
	 *
	 * @param CP_Custom_Content_Handler_Base $handler
	 * @return bool
	 */
	public function register_custom_content_type($handler)
	{
		if (! in_array ( 'CP_Custom_Content_Handler_Base', class_parents($handler)))
		{
			if (is_admin ())
			{
				trigger_error("Unable to register '".get_class($handler)."' custom content types.  Class '".get_class($handler)."' must extend 'CP_Custom_Content_Base'.", E_WARNING);
			}
			return false;
		}

		$this->content_handlers [$handler->get_content_type()] = $handler;
			
		return true;
	}

	/**
	 * Returns registered content handler for given content_type
	 *
	 * @param string $content_type
	 * @return CP_Custom_Content_Handler_Base
	 */
	public function get_content_handler($content_type)
	{
		if(isset($this->content_handlers[$content_type]))
		{
			return $this->content_handlers[$content_type];
		}
		return false;
	}
	
	/**
	 * Tries to rewrite single.php template to {post_type}.php
	 *
	 * @todo deprecated if http://core.trac.wordpress.org/attachment/ticket/9674/9674.18.diff is merged
	 * 
	 * @param string $template
	 * @return string
	 */
	public function single_template($template)
	{
		global $wp_query;
		if( $replacement_template = get_query_template($wp_query->get_queried_object()->post_type) )
			return $replacement_template;
		return $template;
	}

	/**
	 * Replaces date template with date-{post_type}.php
	 *
	 * @param string $template
	 * @return string
	 */
	public function date_template($template)
	{
		if( $replacement_template = get_query_template('date-'.get_query_var('post_type')) )
			return $replacement_template;
		return $template;
	}

	/**
	 * Replaces search template with search-{post_type}.php
	 *
	 * @param unknown_type $template
	 * @return unknown
	 */
	public function search_template($template)
	{
		if( $replacement_template = get_query_template('search-'.get_query_var('post_type')) )
			return $replacement_template;
		return $template;
	}
	
	public function index_template($template)
	{
		if( $replacement_template = get_query_template('index-'.get_query_var('post_type')) )
			return $replacement_template;
		return $template;
	}

	public function home_template($template)
	{
		$template = locate_template(array('home.php', 'index.php'));
		if( $replacement_template = locate_template(array('home-'.get_query_var('post_type').'.php', 'index-'.get_query_var('post_type').'.php', 'home.php', 'index.php')) )
			return $replacement_template;
		return $template;
	}
	
	/**
	 * BEGIN WP 2.9 ONLY METHODS
	 */
	
	/**
	 * Adds the menu items for the registered content types
	 * 
	 * This is only used by WP 2.9
	 * 
	 */
	public function add_menu_items()
	{
		foreach($this->content_handlers as $handler)
		{
			if($handler->get_type_is_public())
			{
				$labels = $handler->get_type_labels();
				//add manager menu and handling
				$page_hook = add_object_page( $labels->edit_item, $labels['name'], 'edit_pages', basename(dirname(__FILE__)).'/manage-'.$handler->get_content_type().'.php', array($handler, 'manage_content_page'), $handler->get_type_icon_url());
				add_action('load-'.$page_hook, array($handler, 'setup_manage_page'), 10);
				add_action('load-'.$page_hook, array($handler, 'handle_manage_page_postback'), 1);
	
				//add add menu and handling
				add_submenu_page(basename(dirname(__FILE__)).'/manage-'.$handler->get_content_type().'.php', $labels['edit_item'], __('Edit'), 'edit_pages',  basename(dirname(__FILE__)).'/manage-'.$handler->get_content_type().'.php', array($handler, 'manage_content_page'));
				$page_hook = add_submenu_page(basename(dirname(__FILE__)).'/manage-'.$handler->get_content_type().'.php', $labels['add_item'], __('Add New'), 'edit_pages',  basename(dirname(__FILE__)).'/add-'.$handler->get_content_type().'.php', array($handler, 'add_content_page'));
				add_action('load-'.$page_hook, array($handler, 'setup_add_page'));
				
				//fix for 2.9 where it blindly replaces '-add' and '-new' in the screen handling.
				$post_type = $handler->get_content_type();
				if(0 === strpos($post_type, 'add') || 0 === strpos($post_type, 'new'))
				{
					add_filter(str_replace(array('-add', '-new'), '', 'manage_' . $post_type . '_page_cp-custom-content/add-'.$post_type.'_columns'), array($this, 'screen_fix_filter'), 10, 1);
					if($_REQUEST['page'] == plugin_basename(basename(dirname(__FILE__)).'/manage-'.$post_type.'.php'))
					{
						add_filter('manage_toplevel_page_cp-custom-content/manages_columns', array($this, 'screen_fix_filter'), 10, 1);
					}
				}
			}
		}
	}
	
	public function screen_fix_filter($columns)
	{
		return wp_manage_pages_columns();
	}
	
	/**
	 * Returns the admin url for creating a new custom content item of the given post_type
	 *
	 * @param string $post_type
	 * @return string
	 */
	public function get_add_custom_content_url($post_type)
	{
		$page = plugin_basename(basename(dirname(__FILE__)).'/add-'.$post_type.'.php');
		$url ='admin.php?page='.$page;
		return apply_filters('add_custom_content_url', $url, $post_type);
	}

	/**
	 * Returns the admin url for creating a new custom content item of the given post_type
	 *
	 * @param string $post_type
	 * @return string
	 */
	public function get_manage_custom_content_url($post_type)
	{
		$url = '';
		if($this->get_content_handler($post_type) !== false)
		{
			$page = plugin_basename(basename(dirname(__FILE__)).'/manage-'.$post_type.'.php');
			$url ='admin.php?page='.$page;
		}
		return apply_filters('manage_custom_content_url', $url, $post_type);
	}

	/**
	 * Filter for returning the url to the edit page for the given post
	 *
	 * @param string $link
	 * @param int $post_ID
	 * @param string $context
	 * @return string
	 */
	public function get_edit_post_link($link, $post_ID, $context)
	{
		$post = &get_post( $post_ID );
		if($this->get_content_handler($post->post_type) !== false)
		{
			$page = plugin_basename(basename(dirname(__FILE__)).'/manage-'.$post->post_type.'.php');
			$link = admin_url('admin.php?page='.$page.'&post='.$post_ID);
		}
		return $link;
	}

	/**
	 * Filters the screen meta screen of the current edit page.
	 *
	 * @param string $screen
	 * @return string
	 */
	public function filter_screen_meta_screen($screen)
	{
		$post_type = str_replace(array('/add-', '/manage-'), '', substr($screen, strpos($screen, '/')));
		if(isset($this->content_handlers[$post_type]))
		{
			return $post_type;
		}
		return $screen;
	}

	/**
	 * Handles the post back from the edit content form.  This isn't really a
	 * ajax post back, but it was the easiest way to handle the request without
	 * a bunch of url/include handling.
	 *
	 * @todo change this to work like the manage page works instead.
	 *
	 */
	public function ajax_submit_custom_content()
	{
		$action = $_POST['hiddenaction'];
		$post_type = $_POST['post_type'];
		do_action('submit_custom_content', $post_type, $action);
		if($handler = $this->get_content_handler($post_type))
		{
			switch ($action)
			{
				case 'post':
					check_admin_referer('add-post');
					$post_ID = write_post();
					$handler->redirect_content($post_ID);
					exit();
					break;
				case 'editpost':
					$post_ID = (int) $_POST['post_ID'];
					check_admin_referer('update-post_' . $post_ID);
					if ( !current_user_can( 'edit_post', $post_ID ) )
					{
						wp_die( __('You are not allowed to edit this post.' ));
					}
					$post_data = &$_POST;
					$handler->update_content($post_ID, $post_data);
					break;
			}
		}
		die(0);
	}
	
	/**
	 * generates nonces for autosaving custom post_types
	 *
	 * this is pending adoption of the following ticket: http://core.trac.wordpress.org/ticket/10634
	 *
	 */
	public function autosave_generate_nonces()
	{
		$post_type = $_POST['post_type'];
		$ID = (int) $_POST['post_ID'];
		if(isset($this->content_handlers[$post_type]))
		{
			if(current_user_can('edit_page', $ID)) {
				die(wp_create_nonce('update-post_' . $ID));
			}
		}
	}
	
	/**
	 * Prints the manage rows for the content type
	 *
	 * @param string $post_type
	 * @param array $posts
	 * @param int $pagenum
	 * @param int $per_page
	 */
	public function manage_rows($post_type, $posts, $pagenum, $per_page)
	{
		if($handler = $this->get_content_handler($post_type))
		{
			$handler->manage_rows($posts, $pagenum, $per_page);
		}
	}
	
	public function map_meta_cap($caps, $cap, $user_id, $args)
	{
		$content_types = array_keys($this->content_handlers);
		foreach($content_types as $content_type)
		{
			if(false !== strpos($cap, $content_type))
			{
				$args = array_merge( array( str_replace($content_type, 'post', $caps[0]), $user_id ), $args );
				$caps = call_user_func_array( 'map_meta_cap',  $args);
				break;
			}
		}
		return $caps;
	}
	
	/**
	 * Adds post_type query_var
	 *
	 * @param array $query_vars
	 * @return array
	 */
	public function query_vars($query_vars)
	{
		if(!in_array('post_type', $query_vars))
		{
			$query_vars[] = 'post_type';
		}
		return $query_vars;
	}
	
	/**
	 * Limits the post_type in the query_vars to ones that should be publicly queryable
	 *
	 * @param WP $wp
	 */
	public function parse_request($wp)
	{
		if(!is_admin() && isset($wp->query_vars['post_type']))
		{
			if( ($handler = $this->get_content_handler($wp->query_vars['post_type'])) && !$handler->get_type_publicly_queryable())
			{
				unset($wp->query_vars['post_type']);
			}
		}
	}
	
	/**
	 * END WP 2.9 ONLY METHODS
	 */

}

require_once (dirname ( __FILE__ ) . '/cp-custom-content-handler-base.php');
