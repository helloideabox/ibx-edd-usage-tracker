<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

function edd_usage_tracking_site_list() {
	if ( isset( $_GET['site_id'] ) && is_numeric( $_GET['site_id'] ) ) {

		do_action( 'edd_ut_site_profile', $_GET['site_id'] );

	} else {
		?>
		<style>
			th.column-url { width: 150px; }
		</style>
		<?php

		$logs_table = new IBX_EDD_Site_Tracking_Sites_Table();
		$logs_table->prepare_items();
		?>
		<form id="edd-logs-filter" method="get" action="<?php echo admin_url( 'edit.php?post_type=download&page=edd-reports&tab=sites' ); ?>">
			<?php
			$logs_table->search_box( __( 'Search', 'edd' ), 'edd-logs' );
			$logs_table->views();
			$logs_table->display();
			?>
			<input type="hidden" name="post_type" value="download" />
			<input type="hidden" name="page" value="edd-usage-tracking" />
			<input type="hidden" name="tab" value="sites" />
			<input type="hidden" name="view" value="<?php echo $logs_table->view; ?>" />
		</form>
		Available Searches:
		<code>email@example.org</code>, <code>url:</code>, <code>edd:</code>, <code>wp:</code>, <code>php:</code>, <code>server:</code>, <code>theme:</code>, <code>plugin:</code>, <code>inactive_plugin:</code>, <code>label:</code>, <code>locale:</code>
		<?php

	}

}
add_action( 'edd_usage_tracking_tab_sites', 'edd_usage_tracking_site_list' );

function edd_ut_display_site_profile( $site_id ) {
	$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB;
	$site     = $sites_db->get( $site_id );

	$logs_db  = new IBX_EDD_Usage_Tracking_Logs_DB;
	?>
	<div class='wrap'>
		<a href="javascript:history.back()">&larr;Go Back</a>
		<h2><?php _e( 'Site Details', 'edd-usage-tracking' );?></h2>

		<?php if ( $site ) : ?>

			<div id="edd-item-card-wrapper" class="edd-customer-card-wrapper" style="float: left">

				<div class="info-wrapper customer-section wp-clearfix">

					<div class="edd-item-info customer-info">

						<div class="customer-id right">
							#<?php echo $site->id; ?>
						</div>

						<div class="customer-main-wrapper left">

							<span class="customer-name info-item"><?php echo $site->url; ?></span>
							<span class="customer-since info-item">
								<?php _e( 'Site Email: ', 'edd-usage-tracking' ); ?>
								<?php echo $site->email; ?>
							</span>
							<span class="customer-since info-item">
								<?php _e( 'User First Name: ', 'edd-usage-tracking' ); ?>
								<?php echo $site->user_firstname; ?>
							</span>
							<span class="customer-since info-item">
								<?php _e( 'User Last Name: ', 'edd-usage-tracking' ); ?>
								<?php echo $site->user_lastname; ?>
							</span>
							<span class="customer-since info-item">
								<?php _e( 'User Email: ', 'edd-usage-tracking' ); ?>
								<?php echo $site->user_email; ?>
							</span>
							<span class="customer-since info-item">
								<?php _e( 'Created: ', 'edd-usage-tracking' ); ?>
								<?php echo ! empty( $site->install_date ) ? date( 'm/d/Y', strtotime( $site->install_date ) ) : 'Unknown'; ?>
							</span>
							<span class="customer-since info-item">
								<?php _e( 'Last Checkin: ', 'edd-usage-tracking' ); ?>
								<?php
								$last_checkin = $logs_db->get_column( 'checkin_date', $site->last_checkin );
								echo ! empty( $last_checkin ) ? date( 'm/d/Y', strtotime( $last_checkin ) ) : 'Unknown';
								?>
							</span>
							<span class="customer-since info-item">
								<?php _e( 'Multisite: ', 'edd-usage-tracking' ); ?>
								<?php echo empty( $site->multisite ) ? 'No' : 'Yes'; ?>
							</span>
							<span class="customer-since info-item">
								<?php _e( 'Theme: ', 'edd-usage-tracking' ); ?>
								<?php echo empty( $site->theme ) ? '<em>n/a</em>' : $site->theme; ?>
							</span>
							<span class="customer-since info-item">
								<?php _e( 'Product Count: ', 'edd-usage-tracking' ); ?>
								<?php echo empty( $site->products ) ? 0 : $site->products; ?>
							</span>
							<span class="customer-since info-item">
								<?php _e( 'Download Label: ', 'edd-usage-tracking' ); ?>
								<?php echo empty( $site->download_label ) ? '<em>n/a</em>' : $site->download_label; ?>
							</span>
							<span class="customer-since info-item">
								<?php _e( 'Site Locale: ', 'edd-usage-tracking' ); ?>
								<?php echo empty( $site->locale ) ? '<em>n/a</em>' : $site->locale; ?>
							</span>
						</div>

					</div>

				</div>

				<div id="edd-item-stats-wrapper" class="customer-stats-wrapper customer-section">
					<ul>
						<li>
							<span class="dashicons dashicons-download"></span>
							<?php echo $site->edd_version; ?>
						</li>
						<li>
							<strong>PHP</strong>
							<?php echo $site->php_version; ?>
						</li>
						<li>
							<span class="dashicons dashicons-wordpress"></span>
							<?php echo $site->wp_version; ?>
						</li>
						<li>
							<span class="dashicons dashicons-desktop"></span>
							<?php echo $site->server; ?>
						</li>
					</ul>
				</div>

				<div id="edd-item-tables-wrapper" class="customer-tables-wrapper customer-section">


					<h3><?php _e( 'Plugins', 'edd-usage-tracking' ); ?></h3>
					<?php
						$plugins = array();
						$active_plugins = json_decode( $site->active_plugins );
						$inactive_plugins = json_decode( $site->inactive_plugins );

						if ( is_array( $active_plugins ) ) {
							foreach ( $active_plugins as $plugin ) {
								$plugins[ $plugin ] = 'active';
							}
						}

						if ( is_array( $inactive_plugins ) ) {
							foreach ( $inactive_plugins as $plugin ) {
								$plugins[ $plugin ] = 'inactive';
							}
						}

						ksort( $plugins );
					?>
					<table class="wp-list-table widefat striped payments">
						<thead>
							<tr>
								<th><?php _e( 'Plugin', 'edd-usage-tracking' ); ?></th>
								<th><?php _e( 'Status', 'edd-usage-tracking' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $plugins ) ) : ?>
								<?php foreach ( $plugins as $plugin => $status ) : ?>
									<tr>
										<td><?php echo $plugin; ?></td>
										<td><?php echo $status; ?></td>
									</tr>
								<?php endforeach; ?>
							<?php else: ?>
								<tr><td colspan="2"><?php _e( 'No Plugins Reported', 'edd-usage-tracking' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>

				</div>

			</div>

		<?php endif; ?>

	</div>
	<?php
}
add_action( 'edd_ut_site_profile', 'edd_ut_display_site_profile', 10, 1 );

