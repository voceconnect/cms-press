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
	 * returns the object types this taxonomy is associated with
	 *
	 */
	public abstract function get_object_types();
	
	/**
	 * Returns the settings for this taxonomy
	 *
	 */
	public abstract function get_settings();
	
	/**
	 * The query_var for the given taxonomy
	 * NOT CURRENTLY USED
	 *
	 * @return string|bool
	 */
	public function get_query_var()
	{
		return true;
	}

	/**
	 * Returns the rewrite for the taxonomy
	 * NOT CURRENTLY USED
	 *
	 */
	public function get_rewrite()
	{
		return false;
	}

	/**
	 * Updates the count for the given taxonomy
	 *
	 * @param array $terms
	 */
	public function update_count_callback($terms)
	{
		global $wpdb;

		foreach ( (array) $terms as $term ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND post_status = 'publish' AND post_type in ('".join("','", $this->get_object_types())."') AND term_taxonomy_id = %d", $term ) );
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
		}
	}

	/**
	 * Returns whether or not the taxonomy is hierarchical
	 *
	 * @todo add handling for hierarchical taxonomies
	 * @return bool false by default
	 */
	public function is_hierarchical()
	{
		return false;
	}

	public function is_singular()
	{
		return false;
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
	 * Default handling for the taxonomy metaboxes
	 *
	 * @todo figure out handling for adding new terms.  Post back is currently tied to categories
	 * and is not easily overwritten.
	 *
	 * @todo make changes to core so category walkers can be used.  These currently can't be used since
	 * wp_set_object_terms only takes the terms as string instead of the id's
	 *
	 * @param object $post
	 */
	public function hierarchical_taxonomy_metabox($post)
	{
		?>
		<div id="<?php echo $this->get_taxonomy_name()?>-all" class="tabs-panel">
			<?php if($this->is_singular()) :?>
				<?php
				$selected = -1;
				if($post->ID)
				{
					$selected_terms = wp_get_object_terms($post->ID, $this->get_taxonomy_name(), array());
					if(count($selected_terms) > 0)
					{
						$selected = $selected_terms[0]->term_id;
					}
				}
				//have to use custom drop down since set object terms turns the int values into new terms
				$terms = get_terms($this->get_taxonomy_name(), array('orderby'=>'name', 'hide_empty' => false));
				?>
				<select name='tax_input[<?php echo $this->get_taxonomy_name()?>]' id='tax_input[<?php echo $this->get_taxonomy_name()?>]' class='postform' >
					<option value='' selected='selected'>none</option>
					<?php foreach($terms as $term) : ?>
						<option class="level-0" value="<?php echo $term->term_id?>" <?php selected($selected, $term->term_id)?>><?php echo $term->name?></option>
					<?php endforeach; ?>
				</select>
			<?php else: ?>
				<input type="hidden" name="tax_input[<?php echo $this->get_taxonomy_name()?>]" value="" /> 
				<ul id="categorychecklist" class="list:category categorychecklist form-no-clear">
					<?php $this->term_checklist($post->ID) ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return;
	}

	/**
	 * Prints the term checklist
	 *
	 * @todo submit changes to core so built in Walker and checklist handling can be used.
	 *
	 * @param int $post_id
	 * @param bool $descendants_and_self
	 * @param array $selected_terms
	 * @param array $popular_terms
	 * @param Walker $walker
	 */
	public function term_checklist( $post_id = 0, $descendants_and_self = 0, $selected_cats = false, $popular_cats = false, $walker = null )
	{
		if ( empty($walker) || !is_a($walker, 'Walker') )
			$walker = new Walker_Taxonomy_Checklist();

		$descendants_and_self = (int) $descendants_and_self;

		$args = array('taxonomy'=>$this->get_taxonomy_name());

		if ( is_array( $selected_cats ) )
			$args['selected_cats'] = $selected_cats;
		elseif ( $post_id )
			$args['selected_cats'] = wp_get_object_terms($post_id, $this->get_taxonomy_name(), array('fields'=>'ids'));
		else
			$args['selected_cats'] = array();

		if ( is_array( $popular_cats ) )
			$args['popular_cats'] = $popular_cats;
		else
			$args['popular_cats'] = get_terms( 'category', array( 'fields' => 'ids', 'orderby' => 'count', 'order' => 'DESC', 'number' => 10, 'hierarchical' => false ) );

		if ( $descendants_and_self ) {
			$categories = get_terms( $this->get_taxonomy_name(), "child_of=$descendants_and_self&hierarchical=0&hide_empty=0" );
			$self = get_term( $descendants_and_self, $this->get_taxonomy_name());
			array_unshift( $categories, $self );
		} else {
			$categories = get_terms($this->get_taxonomy_name(), 'get=all');
		}

		// Then the rest of them
		echo call_user_func_array(array(&$walker, 'walk'), array($categories, 0, $args));
	}

	public function register_management_page()
	{
		add_submenu_page('edit.php', $this->get_taxonomy_label(), $this->get_taxonomy_label(), 'manage_categories', 'edit-tags.php?taxonomy=' . $this->get_name());
		add_submenu_page('edit.php', $this->get_taxonomy_label(), $this->get_taxonomy_label(), 'manage_categories', 'edit-tags.php?taxonomy=' . $this->get_name());
	}
}


if(!class_exists('Walker_Taxonomy_Checklist'))
{
	class Walker_Taxonomy_Checklist extends Walker {
		var $tree_type = 'term';
		var $db_fields = array ('parent' => 'parent', 'id' => 'term_id'); //TODO: decouple this

		function start_lvl(&$output, $depth, $args) {
			$indent = str_repeat("\t", $depth);
			$output .= "$indent<ul class='children' style='margin-left: 15px;'>\n";
		}

		function end_lvl(&$output, $depth, $args) {
			$indent = str_repeat("\t", $depth);
			$output .= "$indent</ul>\n";
		}

		function start_el(&$output, $term, $depth, $args) {
			extract($args);
			$class = in_array( $term->term_id, $popular_cats ) ? ' class="popular-term"' : '';
			$output .= "\n<li id='term-$term->term_id'$class>" . '<label class="selectit"><input value="' . attribute_escape($term->term_id) . '" type="checkbox" name="tax_input['.$taxonomy.'][]" id="in-term-' . $term->term_id . '"' . (in_array( $term->term_id, $selected_cats ) ? ' checked="checked"' : "" ) . '/> ' . esc_html( apply_filters('the_term', $term->name )) . '</label>';
		}

		function end_el(&$output, $term, $depth, $args) {
			$output .= "</li>\n";
		}
	}
}