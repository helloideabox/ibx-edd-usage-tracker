<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * The Usage Tracking DB Class
 *
 * @since  2.4
 */

class IBX_EDD_Usage_Tracking_Sites_DB extends EDD_DB {

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   2.4
	*/
	public function __construct() {

		global $wpdb;

		$this->table_name  = $wpdb->prefix . 'edd_tracking_sites';
		$this->primary_key = 'id';
		$this->version     = '1.1.1';

	}

	/**
	 * Get columns and formats
	 *
	 * @access  public
	 * @since   2.4
	*/
	public function get_columns() {
		return array(
			'id'                => '%d',
			'url'               => '%s',
			'edd_version'       => '%s',
			'php_version'       => '%s',
			'wp_version'        => '%s',
			'server'            => '%s',
			'install_date'      => '%s',
			'multisite'         => '%d',
			'theme'             => '%s',
			'email'             => '%s',
			'active_plugins'    => '%s',
			'inactive_plugins'  => '%s',
			'products'          => '%d',
			'download_label'    => '%s',
			'last_checkin'      => '%d',
			'locale'            => '%s',
			'user_firstname'    => '%s',
			'user_lastname'     => '%s',
			'user_email'        => '%s',
		);
	}

	/**
	 * Get default column values
	 *
	 * @access  public
	 * @since   2.4
	*/
	public function get_column_defaults() {
		return array(
			'id'                => NULL,
			'url'               => '',
			'edd_version'       => NULL,
			'php_version'       => NULL,
			'wp_version'        => NULL,
			'server'            => NULL,
			'install_date'      => NULL,
			'multisite'         => NULL,
			'theme'             => '',
			'email'             => '',
			'active_plugins'    => '',
			'inactive_plugins'  => '',
			'products'          => '',
			'download_label'    => '',
			'last_checkin'      => '',
			'locale'            => '',
			'user_firstname'    => '',
			'user_lastname'     => '',
			'user_email'        => '',
		);
	}

