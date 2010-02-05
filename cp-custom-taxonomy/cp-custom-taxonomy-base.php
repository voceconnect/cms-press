<?php

abstract class CP_Custom_Taxonomy_Base
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
		
		//@todo, only do this for 2.9
	}

	/**
	 * The taxonomy name
	 *
	 * @return string
	 *
	 */
	public abstract function get_taxonomy_name();

	/**
	 * The label for the taxonomy
	 *
	 * @return string
	 *
	 */
	public abstract function get_taxonomy_label();

	/**
	 * The label for the taxonomy
	 *
	 * @return string
	 *
	 */
	public abstract function get_taxonomy_label_plural();
	
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
	 * Returns a pointer to the update count callback for the taxonomy
	 * 
	 * @return mixed  return false to use the default callback
	 *
	 */
	public function get_taxonomy_update_count_callback()
	{
		false;
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
	 * returns the object types this taxonomy is associated with
	 *
	 */
	public abstract function get_object_types();
	
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
		add_submenu_page('edit.php', $this->get_taxonomy_label_plural(), $this->get_taxonomy_label_plural(), 'manage_categories', 'edit-tags.php?taxonomy=' . $this->get_taxonomy_name());
	}
	
	/**
	 * END WP 2.9 ONLY METHODS
	 */
}