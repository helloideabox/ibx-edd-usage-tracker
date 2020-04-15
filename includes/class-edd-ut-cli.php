<?php
/**
 * Easy Digital Downloads WP-CLI Migrator
 *
 * This class provides an integration point with the WP-CLI plugin allowing
 * access to EDD from the command line.
 *
 * @package     EDD
 * @subpackage  Classes/CLI
 * @copyright   Copyright (c) 2015, Pippin Williamson
 * @license     http://opensource.org/license/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

WP_CLI::add_command( 'eddut', 'EDD_Usage_Tracking_CLI' );

/**
 * Work with EDD through WP-CLI
 *
 * EDD_CLI Class
 *
 * Adds CLI support to EDD through WP-CL
 *
 * @since   1.0
 */
class EDD_Usage_Tracking_CLI extends EDD_CLI {

	/**
	 * Interact with the EDD_Logging entries
	 *
	 * ## OPTIONS
	 *
	 * --type=<log_type>: A specific log type to interact with
	 * --before=<string>: A strtotime keyword or date
	 * --after=<string>: A strtotime keyword or date
	 *
	 * ## EXAMPLES
	 *
	 * wp edd logs prune --type=api_reqeusts --before="-1 year"
	 * wp edd logs prune --type=api_reqeusts --before=today
	 * wp edd logs prune --type=api_reqeusts --after=today
	 * wp edd logs count --type=api_reqeusts --before=today
	 */
	public function upgrade( $args, $assoc_args ) {
		global $edd_logs, $wpdb;

		$logs_db  = new IBX_EDD_Usage_Tracking_Logs_DB;
		$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB;

		@$logs_db->create_table();
		@$sites_db->create_table();

		$log_query = array(
			'log_type'       => 'edd_checkin',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'ASC',
		);

		$logs = $edd_logs->get_connected_logs( $log_query );

		if ( ! empty( $logs ) ) {

			$progress = new \cli\progress\Bar( 'Processing Checkin Logs', count( $logs ) );

			foreach ( $logs as $log ) {
				$data = maybe_unserialize( $log->post_content );

				// URLs are kind of the point here, if we don't have one, ditch now
				if ( ! isset( $data['url'] ) ) {
					continue;
				}

				if ( isset( $data['active_plugins'] ) && is_array( $data['active_plugins'] ) ) {
					$data['active_plugins'] = array_values( array_map( 'strtolower', $data['active_plugins'] ) );
				} else {
					$data['active_plugins'] = array();
				}

				if ( isset( $data['inactive_plugins'] ) && is_array( $data['inactive_plugins'] ) ) {
					$data['inactive_plugins'] = array_values( array_map( 'strtolower', $data['inactive_plugins'] ) );
				} else {
					$data['inactive_plugins'] = array();
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

				$progress->tick();
			}

			$progress->finish();
		}

		// Now we need to update each of our sites with the latest checkins
		$sql   = "SELECT COUNT( DISTINCT( site_id ) ) FROM $logs_db->table_name";
		$total = $wpdb->get_var( $sql );

		$sql         = "SELECT id, site_id FROM $logs_db->table_name WHERE id IN (SELECT MAX(id) FROM $logs_db->table_name GROUP BY site_id) ORDER BY id ASC";
		$newest_logs = $wpdb->get_results( $sql );

		if ( ! empty( $newest_logs ) ) {

			$sites_progress = new \cli\progress\Bar( 'Updating sites with latest checkins', count( $newest_logs ) );

			foreach ( $newest_logs as $log ) {
				$sites_db->update( $log->site_id, array( 'last_checkin' => $log->id ), 'id' );
				$sites_progress->tick();
			}

			$sites_progress->finish();


			update_option( 'edd_usage_tracking_version', preg_replace( '/[^0-9.].*/', '', IBX_EDD_UT_VERSION ) );
			edd_set_upgrade_complete( 'usage_tracking_11' );

		}

	}

}
