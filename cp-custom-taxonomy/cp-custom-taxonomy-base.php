<?php

abstract class CP_Custom_Taxonomy_Base implements iCP_Custom_Taxonomy 
{
	/**
	 * Array of associated post_types
	 *
	 * @var unknown_type
	 */
	private $registered_post_types;

	public function __construct()
	{
		add_action('setup_custom_taxonomy', array($this, 'register'));
	}

	/**
	 * Returns whether the taxonomy is hierarchical.  Not supported in WP 2.9
	 *
	 * @return unknown
	 */
	public function get_taxonomy_is_hierarchical()
	{
		return false;
	}

	/**
	 * Returns the rewrite for the taxonomy
	 *
	 * @return unknown
	 */
	public function get_taxonomy_rewrite()
	{
		return false;
	}
	
	/**
	 * Returns whether the taxonomy should show the UI
	 *
	 * @return bool|null
	 */
	public function get_taxonomy_show_ui()
	{
		return null;
	}
	
	public function get_taxonomy_manage_cap()
	{
		return null;
	}
	
	public function get_taxonomy_edit_cap()
	{
		return null;
	}
	
	public function get_taxonomy_delete_cap()
	{
		return null;
	}
	
	public function get_taxonomy_assign_cap()
	{
		return null;
	}
	
	/**
	 * Returns a pointer to the update count callback for the taxonomy
	 * 
	 * @return mixed  return false to use the default callback
	 *
	 */
	public function get_taxonomy_update_count_callback()
	{
		return false;
	}

	/**
	 * Returns the query_var name to use in the rewrite for the taxonomy
	 *
	 * @return string|bool
	 */
	public function get_taxonomy_query_var()
	{
		return true;
	}
	
	/**
	 * Default action for registering the taxonomy.
	 *
	 */
	public function register()
	{
		CP_Custom_Taxonomy_Core::GetInstance()->register_custom_taxonomy($this);
	}

	/**
	 * BEGIN WP 2.9 ONLY METHODS
	 */
	
	/**
	 * Registers the managemet pages for the custom taxonomy in wp 2.9
	 *
	 */
	public function register_management_page()
	{
		$post_type = array_shift($this->get_object_types());
		if('post' == $post_type)
			add_submenu_page('edit.php', $this->get_taxonomy_label_plural(), $this->get_taxonomy_label_plural(), 'manage_categories', 'edit-tags.php?taxonomy=' . $this->get_taxonomy_name());
		elseif ('page' == $post_type)
			add_submenu_page('edit-pages.php', $this->get_taxonomy_label_plural(), $this->get_taxonomy_label_plural(), 'manage_categories', 'edit-tags.php?taxonomy=' . $this->get_taxonomy_name());
		else
		{
			add_submenu_page(str_replace('admin.php?page=', '', CP_Custom_Content_Core::GetInstance()->get_manage_custom_content_url($post_type)), $this->get_taxonomy_label_plural(), $this->get_taxonomy_label_plural(), 'manage_categories', 'edit-tags.php?taxonomy=' . $this->get_taxonomy_name());
		}

	}
	
	public function add_page_taxonomy_support($page, $context)
	{
		if('page' == $page && 'side' == $context)
		{
			add_meta_box('tagsdiv-' . $this->get_taxonomy_name(), $this->get_taxonomy_label_plural(), 'post_tags_meta_box', 'page', 'side', 'core');
		}
	}
	/**
	 * END WP 2.9 ONLY METHODS
	 */
}

interface iCP_Custom_Taxonomy
{
	/**
	 * The taxonomy name
	 *
	 * @return string
	 *
	 */
	public function get_taxonomy_name();

	/**
	 * The label for the taxonomy
	 *
	 * @return string
	 *
	 */
	public function get_taxonomy_label();

	/**
	 * The label for the taxonomy
	 *
	 * @return string
	 *
	 */
	public function get_taxonomy_label_plural();
	
	/**
	 * Returns whether the taxonomy is hierarchical.  Not supported in WP 2.9
	 *
	 * @return unknown
	 */
	public function get_taxonomy_is_hierarchical();
	
	/**
	 * Returns the rewrite for the taxonomy
	 *
	 * @return unknown
	 */
	public function get_taxonomy_rewrite();
	
	/**
	 * Returns a pointer to the update count callback for the taxonomy
	 * 
	 * @return mixed  return false to use the default callback
	 *
	 */
	public function get_taxonomy_update_count_callback();

	/**
	 * Returns the query_var name to use in the rewrite for the taxonomy
	 *
	 * @return string|bool
	 */
	public function get_taxonomy_query_var();
	
	/**
	 * Returns whether the taxonomy should show the UI
	 *
	 * @return bool|null
	 */
	public function get_taxonomy_show_ui();
	
	public function get_taxonomy_manage_cap();
		
	public function get_taxonomy_edit_cap();
	
	public function get_taxonomy_delete_cap();
	
	public function get_taxonomy_assign_cap();
	
	/**
	 * returns the object types this taxonomy is associated with
	 *
	 * @return array
	 */
	public function get_object_types();
	
	/**
	 * Default action for registering the taxonomy.
	 *
	 */
	public function register();

	/**
	 * Registers the managemet pages for the custom taxonomy in wp 2.9
	 *
	 * wp 2.9 only
	 */
	public function register_management_page();
	
	/**
	 * Adds support for the taxonomy to the edit page
	 *
	 * @param string $page
	 * @param stromg $context
	 */
	public function add_page_taxonomy_support($page, $context);
}