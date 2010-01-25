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
		global $wp_rewrite;
		do_action ( 'setup_custom_taxonomy' );

		$prev_installed_taxonomies = get_option('installed_taxonomies');
		$installed_taxonomies = array_keys($this->taxonomy_handlers);
		$has_new_taxonomies = false;
		if(!is_array($prev_installed_taxonomies) || count(array_diff($installed_taxonomies, $prev_installed_taxonomies)) > 0)
		{
			$has_new_taxonomies = true;
		}

		foreach($this->taxonomy_handlers as $handler)
		{
			$args = array(
				'hierarchical'=>$handler->is_hierarchical(),
				'update_count_callback'=>array($handler, 'update_count_callback'),
				'label'=>$handler->get_taxonomy_label(),
				'query_var'=> $handler->get_query_var(),
				'rewrite'=>$handler->get_rewrite()
			);
			register_taxonomy($handler->get_taxonomy_name(), $handler->get_object_types(), $args);
		}
		if(($has_new_taxonomies || true)&& !function_exists('wpcom_is_vip'))
		{
			$wp_rewrite->flush_rules();
			update_option('installed_taxonomies', $installed_taxonomies);
		}
		add_action('do_meta_boxes', array($this, 'register_post_metabox'), 10, 1);
	}

	/**
	 * This registers the heirachal taxonomy box for posts, at least until a fix for ticket:
	 * http://core.trac.wordpress.org/ticket/10122 is accepted.
	 *
	 */
	public function register_post_metabox()
	{
		foreach($this->taxonomy_handlers as $handler)
		{
			if($handler->is_hierarchical() && in_array('post', $handler->get_post_types()))
			{
				add_meta_box('categorydiv-' . $handler->get_taxonomy_name(), $handler->get_taxonomy_label(), array($handler, 'hierarchical_taxonomy_metabox'), 'post', 'side', 'core');
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

	public function drop_down_taxonomy($taxonomy, $args = '')
	{
		$defaults = array(
			'show_option_all' => '', 'show_option_none' => '',
			'orderby' => 'id', 'order' => 'ASC',
			'show_last_update' => 0, 'show_count' => 0,
			'hide_empty' => 0, 'child_of' => 0,
			'exclude' => '', 'echo' => 1,
			'selected' => 0, 'hierarchical' => 0,
			'name' => $taxonomy, 'class' => 'postform',
			'depth' => 0, 'tab_index' => 0
		);

		$r = wp_parse_args( $args, $defaults );
		$r['include_last_update_time'] = $r['show_last_update'];
		extract( $r );

		$tab_index_attribute = '';
		if ( (int) $tab_index > 0 )
		{
			$tab_index_attribute = " tabindex=\"$tab_index\"";
		}
		$terms = get_terms($taxonomy, $r );
		$name = esc_attr($name);
		$class = esc_attr($class);

		$output = '';
		if ( ! empty( $terms ) ) {
			$output = "<select name='$name' id='$name' class='$class' $tab_index_attribute>\n";

			if ( $show_option_all ) {
				$show_option_all = apply_filters( 'list_cats', $show_option_all );
				$selected = ( '0' === strval($r['selected']) ) ? " selected='selected'" : '';
				$output .= "\t<option value='0'$selected>$show_option_all</option>\n";
			}

			if ( $show_option_none ) {
				$show_option_none = apply_filters( 'list_cats', $show_option_none );
				$selected = ( '-1' === strval($r['selected']) ) ? " selected='selected'" : '';
				$output .= "\t<option value='-1'$selected>$show_option_none</option>\n";
			}

			if ( $hierarchical )
			$depth = $r['depth'];  // Walk the full depth.
			else
			$depth = -1; // Flat.

			$output .= walk_category_dropdown_tree( $terms, $depth, $r );
			$output .= "</select>\n";
		}

		if ( $echo )
		echo $output;

		return $output;
	}

}
CP_Custom_Taxonomy_Core::Initialize();
require_once (dirname ( __FILE__ ) . '/cp-custom-taxonomy-base.php');