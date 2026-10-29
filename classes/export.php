<?php namespace IFCF7_SDT;

final class Export {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// private
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	const NONCE = 'ifcf7_sdt_export';

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Exported files hold personal data: keep the folder clean.
	 *
	 * @return void
	 */
	private static function clean_up($dir = ''){
		$files = glob(trailingslashit($dir) . 'ifcf7-sdt-*.xlsx');
		if(!$files){
			return;
		}
		$expiration = time() - absint(apply_filters('ifcf7_sdt_export_expiration', HOUR_IN_SECONDS));
		foreach($files as $file){
			if(filemtime($file) < $expiration){
				wp_delete_file($file);
			}
		}
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	private static function get_columns($form_ids = []){
		$columns = Columns::get_columns($form_ids);
		$aliases = isset($_POST['ifcf7_sdt_columns']) ? explode(',', sanitize_text_field(wp_unslash($_POST['ifcf7_sdt_columns']))) : [];
		$aliases = array_filter(array_map('trim', $aliases));
		if(!$aliases){
			return $columns;
		}
		$visible = array_intersect_key($columns, array_flip($aliases));
		return $visible ? $visible : $columns;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	private static function get_params(){
		$params = [];
		if(isset($_POST['ifcf7_sdt_criteria'])){
			$criteria = json_decode(wp_unslash($_POST['ifcf7_sdt_criteria']), true);
			if(JSON_ERROR_NONE === json_last_error() and is_array($criteria)){
				if(isset($criteria['criteria']) or isset($criteria['logic'])){
					$criteria = [$criteria]; // A single group, the way DataTables does return it.
				}
				$params['ifcf7_sdt_criteria'] = $criteria;
			}
		}
		if(isset($_POST['ifcf7_sdt_search'])){
			$params['search'] = [
				'value' => sanitize_text_field(wp_unslash($_POST['ifcf7_sdt_search'])),
			];
		}
		return $params;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// public
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function get_nonce(){
		return wp_create_nonce(self::NONCE);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return bool
	 */
	public static function is_export(){
		return (isset($_POST['ifcf7_sdt_action']) and 'export' === $_POST['ifcf7_sdt_action']);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public static function maybe_export(){
		if(!self::is_export()){
			return;
		}
		// Hiding the button is no protection at all: the request is checked on its own.
		if(!ifcf7_sdt_can_export()){
			wp_die(__('Sorry, you are not allowed to view this item.'), __('You need a higher level of permission.'), ['response' => 403]);
		}
		$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
		if(!wp_verify_nonce($nonce, self::NONCE)){
			wp_die(__('Are you sure you want to do this?') . ' ' . __('Please try again.'), '', ['response' => 403]);
		}
		$writer = ifwp_xlsx();
		if(is_wp_error($writer)){
			wp_die($writer);
		}
		$dir = ifwp_download_dir();
		if(is_wp_error($dir)){
			wp_die($dir);
		}
		self::clean_up($dir);
		wp_raise_memory_limit('admin');
		if(function_exists('set_time_limit')){
			@set_time_limit(0);
		}
		$form_ids = Columns::sanitize_form_ids(isset($_POST['ifcf7_sdt_form_id']) ? sanitize_text_field(wp_unslash($_POST['ifcf7_sdt_form_id'])) : '');
		$columns = self::get_columns($form_ids);
		$params = self::get_params();
		$sheet = apply_filters('ifcf7_sdt_export_sheet', _x('Posts', 'post type general name'));
		$writer->writeSheetRow($sheet, array_values(wp_list_pluck($columns, 'label')));
		$chunk = absint(apply_filters('ifcf7_sdt_export_chunk', 500));
		$max = absint(apply_filters('ifcf7_sdt_export_max_posts', 50000));
		$offset = 0;
		while($offset < $max){
			$params['length'] = min($chunk, $max - $offset);
			$params['start'] = $offset;
			$args = Query::get_args($params, $form_ids);
			$args['no_found_rows'] = true;
			$query = Query::query($args);
			if(!$query->posts){
				break;
			}
			Columns::prime($query->posts, $columns);
			foreach($query->posts as $post){
				$writer->writeSheetRow($sheet, array_values(Columns::get_row($post, $columns, 'export')));
			}
			$offset += count($query->posts);
			if(count($query->posts) < $params['length']){
				break;
			}
			if(function_exists('wp_cache_flush_runtime')){
				wp_cache_flush_runtime(); // Long exports would eat the whole memory otherwise.
			}
		}
		$filename = 'ifcf7-sdt-' . gmdate('Ymd-His') . '-' . wp_generate_password(12, false) . '.xlsx';
		$file = trailingslashit($dir) . $filename;
		$writer->writeToFile($file);
		$url = ifwp_download_url($file);
		if(is_wp_error($url)){
			wp_die($url);
		}
		wp_redirect($url);
		die;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
