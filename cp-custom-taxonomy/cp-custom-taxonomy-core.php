<?php

class CP_Custom_Taxonomy_Core
{
	private static $instance;

	private $taxonomy_handlers;

	/**
	 * Returns singleton instance of CP_Custom_Taxonomy_Core
	 *
	 * @return CP_Custom_Taxonomy_Core
	 */
	public static function GetInstance()
	{
		if (is_null ( self::$instance ))
		{
			self::$instance = new CP_Custom_Taxonomy_Core ( );
		}
		return self::$instance;
	}

	public static function Initialize()
	{
		$instance = self::GetInstance();
		add_action('init', array($instance, 'setup_custom_taxonomy'));
	}
	
	/**
	 * Constructor method
	 *
	 */
	private function __construct()
	{
		$this->taxonomy_handlers = array ();
	}

	public function setup_custom_taxonomy()
	{
		do_action ( 'setup_custom_taxonomy' );

		foreach($this->taxonomy_handlers as $handler)
		{
			$args = array(
				'hierarchical'=>$handler->get_taxonomy_is_hierarchical(),
				'label'=>$handler->get_taxonomy_label_plural(),
				'query_var'=> $handler->get_taxonomy_query_var(),
				'rewrite'=>$handler->get_taxonomy_rewrite(),
				'show_ui' => $handler->get_taxonomy_show_ui(),
				'manage_cap' => $handler->get_taxonomy_manage_cap(),
				'edit_cap' => $handler->get_taxonomy_edit_cap(),
				'delete_cap' => $handler->get_taxonomy_delete_cap(),
				'assign_cap' => $handler->get_taxonomy_assign_cap(),
			);
			if(false !== ($callback = $handler->get_taxonomy_update_count_callback()))
				$args['update_count_callback'] = $callback;
			
			register_taxonomy($handler->get_taxonomy_name(), $handler->get_object_types(), $args);
			if(version_compare(get_wp_version(), '3.0-dev', '<'))
			{
				if($handler->get_taxonomy_is_hierarchical())
				{
					add_action('admin_menu', array($handler, 'register_management_page'));
				}
				if(in_array('page', $handler->get_object_types()))
				{
					add_action('do_meta_boxes', array($handler, 'add_page_taxonomy_support'), 10, 2);
				}
			}
		}
	}

	/**
	 * Registers the custom term taxonomy into the system
	 *
	 * @param CP_Custom_Taxonomy_Base $handler
	 * @return bool
	 */
	public function register_custom_taxonomy($handler)
	{
		if (! in_array ( 'CP_Custom_Taxonomy_Base', class_parents($handler)))
		{
			if (is_admin ())
			{
				wp_die ( "Unable to register '".get_class($handler)."' custom content types.  Class '".get_class($handler)."' must extend 'CP_Custom_Taxonomy_Base'.", 'Custom Taxonomy Type Error' );
			}
			return false;
		}

		$this->taxonomy_handlers[$handler->get_taxonomy_name()] = $handler;

		return true;
	}

	/**
	 * Returns the set handler for the given taxonomy
	 *
	 * @param string $taxonomy
	 * @return CP_Custom_Taxonomy_Base or false if handler doesn't exist
	 */
	public function get_handler($taxonomy)
	{
		if(isset($this->taxonomy_handlers[$taxonomy]))
		{
			return $this->taxonomy_handlers[$taxonomy];
		}
		return  false;
	}
}
CP_Custom_Taxonomy_Core::Initialize();
require_once (dirname ( __FILE__ ) . '/cp-custom-taxonomy-base.php');