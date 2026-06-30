<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SAP_Generator {
	public static function estimate_total( $settings ) {
		global $wpdb;
		$total = 0;
		$types = array_values( array_filter( array_map( 'sanitize_key', $settings['include_types'] ) ) );
		if ( $types ) {
			$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
			$sql = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status='publish' AND post_password='' AND post_type IN ($placeholders)";
			$total += (int) $wpdb->get_var( $wpdb->prepare( $sql, $types ) );
		}
		foreach ( (array) $settings['include_taxonomies'] as $tax ) {
			$total += (int) wp_count_terms( array( 'taxonomy' => sanitize_key( $tax ), 'hide_empty' => true ) );
		}
		return $total;
	}

	public static function start( $settings ) {
		if ( ! SAP_Files::ensure_directory() ) {
			return new WP_Error( 'sap_not_writable', __( 'A pasta de sitemaps não tem permissão de escrita.', 'sitemap-agencia-privilege' ) );
		}
		if ( ! empty( $settings['clean_before'] ) ) {
			SAP_Files::delete_generated();
		}
		SAP_Settings::save( $settings );
		$tasks = array();
		foreach ( $settings['include_types'] as $type ) {
			$tasks[] = array( 'kind' => 'post_type', 'name' => sanitize_key( $type ), 'last_id' => 0, 'file_index' => 1, 'url_count' => 0 );
		}
		foreach ( $settings['include_taxonomies'] as $tax ) {
			$tasks[] = array( 'kind' => 'taxonomy', 'name' => sanitize_key( $tax ), 'last_id' => 0, 'file_index' => 1, 'url_count' => 0 );
		}
		$state = array( 'settings' => $settings, 'tasks' => $tasks, 'task_index' => 0, 'processed' => 0, 'estimated' => self::estimate_total( $settings ), 'files' => array(), 'finished' => false, 'started_at' => current_time( 'mysql' ) );
		update_option( SAP_OPTION_STATE, $state, false );
		return $state;
	}

	public static function process_next() {
		$state = get_option( SAP_OPTION_STATE, array() );
		if ( empty( $state['tasks'] ) || ! empty( $state['finished'] ) ) { return $state; }
		$settings = $state['settings'];
		$task_i = (int) $state['task_index'];
		if ( ! isset( $state['tasks'][ $task_i ] ) ) { $state['finished'] = true; update_option( SAP_OPTION_STATE, $state, false ); return $state; }
		$task = $state['tasks'][ $task_i ];
		$items = 'taxonomy' === $task['kind'] ? self::query_terms( $task, $settings ) : self::query_posts( $task, $settings );
		if ( empty( $items ) ) {
			self::close_current_file( $task, $state );
			$state['task_index'] = $task_i + 1;
			update_option( SAP_OPTION_STATE, $state, false );
			return self::process_next();
		}
		foreach ( $items as $item ) {
			self::append_url( $task, $state, $item );
			$task['last_id'] = max( (int) $task['last_id'], (int) $item['id'] );
			$state['processed']++;
		}
		$state['tasks'][ $task_i ] = $task;
		update_option( SAP_OPTION_STATE, $state, false );
		return $state;
	}

	private static function query_posts( $task, $settings ) {
		global $wpdb;
		$excluded = SAP_Settings::excluded_ids( $settings );
		$params = array( $task['name'], (int) $task['last_id'], (int) $settings['batch_size'] );
		$not_in = '';
		if ( $excluded ) { $not_in = ' AND ID NOT IN (' . implode( ',', array_fill( 0, count( $excluded ), '%d' ) ) . ')'; $params = array_merge( array( $task['name'], (int) $task['last_id'] ), $excluded, array( (int) $settings['batch_size'] ) ); }
		$sql = "SELECT ID, post_modified_gmt FROM {$wpdb->posts} WHERE post_type=%s AND ID>%d AND post_status='publish' AND post_password='' {$not_in} ORDER BY ID ASC LIMIT %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $settings['exclude_noindex'] ) && self::is_noindex( $row->ID ) ) { continue; }
			$url = get_permalink( $row->ID );
			if ( $url ) { $out[] = array( 'id' => (int) $row->ID, 'loc' => $url, 'lastmod' => get_date_from_gmt( $row->post_modified_gmt, DATE_W3C ), 'type' => $task['name'] ); }
		}
		return $out;
	}

	private static function query_terms( $task, $settings ) {
		global $wpdb;
		$taxonomy = sanitize_key( $task['name'] );
		$sql = "SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.taxonomy = %s AND tt.count > 0 AND t.term_id > %d ORDER BY t.term_id ASC LIMIT %d";
		$term_ids = $wpdb->get_col( $wpdb->prepare( $sql, $taxonomy, (int) $task['last_id'], (int) $settings['batch_size'] ) );
		$out = array();
		foreach ( $term_ids as $term_id ) {
			$term = get_term( (int) $term_id, $taxonomy );
			if ( ! $term || is_wp_error( $term ) ) { continue; }
			$url = get_term_link( $term );
			if ( ! is_wp_error( $url ) && $url ) { $out[] = array( 'id' => (int) $term->term_id, 'loc' => $url, 'lastmod' => current_time( DATE_W3C ), 'type' => 'taxonomy' ); }
		}
		return $out;
	}

	private static function is_noindex( $post_id ) {
		$keys = array( '_yoast_wpseo_meta-robots-noindex', 'rank_math_robots', '_aioseo_robots_noindex', '_seopress_robots_index', 'robotsmeta' );
		foreach ( $keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( is_array( $value ) && in_array( 'noindex', $value, true ) ) { return true; }
			if ( is_string( $value ) && ( '1' === $value || false !== stripos( $value, 'noindex' ) || 'yes' === strtolower( $value ) ) ) { return true; }
		}
		return false;
	}

	private static function filename( $task ) {
		$prefix = 'page' === $task['name'] ? 'pages' : sanitize_title( $task['name'] );
		return 'sitemap-' . $prefix . '-' . (int) $task['file_index'] . '.xml';
	}

	private static function append_url( &$task, &$state, $item ) {
		$settings = $state['settings'];
		if ( (int) $task['url_count'] >= (int) $settings['urls_per_file'] ) { self::close_current_file( $task, $state ); $task['file_index']++; $task['url_count'] = 0; }
		$file = SAP_Files::safe_path( self::filename( $task ) );
		if ( ! $file ) { return; }
		if ( 0 === (int) $task['url_count'] || ! file_exists( $file ) ) { file_put_contents( $file, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n" ); }
		$xml  = "  <url>\n";
		$xml .= '    <loc>' . esc_url( $item['loc'] ) . "</loc>\n";
		if ( ! empty( $settings['include_lastmod'] ) ) { $xml .= '    <lastmod>' . esc_html( $item['lastmod'] ) . "</lastmod>\n"; }
		if ( ! empty( $settings['include_changefreq'] ) ) { $xml .= '    <changefreq>' . esc_html( self::changefreq( $item['type'] ) ) . "</changefreq>\n"; }
		if ( ! empty( $settings['include_priority'] ) ) { $xml .= '    <priority>' . esc_html( self::priority( $item['type'] ) ) . "</priority>\n"; }
		$xml .= "  </url>\n";
		file_put_contents( $file, $xml, FILE_APPEND | LOCK_EX );
		$task['url_count']++;
	}

	private static function close_current_file( &$task, &$state ) {
		if ( empty( $task['url_count'] ) ) { return; }
		$name = self::filename( $task );
		$file = SAP_Files::safe_path( $name );
		if ( $file && file_exists( $file ) ) {
			$content = file_get_contents( $file, false, null, max( 0, filesize( $file ) - 20 ) );
			if ( false === strpos( $content, '</urlset>' ) ) { file_put_contents( $file, "</urlset>\n", FILE_APPEND | LOCK_EX ); }
			$state['files'][ $name ] = array( 'name' => $name, 'urls' => (int) $task['url_count'], 'lastmod' => current_time( DATE_W3C ) );
		}
	}

	public static function finalize() {
		$state = get_option( SAP_OPTION_STATE, array() );
		if ( ! empty( $state['tasks'] ) ) { foreach ( $state['tasks'] as &$task ) { self::close_current_file( $task, $state ); } }
		$info = SAP_Files::upload_info();
		$index = SAP_Files::safe_path( 'sitemap.xml' );
		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
		foreach ( (array) $state['files'] as $file ) { $xml .= "  <sitemap>\n    <loc>" . esc_url( trailingslashit( $info['url'] ) . $file['name'] ) . "</loc>\n    <lastmod>" . esc_html( $file['lastmod'] ) . "</lastmod>\n  </sitemap>\n"; }
		$xml .= "</sitemapindex>\n";
		file_put_contents( $index, $xml, LOCK_EX );
		$state['finished'] = true;
		update_option( SAP_OPTION_STATE, $state, false );
		update_option( SAP_OPTION_LAST_RUN, current_time( 'mysql' ), false );
		return $state;
	}

	private static function changefreq( $type ) { return 'post' === $type ? 'weekly' : 'monthly'; }
	private static function priority( $type ) { return 'page' === $type ? '0.8' : ( 'post' === $type ? '0.7' : '0.6' ); }
}
