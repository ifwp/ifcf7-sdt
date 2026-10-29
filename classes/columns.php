<?php namespace IFCF7_SDT;

final class Columns {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	public static function ifwp_plugins_loaded(){
		add_action('ifcf7_store_submission', [__CLASS__, 'delete_transients']);
		add_action('save_post_' . \WPCF7_ContactForm::post_type, [__CLASS__, 'delete_transients']);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// private
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	const AUTHORS = 'ifcf7_sdt_authors';
	const TRANSIENT = 'ifcf7_sdt_meta_keys';

	private static $authors = null, $columns = [], $contact_forms = null, $meta_keys = null, $post_statuses = null, $post_types = null;

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * The meta keys IFCF7 stores by itself (Helper::get_meta_data() and its "original_" copies).
	 *
	 * @return array
	 */
	private static function get_helper_fields(){
		$fields = [
			'contact_form_id' => ['label' => 'Contact form ID', 'section' => 'form'],
			'contact_form_locale' => ['label' => 'Contact form locale', 'section' => 'form'],
			'contact_form_name' => ['label' => 'Contact form name', 'section' => 'form'],
			'contact_form_title' => ['label' => 'Contact form title', 'section' => 'form'],
			'container_post_id' => ['label' => 'Page ID', 'section' => 'submission'],
			'current_user_id' => ['label' => 'User ID', 'section' => 'submission'],
			'remote_ip' => ['label' => 'IP', 'section' => 'submission'],
			'remote_port' => ['label' => 'Port', 'section' => 'submission'],
			'submission_response' => ['label' => 'Response', 'section' => 'submission'],
			'submission_status' => ['label' => 'Status', 'section' => 'submission'],
			'timestamp' => ['label' => 'Timestamp', 'section' => 'submission'],
			'unit_tag' => ['label' => 'Unit tag', 'section' => 'submission'],
			'url' => ['label' => 'URL', 'section' => 'submission'],
			'user_agent' => ['label' => 'User agent', 'section' => 'submission'],
		];
		$helper_fields = [];
		foreach($fields as $key => $field){
			$helper_fields[$key] = $field;
			$helper_fields['original_' . $key] = [
				'label' => $field['label'] . ' (' . __('Original') . ')',
				'section' => $field['section'],
			];
		}
		return apply_filters('ifcf7_sdt_helper_fields', $helper_fields);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	private static function get_meta_columns($keys = [], $section = '', $helper_fields = [], $filterable = true){
		$columns = [];
		foreach($keys as $key){
			$columns[] = [
				'filterable' => $filterable,
				'key' => $key,
				'label' => isset($helper_fields[$key]['label']) ? $helper_fields[$key]['label'] : $key,
				'section' => $section,
			];
		}
		return $columns;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function get_tag_label($tag = null){
		$label = $tag->name;
		// _ifcf7_placeholder_value() gives the first value of a select away as its placeholder for
		// both first_as_label and include_blank, but only the former does turn that value into a
		// label: with include_blank it stays a plain option, and CF7 prepends an empty one.
		$placeholder = ('select' !== $tag->basetype or $tag->has_option('first_as_label'));
		if($placeholder and function_exists('ifcf7_placeholder_value')){
			$label = ifcf7_placeholder_value($tag, $label);
		}
		if(function_exists('ifcf7_content_label_value')){
			$label = ifcf7_content_label_value($tag, $label);
		}
		$label = trim(wp_strip_all_tags((string) $label));
		// A label of dashes or symbols alone (the "---" of an include_blank, or the prompt of a
		// first_as_label) does name nothing at all: the field itself does a better job. Only a
		// definite 0 falls back, so a pattern that could not run does leave the label alone.
		if(!$label or 0 === preg_match('/[\p{L}\p{N}]/u', $label)){
			$label = $tag->name;
		}
		return $label;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Form tags are the only place where the data type is really known.
	 *
	 * @return string
	 */
	private static function get_tag_type($tag = null){
		if(wpcf7_form_tag_supports($tag->type, 'selectable-values')){
			return 'string'; // Every stored value is matched on its own row, so plain string conditions do apply.
		}
		switch($tag->basetype){
			case 'date':
				return 'date';
			case 'number':
			case 'range':
				return 'num';
		}
		return 'string';
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	private static function get_post_fields(){
		$post_fields = [
			'ID' => [
				'label' => __('ID'),
				'type' => 'num',
				'visible' => true,
			],
			'post_date' => [
				'label' => __('Date'),
				'type' => 'date',
				'visible' => true,
			],
			'post_date_gmt' => [
				'label' => __('Date') . ' (GMT)',
				'type' => 'date',
			],
			'post_modified' => [
				'label' => __('Last Modified'),
				'type' => 'date',
			],
			'post_modified_gmt' => [
				'label' => __('Last Modified') . ' (GMT)',
				'type' => 'date',
			],
			'post_title' => [
				'label' => __('Title'),
				'type' => 'string',
			],
			'post_name' => [
				'label' => __('Slug'),
				'type' => 'string',
			],
			'post_status' => [
				'label' => __('Status'),
				'type' => 'string',
				'value_callback' => [__CLASS__, 'sanitize_post_status_value'],
			],
			'post_author' => [
				'label' => __('Author'),
				'type' => 'num',
				'value_callback' => [__CLASS__, 'sanitize_post_author_value'],
			],
			'post_content' => [
				'label' => __('Content'),
				'type' => 'string',
			],
			'post_excerpt' => [
				'label' => __('Excerpt'),
				'type' => 'string',
			],
			'post_parent' => [
				'label' => __('Parent'),
				'type' => 'num',
			],
			'menu_order' => [
				'label' => __('Order'),
				'type' => 'num',
			],
			'comment_count' => [
				'label' => __('Comments'),
				'type' => 'num',
			],
		];
		return apply_filters('ifcf7_sdt_post_fields', $post_fields);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	private static function get_utm_fields(){
		$utm_fields = [];
		if(!function_exists('ifwp_get_utm_params')){
			return $utm_fields;
		}
		foreach(ifwp_get_utm_params(false) as $key => $label){
			$utm_fields['utm_' . $key] = 'UTM ' . $label;
		}
		return $utm_fields;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * The values SearchBuilder offers as a dropdown for the "equals" and "not equals" conditions.
	 *
	 * @return array
	 */
	private static function get_column_options($column = [], $form_ids = []){
		$options = [];
		$tag = $column['tag'];
		if($tag and wpcf7_form_tag_supports($tag->type, 'selectable-values')){
			// What IFCF7 stores under the name of the tag is the label the user did see: the value
			// before the pipe (Pipes::fix_selectable_values returns $value_orig) and the label of
			// the data option (Data_Options::fix_selectable_values returns $label). Whatever sits
			// behind either one goes to raw_{name} instead. And $tag->values is already the list
			// of "befores" whenever pipes are in use.
			foreach((array) $tag->values as $value){
				$options[] = [
					'label' => $value,
					'value' => $value,
				];
			}
			// Values that are not written in the tag itself: posts, terms, users, catalogues...
			$data = $tag->get_data_option();
			if(is_array($data)){
				$max = absint(apply_filters('ifcf7_sdt_max_data_options', 1000));
				if(count($data) > $max){
					// Never a truncated list: it would look complete, and quietly hide whatever
					// did not fit. A plain text field is the honest fallback.
					return [];
				}
				foreach($data as $label){
					if(!is_scalar($label)){
						continue;
					}
					$options[] = [
						'label' => $label,
						'value' => $label,
					];
				}
			}
			return self::sanitize_options($options);
		}
		if('post' === $column['source']){
			switch($column['key']){
				case 'post_status':
					foreach(get_post_statuses() as $status => $label){
						$options[] = [
							'label' => $label,
							'value' => $status,
						];
					}
					break;
				case 'post_author':
					foreach(self::get_authors() as $id => $login){
						$options[] = [
							'label' => $login,
							'value' => (string) $id,
						];
					}
					break;
			}
			return self::sanitize_options($options);
		}
		$key = preg_replace('/^original_/', '', $column['key']);
		if(!in_array($key, ['contact_form_id', 'contact_form_locale', 'contact_form_name', 'contact_form_title'], true)){
			return [];
		}
		foreach(self::get_contact_forms($form_ids) as $contact_form){
			switch($key){
				case 'contact_form_locale':
					$options[] = [
						'label' => $contact_form->locale(),
						'value' => $contact_form->locale(),
					];
					break;
				case 'contact_form_name':
					$options[] = [
						'label' => $contact_form->name(),
						'value' => $contact_form->name(),
					];
					break;
				// The id column is filtered by title as well: same options, and several forms
				// sharing one title do collapse into the single one they are told apart by.
				case 'contact_form_id':
				case 'contact_form_title':
					$options[] = [
						'label' => $contact_form->title(),
						'value' => $contact_form->title(),
					];
					break;
			}
		}
		return self::sanitize_options($options);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	private static function sanitize_options($options = []){
		$sanitized = [];
		$values = [];
		foreach($options as $option){
			$value = isset($option['value']) ? (string) $option['value'] : '';
			if('' === $value or isset($values[$value])){
				continue;
			}
			$values[$value] = true;
			$label = isset($option['label']) ? (string) $option['label'] : '';
			$sanitized[] = [
				'label' => $label ? $label : $value,
				'value' => $value,
			];
		}
		return $sanitized;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function parse_alias($key = '', $aliases = []){
		$alias = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $key);
		if(!$alias or is_numeric($alias)){
			$alias = 'ifcf7_sdt_' . $alias;
		}
		$unique = $alias;
		$suffix = 2;
		while(isset($aliases[$unique])){
			$unique = $alias . '_' . $suffix;
			$suffix ++;
		}
		return $unique;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	private static function parse_column($column = [], $aliases = []){
		$column = wp_parse_args($column, [
			'filterable' => true,
			'form_ids' => [], // The contact forms defining the field, for the form tags.
			'group' => '',
			'key' => '',
			'label' => '',
			// Columns storing a reference: turns the text being filtered by into the stored values
			// matching it. See lookup_contact_forms().
			'lookup_callback' => null,
			'orderable' => true,
			'render_callback' => null,
			'searchable' => true,
			'section' => '',
			'source' => 'meta',
			'tag' => null,
			'type' => 'string',
			'value_callback' => null,
			'visible' => false,
		]);
		if(!$column['key']){
			return [];
		}
		if(!in_array($column['source'], ['meta', 'post'], true)){
			$column['source'] = 'meta';
		}
		if(!in_array($column['type'], ['array', 'date', 'num', 'string'], true)){
			$column['type'] = 'string';
		}
		if(!$column['label']){
			$column['label'] = $column['key'];
		}
		if(!$column['group']){
			// One search builder per section, and per source for whatever has no section of its own.
			if($column['section']){
				$column['group'] = $column['section'];
			} else {
				$column['group'] = ('post' === $column['source']) ? 'post' : 'submission';
			}
		}
		$column['group'] = sanitize_html_class($column['group']);
		$column['alias'] = self::parse_alias($column['key'], $aliases);
		$column['filterable'] = (bool) $column['filterable'];
		$column['orderable'] = (bool) $column['orderable'];
		$column['searchable'] = (bool) $column['searchable'];
		$column['visible'] = (bool) $column['visible'];
		return $column;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function render_link($url = '', $text = '', $context = 'display'){
		$text = (string) $text;
		if('export' === $context or !$url){
			return esc_html($text);
		}
		return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($text) . '</a>';
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function render_meta($column = [], $post = null, $context = 'display'){
		$key = $column['key'];
		$tag = $column['tag'];
		if($tag and wpcf7_form_tag_supports($tag->type, 'file-uploading')){
			return self::render_uploaded_files($key, $post, $context);
		}
		if(!metadata_exists('post', $post->ID, $key)){
			return self::render_none($context);
		}
		$values = get_post_meta($post->ID, $key, false);
		$values = array_map([__CLASS__, 'stringify'], (array) $values);
		$values = array_filter($values, 'strlen');
		if(!$values){
			return self::render_none($context);
		}
		if(count($values) > 1 or ($tag and wpcf7_form_tag_supports($tag->type, 'selectable-values'))){
			return implode(', ', array_map('esc_html', $values));
		}
		$value = reset($values);
		if($tag and 'textarea' === $tag->basetype){
			return self::render_long_text($value, $post, $context);
		}
		if('export' === $context){
			return $value;
		}
		if($tag and function_exists('ifcf7_format_value')){
			return ifcf7_format_value(esc_html($value), $tag); // Turns emails, phones and urls into links.
		}
		return esc_html($value);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function render_long_text($value = '', $post = null, $context = 'display'){
		$value = (string) $value;
		if('export' === $context){
			return $value;
		}
		$edit_link = get_edit_post_link($post);
		if(!$edit_link){
			return esc_html(wp_trim_words($value, 20));
		}
		return wp_trim_words(esc_html($value), 20, '&hellip; <a href="' . esc_url($edit_link) . '" target="_blank" rel="noopener">' . __('Read more') . '</a>');
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function render_none($context = 'display'){
		if('export' === $context){
			return '';
		}
		// Empty by default: with this many columns a placeholder on every one of them is noise.
		// '<span class="text-muted">' . __('n/a') . '</span>' does bring the old one back.
		return (string) apply_filters('ifcf7_sdt_empty_value', '');
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function render_post($column = [], $post = null, $context = 'display'){
		$key = $column['key'];
		$value = isset($post->{$key}) ? $post->{$key} : '';
		switch($key){
			case 'ID':
				return self::render_link(get_edit_post_link($post), $post->ID, $context);
			case 'post_title':
				return self::render_link(get_edit_post_link($post), $post->post_title, $context);
			case 'post_date':
			case 'post_date_gmt':
			case 'post_modified':
			case 'post_modified_gmt':
				if('0000-00-00 00:00:00' === $value or !$value){
					return self::render_none($context);
				}
				if('export' === $context){
					return $value;
				}
				return esc_html(mysql2date(apply_filters('ifcf7_sdt_date_format', 'Y-m-d H:i'), $value, false));
			case 'post_status':
				$statuses = get_post_statuses();
				if(isset($statuses[$value])){
					return esc_html($statuses[$value]);
				}
				return esc_html($value);
			case 'post_author':
				if(!$value){
					return self::render_none($context);
				}
				$display_name = get_the_author_meta('display_name', $value);
				if(!$display_name){
					return esc_html($value);
				}
				return self::render_link(get_edit_user_link($value), $display_name, $context);
			case 'post_content':
			case 'post_excerpt':
				if(!$value){
					return self::render_none($context);
				}
				return self::render_long_text($value, $post, $context);
		}
		if('' === $value or null === $value){
			return self::render_none($context);
		}
		return esc_html(self::stringify($value));
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function render_uploaded_files($key = '', $post = null, $context = 'display'){
		$attachment_ids = get_post_meta($post->ID, 'uploaded_id_' . $key);
		if(!$attachment_ids){
			$attachment_ids = get_post_meta($post->ID, $key . '_id'); // backcompat
		}
		if(!$attachment_ids){
			return self::render_none($context);
		}
		if('export' === $context){
			$urls = [];
			foreach($attachment_ids as $attachment_id){
				$urls[] = wp_get_attachment_url($attachment_id);
			}
			return implode(', ', array_filter($urls));
		}
		return \IFCF7\Storage::attachments_html('post', $post->ID, $key);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Accents included: strcasecmp() and strtolower() are byte wise, so they do tell "Inscripción"
	 * and "inscripción" apart.
	 *
	 * @return string
	 */
	private static function lowercase($value = ''){
		$value = (string) $value;
		if(function_exists('mb_strtolower')){
			return mb_strtolower($value, 'UTF-8');
		}
		return strtolower($value);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * One criteria group of a saved view, nested ones included.
	 *
	 * @return array
	 */
	private static function migrate_group($details = [], $alias = '', $forms = [], $titles = []){
		if(!is_array($details) or !isset($details['criteria']) or !is_array($details['criteria'])){
			return $details;
		}
		foreach($details['criteria'] as $index => $leaf){
			if(!is_array($leaf)){
				continue;
			}
			if(isset($leaf['criteria'])){
				$details['criteria'][$index] = self::migrate_group($leaf, $alias, $forms, $titles); // Recursive.
				continue;
			}
			$leaf_alias = '';
			foreach(['origData', 'dataOrig'] as $key){
				if(isset($leaf[$key]) and '' !== $leaf[$key]){
					$leaf_alias = $leaf[$key];
					break;
				}
			}
			if($leaf_alias !== $alias or !isset($leaf['condition']) or !in_array($leaf['condition'], ['=', '!='], true)){
				continue;
			}
			if(!isset($leaf['value']) or !is_array($leaf['value'])){
				continue;
			}
			foreach($leaf['value'] as $position => $value){
				$value = trim((string) $value);
				// A title already, and a title is what wins where some form is named "2024".
				if('' === $value or in_array($value, $titles, true)){
					continue;
				}
				$id = absint($value);
				if(!$id or (string) $id !== $value or !isset($forms[$id]) or '' === $forms[$id]->title()){
					continue;
				}
				$details['criteria'][$index]['value'][$position] = $forms[$id]->title();
			}
		}
		return $details;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function stringify($value = ''){
		if(is_array($value) or is_object($value)){
			$value = maybe_serialize($value);
		}
		return (string) $value;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// public
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public static function delete_transients(){
		self::$authors = null;
		self::$meta_keys = null;
		delete_transient(self::AUTHORS);
		delete_transient(self::TRANSIENT);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * The users who did author a submission, and nobody else: a dropdown of every user of the site
	 * would be both huge and mostly useless.
	 *
	 * @return array
	 */
	public static function get_authors(){
		if(is_array(self::$authors)){
			return self::$authors;
		}
		$authors = get_transient(self::AUTHORS);
		if(is_array($authors)){
			self::$authors = $authors;
			return self::$authors;
		}
		global $wpdb;
		$authors = [];
		$post_types = self::get_post_types();
		$limit = absint(apply_filters('ifcf7_sdt_max_authors', 200));
		$placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
		$sql = "SELECT DISTINCT {$wpdb->posts}.post_author FROM {$wpdb->posts} WHERE {$wpdb->posts}.post_type IN ({$placeholders}) AND {$wpdb->posts}.post_author > 0 LIMIT %d";
		$ids = $wpdb->get_col($wpdb->prepare($sql, array_merge($post_types, [$limit + 1]))); // One over, to tell there are more.
		$ids = array_values(array_filter(array_map('absint', (array) $ids)));
		if(count($ids) > $limit){
			$ids = []; // Too many to offer as a list: same reasoning as the data options.
		}
		if($ids){ // An empty include would bring every single user in.
			$users = get_users([
				'fields' => ['ID', 'user_login'],
				'include' => $ids,
				'orderby' => 'user_login',
			]);
			foreach($users as $user){
				$authors[(string) $user->ID] = $user->user_login;
			}
		}
		$authors = apply_filters('ifcf7_sdt_authors', $authors);
		set_transient(self::AUTHORS, $authors, apply_filters('ifcf7_sdt_meta_keys_expiration', DAY_IN_SECONDS));
		self::$authors = $authors;
		return self::$authors;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array|null
	 */
	public static function get_column($alias = '', $form_ids = []){
		$columns = self::get_columns($form_ids);
		if(isset($columns[$alias])){
			return $columns[$alias];
		}
		foreach($columns as $column){ // Fall back to the key and to the label (older SearchBuilder payloads).
			if($alias === $column['key'] or $alias === $column['label']){
				return $column;
			}
		}
		return null;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Every column the table may show, keyed by its (safe and unique) DataTables alias.
	 *
	 * @return array
	 */
	public static function get_columns($form_ids = []){
		$form_ids = self::sanitize_form_ids($form_ids);
		$signature = $form_ids ? implode(',', $form_ids) : 'all';
		if(isset(self::$columns[$signature])){
			return self::$columns[$signature];
		}
		$columns = [];
		foreach(self::get_post_fields() as $key => $post_field){
			$columns[] = array_merge($post_field, [
				'key' => $key,
				'section' => 'post',
				'source' => 'post',
			]);
		}
		$tags = [];
		$tags_forms = [];
		foreach(self::get_contact_forms($form_ids) as $contact_form){
			$form_tags = $contact_form->scan_form_tags([
				'feature' => 'name-attr',
			]);
			foreach($form_tags as $tag){
				// Which forms do define the field is kept even for the ones deduplicated away: it
				// is what lets the table show the fields of the forms being filtered by.
				$tags_forms[$tag->name][] = (string) $contact_form->id();
				if(isset($tags[$tag->name])){
					continue; // The first form defining the field wins.
				}
				$tags[$tag->name] = $tag;
			}
		}
		ksort($tags);
		$helper_fields = self::get_helper_fields();
		$utm_fields = self::get_utm_fields();
		// Anything else already stored (hidden inputs, third party meta, etc), bucketed by section:
		// the dropdown of the search builder does list the columns in this very order.
		$buckets = [];
		foreach(self::get_meta_keys() as $key){
			if(isset($tags[$key]) or isset($utm_fields[$key])){
				continue; // Emitted by their own section.
			}
			if(isset($helper_fields[$key]['section'])){
				$section = $helper_fields[$key]['section'];
			} else {
				// Meta keys nobody around here did declare: added with update_post_meta somewhere
				// in the business logic of the site, and Contact Form 7 never heard of them.
				$section = 'meta';
			}
			$buckets[$section][] = $key;
		}
		$columns[] = [
			'key' => 'original_contact_form_id',
			'label' => __('Contact Form', 'contact-form-7'),
			// What is stored is the id, but what is filtered is the title: the text does resolve
			// into the ids of the forms matching it, and those are what reaches the query. Hence a
			// string, so the conditions are the ones of a text ("contains", "starts with"...).
			'lookup_callback' => [__CLASS__, 'lookup_contact_forms'],
			'render_callback' => [__CLASS__, 'render_contact_form'],
			'section' => 'form',
			'type' => 'string',
			'visible' => true,
		];
		// The id, locale, name and title of the form all do mean the very same form: that one
		// column above is enough to filter by, the rest stay as columns and out of the panel.
		$columns = array_merge($columns, self::get_meta_columns(isset($buckets['form']) ? $buckets['form'] : [], 'form', $helper_fields, false));
		unset($buckets['form']);
		$columns[] = [
			'key' => 'original_container_post_id',
			'label' => __('Page'),
			'render_callback' => [__CLASS__, 'render_container_post'],
			'section' => 'submission',
			'value_callback' => [__CLASS__, 'sanitize_container_post_value'],
			'visible' => true,
		];
		$columns = array_merge($columns, self::get_meta_columns(isset($buckets['submission']) ? $buckets['submission'] : [], 'submission', $helper_fields));
		unset($buckets['submission']);
		$columns = array_merge($columns, self::get_meta_columns(isset($buckets['meta']) ? $buckets['meta'] : [], 'meta', $helper_fields));
		unset($buckets['meta']);
		foreach($buckets as $section => $keys){ // Whatever section the helper fields filter did introduce.
			$columns = array_merge($columns, self::get_meta_columns($keys, $section, $helper_fields));
		}
		foreach($tags as $name => $tag){
			$columns[] = [
				'filterable' => !wpcf7_form_tag_supports($tag->type, 'file-uploading'),
				'form_ids' => array_values(array_unique($tags_forms[$name])),
				'key' => $name,
				'label' => self::get_tag_label($tag),
				'orderable' => !wpcf7_form_tag_supports($tag->type, 'file-uploading'),
				'section' => 'tags',
				'tag' => $tag,
				'type' => self::get_tag_type($tag),
				'visible' => true,
			];
		}
		foreach($utm_fields as $key => $label){ // Hidden inputs: they are stored, but they are not form tags.
			$columns[] = [
				'key' => $key,
				'label' => $label,
				'section' => 'utm',
				'visible' => true,
			];
		}
		$columns = apply_filters('ifcf7_sdt_columns', $columns, $form_ids);
		$parsed = [];
		$keys = [];
		foreach((array) $columns as $column){
			if(!is_array($column) or empty($column['key'])){
				continue;
			}
			$source = (isset($column['source']) and 'post' === $column['source']) ? 'post' : 'meta';
			if(isset($keys[$source . ':' . $column['key']])){
				continue;
			}
			$keys[$source . ':' . $column['key']] = true;
			$column = self::parse_column($column, $parsed);
			if(!$column){
				continue;
			}
			$parsed[$column['alias']] = $column;
		}
		self::$columns[$signature] = $parsed;
		return self::$columns[$signature];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return WPCF7_ContactForm[]
	 */
	public static function get_contact_forms($form_ids = []){
		if(!is_array(self::$contact_forms)){
			self::$contact_forms = [];
			$posts = get_posts([ // Whole posts, so their properties are cached in a single query.
				'order' => 'ASC',
				'orderby' => 'ID',
				'post_status' => 'publish',
				'post_type' => \WPCF7_ContactForm::post_type,
				'posts_per_page' => -1,
			]);
			foreach($posts as $post){
				$contact_form = ifcf7_contact_form($post);
				if(!$contact_form){
					continue;
				}
				self::$contact_forms[$contact_form->id()] = $contact_form;
			}
		}
		$form_ids = self::sanitize_form_ids($form_ids);
		if(!$form_ids){
			return self::$contact_forms;
		}
		return array_intersect_key(self::$contact_forms, array_flip($form_ids));
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Meta keys actually stored by the submissions, so hidden fields do show up too.
	 *
	 * @return string[]
	 */
	public static function get_meta_keys(){
		if(is_array(self::$meta_keys)){
			return self::$meta_keys;
		}
		$meta_keys = get_transient(self::TRANSIENT);
		if(!is_array($meta_keys)){
			global $wpdb;
			$post_types = self::get_post_types();
			$limit = absint(apply_filters('ifcf7_sdt_max_meta_keys', 500));
			$placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
			$sql = "SELECT DISTINCT meta.meta_key FROM {$wpdb->postmeta} AS meta INNER JOIN {$wpdb->posts} AS posts ON (posts.ID = meta.post_id) WHERE posts.post_type IN ({$placeholders}) AND meta.meta_key NOT LIKE %s ORDER BY meta.meta_key ASC LIMIT %d";
			$args = array_merge($post_types, [$wpdb->esc_like('_') . '%', $limit]);
			$meta_keys = array_values(array_filter((array) $wpdb->get_col($wpdb->prepare($sql, $args)), 'strlen'));
			set_transient(self::TRANSIENT, $meta_keys, apply_filters('ifcf7_sdt_meta_keys_expiration', DAY_IN_SECONDS));
		}
		// Metadata of no use in a table of its own: uploaded files are rendered by their own file
		// field, and Akismet does inject a couple of hidden fields on every submission.
		$prefixes = apply_filters('ifcf7_sdt_excluded_prefixes', ['ak_', 'uploaded_error_', 'uploaded_id_']);
		$meta_keys = array_filter($meta_keys, function($meta_key) use($prefixes){
			foreach((array) $prefixes as $prefix){
				if($prefix and 0 === strpos($meta_key, $prefix)){
					return false;
				}
			}
			return true;
		});
		// Both filtered on the way out and never on the way in: what gets cached is the plain
		// discovery, so the hooks do apply right away and are free to depend on anything they like.
		self::$meta_keys = array_values((array) apply_filters('ifcf7_sdt_meta_keys', $meta_keys));
		return self::$meta_keys;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function get_section_label($section = ''){
		$sections = self::get_sections();
		if(isset($sections[$section])){
			return $sections[$section];
		}
		return '';
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Sections do group the options of the search builder dropdown, and columns are ordered so
	 * that every section stays in one piece.
	 *
	 * @return array
	 */
	public static function get_sections(){
		return apply_filters('ifcf7_sdt_sections', [
			'form' => __('Form', 'contact-form-7'),
			'meta' => __('Custom Fields'),
			'post' => _x('Post', 'post type singular name'),
			'submission' => 'Submission',
			'tags' => __('Tags'),
			'utm' => 'UTM',
		]);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @see https://datatables.net/extensions/searchbuilder/ (server side processing)
	 *
	 * @return array
	 */
	public static function get_options($form_ids = []){
		$options = [];
		foreach(self::get_columns($form_ids) as $alias => $column){
			if(!$column['filterable']){
				continue;
			}
			$column_options = self::get_column_options($column, $form_ids);
			if($column_options){
				$options[$alias] = $column_options; // SearchBuilder keys them by columns.data.
			}
		}
		return apply_filters('ifcf7_sdt_options', $options, $form_ids);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string[]
	 */
	public static function get_post_statuses(){
		if(is_array(self::$post_statuses)){
			return self::$post_statuses;
		}
		$post_statuses = ['publish'];
		foreach(self::get_contact_forms() as $contact_form){
			$post_statuses[] = ifcf7_get_post_status($contact_form);
		}
		$post_statuses = apply_filters('ifcf7_sdt_post_statuses', array_values(array_unique(array_filter($post_statuses))));
		self::$post_statuses = array_values(array_intersect((array) $post_statuses, array_keys(get_post_stati())));
		if(!self::$post_statuses){
			self::$post_statuses = ['publish'];
		}
		return self::$post_statuses;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string[]
	 */
	public static function get_post_types(){
		if(is_array(self::$post_types)){
			return self::$post_types;
		}
		$post_types = ['ifcf7-submission'];
		foreach(self::get_contact_forms() as $contact_form){
			if('contact_form' !== ifcf7_action($contact_form)){
				continue; // Only forms storing submissions of their own.
			}
			$post_types[] = ifcf7_get_post_type($contact_form);
		}
		$post_types = apply_filters('ifcf7_sdt_post_types', array_values(array_unique(array_filter($post_types))));
		self::$post_types = array_values(array_filter((array) $post_types, 'post_type_exists'));
		if(!self::$post_types){
			self::$post_types = ['ifcf7-submission'];
		}
		return self::$post_types;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	public static function get_row($post = null, $columns = [], $context = 'display'){
		$row = [];
		foreach($columns as $alias => $column){
			$row[$alias] = self::render($column, $post, $context);
		}
		return $row;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Which contact forms does a text match by title, as the ids the submissions do store. Every
	 * one of them: several forms may carry the very same title, and the query does take a list.
	 *
	 * The id itself is still accepted by "equals" and "not equals": it is what the options of this
	 * column used to be, so the views saved back then do keep working, and typing an id is a fair
	 * thing to try. Whatever operator is not about text (a range, an empty check) never gets here.
	 *
	 * @return string[]
	 */
	public static function lookup_contact_forms($value = '', $op = '=', $column = []){
		$ids = [];
		$value = trim((string) $value);
		if('' === $value){
			return $ids;
		}
		$needle = self::lowercase($value);
		foreach(self::get_contact_forms() as $contact_form){
			$id = (string) $contact_form->id();
			$title = self::lowercase($contact_form->title());
			switch($op){
				case '=':
				case '!=':
					$matches = ($title === $needle or $id === $value);
					break;
				case 'LIKE':
				case 'NOT LIKE':
					$matches = (false !== strpos($title, $needle));
					break;
				case 'STARTS':
				case 'NOT STARTS':
					$matches = (0 === strpos($title, $needle));
					break;
				case 'ENDS':
				case 'NOT ENDS':
					$matches = (substr($title, - strlen($needle)) === $needle);
					break;
				default:
					$matches = false;
					break;
			}
			if($matches){
				$ids[] = $id;
			}
		}
		return $ids;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * A saved view does carry the values of the options it was made with, and the contact form
	 * column used to offer ids where it does offer titles now. Those are rewritten here, so an
	 * older view does show in its dropdown what it is actually filtering by. Nothing is written
	 * back: saving the view again is what makes it permanent.
	 *
	 * @return array
	 */
	public static function migrate_criteria($criteria = [], $form_ids = []){
		if(!is_array($criteria) or !$criteria){
			return $criteria;
		}
		$alias = '';
		foreach(self::get_columns($form_ids) as $key => $column){
			if('original_contact_form_id' === $column['key'] and 'meta' === $column['source']){
				$alias = $key;
				break;
			}
		}
		if(!$alias){
			return $criteria;
		}
		$forms = self::get_contact_forms();
		$titles = [];
		foreach($forms as $contact_form){
			$titles[] = $contact_form->title();
		}
		foreach($criteria as $group => $details){
			$criteria[$group] = self::migrate_group($details, $alias, $forms, $titles);
		}
		return $criteria;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public static function prime($posts = [], $columns = []){
		$post_ids = [];
		foreach(['original_contact_form_id', 'original_container_post_id', 'contact_form_id', 'container_post_id'] as $key){
			foreach($posts as $post){
				$post_id = absint(get_post_meta($post->ID, $key, true));
				if($post_id){
					$post_ids[] = $post_id;
				}
			}
		}
		$post_ids = array_values(array_unique($post_ids));
		if($post_ids){
			_prime_post_caches($post_ids, false, false);
		}
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function render($column = [], $post = null, $context = 'display'){
		if(is_callable($column['render_callback'])){
			$value = call_user_func($column['render_callback'], $column, $post, $context);
		} elseif('post' === $column['source']){
			$value = self::render_post($column, $post, $context);
		} else {
			$value = self::render_meta($column, $post, $context);
		}
		$value = apply_filters('ifcf7_sdt_column_value', $value, $column, $post, $context);
		if('export' === $context){
			return trim(wp_strip_all_tags(html_entity_decode((string) $value, ENT_QUOTES, get_bloginfo('charset'))));
		}
		return (string) $value;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function render_contact_form($column = [], $post = null, $context = 'display'){
		$contact_form_id = absint(get_post_meta($post->ID, $column['key'], true));
		if(!$contact_form_id){
			return self::render_none($context);
		}
		$title = get_the_title($contact_form_id);
		if(!$title){
			return esc_html($contact_form_id);
		}
		$edit_link = add_query_arg([
			'action' => 'edit',
			'post' => $contact_form_id,
		], admin_url('admin.php?page=wpcf7'));
		return self::render_link($edit_link, $title, $context);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function render_container_post($column = [], $post = null, $context = 'display'){
		$container_post_id = absint(get_post_meta($post->ID, $column['key'], true));
		if(!$container_post_id){
			return self::render_none($context);
		}
		$title = get_the_title($container_post_id);
		if(!$title){
			return esc_html($container_post_id);
		}
		return self::render_link(get_permalink($container_post_id), $title, $context);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function sanitize_container_post_value($value = '', $column = []){
		if(is_numeric($value)){
			return $value;
		}
		$page = get_page_by_path($value);
		if($page){
			return (string) $page->ID;
		}
		return $value;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function sanitize_post_author_value($value = '', $column = []){
		if(is_numeric($value)){
			return $value;
		}
		foreach(['login', 'slug', 'email'] as $field){
			$user = get_user_by($field, $value);
			if($user){
				return (string) $user->ID;
			}
		}
		$users = get_users([
			'fields' => 'ID',
			'number' => 1,
			'search' => '*' . $value . '*',
			'search_columns' => ['display_name', 'user_login', 'user_nicename'],
		]);
		if($users){
			return (string) reset($users);
		}
		return $value;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * The column shows labels ("Published"), so accept both the label and the slug.
	 *
	 * @return string
	 */
	public static function sanitize_post_status_value($value = '', $column = []){
		$statuses = get_post_statuses();
		if(isset($statuses[$value])){
			return $value;
		}
		foreach($statuses as $status => $label){
			if(0 === strcasecmp($label, $value)){
				return $status;
			}
		}
		return $value;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return int[]
	 */
	public static function sanitize_form_ids($form_ids = []){
		if(!is_array($form_ids)){
			$form_ids = explode(',', (string) $form_ids);
		}
		$form_ids = array_map('absint', array_filter(array_map('trim', $form_ids), 'is_numeric'));
		return array_values(array_unique(array_filter($form_ids)));
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
