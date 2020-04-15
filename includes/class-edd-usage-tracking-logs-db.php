<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * The Usage Tracking DB Class
 *
 * @since  2.4
 */

class IBX_EDD_Usage_Tracking_Logs_DB extends EDD_DB {

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   2.4
	*/
	public function __construct() {

		global $wpdb;

		$this->table_name  = $wpdb->prefix . 'edd_tracking_logs';
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
			'site_id'           => '%d',
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
			'checkin_date'      => '%s',
			'locale'            => '%s',
			'user_firstname'    => '',
			'user_lastname'     => '',
			'user_email'        => '',
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
			'site_id'           => NULL,
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
			'checkin_date'      => current_time( 'mysql' ),
			'locale'            => '',
			'user_firstname'    => '',
			'user_lastname'     => '',
			'user_email'        => '',
		);
	}

	/**
	 * Retrieve all logs
	 *
	 * @access  public
	 * @since   2.4
	*/
	public function get_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'number'  => 20,
			'offset'  => 0,
			'url'     => '',
			'site_id' => 0,
			'locale'  => '',
			'orderby' => 'id',
			'order'   => 'DESC',
		);

		$args  = wp_parse_args( $args, $defaults );

		// Specific sites
		if( ! empty( $args['url'] ) ) {
			$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB;
			$site     = $sites_db->get_by( 'url', $args['url'] );

			$site_id = ! empty( $site->id ) ? $site->id : 0;

			$args['site_id'] = $site_id;
			unset( $args['url'] );
		}

		if( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
		}

		$where = ' WHERE 1=1 ';

		// specific ids
		if( ! empty( $args['id'] ) ) {

			if( is_array( $args['id'] ) ) {
				$ids = implode( ',', array_map('intval', $args['id'] ) );
			} else {
				$ids = intval( $args['id'] );
			}

			$where .= " AND `id` IN( {$ids} ) ";

		}

		// specific sites
		if( ! empty( $args['site_id'] ) ) {

			$site_id = intval( $args['site_id'] );
			$where  .= " AND `site_id` = '$site_id'";

		}

		// specific locale
		if( ! empty( $args['locale'] ) ) {

			$locale  = sanitize_text_field( $args['locale'] );
			$where  .= " AND `locale` = '$locale'";

		}

		// Logs created for a specific date or in a date range
		if( ! empty( $args['date'] ) ) {

			if( is_array( $args['date'] ) ) {

				if( ! empty( $args['date']['start'] ) ) {

					$start = date( 'Y-m-d H:i:s', strtotime( $args['date']['start'] ) );

					$where .= " AND `checkin_date` >= '{$start}'";

				}

				if( ! empty( $args['date']['end'] ) ) {

					$end = date( 'Y-m-d H:i:s', strtotime( $args['date']['end'] ) );

					$where .= " AND `checkin_date` <= '{$end}'";

				}

			} else {

				$year  = date( 'Y', strtotime( $args['date'] ) );
				$month = date( 'm', strtotime( $args['date'] ) );
				$day   = date( 'd', strtotime( $args['date'] ) );

				$where .= " AND $year = YEAR ( checkin_date ) AND $month = MONTH ( checkin_date ) AND $day = DAY ( checkin_date )";
			}

		}

		$args['orderby'] = ! array_key_exists( $args['orderby'], $this->get_columns() ) ? 'id' : $args['orderby'];

		if( 'amount' == $args['orderby'] ) {
			$args['orderby'] = 'amount+0';
		}

		$cache_key = 'edd_' . md5( 'checkin_logs' . serialize( $args ) );

		$logs = wp_cache_get( $cache_key, 'edd_checkin_logs' );

		$args['orderby'] = esc_sql( $args['orderby'] );
		$args['order']   = esc_sql( $args['order'] );

		if( $logs === false ) {
			$logs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM  $this->table_name $where ORDER BY {$args['orderby']} {$args['order']} LIMIT %d,%d;", absint( $args['offset'] ), absint( $args['number'] ) ), ARRAY_A );

			wp_cache_set( $cache_key, $logs, 'edd_checkin_logs', 3600 );
		}

		return $logs;
	}

	/**
	 * Count the total number of logs in the database
	 *
	 * @access  public
	 * @since   2.4
	*/
	public function count( $args = array() ) {

		global $wpdb;

		$where = ' WHERE 1=1 ';


		// Specific sites
		if( ! empty( $args['url'] ) ) {
			$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB;
			$site     = $sites_db->get_by( 'url', $args['url'] );

			$site_id = ! empty( $site->id ) ? $site->id : 0;

			$args['site_id'] = $site_id;
			unset( $args['url'] );
		}

		$where = ' WHERE 1=1 ';

		// specific ids
		if( ! empty( $args['id'] ) ) {

			if( is_array( $args['id'] ) ) {
				$ids = implode( ',', array_map('intval', $args['id'] ) );
			} else {
				$ids = intval( $args['id'] );
			}

			$where .= " AND `id` IN( {$ids} ) ";

		}

		// specific sites
		if( ! empty( $args['site_id'] ) ) {

			$site_id = intval( $args['site_id'] );
			$where  .= " AND `site_id` = '$site_id'";

		}

		// specific locale
		if( ! empty( $args['locale'] ) ) {

			$locale  = sanitize_text_field( $args['locale'] );
			$where  .= " AND `locale` = '$locale'";

		}

		// Logs created for a specific date or in a date range
		if( ! empty( $args['date'] ) ) {

			if( is_array( $args['date'] ) ) {

				if( ! empty( $args['date']['start'] ) ) {

					$start = date( 'Y-m-d H:i:s', strtotime( $args['date']['start'] ) );

					$where .= " AND `checkin_date` >= '{$start}'";

				}

				if( ! empty( $args['date']['end'] ) ) {

					$end = date( 'Y-m-d H:i:s', strtotime( $args['date']['end'] ) );

					$where .= " AND `checkin_date` <= '{$end}'";

				}

			} else {

				$year  = date( 'Y', strtotime( $args['date'] ) );
				$month = date( 'm', strtotime( $args['date'] ) );
				$day   = date( 'd', strtotime( $args['date'] ) );

				$where .= " AND $year = YEAR ( checkin_date ) AND $month = MONTH ( checkin_date ) AND $day = DAY ( checkin_date )";
			}

		}

		$cache_key = 'edd_' . md5( 'checkin_count' . serialize( $args ) );

		$count = wp_cache_get( $cache_key, 'edd_checkin_logs' );

		if( $count === false ) {

			$sql   = "SELECT COUNT($this->primary_key) FROM " . $this->table_name . "{$where};";
			$count = $wpdb->get_var( $sql );

			wp_cache_set( $cache_key, $count, 'edd_checkin_logs', 3600 );

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
		site_id bigint(20) NOT NULL,
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
		checkin_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY site_id (site_id),
		KEY checkin_date (checkin_date)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

}
