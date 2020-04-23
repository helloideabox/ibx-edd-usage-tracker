<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IBX_EDD_Usage_Tracking_Admin {

	private static $instance;

	private function __construct() {
		$this->hooks();
		$this->filters();
	}

	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof IBX_EDD_Usage_Tracking_Admin ) ) {
			self::$instance = new IBX_EDD_Usage_Tracking_Admin;
		}

		return self::$instance;
	}

	private function hooks() {
		add_action( 'admin_menu', array( $this, 'tracking_menu' ), 10 );
		add_action( 'edd_usage_tracking_tab_general', array( $this, 'general_tab' ) );
		add_action( 'edd_usage_tracking_tab_checkin_graph', array( $this, 'checkin_graph' ) );
		add_action( 'edd_usage_tracking_tab_email_template', array( $this, 'email_template_tab' ) );
		add_action( 'edd_usage_tracking_tab_subscribe', array( $this, 'subscribe_tab' ) );

		$this->save_settings_fields();
	}

	private function filters() {

	}

	public function tracking_menu() {
		$logs_db = new IBX_EDD_Usage_Tracking_Logs_DB;
		if ( ! $logs_db->table_exists( $logs_db->table_name ) ) {
			return;
		}

		add_submenu_page(
			'edit.php?post_type=download',
			__( 'Usage Tracking', 'edd-usage-tracking' ),
			__( 'Usage Tracking', 'edd' ),
			'view_shop_reports',
			'edd-usage-tracking',
			array( $this, 'usage_tracking_page' )
		);
	}

	public function admin_tabs() {
		$tabs = array(
			'general'       => __( 'General', 'edd-usage-tracking' ),
			'sites'         => __( 'Sites', 'edd-usage-tracking' ),
			'logs'          => __( 'Logs', 'edd-usage-tracking' ),
			'checkin_graph' => __( 'Check-In Graph', 'edd-usage-tracking' ),
			'email_template' => __( 'Email', 'edd-usage-tracking' ),
			'subscribe' => __( 'Subscribe', 'edd-usage-tracking' ),
		);

		return $tabs;
	}

	public function usage_tracking_page() {
		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';
	?>
		<div class="wrap">
			<h1 class="nav-tab-wrapper">
				<?php
				foreach( $this->admin_tabs() as $tab_id => $tab_name ) {

					$tab_url = add_query_arg( array(
						'tab' => $tab_id
					) );

					$tab_url = remove_query_arg( array(
						'edd-message',
						'site_id',
						'url',
						'email',
						'paged',
						's',
						'_wpnonce',
						'_wp_http_referer',
						'view',
						'section',
						'service'
					), $tab_url );

					$active = $active_tab == $tab_id ? ' nav-tab-active' : '';
					echo '<a href="' . esc_url( $tab_url ) . '" title="' . esc_attr( $tab_name ) . '" class="nav-tab' . $active . '">' . esc_html( $tab_name ) . '</a>';

				}
				?>
			</h1>
			<div class="metabox-holder">
				<?php do_action( 'edd_usage_tracking_tab_' . $active_tab ); ?>
			</div><!-- .metabox-holder -->
		</div>
		<?php
	}

	public function general_tab() {
		?>
		<style>
			.edd-mix-totals .edd-mix-chart:not(:last-child) {
				border-bottom: 1px solid #f1f1f1;
				min-height: 500px;
			}
			.edd-mix-chart {
				margin-bottom: 25px;
			}
		</style>
		<script>
			var eddUTLabelFormatter = function (label, series) {
				if ( label.length == 0 ) {
					label = 'Unreported';
				} else if ( label.length > 12 ) {
					label = label.substring( 0, 12 ) + '&hellip;';
				}

				var percent = series.percent < 100 ? series.percent.toFixed(2) : series.percent;

				return '<div style="background-color: #fefefe; font-size:12px; text-align:center; padding:3px"><strong>' + label + '</strong><br />' + percent + '%</div>';
			}

			var eddUTLegendFormatter = function (label, series) {
				var display_label = label;

				if ( label.length == 0 ) {
					display_label = 'Unreported';
				}

				var slug    = label.toLowerCase().replace(/\s/g, '-');
				var percent = series.percent < 100 ? series.percent.toFixed(2) : series.percent;
				var color = '<div class="edd-legend-color" style="background-color: ' + series.color + '"></div>';
				var value = '<div class="edd-pie-legend-item">' + display_label + ': ' + Math.round(series.percent) + '% (' + eddFormatNumber(series.data[0][1]) + ')</div>';
				var item = '<div id="' + series.edd_vars.id + slug + '" class="edd-legend-item-wrapper">' + color + value + '</div>';

				jQuery('#edd-pie-legend-' + series.edd_vars.id).append( item );
				return item;
			}
		</script>
		<div class="inside">
			<?php
			// Use minified libraries if SCRIPT_DEBUG is turned off
			$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			wp_enqueue_script( 'jquery-flot', EDD_PLUGIN_URL . 'assets/js/jquery.flot' . $suffix . '.js' );

			do_action( 'edd_graph_load_scripts' );
			?>

			<div class="edd-mix-totals">
				<div class="edd-mix-chart">
					<strong><?php _e( 'EDD Versions: ', 'edd-usage-tracking' ); ?></strong>
					<?php $this->show_version_graph( 'edd_version', true ); ?>
				</div>

				<div class="edd-mix-chart">
					<strong><?php _e( 'PHP Versions: ', 'edd-usage-tracking' ); ?></strong>
					<?php $this->show_version_graph( 'php_version' ); ?>
				</div>

				<div class="edd-mix-chart">
					<strong><?php _e( 'WP Versions: ', 'edd-usage-tracking' ); ?></strong>
					<?php $this->show_version_graph( 'wp_version' ); ?>
				</div>

				<div class="edd-mix-chart">
					<strong><?php _e( 'Servers: ', 'edd-usage-tracking' ); ?></strong>
					<?php $this->show_string_graph( 'server' ); ?>
				</div>

				<div class="edd-mix-chart">
					<strong><?php _e( 'Locales: ', 'edd-usage-tracking' ); ?></strong>
					<?php $this->show_string_graph( 'locale' ); ?>
				</div>
			</div>

		</div>
		<?php
	}

	public function checkin_graph() {
		global $wpdb;
		$logs_db = new IBX_EDD_Usage_Tracking_Logs_DB;
		$weeks   = array();

		$previous_sunday = strtotime( 'last sunday' );
		$sunday          = strtotime( '-52 weeks', $previous_sunday );

		$i = 0;
		while ( $i <= 52 ) {
			$weeks[] = array(
				'start' => date( 'Y-m-d 00:00:00', $sunday ),
				'end'   => date( 'Y-m-d 23:59:50', strtotime( 'next saturday', $sunday ) ),
			);
			$sunday = strtotime( 'next sunday', $sunday );
			$i++;
		}

		$data = array();
		$args = array(
			'number' => -1,
		);

		foreach ( $weeks as $key => $week ) {

			$args['date']     = $week;
			$sunday_timestamp = strtotime( $week['start'] );

			$month = date( 'm', $sunday_timestamp );
			$day   = date( 'd', $sunday_timestamp );
			$year  = date( 'Y', $sunday_timestamp );

			$date_key = mktime( 0, 0, 0, $month, $day, $year ) * 1000;

			$sql    = $wpdb->prepare( "SELECT COUNT( DISTINCT( site_id ) ) FROM $logs_db->table_name WHERE checkin_date BETWEEN '%s' AND '%s'", $week['start'], $week['end'] );
			$count  = $wpdb->get_var( $sql );
			$data[] = array( $date_key, $count );

		}

		$final_data = array(
			__( 'Active Checkins', 'edd-usage-tracking' ) => $data,
		);
		?>
		<div class="inside">
			<?php
			// Use minified libraries if SCRIPT_DEBUG is turned off
			$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			wp_enqueue_script( 'jquery-flot', EDD_PLUGIN_URL . 'assets/js/jquery.flot' . $suffix . '.js' );

			do_action( 'edd_graph_load_scripts' );

			$graph = new EDD_Graph( $final_data );
			$graph->set( 'x_mode', 'time' );
			$graph->set( 'multiple_y_axes', false );
			$graph->display();
			?>
		</div>
		<?php

	}

	private function graph_options() {
		return array(
			'height'          => 350,
			'legend_formatter' => 'eddUTLegendFormatter',
		);
	}

	private function show_string_graph( $column = '' ) {
		global $wpdb;

		$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB();
		$sql      = "SELECT $column, COUNT(*) as count FROM $sites_db->table_name WHERE $column IS NOT NULL GROUP BY $column ORDER BY count DESC";
		$results  = $wpdb->get_results( $sql );

		$data = array();
		foreach( $results as $result ) {
			$key          = esc_attr( $result->$column );
			$break        = strcspn( $key, '()-/' );
			$key          = substr( $key, 0, $break );
			$key          = ucfirst( strtolower( preg_replace( "/[^a-zA-Z_ ]+/", "", $key ) ) );

			if ( empty( $key ) ) {
				$key = 'Unreported';
			}

			if ( empty( $data[ $key ] ) ) {
				$data[ $key ] = $result->count;
			} else {
				$data[ $key ] += $result->count;
			}

		}

		arsort( $data );

		$total_items = array_sum( $data );
		$others = 0;

		$pie_graph = new EDD_Pie_Graph( $data, $this->graph_options() );
		$pie_graph->display();
	}

	private function show_version_graph( $column = '', $minor = false ) {
		global $wpdb;

		$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB();
		$sql      = "SELECT $column, COUNT(*) as count FROM $sites_db->table_name WHERE $column IS NOT NULL GROUP BY $column ORDER BY count DESC";
		$results  = $wpdb->get_results( $sql );

		$data = array();
		foreach( $results as $result ) {
			$key          = strtolower( $result->$column );

			if ( false === $minor ) {

				$first_letter = strcspn( $key, 'abcdefghijklmnopqrstuvwxyz-/' );
				$key          = substr( $key, 0, $first_letter );

				if ( substr_count( $key, '.' ) > 1 ) {
					$pos1 = strpos( $key, '.' );
					$pos2 = strpos( $key, '.', $pos1 + strlen( '.' ) );
					$key  = substr( $key, 0, $pos2 );
				}

			}

			if ( empty( $key ) ) {
				$key = 'Unreported';
			}

			if ( empty( $data[ $key ] ) ) {
				$data[ $key ] = $result->count;
			} else {
				$data[ $key ] += $result->count;
			}

		}

		ksort( $data );

		$pie_graph = new EDD_Pie_Graph( $data, $this->graph_options() );
		$pie_graph->display();
	}

	public function email_template_tab() {
		$current_section = isset( $_GET['section'] ) ? $_GET['section'] : 'discount_code';
		$email_fields = IBX_EDD_Usage_Tracking::get_instance()->get_email_fields();
		$from_name    = $email_fields['from_name'];
		$from_email   = $email_fields['from_email'];
		?>
		<style>
		.form-table textarea {
			min-width: 460px;
			height: 280px;
		}
		.form-table input[type="text"],
		.form-table input[type="email"] {
			min-width: 300px;
		}
		</style>
		<div class="wp-clearfix">
			<ul class="subsubsub">
				<li>
					<a 
						href="<?php echo add_query_arg( 'section', 'discount_code' ); ?>"
						class="<?php echo 'discount_code' === $current_section ? 'current' : ''; ?>"
					>
						<?php _e( 'Discount Code', 'edd-usage-tracker' ); ?>
					</a> | 
				</li>
				<li>
					<a 
						href="<?php echo add_query_arg( 'section', 'discount_expire' ); ?>"
						class="<?php echo 'discount_expire' === $current_section ? 'current' : ''; ?>"
					>
						<?php _e( 'Discount Expire', 'edd-usage-tracker' ); ?>
					</a>
				</li>
			</ul>
		</div>
		<form method="post">
		<table class="form-table">
			<tr valign="top">
				<th><label for="edd_ut_email_from_name"><?php _e( 'From Name', 'edd-usage-tracker' ); ?></label></th>
				<td>
					<input id="edd_ut_email_from_name" name="edd_ut_email_from_name" type="text" value="<?php echo $from_name; ?>" />
				</td>
			</tr>
			<tr valign="top">
				<th><label for="edd_ut_email_from_email"><?php _e( 'From Email', 'edd-usage-tracker' ); ?></label></th>
				<td>
					<input id="edd_ut_email_from_email" name="edd_ut_email_from_email" type="email" value="<?php echo $from_email; ?>" />
				</td>
			</tr>
			<?php
			if ( 'discount_code' === $current_section ) {
				// Discount code email fields.
				$subject    = $email_fields['subject'];
				$heading    = $email_fields['heading'];
				$body      	= $email_fields['body'];
			?>
			<tr valign="top">
				<th><label for="edd_ut_email_subject"><?php _e( 'Subject', 'edd-usage-tracker' ); ?></label></th>
				<td>
					<input id="edd_ut_email_subject" name="edd_ut_email_subject" type="text" value="<?php echo $subject; ?>" />
				</td>
			</tr>
			<tr valign="top">
				<th><label for="edd_ut_email_heading"><?php _e( 'Heading', 'edd-usage-tracker' ); ?></label></th>
				<td>
					<input id="edd_ut_email_heading" name="edd_ut_email_heading" type="text" value="<?php echo $heading; ?>" />
				</td>
			</tr>
			<tr valign="top">
				<th><label for="edd_ut_email_template"><?php _e( 'Body', 'edd-usage-tracker' ); ?></label></th>
				<td>
					<?php wp_editor( $body, 'edd_ut_email_template', $settings = array( 'textarea_name' => 'edd_ut_email_template' ) ); ?>
					<p class="description">
						{name} - User's first name.<br />
						{fullname} - User's first and last name.<br />
						{discount_code} - Auto generated discount code.
					</p>
				</td>
			</tr>
			<?php } ?>

			<?php
			if ( 'discount_expire' === $current_section ) { // Discount expire email fields.
				$subject    = $email_fields['subject_disc_exp'];
				$heading    = $email_fields['heading_disc_exp'];
				$body      	= $email_fields['body_disc_exp'];
			?>
			<tr valign="top">
				<th><label for="edd_ut_email_subject_disc_exp"><?php _e( 'Subject', 'edd-usage-tracker' ); ?></label></th>
				<td>
					<input id="edd_ut_email_subject_disc_exp" name="edd_ut_email_subject_disc_exp" type="text" value="<?php echo $subject; ?>" />
				</td>
			</tr>
			<tr valign="top">
				<th><label for="edd_ut_email_heading_disc_exp"><?php _e( 'Heading', 'edd-usage-tracker' ); ?></label></th>
				<td>
					<input id="edd_ut_email_heading_disc_exp" name="edd_ut_email_heading_disc_exp" type="text" value="<?php echo $heading; ?>" />
				</td>
			</tr>
			<tr valign="top">
				<th><label for="edd_ut_email_template_disc_exp"><?php _e( 'Body', 'edd-usage-tracker' ); ?></label></th>
				<td>
					<?php wp_editor( $body, 'edd_ut_email_template_disc_exp', $settings = array( 'textarea_name' => 'edd_ut_email_template_disc_exp' ) ); ?>
					<p class="description">
						{name} - User's first name.<br />
						{fullname} - User's first and last name.<br />
						{discount_code} - Auto generated discount code.<br />
						{discount_expire_date} - Discount expiring date.
					</p>
				</td>
			</tr>
			<?php } ?>
		</table>
		<?php
		wp_nonce_field( 'edd_ut_admin_settings', 'edd_ut_admin_settings' );
		submit_button();
		?>
		</form>
		<?php
	}

	public function subscribe_tab() {
		if ( isset( $_GET['service'] ) ) {
			$service = $_GET['service'];
		} else {
			$service = edd_get_option( 'edd_ut_subscribe_service' );
		}
		$services = (array) apply_filters( 'ibx_edd_ut_subscribe_services', array() );
		if ( class_exists( 'EDD_Mailerlite' ) ) {
			$services['mailerlite'] = 'EDD Mailerlite';
		}
		if ( class_exists( 'EDD_MailChimp_List' ) ) {
			$services['mailchimp'] = 'EDD MailChimp';
		}

		$group = edd_get_option( 'edd_ut_subscribe_service_group' );
		?>
		<form method="post">
		<table class="form-table">
			<tr valign="top">
				<th><label for="edd_ut_subscribe_service"><?php _e( 'Service', 'edd-usage-tracker' ); ?></label></th>
				<td>
					<select name="edd_ut_subscribe_service" id="edd_ut_subscribe_service">
						<option value=""><?php esc_html_e( 'None', 'edd-usage-tracker' ); ?></option>
						<?php
						if ( ! empty( $services ) ) {
							foreach ( $services as $key => $label ) {
						?>
						<option value="<?php echo $key; ?>" <?php selected( $service, $key, true ); ?>><?php echo $label; ?></option>
						<?php
							}
						}
						?>
					</select>
				</td>
			</tr>
			<?php if ( $service && isset( $services[ $service ] ) ) { ?>
			<tr valign="top">
				<th><label for="edd_ut_subscribe_service_group"><?php _e( 'Group', 'edd-usage-tracker' ); ?></label></th>
				<td>
					<select id="edd_ut_subscribe_service_group" name="edd_ut_subscribe_service_group">
						<?php if ( 'mailerlite' === $service && function_exists( 'edd_ml_settings_get_group_options' ) ) { ?>
							<?php
								$groups = edd_ml_settings_get_group_options();
								foreach ( $groups as $id => $name ) {
									?>
									<option value="<?php echo $id; ?>" <?php selected( $group, $id, true ); ?>><?php echo $name; ?></option>
									<?php
								}
							?>
						<?php } ?>
						<?php if ( 'mailchimp' === $service && class_exists( 'EDD_MailChimp_List' ) ) { ?>
							<?php
							try {
								$result = EDD_MailChimp_List::all();
							} catch (Exception $e) {
								?>
								<option value=""><?php _e('Please supply a valid MailChimp API key.', 'edd-usage-tracker'); ?></option>
								<?php
							}
							if ( isset( $result ) && isset( $result['lists'] ) && ! empty( $result['lists'] ) ) {
								foreach ( $result['lists'] as $list ) {
									?>
									<option value="<?php echo $list['id']; ?>" <?php selected( $group, $list['id'], true ); ?>><?php echo $list['name']; ?></option>
									<?php
								}
							}
							?>
						<?php } ?>
					</select>
				</td>
			</tr>
			<?php } ?>
		</table>
		<?php
		wp_nonce_field( 'edd_ut_admin_settings', 'edd_ut_admin_settings' );
		submit_button();
		?>
		</form>
		<script>
			(function($) {
				$('#edd_ut_subscribe_service').on('change', function() {
					<?php $url = add_query_arg( 'service', $service ); ?>
					var url = '<?php echo $url; ?>'.split('&service')[0];
					url += '&service=' + $(this).val();
					window.location.href = url;
				});
			})(jQuery);
		</script>
		<?php
	}

	private function save_settings_fields() {
		if ( ! isset( $_POST['edd_ut_admin_settings'] ) || ! wp_verify_nonce( $_POST['edd_ut_admin_settings'], 'edd_ut_admin_settings' ) ) {
			return;
		}

		if ( isset( $_POST['edd_ut_email_from_name'] ) ) {
			edd_update_option( 'edd_ut_email_from_name', sanitize_text_field( $_POST['edd_ut_email_from_name'] ) );
		}
		if ( isset( $_POST['edd_ut_email_from_email'] ) ) {
			edd_update_option( 'edd_ut_email_from_email', sanitize_text_field( $_POST['edd_ut_email_from_email'] ) );
		}
		if ( isset( $_POST['edd_ut_email_subject'] ) ) {
			edd_update_option( 'edd_ut_email_subject', sanitize_text_field( $_POST['edd_ut_email_subject'] ) );
		}
		if ( isset( $_POST['edd_ut_email_subject_disc_exp'] ) ) {
			edd_update_option( 'edd_ut_email_subject_disc_exp', sanitize_text_field( $_POST['edd_ut_email_subject_disc_exp'] ) );
		}
		if ( isset( $_POST['edd_ut_email_heading'] ) ) {
			edd_update_option( 'edd_ut_email_heading', sanitize_text_field( $_POST['edd_ut_email_heading'] ) );
		}
		if ( isset( $_POST['edd_ut_email_heading_disc_exp'] ) ) {
			edd_update_option( 'edd_ut_email_heading_disc_exp', sanitize_text_field( $_POST['edd_ut_email_heading_disc_exp'] ) );
		}
		if ( isset( $_POST['edd_ut_email_template'] ) ) {
			add_filter( 'safe_style_css', array( $this, 'safe_style_css' ) );
			$template = wp_kses_post( $_POST['edd_ut_email_template'] );
			remove_filter( 'safe_style_css', array( $this, 'safe_style_css' ) );
			edd_update_option( 'edd_ut_email_template', $template );
		}
		if ( isset( $_POST['edd_ut_email_template_disc_exp'] ) ) {
			add_filter( 'safe_style_css', array( $this, 'safe_style_css' ) );
			$template = wp_kses_post( $_POST['edd_ut_email_template_disc_exp'] );
			remove_filter( 'safe_style_css', array( $this, 'safe_style_css' ) );
			edd_update_option( 'edd_ut_email_template_disc_exp', $template );
		}

		// Subscribe settings.
		if ( isset( $_POST['edd_ut_subscribe_service'] ) ) {
			edd_update_option( 'edd_ut_subscribe_service', sanitize_text_field( $_POST['edd_ut_subscribe_service'] ) );
		}
	}

	public function safe_style_css( $styles ) {
		$styles[] = 'display';
    	return $styles;
	}
}

$usage_tracking_admin = IBX_EDD_Usage_Tracking_Admin::get_instance();