/**
 * EDD_Site_Tracking_Logs_Table Class
 *
 * Renders the file downloads log view
 *
 * @since 1.4
 */
class IBX_EDD_Site_Tracking_Sites_Table extends WP_List_Table {

	/**
	 * Number of items per page
	 *
	 * @var int
	 * @since 1.4
	 */
	public $per_page = 15;

	/**
	 * Base URL
	 *
	 * @var int
	 * @since 1.4
	 */
	public $base;

	public $search_args = NULL;
	public $view = 'all';

	/**
	 * Get things started
	 *
	 * @since 1.4
	 * @see WP_List_Table::__construct()
	 */
	public function __construct() {
		global $status, $page;

		// Set parent defaults
		parent::__construct( array(
			'singular'  => 'edd-ut-site',
			'plural'    => 'edd-ut-sites',
			'ajax'      => false,
		) );

		$this->base = admin_url( 'edit.php?post_type=download&page=edd-usage-tracking&tab=sites' );
		if ( isset( $_GET['s'] ) ) {
			$this->base = add_query_arg( array( 's' => $_GET['s'] ), $this->base );
		}
		$this->set_view();
	}

	/**
	 * Show the search field
	 *
	 * @since 1.4
	 * @access public
	 *
	 * @param string $text Label for the search box
	 * @param string $input_id ID of the search box
	 *
	 * @return void
	 */
	public function search_box( $text, $input_id ) {
		$input_id = $input_id . '-search-input';

		if ( ! empty( $_REQUEST['orderby'] ) )
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		if ( ! empty( $_REQUEST['order'] ) )
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
			<input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>" />
			<?php submit_button( $text, 'button', false, false, array('ID' => 'search-submit') ); ?>
		</p>
		<?php
	}

	protected function get_primary_column_name() {
		return 'url';
	}

