<?php

/**
 * Upgrade Notices
 *
 * @since 1.1
 *
 */
function edd_show_usage_tracking_upgrade_notices() {

	global $wpdb;

	// Don't show notices on the upgrades page
	if ( isset( $_GET['page'] ) && $_GET['page'] == 'edd-upgrades' ) {
		return;
	}

	if ( ! edd_has_upgrade_completed( 'usage_tracking_11' ) ) {

		$has_checkins = $wpdb->get_col( "SELECT COUNT(DISTINCT meta_value) FROM $wpdb->postmeta WHERE meta_key = '_edd_log_url'" );

		if( empty( $has_checkins ) ) {
			return;
		}

		printf(
			'<div class="error"><p>' . __( 'Easy Digital Downloads needs to upgrade the usage tracking database, click <a href="%s">here</a> to start the upgrade.', 'edd-usage-tracking' ) . '</p></div>',
			esc_url( admin_url( 'index.php?page=edd-upgrades&edd-upgrade=usage_tracking_11' ) )
		);
	}

}
add_action( 'admin_notices', 'edd_show_usage_tracking_upgrade_notices' );

/**
 * Migrates usage tracking logs to their own table
 *
 * @since  1.1
 * @return void
 */
function edd_usage_tracking_11_migration() {
	global $wpdb, $edd_logs;

	if ( ! current_user_can( 'manage_shop_settings' ) ) {
		wp_die( __( 'You do not have permission to do shop upgrades', 'edd-usage-tracking' ), __( 'Error', 'edd-usage-tracking' ), array( 'response' => 403 ) );
	}

	ignore_user_abort( true );
	if ( ! edd_is_func_disabled( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
		@set_time_limit( 0 );
	}

	$step   = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
	$number = isset( $_GET['number'] ) ? absint( $_GET['number'] ) : 25;
	$offset = $step == 1 ? 0 : ( $step - 1 ) * $number;

	$logs_db  = new IBX_EDD_Usage_Tracking_Logs_DB;
	$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB;

	if ( $step < 2 ) {

		@$logs_db->create_table();
		@$sites_db->create_table();

		// Check if we have any checkins before moving on
		$has_checkins = $wpdb->get_col( "SELECT COUNT(DISTINCT meta_value) FROM $wpdb->postmeta WHERE meta_key = '_edd_log_url'" );

		if ( empty( $has_checkins ) ) {
			// We had no payments, just complete
			update_option( 'edd_usage_tracking_version', preg_replace( '/[^0-9.].*/', '', IBX_EDD_UT_VERSION ) );
			edd_set_upgrade_complete( 'usage_tracking_11' );
			delete_option( 'edd_doing_upgrade' );
			wp_redirect( admin_url() );
			exit;
		}
	}

	$total = isset( $_GET['total'] ) ? absint( $_GET['total'] ) : false;
	if ( empty( $total ) || $total <= 1 ) {
		$total = $edd_logs->get_log_count( 0, 'edd_checkin' );
	}

	$log_query = array(
		'log_type'       => 'edd_checkin',
		'offset'         => $offset,
		'posts_per_page' => $number,
		'orderby'        => 'date',
		'order'          => 'ASC',
	);

	$logs = $edd_logs->get_connected_logs( $log_query );

	if ( ! empty( $logs ) ) {

		foreach ( $logs as $log ) {
			$data = maybe_unserialize( $log->post_content );

			if ( is_array( $data['active_plugins'] ) ) {
				$data['active_plugins'] = array_values( array_map( 'strtolower', $data['active_plugins'] ) );
			}

			if ( is_array( $data['inactive_plugins'] ) ) {
				$data['inactive_plugins'] = array_values( array_map( 'strtolower', $data['inactive_plugins'] ) );
			}

			$url   = strtolower( trailingslashit( $data['url'] ) );
			$email = sanitize_email( $data['email'] );

			$sql         = $wpdb->prepare( "SELECT id, url FROM $sites_db->table_name WHERE url = '%s' AND email = '%s'", $url, $email );
			$site_exists = $wpdb->get_results( $sql );

			if( empty( $site_exists ) ) {
				$site_data = array(
					'url'              => $url,
					'edd_version'      => isset( $data['edd_version'] )     ? $data['edd_version']     : NULL,
					'php_version'      => isset( $data['php_version'] )     ? $data['php_version']     : NULL,
					'wp_version'       => isset( $data['wp_version'] )      ? $data['wp_version']      : NULL,
					'server'           => isset( $data['server'] )          ? $data['server']          : NULL,
					'install_date'     => isset( $data['install_date'] )    ? $data['install_date']    : NULL,
					'multisite'        => isset( $data['multisite'] )       ? (int) $data['multisite'] : NULL,
					'theme'            => $data['theme'],
					'email'            => $email,
					'active_plugins'   => json_encode( $data['active_plugins'] ),
					'inactive_plugins' => json_encode( $data['inactive_plugins'] ),
					'products'         => (int) $data['products'],
					'download_label'   => $data['download_label'],
					'last_checkin'     => 0,
				);

				$site_id = $sites_db->insert( $site_data );
			} else {
				$site_exists = $site_exists[0];
				$site_id     = $site_exists->id;
			}

			$new_log = array(
				'site_id'          => $site_id,
				'edd_version'      => isset( $data['edd_version'] )     ? $data['edd_version']     : NULL,
				'php_version'      => isset( $data['php_version'] )     ? $data['php_version']     : NULL,
				'wp_version'       => isset( $data['wp_version'] )      ? $data['wp_version']      : NULL,
				'server'           => isset( $data['server'] )          ? $data['server']          : NULL,
				'install_date'     => isset( $data['install_date'] )    ? $data['install_date']    : NULL,
				'multisite'        => isset( $data['multisite'] )       ? (int) $data['multisite'] : NULL,
				'theme'            => $data['theme'],
				'email'            => $email,
				'active_plugins'   => json_encode( $data['active_plugins'] ),
				'inactive_plugins' => json_encode( $data['inactive_plugins'] ),
				'products'         => (int) $data['products'],
				'download_label'   => $data['download_label'],
				'checkin_date'     => $log->post_date,
			);

			$logs_db->insert( $new_log );
		}

		$step ++;
		$redirect = add_query_arg( array(
			'page'        => 'edd-upgrades',
			'edd-upgrade' => 'usage_tracking_11',
			'step'        => $step,
			'number'      => $number,
			'total'       => $total
		), admin_url( 'index.php' ) );

		wp_redirect( $redirect );
		exit;

	} else {

		$redirect = add_query_arg( array(
			'page'        => 'edd-upgrades',
			'edd-upgrade' => 'usage_tracking_11_sites',
			'step'        => 1,
		), admin_url( 'index.php' ) );

		wp_redirect( $redirect );
		exit;

	}

}
add_action( 'edd_usage_tracking_11', 'edd_usage_tracking_11_migration' );

/**
 * Migrates usage tracking logs to their own table
 *
 * @since  1.1
 * @return void
 */
function edd_usage_tracking_11_migration_sites() {
	global $wpdb;

	if ( ! current_user_can( 'manage_shop_settings' ) ) {
		wp_die( __( 'You do not have permission to do shop upgrades', 'edd-usage-tracking' ), __( 'Error', 'edd-usage-tracking' ), array( 'response' => 403 ) );
	}

	ignore_user_abort( true );
	if ( ! edd_is_func_disabled( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
		@set_time_limit( 0 );
	}

	$step   = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
	$number = isset( $_GET['number'] ) ? absint( $_GET['number'] ) : 25;
	$offset = $step == 1 ? 0 : ( $step - 1 ) * $number;

	if ( $step < 2 ) {

		$db = new IBX_EDD_Usage_Tracking_Sites_DB;
		@$db->create_table();

		// Check if we have any checkins before moving on
		$has_checkins = $wpdb->get_col( "SELECT COUNT(DISTINCT meta_value) FROM $wpdb->postmeta WHERE meta_key = '_edd_log_url'" );

		if ( empty( $has_checkins ) ) {
			// We had no payments, just complete
			update_option( 'edd_usage_tracking_version', preg_replace( '/[^0-9.].*/', '', IBX_EDD_UT_VERSION ) );
			edd_set_upgrade_complete( 'usage_tracking_11' );
			delete_option( 'edd_doing_upgrade' );
			wp_redirect( admin_url() );
			exit;
		}
	}

	$logs_db = new IBX_EDD_Usage_Tracking_Logs_DB;
	$total = isset( $_GET['total'] ) ? absint( $_GET['total'] ) : false;

	if ( empty( $total ) || $total <= 1 ) {
		$sql   = "SELECT COUNT( DISTINCT( site_id ) ) FROM $logs_db->table_name";
		$total = $wpdb->get_var( $sql );
	}

	$sql         = $wpdb->prepare( "SELECT id, site_id FROM $logs_db->table_name WHERE id IN (SELECT MAX(id) FROM $logs_db->table_name GROUP BY site_id) ORDER BY id ASC LIMIT %d,%d", $offset, $number );
	$newest_logs = $wpdb->get_results( $sql );

	if ( ! empty( $newest_logs ) ) {

		foreach ( $newest_logs as $log ) {
			$db->update( $log->site_id, array( 'last_checkin' => $log->id ), 'id' );
		}

		$step ++;
		$redirect = add_query_arg( array(
			'page'        => 'edd-upgrades',
			'edd-upgrade' => 'usage_tracking_11_sites',
			'step'        => $step,
			'number'      => $number,
			'total'       => $total
		), admin_url( 'index.php' ) );

		wp_redirect( $redirect );
		exit;

	} else {

		update_option( 'edd_usage_tracking_version', preg_replace( '/[^0-9.].*/', '', IBX_EDD_UT_VERSION ) );
		edd_set_upgrade_complete( 'usage_tracking_11' );
		delete_option( 'edd_doing_upgrade' );

		wp_redirect( admin_url() );
		exit;

	}

}
add_action( 'edd_usage_tracking_11_sites', 'edd_usage_tracking_11_migration_sites' );