	/**
	 * Retrieve all subscriptions for a customer
	 *
	 * @access  public
	 * @since   2.4
	*/
	public function get_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'number'         => 20,
			'offset'         => 0,
			'url'            => '',
			'email'          => '',
			'orderby'        => 'id',
			'order'          => 'DESC',
			'php_version'    => '',
			'edd_version'    => '',
			'wp_version'     => '',
			'server'         => '',
			'theme'          => '',
			'plugin'         => '',
			'download_label' => '',
			'status'         => 'all',
		);

		$args  = wp_parse_args( $args, $defaults );

		if( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
		}

		$where = ' WHERE 1=1 ';

		if ( 'active' === $args['status'] || 'inactive' === $args['status'] ) {
			switch( $args['status'] ) {

				case 'active':
					$compare = '<';
					break;

				case 'inactive':
					$compare = '>';
					break;

			}

			$logs_db = new IBX_EDD_Usage_Tracking_Logs_DB;
			$where .= " AND DATE_SUB(NOW(), INTERVAL 1 MONTH) $compare (SELECT checkin_date FROM $logs_db->table_name WHERE id = st.last_checkin)";
		}


		// Specific sites
		if( ! empty( $args['url'] ) ) {

			$url    = trim( sanitize_text_field( $args['url'] ) );
			$where .= $wpdb->prepare( " AND `url` LIKE '%s'", '%' . $wpdb->esc_like( $url ) . '%' );

		}

		// Specific emails
		if( ! empty( $args['email'] ) ) {

			$email  = trim( sanitize_email( $args['email'] ) );
			$where .= $wpdb->prepare( " AND `email` = '%s'", $email );

		}

		if ( ! empty( $args['edd_version'] ) ) {

			$version = trim( sanitize_text_field( $args['edd_version'] ) );
			$where  .= $wpdb->prepare( " AND `edd_version` LIKE '%s'", $wpdb->esc_like( $version ) . '%' );

		}

		if ( ! empty( $args['php_version'] ) ) {

			$version = trim( sanitize_text_field( $args['php_version'] ) );
			$where  .= $wpdb->prepare( " AND `php_version` LIKE '%s'", $wpdb->esc_like( $version ) . '%' );

		}

		if ( ! empty( $args['wp_version'] ) ) {

			$version = trim( sanitize_text_field( $args['wp_version'] ) );
			$where  .= $wpdb->prepare( " AND `wp_version` LIKE '%s'", $wpdb->esc_like( $version ) . '%' );

		}

		if ( ! empty( $args['server'] ) ) {

			$server  = trim( sanitize_text_field( $args['server'] ) );
			$where  .= $wpdb->prepare( " AND `server` LIKE '%s'", '%' . $wpdb->esc_like( $server ) . '%' );

		}

		if ( ! empty( $args['locale'] ) ) {

			$locale  = trim( sanitize_text_field( $args['locale'] ) );
			$where  .= $wpdb->prepare( " AND `locale` LIKE '%s'", '%' . $wpdb->esc_like( $locale ) . '%' );

		}

		if ( ! empty( $args['theme'] ) ) {

			$theme   = trim( sanitize_text_field( $args['theme'] ) );
			$where  .= $wpdb->prepare( " AND `theme` LIKE '%s'", '%' . $wpdb->esc_like( $theme ) . '%' );

		}

		if ( ! empty( $args['plugin'] ) ) {

			$plugin  = trim( sanitize_text_field( $args['plugin'] ) );
			$where  .= $wpdb->prepare( " AND `active_plugins` LIKE '%s'", '%' . $wpdb->esc_like( $plugin ) . '%' );

		}

		if ( ! empty( $args['inactive_plugin'] ) ) {

			$plugin  = trim( sanitize_text_field( $args['inactive_plugin'] ) );
			$where  .= $wpdb->prepare( " AND `inactive_plugins` LIKE '%s'", '%' . $wpdb->esc_like( $plugin ) . '%' );

		}

		if ( ! empty( $args['download_label'] ) ) {

			$label   = trim( sanitize_text_field( $args['download_label'] ) );
			$where  .= $wpdb->prepare( " AND `download_label` LIKE '%s'", '%' . $wpdb->esc_like( $label ) . '%' );

		}

		if ( ! empty( $args['user_firstname'] ) ) {

			$label   = trim( sanitize_text_field( $args['user_firstname'] ) );
			$where  .= $wpdb->prepare( " AND `user_firstname` LIKE '%s'", '%' . $wpdb->esc_like( $label ) . '%' );

		}

		if ( ! empty( $args['user_lastname'] ) ) {

			$label   = trim( sanitize_text_field( $args['user_lastname'] ) );
			$where  .= $wpdb->prepare( " AND `user_lastname` LIKE '%s'", '%' . $wpdb->esc_like( $label ) . '%' );

		}

		if ( ! empty( $args['user_email'] ) ) {

			$label   = trim( sanitize_text_field( $args['user_email'] ) );
			$where  .= $wpdb->prepare( " AND `user_email` LIKE '%s'", '%' . $wpdb->esc_like( $label ) . '%' );

		}

		// Logs created for a specific date or in a date range
		if( ! empty( $args['date'] ) ) {

			if( is_array( $args['date'] ) ) {

				if( ! empty( $args['date']['start'] ) ) {

					$start = date( 'Y-m-d H:i:s', strtotime( $args['date']['start'] ) );

					$where .= " AND `date_created` >= '{$start}'";

				}

				if( ! empty( $args['date']['end'] ) ) {

					$end = date( 'Y-m-d H:i:s', strtotime( $args['date']['end'] ) );

					$where .= " AND `date_created` <= '{$end}'";

				}

			} else {

				$year  = date( 'Y', strtotime( $args['date'] ) );
				$month = date( 'm', strtotime( $args['date'] ) );
				$day   = date( 'd', strtotime( $args['date'] ) );

				$where .= " AND $year = YEAR ( last_checkin ) AND $month = MONTH ( last_checkin ) AND $day = DAY ( last_checkin )";
			}

		}

		$args['orderby'] = ! array_key_exists( $args['orderby'], $this->get_columns() ) ? 'id' : $args['orderby'];

		if( 'amount' == $args['orderby'] ) {
			$args['orderby'] = 'amount+0';
		}

		$cache_key = 'edd_' . md5( 'checkin_sites' . serialize( $args ) );

		$sites = wp_cache_get( $cache_key, 'edd_checkin_sites' );

		$args['orderby'] = esc_sql( $args['orderby'] );
		$args['order']   = esc_sql( $args['order'] );

		if( $sites === false ) {
			$offset = absint( $args['offset'] );
			$number = absint( $args['number'] );

			$sql   = "SELECT * FROM  $this->table_name st $where ORDER BY {$args['orderby']} {$args['order']} LIMIT $offset,$number;";
			$sites = $wpdb->get_results( $sql, ARRAY_A );

			wp_cache_set( $cache_key, $sites, 'edd_checkin_sites', 3600 );
		}

		return $sites;
	}

	/**
	 * Count the total number of sites in the database
	 *
	 * @access  public
	 * @since   2.4
	*/
	public function count( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'number'         => 20,
			'offset'         => 0,
			'url'            => '',
			'email'          => '',
			'orderby'        => 'id',
			'order'          => 'DESC',
			'php_version'    => '',
			'edd_version'    => '',
			'wp_version'     => '',
			'server'         => '',
			'theme'          => '',
			'plugin'         => '',
			'download_label' => '',
			'locale'         => '',
			'status'         => 'all',
		);

		$args  = wp_parse_args( $args, $defaults );

		if( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
		}

		$where = ' WHERE 1=1 ';

		if ( 'active' === $args['status'] || 'inactive' === $args['status'] ) {
			switch( $args['status'] ) {

				case 'active':
					$compare = '<';
					break;

				case 'inactive':
					$compare = '>';
					break;

			}

			$logs_db = new IBX_EDD_Usage_Tracking_Logs_DB;
			$where .= " AND DATE_SUB(NOW(), INTERVAL 1 MONTH) $compare (SELECT checkin_date FROM $logs_db->table_name WHERE id = st.last_checkin)";
		}

		// Specific sites
		if( ! empty( $args['url'] ) ) {

			$url    = trim( sanitize_text_field( $args['url'] ) );
			$where .= $wpdb->prepare( " AND `url` LIKE '%s'", '%' . $wpdb->esc_like( $url ) . '%' );

		}

		// Specific emails
		if( ! empty( $args['email'] ) ) {

			$email  = trim( sanitize_email( $args['email'] ) );
			$where .= $wpdb->prepare( " AND `email` = '%s'", $email );

		}

		if ( ! empty( $args['edd_version'] ) ) {

			$version = trim( sanitize_text_field( $args['edd_version'] ) );
			$where  .= $wpdb->prepare( " AND `edd_version` LIKE '%s'", '%' . $wpdb->esc_like( $version ) . '%' );

		}

		if ( ! empty( $args['php_version'] ) ) {

			$version = trim( sanitize_text_field( $args['php_version'] ) );
			$where  .= $wpdb->prepare( " AND `php_version` LIKE '%s'", '%' . $wpdb->esc_like( $version ) . '%' );

		}

		if ( ! empty( $args['wp_version'] ) ) {

			$version = trim( sanitize_text_field( $args['wp_version'] ) );
			$where  .= $wpdb->prepare( " AND `wp_version` LIKE '%s'", '%' . $wpdb->esc_like( $version ) . '%' );

		}

		if ( ! empty( $args['server'] ) ) {

			$server  = trim( sanitize_text_field( $args['server'] ) );
			$where  .= $wpdb->prepare( " AND `server` LIKE '%s'", '%' . $wpdb->esc_like( $server ) . '%' );

		}

		if ( ! empty( $args['locale'] ) ) {

			$locale  = trim( sanitize_text_field( $args['locale'] ) );
			$where  .= $wpdb->prepare( " AND `locale` LIKE '%s'", '%' . $wpdb->esc_like( $locale ) . '%' );

		}

		if ( ! empty( $args['theme'] ) ) {

			$theme   = trim( sanitize_text_field( $args['theme'] ) );
			$where  .= $wpdb->prepare( " AND `theme` LIKE '%s'", '%' . $wpdb->esc_like( $theme ) . '%' );

		}

		if ( ! empty( $args['plugin'] ) ) {

			$plugin  = trim( sanitize_text_field( $args['plugin'] ) );
			$where  .= $wpdb->prepare( " AND `active_plugins` LIKE '%s'", '%' . $wpdb->esc_like( $plugin ) . '%' );

		}

		if ( ! empty( $args['inactive_plugin'] ) ) {

			$plugin  = trim( sanitize_text_field( $args['inactive_plugin'] ) );
			$where  .= $wpdb->prepare( " AND `inactive_plugins` LIKE '%s'", '%' . $wpdb->esc_like( $plugin ) . '%' );

		}

		if ( ! empty( $args['download_label'] ) ) {

			$label   = trim( sanitize_text_field( $args['download_label'] ) );
			$where  .= $wpdb->prepare( " AND `download_label` LIKE '%s'", '%' . $wpdb->esc_like( $label ) . '%' );

		}

		if ( ! empty( $args['user_firstname'] ) ) {

			$label   = trim( sanitize_text_field( $args['user_firstname'] ) );
			$where  .= $wpdb->prepare( " AND `user_firstname` LIKE '%s'", '%' . $wpdb->esc_like( $label ) . '%' );

		}

		if ( ! empty( $args['user_lastname'] ) ) {

			$label   = trim( sanitize_text_field( $args['user_lastname'] ) );
			$where  .= $wpdb->prepare( " AND `user_lastname` LIKE '%s'", '%' . $wpdb->esc_like( $label ) . '%' );

		}

		if ( ! empty( $args['user_email'] ) ) {

			$label   = trim( sanitize_text_field( $args['user_email'] ) );
			$where  .= $wpdb->prepare( " AND `user_email` LIKE '%s'", '%' . $wpdb->esc_like( $label ) . '%' );

		}

		// Logs created for a specific date or in a date range
		if( ! empty( $args['date'] ) ) {

			if( is_array( $args['date'] ) ) {

				if( ! empty( $args['date']['start'] ) ) {

					$start = date( 'Y-m-d H:i:s', strtotime( $args['date']['start'] ) );

					$where .= " AND `date_created` >= '{$start}'";

				}

				if( ! empty( $args['date']['end'] ) ) {

					$end = date( 'Y-m-d H:i:s', strtotime( $args['date']['end'] ) );

					$where .= " AND `date_created` <= '{$end}'";

				}

			} else {

				$year  = date( 'Y', strtotime( $args['date'] ) );
				$month = date( 'm', strtotime( $args['date'] ) );
				$day   = date( 'd', strtotime( $args['date'] ) );

				$where .= " AND $year = YEAR ( last_checkin ) AND $month = MONTH ( last_checkin ) AND $day = DAY ( last_checkin )";
			}

		}

		$cache_key = 'edd_' . md5( 'site_count' . serialize( $args ) );

		$count = wp_cache_get( $cache_key, 'edd_checkin_sites' );

		if( $count === false ) {

			$sql   = "SELECT COUNT($this->primary_key) FROM " . $this->table_name . " st {$where};";
			$count = $wpdb->get_var( $sql );

			wp_cache_set( $cache_key, $count, 'edd_checkin_sites', 3600 );

		}

		return absint( $count );

	}

	/**
	 * Create the table
	 *
	 * @access  public
	 * @since   2.4
	*/
	public function create_table() {

		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE " . $this->table_name . " (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		url varchar(125) NOT NULL,
		edd_version varchar(100) DEFAULT NULL,
		php_version varchar(100) DEFAULT NULL,
		wp_version varchar(100) DEFAULT NULL,
		server varchar(100) DEFAULT NULL,
		install_date datetime DEFAULT NULL,
		multisite tinyint DEFAULT NULL,
		theme varchar(100) DEFAULT NULL,
		locale varchar(5) DEFAULT NULL,
		email varchar(100) DEFAULT NULL,
		active_plugins mediumtext DEFAULT NULL,
		inactive_plugins mediumtext DEFAULT NULL,
		products int DEFAULT NULL,
		download_label varchar(100) DEFAULT NULL,
		user_firstname varchar(100) DEFAULT NULL,
		user_lastname varchar(100) DEFAULT NULL,
		user_email varchar(100) DEFAULT NULL,
		last_checkin bigint(20) NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY `site_email` (url,email),
		KEY edd_version (edd_version),
		KEY php_version (php_version),
		KEY wp_version (wp_version),
		KEY multisite (multisite)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

}
