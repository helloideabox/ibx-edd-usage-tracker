<?php
/*
 * Plugin Name: IBX EDD Usage Tracking
 * Description: Tracks check-ins from IBX plugin users and sends discounts codes on the first send
 * Version: 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IBX_EDD_UT_VERSION', 1.0 );

// Plugin Folder Path.
if ( ! defined( 'IBX_EDD_UT_PLUGIN_DIR' ) ) {
	define( 'IBX_EDD_UT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin Folder URL.
if ( ! defined( 'IBX_EDD_UT_PLUGIN_URL' ) ) {
	define( 'IBX_EDD_UT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

class IBX_EDD_Usage_Tracking {

	private static $instance;

	private function __construct() {
		$this->includes();

		if ( IBX_EDD_UT_VERSION != get_option( 'edd_usage_tracking_version' ) ) {
			edd_usage_tracking_install();
		}

		$this->hooks();
		$this->filters();
	}

	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof IBX_EDD_Usage_Tracking ) ) {
			self::$instance = new IBX_EDD_Usage_Tracking;
		}

		return self::$instance;
	}

	private function includes() {
		include_once IBX_EDD_UT_PLUGIN_DIR . 'includes/class-edd-usage-tracking-logs-db.php';
		include_once IBX_EDD_UT_PLUGIN_DIR . 'includes/class-edd-usage-tracking-sites-db.php';

		if ( is_admin() ) {
			include_once IBX_EDD_UT_PLUGIN_DIR . 'includes/admin/admin.php';
			include_once IBX_EDD_UT_PLUGIN_DIR . 'includes/admin/class-usage-tracking-list-table.php';
			include_once IBX_EDD_UT_PLUGIN_DIR . 'includes/admin/class-edd-tracking-sites-list-table.php';
			include_once IBX_EDD_UT_PLUGIN_DIR . 'includes/admin/upgrades.php';
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once IBX_EDD_UT_PLUGIN_DIR . 'includes/class-edd-ut-cli.php';
		}
	}

	private function hooks() {
		add_action( 'edd_checkin', array( $this, 'track_checkin' ) );
		add_action( 'edd_daily_scheduled_events', array( $this, 'send_expiring_notice' ) );
	}

	private function filters() {
		add_filter( 'edd_log_types', array( $this, 'log_type' ), 10, 1 );
		add_filter( 'edd_start_session', array( $this, 'maybe_start_session' ), 10, 1 );
	}

	function maybe_start_session( $start_session ) {
		if ( ! empty( $_GET['edd_action'] ) && 'checkin' === $_GET['edd_action'] ) {
			$start_session = false;
		}

		return $start_session;
	}

	public function log_type( $types ) {
		$types[] = 'edd_checkin';
		return $types;
	}

	public function track_checkin() {
		global $wpdb;

		$logs_db  = new IBX_EDD_Usage_Tracking_Logs_DB;
		$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB;

		$user_agent = array_map( 'trim', explode( ';', $_SERVER['HTTP_USER_AGENT'] ) );
		$name       = $user_agent[0];
		$domain     = $user_agent[1];

		if ( strpos( $name, 'EDD' ) === false ) {
			die( '-1' ); // Not a real EDD checkin
		}

		$email = sanitize_text_field( $_POST['email'] );
		$url   = strtolower( trailingslashit( $_POST['url'] ) );

		$is_local_url = $this->is_local_url( $url );
		if ( true === $is_local_url ) {
			die( '0' ); // Not a live domain, don't track it
		}

		$is_first_checkin = true;
		$sql      = $wpdb->prepare( "SELECT id, url, edd_version, last_checkin, email FROM $sites_db->table_name WHERE url = '%s'", $url );
		$site_log = $wpdb->get_results( $sql );

		if ( ! empty( $site_log ) ) {
			$is_first_checkin = false;

			$site_log_key = 0;
			foreach ( $site_log as $key => $log ) {
				if ( strtolower( $log->email ) === strtolower( $email ) ) {
					$site_log_key = $key;
					break;
				}
			}

			$sent_edd_version = isset( $_POST['edd_version'] ) ? sanitize_text_field( $_POST['edd_version'] ) : false;
			$last_checkin     = $logs_db->get_logs( array(
				'id' => $site_log[ $site_log_key ]->last_checkin,
			) );
			$time_since_last  = current_time( 'timestamp' ) - strtotime( $last_checkin[0]['checkin_date'], current_time( 'timestamp' ) );

			// Check if the admin email is different
			$current_email = $site_log[ $site_log_key ]->email;
			if ( strtolower( $current_email ) !== strtolower( $email ) ) {
				$sites_db->update( $site_log[ $site_log_key ]->id, array(
					'email' => $email,
				) );
			}

			if ( $time_since_last < WEEK_IN_SECONDS && $sent_edd_version == $site_log[ $site_log_key ]->edd_version ) {
				die( '-2' ); // Same site on same EDD version is checking in within a week
			}
		}

		if ( is_array( $_POST['active_plugins'] ) ) {
			$_POST['active_plugins'] = array_values( array_map( 'strtolower', $_POST['active_plugins'] ) );
		}

		if ( is_array( $_POST['inactive_plugins'] ) ) {
			$_POST['inactive_plugins'] = array_values( array_map( 'strtolower', $_POST['inactive_plugins'] ) );
		}

		$log_data = array(
			'edd_version'      => isset( $_POST['edd_version'] )     ? sanitize_text_field( $_POST['edd_version'] )    : null,
			'php_version'      => isset( $_POST['php_version'] )     ? sanitize_text_field( $_POST['php_version'] )    : null,
			'wp_version'       => isset( $_POST['wp_version'] )      ? sanitize_text_field( $_POST['wp_version'] )     : null,
			'server'           => isset( $_POST['server'] )          ? sanitize_text_field( $_POST['server'] )         : null,
			'install_date'     => isset( $_POST['install_date'] )    ? sanitize_text_field( $_POST['install_date'] )   : null,
			'multisite'        => isset( $_POST['multisite'] )       ? (int) $_POST['multisite'] : null,
			'theme'            => sanitize_text_field( $_POST['theme'] ),
			'email'            => sanitize_email( $_POST['email'] ),
			'active_plugins'   => json_encode( $_POST['active_plugins'] ),
			'inactive_plugins' => json_encode( $_POST['inactive_plugins'] ),
			'products'         => (int) $_POST['products'],
			'download_label'   => sanitize_text_field( $_POST['download_label'] ),
			'locale'           => sanitize_text_field( $_POST['locale'] ),
			'user_firstname'	=> sanitize_text_field( $_POST['user_firstname'] ),
			'user_lastname'		=> sanitize_text_field( $_POST['user_lastname'] ),
			'user_email'		=> sanitize_text_field( $_POST['user_email'] ),
		);

		foreach ( $log_data as $key => $value ) {
			if ( $key === 'active_plugins' || $key === 'inactive_plugins' ) {
				continue;
			}

			$log_data[ $key ] = esc_sql( str_replace( '~', '', $value ) );
		}

		$site_data = $log_data;
		$site_data['last_checkin'] = 0;
		$userdata = array(
			'first_name' => $log_data['user_firstname'],
			'last_name' => $log_data['user_lastname'],
			'email' => $log_data['user_email'],
		);

		if ( $is_first_checkin ) {
			$site_data['url'] = $url;
			$site_id = $sites_db->insert( $site_data );

			$this->send_discount( $email, $userdata, $url, $site_id );
			$this->subscribe_email( $email, $userdata );
		} else {
			$site_id = $site_log[ $site_log_key ]->id;
			unset( $site_data['email'], $site_data['url'] );
			$sites_db->update( $site_log[ $site_log_key ]->id, $site_data, 'id' );
		}

		$log_data['site_id'] = $site_id;
		$log_id              = $logs_db->insert( $log_data );
		$sites_db->update( $site_id, array(
			'last_checkin' => $log_id,
		), 'id' );

		die( 'success' );
	}

	private function subscribe_email( $email, $userdata ) {
		if ( function_exists( 'flowdee_ml_add_subscriber' ) ) {
			$group = edd_get_option( 'edd_ml_group' );

			if ( empty( $group ) ) {
				return;
			}

			$double_option = edd_get_option( 'edd_ml_double_optin', false );

			if ( isset( $userdata['email'] ) ) {
				$subscriber = array(
					'email' => $userdata['email'],
					'fields' => array(
						'name' => ( isset( $userdata['first_name'] ) ) ? $userdata['first_name'] : '',
						'last_name' => ( isset( $userdata['last_name'] ) ) ? $userdata['last_name'] : '',
					),
					'type' => ( $double_option ) ? 'unconfirmed' : 'subscribed',// subscribed, active, unconfirmed
				);

				$added = flowdee_ml_add_subscriber( $group, $subscriber );
			}

			$subscriber = array(
				'email' => $email,
				'fields' => array(
					'name' => '',
					'last_name' => '',
				),
				'type' => ( $double_option ) ? 'unconfirmed' : 'subscribed',// subscribed, active, unconfirmed
			);

			// edd_ml_debug_log( $subscriber );
			$added = flowdee_ml_add_subscriber( $group, $subscriber );
		}
	}

	public function get_email_body() {
		$message  = '<p>Thank you for allowing us to track your usage of PowerPack Elements Lite.</p>';
		$message .= '<p style="text-align: center">';
		$message .= '<a style="background-color: #2794da; color: #ffffff; display: block; font-family: sans-serif; font-size: 16px; font-weight: bold; line-height: 48px; text-align: center; margin: 0 auto; text-decoration: none; width: 200px; word-wrap: break-word; padding: 2px 8px;" href="https://powerpackelements.com/pricing/?discount={discount_code}&utm_source={discount_code}&utm_medium=email&utm_term=code&utm_campaign=PPEUsageTracking">Apply Your Discount Now</a>';
		$message .= '</p>';
		$message .= '<p>This is a one-time use discount code, and will be valid until <span style="font-weight: 400;"><em>' . date( 'F j, Y', strtotime( '+1 month' ) ) . '</em></span>.</p>';
		$message .= '<p>Head on over to the PowerPack Elements site to <br />';
		$message .= '<a href="https://powerpackelements.com/pricing/?discount={discount_code}&utm_source={discount_code}&utm_medium=email&utm_term=view&utm_campaign=PPEUsageTracking">Upgrade to PowerPack Elements PRO</a>';
		$message .= '<p>Note that we do NOT track any sales or customer information from your website, only the site URL, the active theme, and the active plugins. No sensitive data whatsoever is collected.</p>';
		$message .= '<p>You can manually apply your discount at checkout with the code: <strong>{discount_code}</strong></p>';
		$message .= '<p>Thank you!</p>';

		return $message;
	}

	public function get_email_body_disc_exp() {
		$message  = '<p>Your discount code for PowerPack Elements Pro is expiring soon! Once it has expired, it cannot be redeemed to save 15% off your purchase.</p>';
		$message .= '<p>This is a one-time use discount code, and will expire on <strong>{discount_expire_date}</strong>.</p>';
		$message .= '<p>Head on over to the PowerPack Elements site to ';
		$message .= '<a href="https://powerpackelements.com/pricing/?discount={discount_code}&utm_source={discount_code}&utm_medium=email&utm_term=view&utm_campaign=PPEUsageTracking">Upgrade to PowerPack Elements PRO</a>';
		$message .= '<p>Note that we do NOT track any sales or customer information from your website, only the site URL, the active theme, and the active plugins. No sensitive data whatsoever is collected.</p>';
		$message .= '<p>You can manually apply your discount at checkout with the code: <strong>{discount_code}</strong></p>';
		$message .= '<p>Thank you!</p>';
		$message .= '<p style="text-align: center">';
		$message .= '<a style="background-color: #2794da; color: #ffffff; display: block; font-family: sans-serif; font-size: 16px; font-weight: bold; line-height: 48px; text-align: center; margin: 0 auto; text-decoration: none; width: 200px; word-wrap: break-word; padding: 2px 8px;" href="https://powerpackelements.com/pricing/?discount={discount_code}&utm_source={discount_code}&utm_medium=email&utm_term=code&utm_campaign=PPEUsageTracking&utm_content=expiring">Apply Your Discount Now</a>';
		$message .= '</p>';

		return $message;
	}

	public function get_email_fields() {
		$from_name = trim( edd_get_option( 'edd_ut_email_from_name' ) );
		$from_name = empty( $from_name ) ? edd_get_option( 'from_name', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) : $from_name;
		$from_email = trim( edd_get_option( 'edd_ut_email_from_email' ) );
		$from_email = empty( $from_email ) ? edd_get_option( 'from_email', get_bloginfo( 'admin_email' ) ) : $from_email;

		// Discount code.
		$subject = trim( edd_get_option( 'edd_ut_email_subject' ) );
		$subject = empty( $subject ) ? esc_html__( 'PowerPack Elements Pro Discount', 'edd-usage-tracker' ) : $subject;
		$heading = trim( edd_get_option( 'edd_ut_email_heading' ) );
		$heading = empty( $heading ) ? esc_html__( 'Your Discount is Ready', 'edd-usage-tracker' ) : $heading;
		$body = wpautop( trim( edd_get_option( 'edd_ut_email_template' ) ) );
		$body = empty( $body ) ? wpautop( $this->get_email_body() ) : $body;

		// Discount expire.
		$subject_disc_exp = trim( edd_get_option( 'edd_ut_email_subject_disc_exp' ) );
		$subject_disc_exp = empty( $subject_disc_exp ) ? esc_html__( 'Your PowerPack Elements Pro Discount is Expiring Soon!', 'edd-usage-tracker' ) : $subject_disc_exp;
		$heading_disc_exp = trim( edd_get_option( 'edd_ut_email_heading_disc_exp' ) );
		$heading_disc_exp = empty( $heading_disc_exp ) ? esc_html__( 'Your Discount is Expiring Soon!', 'edd-usage-tracker' ) : $heading_disc_exp;
		$body_disc_exp = wpautop( trim( edd_get_option( 'edd_ut_email_template_disc_exp' ) ) );
		$body_disc_exp = empty( $body_disc_exp ) ? wpautop( $this->get_email_body_disc_exp() ) : $body_disc_exp;

		return array(
			'from_name' => $from_name,
			'from_email' => $from_email,
			'subject' => $subject,
			'heading'	=> $heading,
			'body'	=> $body,
			'subject_disc_exp' => $subject_disc_exp,
			'heading_disc_exp' => $heading_disc_exp,
			'body_disc_exp' => $body_disc_exp,
		);
	}

	private function send_discount( $email, $userdata, $url, $site_id ) {
		// Force use user email instead of site email.
		if ( is_email( $userdata['email'] ) ) {
			$email = $userdata['email'];
		}

		// Generate a 15 character code
		$code    = substr( md5( $email . $url ), 0, 15 );

		$details = array(
			'name'              => $email,
			'code'              => $code,
			'max'               => 1,
			'amount'            => '15',
			'start'             => '-1 day',
			'expiration'        => '+1 month',
			'type'              => 'percent',
			'use_once'          => true,
			'excluded-products' => array(),
		);
		$discount_id = edd_store_discount( $details );

		update_post_meta( $discount_id, '_edd_tracking_site_id', $site_id );
		update_post_meta( $discount_id, '_edd_tracking_userdata', maybe_serialize( $userdata ) );

		$email_fields = $this->get_email_fields();

		$from_name    = $email_fields['from_name'];
		$from_email   = $email_fields['from_email'];
		$subject      = $email_fields['subject'];
		$heading      = $email_fields['heading'];
		$body		  = $email_fields['body'];

		// Replace discount code.
		$body = str_replace( '{discount_code}', $code, $body );

		// Replace user name - first name.
		$body = str_replace( '{name}', $userdata['first_name'], $body );

		// Replace fullname.
		$fullname = $userdata['first_name'] . ' ' . $userdata['last_name'];
		$body = str_replace( '{fullname}', trim( $fullname ), $body );

		$headers  = 'From: ' . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>\r\n";
		$headers .= 'Reply-To: ' . $from_email . "\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: text/html; charset=utf-8\r\n";

		$attachments = array();

		$emails = EDD()->emails;

		$emails->__set( 'from_name', $from_name );
		$emails->__set( 'from_email', $from_email );
		$emails->__set( 'heading', $heading );
		$emails->__set( 'headers', $headers );

		$emails->send( $email, $subject, $body, $attachments );
	}

	public function send_expiring_notice() {

		$expiring = $this->get_expiring_discounts();

		if ( $expiring && is_array( $expiring ) ) {

			$email_fields = $this->get_email_fields();

			$from_name    = $email_fields['from_name'];
			$from_email   = $email_fields['from_email'];
			$subject      = $email_fields['subject_disc_exp'];
			$heading      = $email_fields['heading_disc_exp'];

			$headers    = 'From: ' . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>\r\n";
			$headers   .= 'Reply-To: ' . $from_email . "\r\n";
			$headers   .= "MIME-Version: 1.0\r\n";
			$headers   .= "Content-Type: text/html; charset=utf-8\r\n";

			$attachments = array();

			foreach ( $expiring as $discount ) {

				$email = $discount->post_title;
				$code  = edd_get_discount_code( $discount->ID );
				$userdata = get_post_meta( $discount->ID, '_edd_tracking_userdata', true );
				$userdata = maybe_unserialize( $userdata );

				$expiring_date = date( 'F j, Y', strtotime( '+1 month', strtotime( $discount->post_date ) ) );

				$body = $email_fields['body_disc_exp'];

				// Replace discount code.
				$body = str_replace( '{discount_code}', $code, $body );

				// Replace discount expire date.
				$body = str_replace( '{discount_expire_date}', $expiring_date, $body );

				// Replace user name - first name.
				$body = str_replace( '{name}', $userdata['first_name'], $body );

				// Replace fullname.
				$fullname = $userdata['first_name'] . ' ' . $userdata['last_name'];
				$body = str_replace( '{fullname}', trim( $fullname ), $body );

				$emails = EDD()->emails;

				$emails->__set( 'from_name', $from_name );
				$emails->__set( 'from_email', $from_email );
				$emails->__set( 'heading', $heading );
				$emails->__set( 'headers', $headers );

				$emails->send( $email, $subject, $body, $attachments );
			}
		}// End if().
	}

	public function get_expiring_discounts() {

		$args = array(
			'post_type'              => 'edd_discount',
			'nopaging'               => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'post_parent'            => 0,
			'date_query' => array(
			array(
					'after'     => '-24 days',
					'before'    => '-23 days',
					'inclusive' => true,
				),
			),
		);

		$query = new WP_Query;
		$keys  = $query->query( $args );
		if ( ! $keys ) {
			return false; // no expiring keys found
		}

		return $keys;
	}

	public function discount_codes_created() {
		$args = array(
			'post_type'              => 'edd_discount',
			'meta_key'               => '_edd_tracking_site_id',
			'nopaging'               => true,
			'fields'                 => 'ids',
			'suppress_filters'       => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		);
		$query = new WP_Query( $args );

		return $query->post_count;
	}

	public function discount_codes_redeemed() {
		$args = array(
			'post_type'  => 'edd_discount',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => '_edd_tracking_site_id',
				),
				array(
					'key'   => '_edd_discount_uses',
					'value' => 1,
				)
			),
			'nopaging' => true,
			'fields' => 'ids',
			'update_post_term_cache' => false,
		);
		$query = new WP_Query( $args );
		return $query->post_count;
	}

	public function get_unique_site_count() {
		$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB;
		return $sites_db->count( array(
			'number' => -1,
		) );
	}

	public function get_unique_user_count() {
		global $wpdb;

		$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB;
		$count    = $wpdb->get_var( "SELECT COUNT(DISTINCT email) FROM $sites_db->table_name" );
		return $count;
	}

	public function is_local_url( $url = '' ) {
		$is_local_url = false;

		// Trim it up
		$url = strtolower( trim( $url ) );

		// Need to get the host...so let's add the scheme so we can use parse_url
		if ( false === strpos( $url, 'http://' ) && false === strpos( $url, 'https://' ) ) {
			$url = 'http://' . $url;
		}

		$url_parts = parse_url( $url );
		$host      = ! empty( $url_parts['host'] ) ? $url_parts['host'] : false;

		if ( ! empty( $url ) && ! empty( $host ) ) {

			if ( false !== ip2long( $host ) ) {
				if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					$is_local_url = true;
				}
			} elseif ( 'localhost' === $host ) {
				$is_local_url = true;
			}

			$check_tlds = apply_filters( 'edd_ut_validate_tlds', true );
			if ( $check_tlds ) {
				$tlds_to_check = apply_filters( 'edd_ut_url_tlds', array(
					'.dev',
					'.local',
				) );

				foreach ( $tlds_to_check as $tld ) {
					if ( false !== strpos( $host, $tld ) ) {
						$is_local_url = true;
						continue;
					}
				}
			}

			if ( substr_count( $host, '.' ) > 1 ) {
				$subdomains_to_check = apply_filters( 'edd_ut_url_subdomains', array(
					'dev.',
				) );

				foreach ( $subdomains_to_check as $subdomain ) {
					if ( 0 === strpos( $host, $subdomain ) ) {
						$is_local_url = true;
						continue;
					}
				}
			}
		}// End if().

		return apply_filters( 'edd_ut_is_local_url', $is_local_url, $url );
	}


}

function edd_usage_tracking() {
	return IBX_EDD_Usage_Tracking::get_instance();
}
add_action( 'plugins_loaded', 'edd_usage_tracking' );

function edd_usage_tracking_install() {
	global $wpdb, $edd_logs;

	include_once IBX_EDD_UT_PLUGIN_DIR . 'includes/class-edd-usage-tracking-logs-db.php';
	include_once IBX_EDD_UT_PLUGIN_DIR . 'includes/class-edd-usage-tracking-sites-db.php';

	$version = get_option( 'edd_usage_tracking_version' );

	if ( empty( $version ) ) {

		// This is a new install or an update from pre 2.4, look to see if we have recurring products
		$has_checkins = $wpdb->get_col( "SELECT COUNT(DISTINCT meta_value) FROM $wpdb->postmeta WHERE meta_key = '_edd_log_url'" );

		if ( empty( $has_checkins ) ) {
			// Make sure this upgrade routine is never shown as needed
			edd_set_upgrade_complete( 'usage_tracking_11' );
		}
	}

	$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB();
	$sites_db->create_table();

	$logs_db  = new IBX_EDD_Usage_Tracking_Logs_DB();
	$logs_db->create_table();

	update_option( 'edd_usage_tracking_version', IBX_EDD_UT_VERSION, '', false );
}
register_activation_hook( __FILE__, 'edd_usage_tracking_install' );

