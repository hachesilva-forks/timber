<?php

class TimberTerm extends TimberCore {

	var $taxonomy;
	var $_children;
	var $PostClass = 'TimberPost';
	var $TermClass = 'TimberTerm';

	public static $representation = 'term';

	function __construct($tid = null) {
		if ($tid === null) {
			$tid = $this->get_term_from_query();
		}
		$this->init($tid);
	}

	function __toString(){
		return $this->name;
	}

	private function get_term_from_query() {
		global $wp_query;
		$qo = $wp_query->queried_object;
		return $qo->term_id;
	}

	function init($tid) {
		global $wpdb;
		$term = $this->get_term($tid);
		if (isset($term->id)) {
			$term->ID = $term->id;
		} else if (isset($term->term_id)) {
			$term->ID = $term->term_id;
		} else if (is_string($tid)) {
			//echo 'bad call using '.$tid;
			//TimberHelper::error_log(debug_backtrace());
		}
		if (function_exists('get_fields')) {
			//lets get whatever we can from advanced custom fields;
			//IF you have the wonderful ACF installed
			$searcher = $term->taxonomy . "_" . $term->ID; // save to a specific category
			$fields = array();
			$fds = get_fields($searcher);
			if (is_array($fds)) {
				foreach ($fds as $key => $value) {
					$key = preg_replace('/_/', '', $key, 1);
					$key = str_replace($searcher, '', $key);
					$key = preg_replace('/_/', '', $key, 1);
					$field = get_field($key, $searcher);
					$fields[$key] = $field;
				}
			}
			$this->import($fields);
		}
		$this->import($term);
	}

	function get_term($tid) {
		if (is_object($tid) || is_array($tid)) {
			return $tid;
		}
		$tid = self::get_tid($tid);
		global $wpdb;
		$query = $wpdb->prepare("SELECT * FROM $wpdb->term_taxonomy WHERE term_id = %d LIMIT 1", $tid);
		$tax = $wpdb->get_row($query);
		if (isset($tax) && isset($tax->taxonomy)) {
			if ($tax->taxonomy) {
				$term = get_term($tid, $tax->taxonomy);
				return $term;
			}
		}
		return null;
	}

	function get_tid($tid) {
		global $wpdb;
		if (is_numeric($tid)) {
			return $tid;
		}
		if (gettype($tid) == 'object') {
			$tid = $tid->term_id;
		}
		if (is_numeric($tid)) {
			$query = $wpdb->prepare("SELECT * FROM $wpdb->terms WHERE term_id = %d", $tid);
		} else {
			$query = $wpdb->prepare("SELECT * FROM $wpdb->terms WHERE slug = %s", $tid);
		}
		$result = $wpdb->get_row($query);
		if (isset($result->term_id)) {
			$result->ID = $result->term_id;
			return $result->ID;
		}
		return 0;
	}

	function get_path() {
		$link = $this->get_link();
		$rel = TimberHelper::get_rel_url($link, true);
		return apply_filters('timber_term_path', $rel, $this);
	}

	function get_link() {
		$link = get_term_link($this);
		return apply_filters('timber_term_link', $link, $this);
	}

	public function get_posts($numberposts = 10, $post_type = 'any', $PostClass = '') {
		if (!strlen($PostClass)) {
			$PostClass = $this->PostClass;
		}
		$default_tax_query = array(array(
					'field' => 'id',
					'terms' => $this->ID,
					'taxonomy' => $this->taxonomy,
				));
		if (is_string($numberposts) && strstr($numberposts, '=')){
			$args = $numberposts;
			$new_args = array();
			parse_str($args, $new_args);
			$args = $new_args;
			$args['tax_query'] = $default_tax_query;
			if (!isset($args['post_type'])){
				$args['post_type'] = 'any';
			}
			if (class_exists($post_type)){
				$PostClass = $post_type;
			}
		} else if (is_array($numberposts)) {
			//they sent us an array already baked
			$args = $numberposts;
			if (!isset($args['tax_query'])){
				$args['tax_query'] = $default_tax_query;
			}
			if (class_exists($post_type)){
				$PostClass = $post_type;
			}
			if (!isset($args['post_type'])){
				$args['post_type'] = 'any';
			}
		} else {
			$args = array(
				'numberposts' => $numberposts,
				'tax_query' => $default_tax_query,
				'post_type' => $post_type
			);
		}
		return Timber::get_posts($args, $PostClass);
	}

	public function get_children(){
		if (!isset($this->_children)){
			$children = get_term_children($this->ID, $this->taxonomy);
			foreach($children as &$child){
				$child = new TimberTerm($child);
			}
			$this->_children = $children;
		}
		return $this->_children;
	}

	/* Alias
	====================== */

	public function children(){
		return $this->get_children();
	}

	public function get_url() {
		return $this->get_link();
	}

	public function link(){
		return $this->get_link();
	}

	public function path(){
		return $this->get_path();
	}

	public function posts($numberposts_or_args = 10, $post_type_or_class = 'any', $post_class = ''){
		return $this->get_posts($numberposts_or_args, $post_type_or_class, $post_class);
	}

	public function url(){
		return $this->get_url();
	}

	/* Deprecated
	===================== */

	function get_page($i) {
		return $this->get_path() . '/page/' . $i;
	}

}
