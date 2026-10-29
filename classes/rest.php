<?php namespace IFCF7_SDT;

final class Rest {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	public static function ifwp_plugins_loaded(){
		add_action('rest_api_init', [__CLASS__, 'rest_api_init']);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// public
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function get_namespace(){
		return apply_filters('ifcf7_sdt_rest_namespace', 'ifcf7/v1');
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function get_url(){
		return rest_url(self::get_namespace() . '/submissions');
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function get_views_url(){
		return rest_url(self::get_namespace() . '/views');
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array|WP_Error
	 */
	public static function insert_view($request){
		$criteria = $request->get_param('criteria');
		$post_id = Views::insert($request->get_param('name'), is_array($criteria) ? $criteria : [], $request->get_param('form_id'), (string) $request->get_param('search'), $request->get_param('columns'));
		if(is_wp_error($post_id)){
			$post_id->add_data(['status' => 400]);
			return $post_id;
		}
		$view = Views::get($post_id);
		return [
			'id' => $post_id,
			'name' => $view ? $view->post_title : '',
			'url' => Page::get_url([], $view),
		];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return bool
	 */
	public static function permission_callback($request){
		return current_user_can(ifcf7_sdt_capability());
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public static function rest_api_init(){
		register_rest_route(self::get_namespace(), '/submissions', [
			'callback' => [__CLASS__, 'submissions'],
			'methods' => 'GET',
			'permission_callback' => [__CLASS__, 'permission_callback'],
		]);
		register_rest_route(self::get_namespace(), '/views', [
			'args' => [
				'name' => [
					'required' => true,
					'type' => 'string',
				],
			],
			'callback' => [__CLASS__, 'insert_view'],
			'methods' => 'POST',
			'permission_callback' => [__CLASS__, 'permission_callback'],
		]);
		register_rest_route(self::get_namespace(), '/views/(?P<id>\d+)', [
			[
				'callback' => [__CLASS__, 'update_view'],
				'methods' => 'POST',
				'permission_callback' => [__CLASS__, 'permission_callback'],
			],
			[
				'callback' => [__CLASS__, 'delete_view'],
				'methods' => 'DELETE',
				'permission_callback' => [__CLASS__, 'permission_callback'],
			],
		]);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array|WP_Error
	 */
	public static function delete_view($request){
		$view = Views::get($request->get_param('id'));
		if(!$view){
			return ifwp_error(__('Invalid post ID.'), ['status' => 404]);
		}
		if(!current_user_can('delete_post', $view->ID)){
			return ifwp_error(__('Sorry, you are not allowed to delete this item.'), ['status' => 403]);
		}
		$post_id = Views::delete($view);
		if(is_wp_error($post_id)){
			$post_id->add_data(['status' => 400]);
			return $post_id;
		}
		return [
			'id' => $post_id,
			'url' => Page::get_url(), // Back to all of them.
		];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array|WP_Error
	 */
	public static function update_view($request){
		$view = Views::get($request->get_param('id'));
		if(!$view){
			return ifwp_error(__('Invalid post ID.'), ['status' => 404]);
		}
		if(!current_user_can('edit_post', $view->ID)){
			return ifwp_error(__('Sorry, you are not allowed to edit this item.'), ['status' => 403]);
		}
		$criteria = $request->get_param('criteria');
		$post_id = Views::update($view, is_array($criteria) ? $criteria : [], $request->get_param('form_id'), (string) $request->get_param('search'), $request->get_param('columns'));
		if(is_wp_error($post_id)){
			$post_id->add_data(['status' => 400]);
			return $post_id;
		}
		return [
			'id' => $post_id,
			'name' => $view->post_title,
			'url' => Page::get_url([], $view),
		];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @see https://datatables.net/manual/server-side
	 *
	 * @return array
	 */
	public static function submissions($request){
		$params = $request->get_params();
		$form_ids = Columns::sanitize_form_ids($request->get_param('form_id'));
		$columns = Columns::get_columns($form_ids);
		$query = Query::query(Query::get_args($params, $form_ids));
		Columns::prime($query->posts, $columns);
		$data = [];
		foreach($query->posts as $post){
			$data[] = Columns::get_row($post, $columns, 'display');
		}
		$draw = absint($request->get_param('draw'));
		$response = [
			'data' => $data,
			'draw' => $draw ? $draw : 1,
			'recordsFiltered' => (int) $query->found_posts,
			'recordsTotal' => Query::get_total($form_ids),
		];
		if($draw <= 1){
			// The value dropdowns of SearchBuilder. Only worth sending once: it does keep them.
			$response['searchBuilder'] = [
				'options' => (object) Columns::get_options($form_ids),
			];
		}
		return $response;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
