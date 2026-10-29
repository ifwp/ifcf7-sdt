<?php namespace IFCF7_SDT;

final class Views {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	public static function ifwp_plugins_loaded(){
		add_action('init', [__CLASS__, 'init']);
		add_filter('post_row_actions', [__CLASS__, 'post_row_actions'], 10, 2);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// private
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	const COLUMNS = '_ifcf7_sdt_columns';
	const CRITERIA = '_ifcf7_sdt_criteria';
	const FORM_ID = '_ifcf7_sdt_form_id';
	const POST_TYPE = 'ifcf7-sdt-view';
	const SEARCH = '_ifcf7_sdt_search';

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string|WP_Error
	 */
	private static function get_criteria_json($criteria = []){
		$json = wp_json_encode(self::sanitize_criteria($criteria));
		$max = absint(apply_filters('ifcf7_sdt_max_criteria_length', 64 * KB_IN_BYTES));
		if(strlen($json) > $max){
			return ifwp_error(sprintf(__('Invalid parameter(s): %s'), 'criteria') . '.');
		}
		return $json;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	private static function save($post_id = 0, $json = '', $form_ids = [], $search = '', $columns = []){
		update_post_meta($post_id, self::CRITERIA, wp_slash($json)); // Metadata is unslashed on the way in.
		update_post_meta($post_id, self::FORM_ID, implode(',', Columns::sanitize_form_ids($form_ids)));
		update_post_meta($post_id, self::SEARCH, sanitize_text_field($search));
		$columns = self::sanitize_columns($columns);
		if($columns){
			update_post_meta($post_id, self::COLUMNS, implode(',', $columns));
		} else {
			delete_post_meta($post_id, self::COLUMNS); // No columns of its own: whatever the defaults are.
		}
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string[]
	 */
	private static function sanitize_columns($columns = []){
		if(!is_array($columns)){
			$columns = explode(',', (string) $columns);
		}
		$sanitized = [];
		$max = absint(apply_filters('ifcf7_sdt_max_view_columns', 500));
		foreach($columns as $column){
			$column = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $column); // The aliases of the columns.
			if(!$column or in_array($column, $sanitized, true) or count($sanitized) >= $max){
				continue;
			}
			$sanitized[] = $column;
		}
		return $sanitized;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// public
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return WP_Post|null
	 */
	public static function get($view = null){
		if($view instanceof \WP_Post){
			return (self::POST_TYPE === $view->post_type) ? $view : null;
		}
		$view = trim((string) $view);
		if(!$view){
			return null;
		}
		if(is_numeric($view)){
			$post = get_post(absint($view));
		} else {
			$post = get_page_by_path($view, OBJECT, self::POST_TYPE);
		}
		if(!$post or self::POST_TYPE !== $post->post_type or 'publish' !== $post->post_status){
			return null;
		}
		return $post;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * One criteria group per search builder, keyed by its group so panels may be reordered.
	 *
	 * @return array
	 */
	public static function get_criteria($view = null){
		$view = self::get($view);
		if(!$view){
			return [];
		}
		$criteria = json_decode(get_post_meta($view->ID, self::CRITERIA, true), true);
		if(JSON_ERROR_NONE !== json_last_error() or !is_array($criteria)){
			return [];
		}
		return self::sanitize_criteria($criteria);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * The columns a view does show, by alias. Empty means it has none of its own.
	 *
	 * @return string[]
	 */
	public static function get_columns($view = null){
		$view = self::get($view);
		if(!$view){
			return [];
		}
		return self::sanitize_columns(get_post_meta($view->ID, self::COLUMNS, true));
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return int[]
	 */
	public static function get_form_ids($view = null){
		$view = self::get($view);
		if(!$view){
			return [];
		}
		return Columns::sanitize_form_ids(get_post_meta($view->ID, self::FORM_ID, true));
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function get_search($view = null){
		$view = self::get($view);
		if(!$view){
			return '';
		}
		return (string) get_post_meta($view->ID, self::SEARCH, true);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return WP_Post[]
	 */
	public static function get_views(){
		return get_posts([
			'order' => 'ASC',
			'orderby' => 'title',
			'post_status' => 'publish',
			'post_type' => self::POST_TYPE,
			'posts_per_page' => absint(apply_filters('ifcf7_sdt_max_views', 100)),
			'suppress_filters' => false,
		]);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public static function init(){
		register_post_type(self::POST_TYPE, [
			'labels' => ifwp_post_type_labels('Saved view', 'Saved views', false),
			// Not publicly queryable on purpose: the only way in is our own capability checked page.
			'public' => false,
			'query_var' => false,
			'rewrite' => false,
			'show_in_admin_bar' => false,
			'show_in_menu' => ifcf7_sdt_menu_slug(),
			'show_ui' => true,
			'supports' => ['author', 'title'],
		]);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return int|WP_Error
	 */
	public static function insert($name = '', $criteria = [], $form_ids = [], $search = '', $columns = []){
		$name = sanitize_text_field($name);
		if(!$name){
			return ifwp_error(sprintf(__('Invalid parameter(s): %s'), 'name') . '.');
		}
		$json = self::get_criteria_json($criteria);
		if(is_wp_error($json)){
			return $json; // Checked before the post is created.
		}
		$post_id = wp_insert_post([
			'post_author' => get_current_user_id(),
			'post_status' => 'publish',
			'post_title' => $name,
			'post_type' => self::POST_TYPE,
		], true);
		if(is_wp_error($post_id)){
			return $post_id;
		}
		self::save($post_id, $json, $form_ids, $search, $columns);
		do_action('ifcf7_sdt_insert_view', $post_id, $criteria);
		return $post_id;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Trashed rather than deleted for good: a view is cheap to keep and expensive to rebuild by
	 * hand. The ifcf7_sdt_force_delete_view hook does make it permanent.
	 *
	 * @return int|WP_Error
	 */
	public static function delete($view = null){
		$view = self::get($view);
		if(!$view){
			return ifwp_error(__('Invalid post ID.'));
		}
		if(apply_filters('ifcf7_sdt_force_delete_view', false, $view)){
			$deleted = wp_delete_post($view->ID, true);
		} else {
			$deleted = wp_trash_post($view->ID);
		}
		if(!$deleted){
			return ifwp_error(__('Error in moving the item to Trash.'));
		}
		do_action('ifcf7_sdt_delete_view', $view->ID);
		return $view->ID;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return int|WP_Error
	 */
	public static function update($view = null, $criteria = [], $form_ids = [], $search = '', $columns = []){
		$view = self::get($view);
		if(!$view){
			return ifwp_error(__('Invalid post ID.'));
		}
		$json = self::get_criteria_json($criteria);
		if(is_wp_error($json)){
			return $json;
		}
		self::save($view->ID, $json, $form_ids, $search, $columns);
		do_action('ifcf7_sdt_update_view', $view->ID, $criteria);
		return $view->ID;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	public static function post_row_actions($actions, $post){
		if(self::POST_TYPE !== $post->post_type){
			return $actions;
		}
		if(!current_user_can(ifcf7_sdt_capability())){
			return $actions;
		}
		$actions['ifcf7_sdt_view'] = '<a href="' . esc_url(Page::get_url([], $post)) . '">' . __('View') . '</a>';
		return $actions;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Criteria are validated again when the query runs, this is just about the shape.
	 *
	 * @return array
	 */
	public static function sanitize_criteria($criteria = []){
		if(!is_array($criteria)){
			return [];
		}
		$sanitized = [];
		foreach($criteria as $group => $details){
			if(!is_array($details) or !$details){
				continue;
			}
			$group = sanitize_html_class($group);
			if(!$group){
				continue;
			}
			$sanitized[$group] = $details;
		}
		return $sanitized;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
