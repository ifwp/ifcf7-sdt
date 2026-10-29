<?php namespace IFCF7_SDT;

final class Query {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	public static function ifwp_plugins_loaded(){
		add_filter('posts_groupby', [__CLASS__, 'posts_groupby'], 10, 2);
		add_filter('posts_join', [__CLASS__, 'posts_join'], 10, 2);
		add_filter('posts_orderby', [__CLASS__, 'posts_orderby'], 10, 2);
		add_filter('posts_where', [__CLASS__, 'posts_where'], 10, 2);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// private
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	private static $totals = [];

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function cast($value = '', $type = 'string'){
		if('num' === $type){
			return 'CAST(' . $value . ' AS DECIMAL(20,6))';
		}
		if('date' === $type){
			return 'CAST(' . $value . ' AS DATE)';
		}
		return $value;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Turns a normalized operator into a SQL fragment plus its (still unprepared) arguments.
	 *
	 * @return array
	 */
	private static function compare_sql($left = '', $node = []){
		$op = $node['op'];
		$values = $node['values'];
		$value1 = isset($values[0]) ? $values[0] : '';
		$value2 = isset($values[1]) ? $values[1] : '';
		global $wpdb;
		switch($op){
			case 'EMPTY':
				return [
					'args' => [],
					'sql' => '(' . $left . ' IS NULL OR ' . $left . ' = \'\')',
				];
			case 'NOT EMPTY':
				return [
					'args' => [],
					'sql' => '(' . $left . ' IS NOT NULL AND ' . $left . ' <> \'\')',
				];
			case 'LIKE':
			case 'NOT LIKE':
				return [
					'args' => ['%' . $wpdb->esc_like($value1) . '%'],
					'sql' => $left . ' ' . $op . ' %s',
				];
			case 'STARTS':
			case 'NOT STARTS':
				return [
					'args' => [$wpdb->esc_like($value1) . '%'],
					'sql' => $left . ' ' . ('STARTS' === $op ? 'LIKE' : 'NOT LIKE') . ' %s',
				];
			case 'ENDS':
			case 'NOT ENDS':
				return [
					'args' => ['%' . $wpdb->esc_like($value1)],
					'sql' => $left . ' ' . ('ENDS' === $op ? 'LIKE' : 'NOT LIKE') . ' %s',
				];
			case 'BETWEEN':
			case 'NOT BETWEEN':
				return [
					'args' => [$value1, $value2],
					'sql' => $left . ' ' . $op . ' %s AND %s',
				];
			case 'IN':
			case 'NOT IN':
				if(!$values){
					return []; // Never an IN () of its own, which is not even valid SQL.
				}
				return [
					'args' => $values,
					'sql' => $left . ' ' . $op . ' (' . implode(', ', array_fill(0, count($values), '%s')) . ')',
				];
			case '=':
			case '!=':
			case '<':
			case '<=':
			case '>':
			case '>=':
				return [
					'args' => [$value1],
					'sql' => $left . ' ' . ('!=' === $op ? '<>' : $op) . ' %s',
				];
		}
		return [];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	private static function dispatch($node = [], &$parts = []){
		$sources = self::get_sources($node);
		if(1 === count($sources)){
			$source = reset($sources);
			// Metadata goes to meta_query, unless asked to go the EXISTS way below: no joins there,
			// so no rows to deduplicate and, more often than not, a faster query.
			if('meta' === $source and apply_filters('ifcf7_sdt_meta_query', true)){
				$clause = self::to_meta_query($node);
				if($clause){
					$parts['meta_query'][] = $clause;
				}
				return;
			}
			if('date' === $source){
				$clause = self::to_date_query($node);
				if($clause){
					$parts['date_query'][] = $clause;
				}
				return;
			}
		}
		if(self::is_group($node) and 'AND' === $node['relation']){
			foreach($node['children'] as $child){
				self::dispatch($child, $parts); // Recursive.
			}
			return;
		}
		// A single source, or an OR group mixing post fields with metadata: plain SQL is the only exact answer.
		$sql = self::to_sql($node);
		if($sql){
			$parts['sql'][] = $sql;
		}
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function get_end_of_day($node = [], $index = 0){
		$raw = isset($node['raw'][$index]) ? $node['raw'][$index] : '';
		return self::parse_date($raw, true, ('post' === $node['column']['source']));
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string[]
	 */
	private static function get_keys($node = []){
		if(self::is_group($node)){
			$keys = [];
			foreach($node['children'] as $child){
				$keys = array_merge($keys, self::get_keys($child)); // Recursive.
			}
			return array_values(array_unique($keys));
		}
		return [$node['column']['source'] . ':' . $node['column']['key']];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string[]
	 */
	private static function get_sources($node = []){
		if(self::is_group($node)){
			$sources = [];
			foreach($node['children'] as $child){
				$sources = array_merge($sources, self::get_sources($child)); // Recursive.
			}
			return array_values(array_unique($sources));
		}
		return [$node['source']];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return bool
	 */
	private static function is_group($node = []){
		return (is_array($node) and isset($node['children'], $node['relation']));
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function leaf_to_sql($node = []){
		global $wpdb;
		$column = $node['column'];
		$type = $column['type'];
		if('meta' === $column['source']){
			$left = self::cast('ifcf7_sdt_meta.meta_value', $type);
			if('EMPTY' === $node['op']){ // No row at all, or an empty one.
				return $wpdb->prepare("NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS ifcf7_sdt_meta WHERE ifcf7_sdt_meta.post_id = {$wpdb->posts}.ID AND ifcf7_sdt_meta.meta_key = %s AND ifcf7_sdt_meta.meta_value <> '')", $column['key']);
			}
			if('NOT EMPTY' === $node['op']){
				return $wpdb->prepare("EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS ifcf7_sdt_meta WHERE ifcf7_sdt_meta.post_id = {$wpdb->posts}.ID AND ifcf7_sdt_meta.meta_key = %s AND ifcf7_sdt_meta.meta_value <> '')", $column['key']);
			}
			$compare = self::compare_sql($left, $node);
			if(!$compare){
				return '';
			}
			$sql = "EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS ifcf7_sdt_meta WHERE ifcf7_sdt_meta.post_id = {$wpdb->posts}.ID AND ifcf7_sdt_meta.meta_key = %s AND (" . $compare['sql'] . '))';
			return $wpdb->prepare($sql, array_merge([$column['key']], $compare['args']));
		}
		if(!in_array($column['key'], self::get_post_columns(), true)){
			return '';
		}
		$left = self::cast($wpdb->posts . '.' . $column['key'], ('date' === $type ? 'string' : $type)); // Date columns are already DATETIME.
		if('date' === $type and in_array($node['op'], ['=', '!='], true)){
			$start = isset($node['values'][0]) ? $node['values'][0] : '';
			$end = self::get_end_of_day($node, 0);
			if($start and $end){ // A date does mean the whole day, not a single second.
				return '(' . $wpdb->prepare($left . ('!=' === $node['op'] ? ' NOT' : '') . ' BETWEEN %s AND %s', [$start, $end]) . ')';
			}
		}
		$compare = self::compare_sql($left, $node);
		if(!$compare){
			return '';
		}
		if(!$compare['args']){
			return '(' . $compare['sql'] . ')';
		}
		return '(' . $wpdb->prepare($compare['sql'], $compare['args']) . ')';
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array|null
	 */
	private static function normalize($group = [], $form_ids = []){
		if(!is_array($group)){
			return null;
		}
		if(isset($group['criteria']) and is_array($group['criteria'])){
			$relation = (isset($group['logic']) and 'OR' === strtoupper($group['logic'])) ? 'OR' : 'AND';
			$children = [];
			foreach($group['criteria'] as $criteria){
				$child = self::normalize($criteria, $form_ids); // Recursive.
				if($child){
					$children[] = $child;
				}
			}
			if(!$children){
				return null;
			}
			if(1 === count($children)){
				return $children[0];
			}
			return [
				'children' => $children,
				'relation' => $relation,
			];
		}
		return self::normalize_leaf($group, $form_ids);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @see https://datatables.net/reference/type/SearchBuilder.Criteria
	 *
	 * @return array|null
	 */
	private static function normalize_leaf($leaf = [], $form_ids = []){
		if(!is_array($leaf) or !isset($leaf['condition'])){
			return null;
		}
		$alias = '';
		foreach(['origData', 'dataOrig', 'data'] as $key){ // origData is what SearchBuilder sends, the rest are fallbacks.
			if(isset($leaf[$key]) and '' !== $leaf[$key]){
				$alias = $leaf[$key];
				break;
			}
		}
		$column = Columns::get_column($alias, $form_ids);
		if(!$column or !$column['filterable']){
			return null;
		}
		$op = self::parse_condition($leaf['condition'], $column);
		if(!$op){
			return null;
		}
		$value = isset($leaf['value']) ? (array) $leaf['value'] : [];
		$value1 = isset($leaf['value1']) ? $leaf['value1'] : (isset($value[0]) ? $value[0] : '');
		$value2 = isset($leaf['value2']) ? $leaf['value2'] : (isset($value[1]) ? $value[1] : '');
		$raw = [];
		$values = [];
		foreach([$value1, $value2] as $index => $single){
			$single = is_array($single) ? reset($single) : $single;
			$single = trim(wp_strip_all_tags((string) $single));
			if(is_callable($column['value_callback'])){
				$single = call_user_func($column['value_callback'], $single, $column);
			}
			$raw[$index] = $single;
			if('date' === $column['type']){
				// A plain date does mean the whole day: "> May 1st" ends it, ">= May 1st" opens it.
				$end = ($index or in_array($op, ['>', '<='], true));
				$single = self::parse_date($single, $end, ('post' === $column['source']));
			}
			$values[$index] = $single;
		}
		$required = self::parse_required_values($op);
		for($index = 0; $index < $required; $index ++){
			if(!isset($values[$index]) or '' === $values[$index]){
				return null; // Not enough data to filter anything out.
			}
		}
		// A column storing a reference (the contact form is an id, the label is its title) does
		// resolve the text into the stored values matching it, and is filtered by those instead.
		// Only the operators about text: a range or an empty check still means the stored value.
		if(is_callable($column['lookup_callback']) and in_array($op, ['=', '!=', 'LIKE', 'NOT LIKE', 'STARTS', 'NOT STARTS', 'ENDS', 'NOT ENDS'], true)){
			$matches = call_user_func($column['lookup_callback'], isset($values[0]) ? $values[0] : '', $op, $column);
			if(is_array($matches)){
				$op = ('!=' === $op or 0 === strpos($op, 'NOT ')) ? 'NOT IN' : 'IN';
				// Nothing did match: a stored value no row does carry, so IN ('0') keeps no row and
				// NOT IN ('0') keeps them all, which is what a text matching no form does mean.
				$values = $matches ? array_values($matches) : ['0'];
			}
		}
		$source = $column['source'];
		if('date' === $column['type'] and 'post' === $source and in_array($column['key'], self::get_date_columns(), true) and in_array($op, ['=', '<', '<=', '>', '>=', 'BETWEEN'], true)){
			$source = 'date'; // Everything WP_Date_Query is able to express by itself.
		}
		return [
			'column' => $column,
			'op' => $op,
			'raw' => $raw,
			'source' => $source,
			'values' => $values,
		];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function parse_condition($condition = '', $column = []){
		$is_array = ('array' === $column['type']);
		switch($condition){
			case '=':
			case 'equals':
			case 'has':
				return '=';
			case '!=':
			case '!equals':
				return '!=';
			case 'contains':
				return $is_array ? '=' : 'LIKE'; // Array values are stored one meta row each.
			case '!contains':
			case 'without':
				return $is_array ? '!=' : 'NOT LIKE';
			case 'starts':
				return 'STARTS';
			case '!starts':
				return 'NOT STARTS';
			case 'ends':
				return 'ENDS';
			case '!ends':
				return 'NOT ENDS';
			case '<':
			case 'lt':
			case 'before':
				return '<';
			case '<=':
			case 'lte':
				return '<=';
			case '>':
			case 'gt':
			case 'after':
				return '>';
			case '>=':
			case 'gte':
				return '>=';
			case 'between':
				return 'BETWEEN';
			case '!between':
				return 'NOT BETWEEN';
			case 'null':
			case 'empty':
				return 'EMPTY';
			case '!null':
			case '!empty':
				return 'NOT EMPTY';
		}
		return '';
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function parse_date($value = '', $end = false, $datetime = true){
		$value = trim((string) $value);
		if(!$value){
			return '';
		}
		if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)){
			if(!$datetime){
				return $value; // Date fields are stored as plain dates.
			}
			return $value . ($end ? ' 23:59:59' : ' 00:00:00');
		}
		$timestamp = strtotime($value);
		if(false === $timestamp){
			return '';
		}
		if(!$datetime){
			return date('Y-m-d', $timestamp);
		}
		return date('Y-m-d H:i:s', $timestamp);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return int
	 */
	private static function parse_required_values($op = ''){
		if(in_array($op, ['EMPTY', 'NOT EMPTY'], true)){
			return 0;
		}
		if(in_array($op, ['BETWEEN', 'NOT BETWEEN'], true)){
			return 2;
		}
		return 1;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string[]
	 */
	private static function parse_search_terms($search = ''){
		$terms = [];
		if(!preg_match_all('/".*?("|$)|((?<=[\t ",+])|^)[^\t ",+]+/', $search, $matches)){
			return [trim($search)];
		}
		foreach($matches[0] as $term){
			if(preg_match('/^".+"$/', $term)){
				$term = trim($term, '"\'');
			} else {
				$term = trim($term, '"\' ');
			}
			if(!$term or 1 === strlen($term)){
				continue;
			}
			$terms[] = $term;
		}
		if(!$terms or count($terms) > 9){
			return [trim($search)]; // Too many terms: match the whole sentence instead.
		}
		return $terms;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function regexp_quote($value = ''){
		return preg_quote((string) $value);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array|null
	 */
	private static function to_date_query($node = []){
		if(self::is_group($node)){
			$clauses = [
				'relation' => $node['relation'],
			];
			foreach($node['children'] as $child){
				$clause = self::to_date_query($child); // Recursive.
				if($clause){
					$clauses[] = $clause;
				}
			}
			return (count($clauses) > 1) ? $clauses : null;
		}
		$column = $node['column']['key'];
		$values = $node['values'];
		$start = isset($values[0]) ? $values[0] : ''; // Already the end of the day for > and <=.
		switch($node['op']){
			case '=':
				return [
					'after' => $start,
					'before' => self::get_end_of_day($node, 0),
					'column' => $column,
					'inclusive' => true,
				];
			case '>':
				return [
					'after' => $start,
					'column' => $column,
					'inclusive' => false,
				];
			case '>=':
				return [
					'after' => $start,
					'column' => $column,
					'inclusive' => true,
				];
			case '<':
				return [
					'before' => $start,
					'column' => $column,
					'inclusive' => false,
				];
			case '<=':
				return [
					'before' => $start,
					'column' => $column,
					'inclusive' => true,
				];
			case 'BETWEEN':
				return [
					'after' => $start,
					'before' => isset($values[1]) ? $values[1] : '',
					'column' => $column,
					'inclusive' => true,
				];
		}
		return null;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array|null
	 */
	private static function to_meta_query($node = []){
		if(self::is_group($node)){
			$clauses = [
				'relation' => $node['relation'],
			];
			foreach($node['children'] as $child){
				$clause = self::to_meta_query($child); // Recursive.
				if($clause){
					$clauses[] = $clause;
				}
			}
			return (count($clauses) > 1) ? $clauses : null;
		}
		$column = $node['column'];
		$key = $column['key'];
		$values = $node['values'];
		$value1 = isset($values[0]) ? $values[0] : '';
		$value2 = isset($values[1]) ? $values[1] : '';
		if('num' === $column['type']){
			$type = 'NUMERIC';
		} elseif('date' === $column['type']){
			$type = 'DATE';
		} else {
			$type = 'CHAR';
		}
		switch($node['op']){
			case 'EMPTY':
				return [
					'relation' => 'OR',
					[
						'compare' => 'NOT EXISTS',
						'key' => $key,
					],
					[
						'compare' => '=',
						'key' => $key,
						'value' => '',
					],
				];
			case 'NOT EMPTY':
				return [
					'compare' => '!=',
					'key' => $key,
					'value' => '',
				];
			case 'LIKE':
			case 'NOT LIKE':
				return [
					'compare' => $node['op'], // WP_Meta_Query wraps the value with wildcards by itself.
					'key' => $key,
					'value' => $value1,
				];
			case 'STARTS':
			case 'NOT STARTS':
				return [
					'compare' => ('STARTS' === $node['op'] ? 'REGEXP' : 'NOT REGEXP'),
					'key' => $key,
					'value' => '^' . self::regexp_quote($value1),
				];
			case 'ENDS':
			case 'NOT ENDS':
				return [
					'compare' => ('ENDS' === $node['op'] ? 'REGEXP' : 'NOT REGEXP'),
					'key' => $key,
					'value' => self::regexp_quote($value1) . '$',
				];
			case 'BETWEEN':
			case 'NOT BETWEEN':
				return [
					'compare' => $node['op'],
					'key' => $key,
					'type' => $type,
					'value' => [$value1, $value2],
				];
			case 'IN':
			case 'NOT IN':
				if(!$values){
					return null;
				}
				return [
					'compare' => $node['op'],
					'key' => $key,
					'type' => $type,
					'value' => array_values($values), // A single join and a list, not a join each.
				];
			case '=':
			case '!=':
			case '<':
			case '<=':
			case '>':
			case '>=':
				return [
					'compare' => $node['op'],
					'key' => $key,
					'type' => $type,
					'value' => $value1,
				];
		}
		return null;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	private static function to_sql($node = []){
		if(self::is_group($node)){
			$parts = [];
			foreach($node['children'] as $child){
				$sql = self::to_sql($child); // Recursive.
				if($sql){
					$parts[] = $sql;
				}
			}
			if(!$parts){
				return '';
			}
			return '(' . implode(' ' . $node['relation'] . ' ', $parts) . ')';
		}
		return self::leaf_to_sql($node);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
	//
	// public
	//
	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	public static function get_args($params = [], $form_ids = []){
		$form_ids = Columns::sanitize_form_ids($form_ids);
		$length = isset($params['length']) ? (int) $params['length'] : 0;
		if($length < 1){
			$length = absint(apply_filters('ifcf7_sdt_max_posts_per_page', 500));
		}
		$args = [
			'ignore_sticky_posts' => true,
			'offset' => isset($params['start']) ? max(0, (int) $params['start']) : 0,
			'order' => 'DESC',
			'orderby' => 'ID',
			'post_status' => Columns::get_post_statuses(),
			'post_type' => Columns::get_post_types(),
			'posts_per_page' => $length,
			'suppress_filters' => false,
			'update_post_term_cache' => false,
		];
		$meta_query = [];
		if($form_ids){
			$meta_query[] = [
				'compare' => 'IN',
				'key' => 'original_contact_form_id',
				'value' => $form_ids,
			];
		}
		$where = [];
		$groups = self::get_criteria($params);
		if($groups){
			$parts = self::parse_criteria($groups, $form_ids);
			if($parts['meta_query']){
				// A meta key holding several values would join several rows of the very same post.
				$args['ifcf7_sdt_dedupe'] = true;
				$meta_query = array_merge($meta_query, $parts['meta_query']);
			}
			$where = array_merge($where, $parts['sql']);
			if($parts['date_query']){
				$args['date_query'] = $parts['date_query'];
			}
			if($parts['post_status']){
				$args['post_status'] = array_keys(get_post_stati()); // The criteria itself narrows the status down.
			}
		}
		$search = '';
		// Gated here rather than in the front end alone: a stale saved state, a view stored while
		// the box was on, or a handcrafted request would otherwise filter the table by a text
		// nobody can see, let alone clear.
		if(ifcf7_sdt_search() and isset($params['search']['value'])){
			$search = trim((string) $params['search']['value']);
		}
		if($search){
			$sql = self::get_search_sql($search, $form_ids);
			if($sql){
				$where[] = $sql;
			}
		}
		if($meta_query){
			$args['meta_query'] = $meta_query;
		}
		if($where){
			$args['ifcf7_sdt_where'] = implode(' AND ', $where);
		}
		$orderby = self::get_orderby($params, $form_ids);
		if($orderby){
			$args['ifcf7_sdt_orderby'] = $orderby;
		}
		return apply_filters('ifcf7_sdt_query_args', $args, $params, $form_ids);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Every criteria group of the request: one per search builder, or the plain SearchBuilder payload.
	 *
	 * @return array
	 */
	public static function get_criteria($params = []){
		$groups = [];
		if(isset($params['ifcf7_sdt_criteria']) and is_array($params['ifcf7_sdt_criteria']) and $params['ifcf7_sdt_criteria']){
			foreach($params['ifcf7_sdt_criteria'] as $group){
				if(is_array($group) and $group){
					$groups[] = $group;
				}
			}
			return $groups;
		}
		if(isset($params['searchBuilder']) and is_array($params['searchBuilder']) and $params['searchBuilder']){
			$groups[] = $params['searchBuilder'];
		}
		return $groups;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string[]
	 */
	public static function get_date_columns(){
		return ['post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt'];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array|null
	 */
	public static function get_orderby($params = [], $form_ids = []){
		if(!isset($params['order'][0]['column'])){
			return null;
		}
		$order = $params['order'][0];
		$index = (int) $order['column'];
		if(!isset($params['columns'][$index]['data'])){
			return null;
		}
		$column = Columns::get_column($params['columns'][$index]['data'], $form_ids);
		if(!$column or !$column['orderable']){
			return null;
		}
		$dir = (isset($order['dir']) and 'asc' === strtolower($order['dir'])) ? 'ASC' : 'DESC';
		if('post' === $column['source']){
			if(!in_array($column['key'], self::get_post_columns(), true)){
				return null;
			}
			return [
				'dir' => $dir,
				'field' => $column['key'],
				'source' => 'post',
			];
		}
		return [
			'dir' => $dir,
			'key' => $column['key'],
			'source' => 'meta',
			'type' => $column['type'],
		];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string[]
	 */
	public static function get_post_columns(){
		return ['ID', 'comment_count', 'comment_status', 'menu_order', 'ping_status', 'post_author', 'post_content', 'post_date', 'post_date_gmt', 'post_excerpt', 'post_mime_type', 'post_modified', 'post_modified_gmt', 'post_name', 'post_parent', 'post_status', 'post_title', 'post_type'];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * The global search box: post fields and every searchable meta key at once.
	 *
	 * @return string
	 */
	public static function get_search_sql($search = '', $form_ids = []){
		global $wpdb;
		$meta_keys = [];
		$post_fields = [];
		foreach(Columns::get_columns($form_ids) as $column){
			if(!$column['searchable']){
				continue;
			}
			if('meta' === $column['source']){
				$meta_keys[] = $column['key'];
			} elseif(in_array($column['key'], ['post_content', 'post_excerpt', 'post_name', 'post_title'], true)){
				$post_fields[] = $column['key'];
			}
		}
		$meta_keys = array_values(array_unique($meta_keys));
		$post_fields = array_values(array_unique($post_fields));
		if(!$meta_keys and !$post_fields){
			return '';
		}
		$parts = [];
		foreach(self::parse_search_terms($search) as $term){
			$exclude = (0 === strpos($term, '-'));
			if($exclude){
				$term = substr($term, 1);
			}
			if(!$term){
				continue;
			}
			$like = '%' . $wpdb->esc_like($term) . '%';
			$part = [];
			foreach($post_fields as $post_field){
				$part[] = $wpdb->prepare("{$wpdb->posts}.{$post_field} LIKE %s", $like);
			}
			if($meta_keys){
				$placeholders = implode(', ', array_fill(0, count($meta_keys), '%s'));
				$part[] = $wpdb->prepare("EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS ifcf7_sdt_search WHERE ifcf7_sdt_search.post_id = {$wpdb->posts}.ID AND ifcf7_sdt_search.meta_key IN ({$placeholders}) AND ifcf7_sdt_search.meta_value LIKE %s)", array_merge($meta_keys, [$like]));
			}
			if(!$part){
				continue;
			}
			$parts[] = ($exclude ? 'NOT ' : '') . '(' . implode(' OR ', $part) . ')';
		}
		if(!$parts){
			return '';
		}
		return '(' . implode(' AND ', $parts) . ')';
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return int
	 */
	public static function get_total($form_ids = []){
		$form_ids = Columns::sanitize_form_ids($form_ids);
		$signature = $form_ids ? implode(',', $form_ids) : 'all';
		if(isset(self::$totals[$signature])){
			return self::$totals[$signature];
		}
		global $wpdb;
		$post_statuses = Columns::get_post_statuses();
		$post_types = Columns::get_post_types();
		$args = array_merge($post_types, $post_statuses);
		$sql = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$wpdb->posts}.post_type IN (" . implode(', ', array_fill(0, count($post_types), '%s')) . ") AND {$wpdb->posts}.post_status IN (" . implode(', ', array_fill(0, count($post_statuses), '%s')) . ')';
		if($form_ids){
			$sql .= " AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS ifcf7_sdt_meta WHERE ifcf7_sdt_meta.post_id = {$wpdb->posts}.ID AND ifcf7_sdt_meta.meta_key = 'original_contact_form_id' AND ifcf7_sdt_meta.meta_value IN (" . implode(', ', array_fill(0, count($form_ids), '%d')) . '))';
			$args = array_merge($args, $form_ids);
		}
		self::$totals[$signature] = (int) $wpdb->get_var($wpdb->prepare($sql, $args));
		return self::$totals[$signature];
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Splits one or more SearchBuilder trees into what WP_Query is able to run natively.
	 *
	 * @return array
	 */
	public static function parse_criteria($groups = [], $form_ids = []){
		$parts = [
			'date_query' => [],
			'meta_query' => [],
			'post_status' => false,
			'sql' => [],
		];
		foreach($groups as $group){
			if(!is_array($group) or !$group){
				continue;
			}
			$tree = self::normalize($group, $form_ids);
			if(!$tree){
				continue;
			}
			if(in_array('post:post_status', self::get_keys($tree), true)){
				$parts['post_status'] = true;
			}
			self::dispatch($tree, $parts); // Every group narrows the previous ones down.
		}
		if(count($parts['date_query']) > 1){
			$parts['date_query'] = array_merge(['relation' => 'AND'], $parts['date_query']);
		} elseif($parts['date_query']){
			$parts['date_query'] = reset($parts['date_query']);
		}
		return $parts;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	public static function parse_search_builder($search_builder = [], $form_ids = []){
		return self::parse_criteria([$search_builder], $form_ids);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * Deduplicating by grouping on the primary key, never with DISTINCT: the latter is applied to
	 * wp_posts.*, and comparing a LONGTEXT column (post_content) forces MySQL into an on disk
	 * temporary table. Alongside SQL_CALC_FOUND_ROWS that is enough to eat a whole PHP worker.
	 *
	 * @return string
	 */
	public static function posts_groupby($groupby, $query){
		$orderby = $query->get('ifcf7_sdt_orderby');
		$ordering_by_meta = (is_array($orderby) and 'meta' === $orderby['source']);
		if(!$query->get('ifcf7_sdt_dedupe') and !$ordering_by_meta){
			return $groupby;
		}
		global $wpdb;
		return $wpdb->posts . '.ID'; // One row per post, no matter how many rows the joins bring in.
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function posts_join($join, $query){
		$orderby = $query->get('ifcf7_sdt_orderby');
		if(!is_array($orderby) or 'meta' !== $orderby['source']){
			return $join;
		}
		global $wpdb;
		// LEFT JOIN, so ordering by a meta key does not hide the posts missing it.
		return $join . $wpdb->prepare(" LEFT JOIN {$wpdb->postmeta} AS ifcf7_sdt_orderby ON ({$wpdb->posts}.ID = ifcf7_sdt_orderby.post_id AND ifcf7_sdt_orderby.meta_key = %s)", $orderby['key']);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function posts_orderby($orderby_sql, $query){
		$orderby = $query->get('ifcf7_sdt_orderby');
		if(!is_array($orderby) or !$orderby){
			return $orderby_sql;
		}
		global $wpdb;
		$dir = ('ASC' === $orderby['dir']) ? 'ASC' : 'DESC';
		if('post' === $orderby['source']){
			if(!in_array($orderby['field'], self::get_post_columns(), true)){
				return $orderby_sql;
			}
			return $wpdb->posts . '.' . $orderby['field'] . ' ' . $dir . ', ' . $wpdb->posts . '.ID ' . $dir;
		}
		$value = self::cast('ifcf7_sdt_orderby.meta_value', $orderby['type']);
		return 'MIN(' . $value . ') ' . $dir . ', ' . $wpdb->posts . '.ID ' . $dir;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return string
	 */
	public static function posts_where($where, $query){
		$sql = $query->get('ifcf7_sdt_where');
		if(!$sql or !is_string($sql)){
			return $where;
		}
		return $where . ' AND ' . $sql;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return WP_Query
	 */
	public static function query($args = []){
		$query = new \WP_Query;
		$query->query($args);
		return $query;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