	/**
	 * Retrieve the table columns
	 *
	 * @access public
	 * @since 1.4
	 * @return array $columns Array of all the list table columns
	 */
	public function get_columns() {
		$columns = array(
			'url'            => __( 'URL', 'edd' ),
			'edd_version'    => __( 'EDD', 'edd-usage-tracking' ),
			'php_version'    => __( 'PHP', 'edd-usage-tracking' ),
			'wp_version'     => __( 'WP', 'edd-usage-tracking' ),
			'server'         => __( 'Server', 'edd-usage-tracking' ),
			'multisite'      => __( 'Multisite', 'edd-usage-tracking' ),
			'install_date'   => __( 'Install Date', 'edd-usage-tracking' ),
			'theme'          => __( 'Theme', 'edd' ),
			'email'          => __( 'Email', 'edd' ),
			'plugins'        => __( 'Plugins', 'edd' ),
			'products'       => __( 'Products', 'edd-usage-tracking' ),
			'download_label' => __( 'Label', 'edd-usage-tracking' ),
			'locale'         => __( 'Locale', 'edd-usage-tracking' ),
		);
		return $columns;
	}

	/**
	 * Setup available views
	 *
	 * @access      private
	 * @since       1.0
	 * @return      array
	 */

	function get_views() {

		$base = $this->base;
		$current = $this->view;

		$link_html = '<a href="%s"%s>%s</a>(%s)';

		$sites_db  = new IBX_EDD_Usage_Tracking_Sites_DB;
		$all_args           = $this->parse_search();
		$all_args['status'] = 'all';
		$all_count          = $sites_db->count( $all_args );

		$active_args           = $this->parse_search();
		$active_args['status'] = 'active';
		$active_count          = $sites_db->count( $active_args );

		$inactive_args           = $this->parse_search();
		$inactive_args['status'] = 'inactive';
		$inactive_count          = $sites_db->count( $inactive_args );

		$views = array(
			'all'      => sprintf( $link_html,
				esc_url( remove_query_arg( 'view', $base ) ),
				$current === 'all' || $current == '' ? ' class="current"' : '',
				esc_html__( 'All', 'edd_sl' ),
				$all_count
			),
			'active'   => sprintf( $link_html,
				esc_url( add_query_arg( 'view', 'active', $base ) ),
				$current === 'active' ? ' class="current"' : '',
				esc_html__( 'Active', 'edd_sl' ),
				$active_count
			),
			'inactive' => sprintf( $link_html,
				esc_url( add_query_arg( 'view', 'inactive', $base ) ),
				$current === 'inactive' ? ' class="current"' : '',
				esc_html__( 'Inactive', 'edd_sl' ),
				$inactive_count
			),
		);

		return $views;

	}

	/**
	 * This function renders most of the columns in the list table.
	 *
	 * @access public
	 * @since 1.4
	 *
	 * @param array $item Contains all the data of the discount code
	 * @param string $column_name The name of the column
	 *
	 * @return string Column Name
	 */
	public function column_default( $item, $column_name ) {
		if( 'site_id' === $column_name ) {
			return;
		}

		switch ( $column_name ) {

			case 'email' :
				return '<a href="' . add_query_arg( 'email', $item['email'], $this->base ) . '">' . $item['email'] . '</a>';

			case 'multisite':
				return empty( $item['multisite'] ) ? 'No' : 'Yes';

			case 'install_date':
				$date = '00/00/00 00:00:00' === $item['install_date'] || strtotime( $item['install_date'] ) ? date( 'm/d/y', strtotime( $item['install_date'] ) ) : '&mdash;';
				return $date;

			default:
				return ! empty( $item[ $column_name ] ) ? $item[ $column_name ] : '&mdash;';

		}
	}

	public function column_url( $item ) {
		$logs_base = admin_url( 'edit.php?post_type=download&page=edd-usage-tracking&tab=logs' );
		$site_url  = untrailingslashit( str_replace( array( 'http://', 'https://' ), '', $item['url'] ) );

		$actions = array();
		$actions['view_logs']    = sprintf( '<a href="%s" >Logs</a>', add_query_arg( 'site_id', $item['id'], $logs_base ) );
		$actions['view_profile'] = sprintf( '<a href="%s" >Profile</a>', add_query_arg( 'site_id', $item['id'], $this->base ) );
		$actions['view_site']    = sprintf( '<a href="%s" target="_blank">Visit</a>', esc_url( $item['url'] ) );

		return esc_html( $site_url ) . $this->row_actions( $actions );
	}

	/**
	 * Retrieve the current page number
	 *
	 * @access public
	 * @since 1.4
	 * @return int Current page number
	 */
	function get_paged() {
		return isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	}

