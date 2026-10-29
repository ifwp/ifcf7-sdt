<?php namespace IFCF7_SDT;

final class Page {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	public static function ifwp_plugins_loaded(){
		add_action('admin_init', [__CLASS__, 'admin_init']);
		add_action('admin_menu', [__CLASS__, 'admin_menu'], 20);
		add_action('init', [__CLASS__, 'init']);
		add_action('template_redirect', [__CLASS__, 'template_redirect'], 0);
		add_action('wp_enqueue_scripts', [__CLASS__, 'wp_enqueue_scripts']);
		add_filter('query_vars', [__CLASS__, 'query_vars']);
		add_filter('script_loader_tag', [__CLASS__, 'script_loader_tag'], 10, 2);
		add_filter('style_loader_tag', [__CLASS__, 'style_loader_tag'], 10, 2);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// private
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	const QUERY_VAR = 'ifcf7_sdt';
	const VIEW_QUERY_VAR = 'ifcf7_sdt_view';

	private static $enqueued = false, $tables = 0, $view = false;

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Third party libraries, straight from their CDN and with their own integrity hashes.
	 *
	 * @return array
	 */
	private static function get_assets(){
		$datatables = 'https://cdn.datatables.net/v/bs4/jszip-3.10.1/dt-2.3.6/b-3.2.6/b-colvis-3.2.6/b-html5-3.2.6/b-print-3.2.6/date-1.6.3/sb-1.8.4/datatables.min.';
		$assets = [
			'styles' => [
				'ifcf7-sdt-bootstrap' => [
					'deps' => [],
					'integrity' => 'sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N',
					'src' => 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css',
				],
				'ifcf7-sdt-fontawesome' => [
					'deps' => [],
					'integrity' => 'sha256-MVopmdyC2tYTiJ8wlktf0uh0v4NgT+vNdyVFepi7Q0c=',
					'src' => 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.2.0/css/all.min.css',
				],
				'ifcf7-sdt-datatables' => [
					'deps' => [],
					'integrity' => 'sha384-hMm+6J9H0TQo+N9u9Sl8LWBInxd3EgnAW1+UlKc0kYwRw0+yC12AtlNg7zmT/Kp5',
					'src' => $datatables . 'css',
				],
			],
			'scripts' => [
				'ifcf7-sdt-bootstrap' => [
					'deps' => ['jquery'],
					'integrity' => 'sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct',
					'src' => 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js',
				],
				'ifcf7-sdt-datatables' => [
					'deps' => ['jquery'],
					'integrity' => 'sha384-6mgKsm9UW9wE0XuIvr+MDNHRVrxPxeZVsUf+7SoZbWpZp2wmNAdn0YSAjVG37Bw2',
					'src' => $datatables . 'js',
				],
			],
		];
		if(ifcf7_sdt_select2()){
			// The very handles IFCF7 does use, so neither of us loads Select2 twice.
			$assets['styles']['select2'] = [
				'deps' => [],
				'integrity' => 'sha384-KZO2FRYNmIHerhfYMjCIUaJeGBRXP7CN24SiNSG+wdDzgwvxWbl16wMVtWiJTcMt',
				'src' => 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css',
			];
			$assets['styles']['select2-theme-bootstrap4'] = [
				'deps' => ['select2'],
				'integrity' => 'sha384-mB+6PD+9eK4GUAS2hXvG6wWU5huvieD5h/iPZ2mioFEpXLjsb7hb0aWTypi9xe0s',
				'src' => 'https://cdn.jsdelivr.net/npm/select2-theme-bootstrap4@1.0.1/dist/select2-bootstrap.min.css',
			];
			$assets['scripts']['select2'] = [
				'deps' => ['jquery'],
				'integrity' => 'sha384-HCjW0//bc6Gu9bS3OISjenLhzVqjRipLVVj9LZtzKu+FYXXOZVCN7WDv2TYxCfmo',
				'src' => 'https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.full.min.js',
			];
		}
		return apply_filters('ifcf7_sdt_assets', $assets);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function get_column_class($column = []){
		$class = 'text-nowrap ifcf7-sdt-group-' . $column['group'];
		if(!$column['filterable']){
			$class .= ' ifcf7-sdt-no-filter';
		}
		return $class;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * SearchBuilder can not render optgroups: it reads the selected column with
	 * children('option:selected'), which does not see options nested in one. Prefixing the label
	 * of every option with its section, and keeping the sections together, is the way to group them.
	 *
	 * @return string
	 */
	private static function get_search_builder_title($column = [], $sections = []){
		// DataTables sets the option with text(), so entities have to be decoded beforehand.
		$title = html_entity_decode(wp_strip_all_tags($column['label']), ENT_QUOTES, get_bloginfo('charset'));
		$section = Columns::get_section_label($column['section']);
		if($section and isset($sections[$column['group']]) and count($sections[$column['group']]) > 1){
			$title = $section . ' ' . apply_filters('ifcf7_sdt_section_separator', '·') . ' ' . $title;
		}
		return apply_filters('ifcf7_sdt_search_builder_title', $title, $column, $sections);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * One search builder per group of columns, in this very order.
	 *
	 * @return array
	 */
	private static function get_colvis_groups($form_ids = []){
		if(!apply_filters('ifcf7_sdt_colvis_groups', true)){
			return [];
		}
		$groups = [];
		foreach(Columns::get_columns($form_ids) as $column){
			$groups[$column['group']] = true; // Every column, filterable or not.
		}
		$colvis_groups = [];
		foreach(self::get_search_builder_groups() as $group => $label){
			$group = sanitize_html_class($group);
			if(empty($groups[$group])){
				continue;
			}
			$colvis_groups[] = [
				'group' => $group,
				'title' => $label,
			];
			unset($groups[$group]);
		}
		foreach(array_keys($groups) as $group){ // Whatever group a filter did introduce.
			$colvis_groups[] = [
				'group' => $group,
				'title' => $group,
			];
		}
		if(count($colvis_groups) < 2){
			return []; // One group only: grouping would just add a click.
		}
		return $colvis_groups;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	private static function get_search_builder_groups_defaults(){
		$groups = [];
		// One panel per section, sharing their very labels: ifcf7_sdt_sections does retitle both.
		foreach(['post', 'form', 'tags', 'utm', 'submission', 'meta'] as $section){
			$groups[$section] = Columns::get_section_label($section);
		}
		return $groups;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	private static function get_search_builder_groups(){
		return apply_filters('ifcf7_sdt_search_builder_groups', self::get_search_builder_groups_defaults());
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	private static function get_search_builders($form_ids = []){
		$groups = [];
		foreach(Columns::get_columns($form_ids) as $column){
			if(!$column['filterable']){
				continue;
			}
			$groups[$column['group']] = true;
		}
		$search_builders = [];
		foreach(self::get_search_builder_groups() as $group => $label){
			$group = sanitize_html_class($group);
			if(empty($groups[$group])){
				continue; // Nothing to filter in there.
			}
			$search_builders[] = [
				'group' => $group,
				'selector' => '.ifcf7-sdt-group-' . $group . ':not(.ifcf7-sdt-no-filter)',
				'title' => [
					0 => '<span class="font-weight-bold">' . $label . '</span>',
					'_' => '<span class="font-weight-bold">' . $label . '</span> <span class="text-secondary">(%d)</span>',
				],
			];
		}
		return $search_builders;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function get_integrity($handle = '', $type = 'styles'){
		$assets = self::get_assets();
		if(isset($assets[$type][$handle]['integrity'])){
			return $assets[$type][$handle]['integrity'];
		}
		return '';
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * DataTables ships one translation file per locale, WordPress does not use the same names.
	 *
	 * @return string
	 */
	private static function get_language_url(){
		$locale = str_replace('_', '-', determine_locale());
		$locales = [
			'af', 'ar', 'az-AZ', 'bg', 'bn', 'bs-BA', 'ca', 'co', 'cs', 'cy', 'da', 'de-CH', 'de-DE', 'el', 'en-GB', 'es-AR', 'es-CL', 'es-CO', 'es-ES', 'es-MX', 'et', 'eu', 'fa', 'fi', 'fr-FR', 'ga', 'gl', 'gu', 'he', 'hi', 'hr', 'hu', 'hy', 'id', 'is', 'it-IT', 'ja', 'ka', 'kk', 'km', 'ko', 'ku', 'ky', 'lt', 'lv', 'mk', 'mn', 'ms', 'ne', 'nl-NL', 'no-NB', 'no-NO', 'pl', 'ps', 'pt-BR', 'pt-PT', 'ro', 'ru', 'si', 'sk', 'sl', 'sq', 'sr', 'sr-SP', 'sv-SE', 'sw', 'ta', 'te', 'tg', 'th', 'tr', 'uk', 'ur', 'uz', 'uz-CY', 'vi', 'zh', 'zh-HANT',
		];
		$fallbacks = [
			'de' => 'de-DE',
			'en' => '',
			'es' => 'es-ES',
			'fr' => 'fr-FR',
			'it' => 'it-IT',
			'nl' => 'nl-NL',
			'no' => 'no-NB',
			'pt' => 'pt-PT',
			'sv' => 'sv-SE',
		];
		if(!in_array($locale, $locales, true)){
			$language = strtolower(substr($locale, 0, 2));
			if(isset($fallbacks[$language])){
				$locale = $fallbacks[$language];
			} elseif(in_array($language, $locales, true)){
				$locale = $language;
			} else {
				$locale = '';
			}
		}
		$url = $locale ? '//cdn.datatables.net/plug-ins/2.3.6/i18n/' . $locale . '.json' : '';
		return apply_filters('ifcf7_sdt_language_url', $url, $locale);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * WordPress does translate all of these already, no need for a text domain of our own.
	 *
	 * @return array
	 */
	private static function get_settings(){
		global $wp_locale;
		$view = self::get_current_view();
		return apply_filters('ifcf7_sdt_settings', [
			'export' => [
				'confirm' => __('Are you sure you want to do this?') . ' ' . ifwp_first_p(__('Be sure to save this in a safe location. You will not be able to retrieve it.')),
				// Off unless the ifcf7_sdt_export hook does turn it on. No nonce handed out either.
				'enabled' => ifcf7_sdt_can_export(),
				'nonce' => ifcf7_sdt_can_export() ? Export::get_nonce() : '',
				'text' => __('Download personal data'),
				'url' => self::get_url(),
				'waiting' => __('Downloading data...'),
			],
			'language' => [
				'decimal' => $wp_locale->number_format['decimal_point'],
				'emptyTable' => __('No results found.'),
				'info' => '<span class="font-weight-bold">' . _x('Posts', 'post type general name') . '</span> <span class="text-secondary">(_TOTAL_)</span>',
				'infoEmpty' => '<span class="font-weight-bold">' . _x('Posts', 'post type general name') . '</span>',
				'lengthMenu' => __('Number of items per page:') . ' _MENU_',
				'processing' => '<div class="my-3">' . __('Loading more results... please wait.') . '</div>',
				'search' => __('Search Posts') . ':',
				// No searchBuilder.title in here: each search builder does carry its own one.
				'thousands' => $wp_locale->number_format['thousands_sep'],
				'url' => self::get_language_url(),
			],
			// Picking a contact form does show its fields and hide the ones of the other forms. On
			// false that becomes a button of its own instead.
			'autoSync' => (bool) apply_filters('ifcf7_sdt_auto_sync', true),
			// Spreads the (many) columns over several ones instead of a single long dropdown. An
			// empty value brings the plain dropdown back.
			'colvisLayout' => apply_filters('ifcf7_sdt_colvis_layout', 'fixed columns'),
			'nonce' => wp_create_nonce('wp_rest'),
			// The search box over every column at once, off unless the ifcf7_sdt_search hook does
			// turn it on. The search builders do not depend on it.
			'search' => ifcf7_sdt_search(),
			'select2' => ifcf7_sdt_select2(),
			// The search builders in a sliding sidebar, behind a single button.
			'sidebar' => (bool) apply_filters('ifcf7_sdt_sidebar', true),
			'text' => [
				'cancel' => __('Cancel'),
				'close' => __('Close'),
				'columns' => __('Columns'),
				'filters' => __('Filter'),
				'filtersTitle' => __('Filter items list'),
				'name' => __('Name'),
				'save' => __('Save'),
				'sync' => 'Sync columns',
			],
			'url' => Rest::get_url(),
			'views' => [
				'archive' => self::get_url(), // Where a deleted view does leave you.
				'confirm' => __('Are you sure you want to do this?'),
				'create' => current_user_can(ifcf7_sdt_capability()),
				// Urls when the current view may be overwritten or deleted, empty otherwise.
				'delete' => ($view and current_user_can('delete_post', $view->ID)) ? Rest::get_views_url() . '/' . $view->ID : '',
				'deleteText' => __('Delete'),
				// Why saving is off while no search builder does hold a single criteria.
				'noFilters' => 'A view needs at least one filter',
				'save' => __('Save'),
				'saveAs' => 'Save as',
				'text' => 'Create view',
				'update' => ($view and current_user_can('edit_post', $view->ID)) ? Rest::get_views_url() . '/' . $view->ID : '',
				'url' => Rest::get_views_url(),
			],
		]);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	private static function get_table_settings($form_ids = [], $id = ''){
		$columns = [];
		$order = [0, 'desc'];
		$index = 0;
		$sections = [];
		foreach(Columns::get_columns($form_ids) as $column){
			if(!$column['filterable'] or !$column['section']){
				continue;
			}
			$sections[$column['group']][$column['section']] = true;
		}
		$form_column = '';
		$form_titles = [];
		foreach(Columns::get_contact_forms($form_ids) as $contact_form){
			$form_titles[(string) $contact_form->id()] = $contact_form->title();
		}
		$view = self::get_current_view();
		// A view does define which columns it shows: its own list wins over the defaults.
		$view_columns = $view ? Views::get_columns($view) : [];
		foreach(Columns::get_columns($form_ids) as $alias => $column){
			if('ID' === $column['key'] and 'post' === $column['source']){
				$order = [$index, 'desc'];
			}
			if('original_contact_form_id' === $column['key']){
				$form_column = $alias;
			}
			$settings = [
				'className' => self::get_column_class($column),
				'data' => $alias,
				'name' => $column['key'],
				'orderable' => $column['orderable'],
				// The dropdown of the search builder does read this one instead of the title.
				'searchBuilderTitle' => self::get_search_builder_title($column, $sections),
				'searchable' => $column['searchable'],
				'title' => $column['label'],
				'type' => $column['type'],
				'visible' => $view_columns ? in_array($alias, $view_columns, true) : $column['visible'],
			];
			if($column['form_ids']){ // Only the form tags carry these.
				$settings['formIds'] = $column['form_ids'];
			}
			$columns[] = $settings;
			$index ++;
		}
		return [
			'columns' => $columns,
			// One column visibility list per group, empty for a single list with all of them.
			'colvisGroups' => self::get_colvis_groups($form_ids),
			// A saved view does preload its criteria, one group per search builder.
			'criteria' => $view ? Columns::migrate_criteria(Views::get_criteria($view), $form_ids) : [],
			// The column holding the contact form, and every form there is: what the sync button
			// needs to tell which fields do belong to the forms being filtered by.
			'formColumn' => $form_column,
			'formIds' => array_map('strval', array_keys(Columns::get_contact_forms($form_ids))),
			// The criteria of that column do carry titles now, so the browser needs the very same
			// list to tell which forms a criteria does mean. An object, always: ids as keys.
			'formTitles' => (object) $form_titles,
			'form_id' => implode(',', Columns::sanitize_form_ids($form_ids)),
			'id' => $id,
			'isView' => $view ? true : false,
			// With columns of its own, the saved state must not put its own back.
			'viewColumns' => $view_columns ? true : false,
			'order' => [$order],
			'pageLength' => absint(apply_filters('ifcf7_sdt_page_length', 25)),
			'search' => $view ? Views::get_search($view) : '',
			'searchBuilders' => self::get_search_builders($form_ids),
			// The saved state maps the visible columns by index: it has to be dropped whenever the
			// set of columns, or their order, does change.
			'signature' => substr(md5(implode(',', wp_list_pluck($columns, 'data'))), 0, 8),
			'stateSave' => (bool) apply_filters('ifcf7_sdt_state_save', true), // Remembers the visible columns.
		];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string[]
	 */
	private static function print_assets($type = 'styles', $bootstrap = true){
		$assets = self::get_assets();
		$handles = array_keys($assets[$type]);
		if(!$bootstrap){
			$handles = array_diff($handles, ['ifcf7-sdt-bootstrap']);
		}
		$handles[] = 'ifcf7-sdt';
		if('styles' === $type){
			wp_print_styles($handles);
		} else {
			wp_print_scripts($handles);
		}
		return $handles;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// public
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public static function admin_init(){
		if(!get_option('permalink_structure')){
			return; // Plain permalinks: the ?ifcf7_sdt=1 query var is enough.
		}
		if(!get_option('ifcf7_sdt_flush_rewrite_rules')){
			$rules = get_option('rewrite_rules');
			if(is_array($rules) and !array_diff_key(self::get_rules(), $rules)){
				return; // Every rule of ours is already there.
			}
		}
		delete_option('ifcf7_sdt_flush_rewrite_rules');
		flush_rewrite_rules(false);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public static function admin_menu(){
		if(!current_user_can(ifcf7_sdt_capability())){
			return;
		}
		// A submenu slug that is an url does render as a plain link.
		add_submenu_page(ifcf7_sdt_menu_slug(), 'DataTables', 'DataTables', ifcf7_sdt_capability(), self::get_url());
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public static function enqueue($bootstrap = true){
		$assets = self::get_assets();
		foreach($assets['styles'] as $handle => $asset){
			if(!$bootstrap and 'ifcf7-sdt-bootstrap' === $handle){
				continue; // The theme is expected to load Bootstrap by itself.
			}
			wp_enqueue_style($handle, $asset['src'], $asset['deps'], null);
		}
		foreach($assets['scripts'] as $handle => $asset){
			if(!$bootstrap and 'ifcf7-sdt-bootstrap' === $handle){
				continue;
			}
			wp_enqueue_script($handle, $asset['src'], $asset['deps'], null, true);
		}
		ifwp_enqueue_asset('ifcf7-sdt', 'datatables.css', ['ifcf7-sdt-datatables']);
		ifwp_enqueue_asset('ifcf7-sdt', 'datatables.js', ['ifcf7-sdt-datatables']);
		if(!self::$enqueued){
			self::$enqueued = true;
			wp_localize_script('ifcf7-sdt', 'ifcf7_sdt_settings', self::get_settings());
		}
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return int[]
	 */
	public static function get_current_form_ids(){
		$view = self::get_current_view();
		if($view){
			$form_ids = Views::get_form_ids($view); // A view does remember its own scope.
			if($form_ids){
				return $form_ids;
			}
		}
		$form_ids = isset($_GET['form_id']) ? wp_unslash($_GET['form_id']) : [];
		return Columns::sanitize_form_ids($form_ids);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return WP_Post|null
	 */
	public static function get_current_view(){
		if(false !== self::$view){
			return self::$view;
		}
		self::$view = Views::get(get_query_var(self::VIEW_QUERY_VAR));
		return self::$view;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function get_regex(){
		return '^' . self::get_slug() . '/?$';
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * The saved views do live under the very same page, one slug each.
	 *
	 * @return array
	 */
	public static function get_rules(){
		return [
			'^' . self::get_slug() . '/([^/]+)/?$' => 'index.php?' . self::QUERY_VAR . '=1&' . self::VIEW_QUERY_VAR . '=$matches[1]',
			self::get_regex() => 'index.php?' . self::QUERY_VAR . '=1',
		];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function get_slug(){
		$slug = apply_filters('ifcf7_sdt_slug', 'ifcf7-submissions');
		return trim(sanitize_title($slug), '/');
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function get_url($args = [], $view = null){
		$view = Views::get($view);
		$slug = '';
		if($view){
			$slug = $view->post_name ? $view->post_name : $view->ID; // Views::get() does accept both.
		}
		if(get_option('permalink_structure')){
			$url = home_url('/' . self::get_slug() . '/' . ($slug ? $slug . '/' : ''));
		} else {
			$url = home_url('/?' . self::QUERY_VAR . '=1');
			if($slug){
				$url = add_query_arg(self::VIEW_QUERY_VAR, $slug, $url);
			}
		}
		if($args){
			$url = add_query_arg($args, $url);
		}
		return $url;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public static function init(){
		foreach(self::get_rules() as $regex => $query){
			add_rewrite_rule($regex, $query, 'top');
		}
		add_shortcode('ifcf7_sdt_table', [__CLASS__, 'shortcode']);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	public static function query_vars($query_vars){
		$query_vars[] = self::QUERY_VAR;
		$query_vars[] = self::VIEW_QUERY_VAR;
		return $query_vars;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public static function render(){
		$form_ids = self::get_current_form_ids();
		$view = self::get_current_view();
		$views = Views::get_views();
		self::enqueue();
		$profile_url = '';
		if(current_user_can('read')){
			$profile_url = get_edit_profile_url(get_current_user_id());
		} elseif(is_multisite()){
			$profile_url = get_dashboard_url(get_current_user_id(), 'profile.php');
		}
		$heading = $view ? $view->post_title : _x('Posts', 'post type general name');
		$title = apply_filters('ifcf7_sdt_title', $heading . ' &rarr; ' . get_bloginfo('name'), $view);
		$goto = trim(str_replace('&larr;', '', sprintf(_x('&larr; Go to %s', 'site'), '<a href="' . esc_url(home_url()) . '">' . get_bloginfo('name') . '</a>')));
		nocache_headers();
		?><!doctype html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo('charset'); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta name="robots" content="noindex, nofollow">
		<title><?php echo esc_html(wp_strip_all_tags($title)); ?></title>
		<?php
		self::print_assets('styles');
		do_action('ifcf7_sdt_head');
		?>
	</head>
	<body class="ifcf7-sdt">
		<nav class="navbar navbar-expand-md navbar-dark bg-dark">
			<a class="navbar-brand" href="<?php echo esc_url(self::get_url()); ?>"><?php echo esc_html(get_bloginfo('name')); ?></a>
			<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#ifcf7-sdt-navbar" aria-controls="ifcf7-sdt-navbar" aria-expanded="false" aria-label="<?php echo esc_attr__('Toggle panel'); ?>">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="ifcf7-sdt-navbar">
				<div class="navbar-nav mr-auto">
					<a class="nav-link" href="<?php echo esc_url(admin_url()); ?>"><i class="d-none d-md-inline fa-solid fa-gauge-high"></i> <?php echo esc_html__('Dashboard'); ?></a>
					<?php
					if($views){
						?>
						<div class="nav-item dropdown">
							<a class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-expanded="false"><i class="d-none d-md-inline fa-regular fa-bookmark"></i> <?php echo esc_html($heading); ?></a>
							<div class="dropdown-menu">
								<a class="dropdown-item<?php echo $view ? '' : ' active'; ?>" href="<?php echo esc_url(self::get_url()); ?>"><?php echo esc_html(_x('Posts', 'post type general name')); ?></a>
								<div class="dropdown-divider"></div>
								<?php
								foreach($views as $single){
									?><a class="dropdown-item<?php echo ($view and $view->ID === $single->ID) ? ' active' : ''; ?>" href="<?php echo esc_url(self::get_url([], $single)); ?>"><?php echo esc_html($single->post_title); ?></a><?php
								}
								?>
							</div>
						</div>
						<?php
					}
					?>
				</div>
				<ul class="navbar-nav">
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-expanded="false">
							<span class="d-none d-md-inline"><?php echo esc_html(trim(sprintf(__('Howdy, %s'), ''))); ?> </span><?php echo esc_html(wp_get_current_user()->display_name); ?> <i class="d-none d-md-inline fa-regular fa-user"></i>
						</a>
						<ul class="dropdown-menu dropdown-menu-right">
							<?php
							if($profile_url){
								?><li><a class="dropdown-item" href="<?php echo esc_url($profile_url); ?>"><?php echo esc_html__('Edit Profile'); ?></a></li><?php
							}
							?>
							<li><a class="dropdown-item" href="<?php echo esc_url(wp_logout_url()); ?>"><?php echo esc_html__('Log Out'); ?></a></li>
						</ul>
					</li>
				</ul>
			</div>
		</nav>
		<div class="container-fluid">
			<div class="my-3">
				<?php
				echo self::render_table($form_ids);
				do_action('ifcf7_sdt_after_table', $form_ids);
				?>
				<div class="text-secondary text-center"><?php echo wp_kses_post($goto); ?></div>
			</div>
		</div>
		<?php
		self::print_assets('scripts');
		do_action('ifcf7_sdt_footer');
		?>
	</body>
</html><?php
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function render_table($form_ids = []){
		self::$tables ++;
		$id = 'ifcf7-sdt-table-' . self::$tables;
		$columns = Columns::get_columns($form_ids);
		if(!$columns){
			return '<div class="alert alert-info" role="alert">' . __('No fields found.') . '</div>';
		}
		wp_add_inline_script('ifcf7-sdt', 'ifcf7_sdt.add(' . wp_json_encode(self::get_table_settings($form_ids, $id)) . ');');
		$html = '<table id="' . esc_attr($id) . '" class="table table-striped table-bordered table-hover" style="width: 100%;">';
		$html .= '<thead>';
		$html .= '<tr>';
		foreach($columns as $column){
			$html .= '<th class="' . esc_attr(self::get_column_class($column)) . '">' . esc_html($column['label']) . '</th>';
		}
		$html .= '</tr>';
		$html .= '</thead>';
		$html .= '</table>';
		return $html;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function script_loader_tag($tag, $handle){
		$integrity = self::get_integrity($handle, 'scripts');
		if(!$integrity){
			return $tag;
		}
		return str_replace(' src=', ' integrity="' . esc_attr($integrity) . '" crossorigin="anonymous" src=', $tag);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function shortcode($atts = [], $content = ''){
		$atts = shortcode_atts([
			'bootstrap' => 0,
			'form_id' => '',
		], $atts, 'ifcf7_sdt_table');
		if(!current_user_can(ifcf7_sdt_capability())){
			return '<div class="alert alert-danger" role="alert">' . __('Sorry, you are not allowed to view this item.') . ' ' . __('You need a higher level of permission.') . '</div>';
		}
		$bootstrap = ifwp_seems_true($atts['bootstrap']);
		self::enqueue($bootstrap);
		$html = self::render_table(Columns::sanitize_form_ids($atts['form_id']));
		if(did_action('wp_head')){
			ob_start(); // Page builders do render their content once the head is already gone.
			self::print_assets('styles', $bootstrap);
			$html = ob_get_clean() . $html;
		}
		return $html;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function style_loader_tag($tag, $handle){
		$integrity = self::get_integrity($handle, 'styles');
		if(!$integrity){
			return $tag;
		}
		return str_replace(' href=', ' integrity="' . esc_attr($integrity) . '" crossorigin="anonymous" href=', $tag);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public static function template_redirect(){
		if(!get_query_var(self::QUERY_VAR)){
			return;
		}
		// Every request, saved views included, goes through the very same checks.
		if(!is_user_logged_in()){
			auth_redirect();
		}
		if(!current_user_can(ifcf7_sdt_capability())){
			wp_die(__('Sorry, you are not allowed to view this item.'), __('You need a higher level of permission.'), 403);
		}
		if(get_query_var(self::VIEW_QUERY_VAR) and !self::get_current_view()){
			wp_die(__('Invalid post ID.'), '', ['response' => 404]);
		}
		ifcf7_sdt_load_admin_textdomain();
		Export::maybe_export();
		self::render();
		die;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public static function wp_enqueue_scripts(){
		global $post;
		if(!$post or is_archive()){
			return;
		}
		if(!has_shortcode($post->post_content, 'ifcf7_sdt_table')){
			return;
		}
		if(!current_user_can(ifcf7_sdt_capability())){
			return;
		}
		self::enqueue(false);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