	public function parse_search() {
		if ( ! is_null( $this->search_args ) ) {
			return $this->search_args;
		}

		$search_args = array();

		if ( ! empty( $_GET['email'] ) ) {
			$_GET['s'] = $_GET['email'];
			unset( $_GET['email'] );
		}

		// Parse out a search
		if ( isset( $_GET['s'] ) ) {
			$search = $_GET['s'];
			unset( $_GET['s'] );

			// Email search
			if ( is_email( $search ) ) {
				$search_args['email'] = sanitize_email( $search );
			} elseif( strpos( $search, 'edd:' ) !== false ) {
				$search                     = str_replace( 'edd:', '', $search );
				$search_args['edd_version'] = sanitize_text_field( $search );
			} elseif( strpos( $search, 'php:' ) !== false ) {
				$search                     = str_replace( 'php:', '', $search );
				$search_args['php_version'] = sanitize_text_field( $search );
			} elseif( strpos( $search, 'wp:' ) !== false ) {
				$search                    = str_replace( 'wp:', '', $search );
				$search_args['wp_version'] = sanitize_text_field( $search );
			} elseif( strpos( $search, 'server:' ) !== false ) {
				$search                = str_replace( 'server:', '', $search );
				$search_args['server'] = sanitize_text_field( $search );
			} elseif( strpos( $search, 'theme:' ) !== false ) {
				$search               = str_replace( 'theme:', '', $search );
				$search_args['theme'] = sanitize_text_field( $search );
			} elseif( strpos( $search, 'plugin:' ) !== false ) {
				$search                = str_replace( 'plugin:', '', $search );
				$search_args['plugin'] = sanitize_text_field( $search );
			} elseif( strpos( $search, 'inactive_plugin:' ) !== false ) {
				$search                = str_replace( 'inactive_plugin:', '', $search );
				$search_args['inactive_plugin'] = sanitize_text_field( $search );
			} elseif( strpos( $search, 'label:' ) !== false ) {
				$search                        = str_replace( 'label:', '', $search );
				$search_args['download_label'] = sanitize_text_field( $search );
			} elseif ( strpos( $search, 'url:' ) !== false ) {
				$search             = str_replace( 'url:', '', $search );
				$search_args['url'] = sanitize_text_field( $search );
			}

		}

		$search_args['status'] = $this->view;
		$this->search_args     = $search_args;

		return $search_args;
	}

	/**
	 * Gets the log entries for the current view
	 *
	 * @access public
	 * @since 1.4
	 * @global object $edd_logs EDD Logs Object
	 * @return array $logs_data Array of all the Log entires
	 */
	function get_sites() {
		$paged     = $this->get_paged();
		$offset    = 1 == $paged ? 0 : ( $paged - 1 ) * $this->per_page;
		$args      = array(
			'offset' => $offset,
			'number' => $this->per_page,
		);

		$args     = array_merge( $args, $this->parse_search() );
		$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB;

		$sites = $sites_db->get_logs( $args );
		foreach ( $sites as $key => $site ) {
			$active_count = count( json_decode( $site['active_plugins'] ) );
			$total = count( json_decode( $site['active_plugins'] ) ) + count( json_decode( $site['inactive_plugins'] ) );
			$sites[ $key ]['plugins'] = '<span style="color:green;""><strong>' . $active_count . '</strong></span> / <span style="color:gray;">' . $total . '</span>';
		}


		return $sites;
	}

	private function get_total_count() {
		$args = array(
			'paged'  => false,
			'number' => -1,
		);

		$args     = array_merge( $args, $this->parse_search() );
		$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB;
		$count    = $sites_db->count( $args );

		return $count;
	}

	public function set_view() {
		$this->view = isset( $_GET['view'] ) ? $_GET['view'] : 'all';
	}

	/**
	 * Setup the final data for the table
	 *
	 * @access public
	 * @since 1.5
	 * @global object $edd_logs EDD Logs Object
	 * @uses EDD_Site_Tracking_Logs_Table::get_columns()
	 * @uses WP_List_Table::get_sortable_columns()
	 * @uses EDD_Site_Tracking_Logs_Table::get_pagenum()
	 * @uses EDD_Site_Tracking_Logs_Table::get_logs()
	 * @uses EDD_Site_Tracking_Logs_Table::get_log_count()
	 * @uses WP_List_Table::set_pagination_args()
	 * @return void
	 */
	function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array(); // No hidden columns
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$current_page          = $this->get_pagenum();
		$this->items           = $this->get_sites();

		$sites_db              = new IBX_EDD_Usage_Tracking_Sites_DB();
		$total_items           = $this->get_total_count();
		$this->set_pagination_args( array(
				'total_items'  => $total_items,
				'per_page'     => $this->per_page,
				'total_pages'  => ceil( $total_items / $this->per_page )
			)
		);
	}
}
