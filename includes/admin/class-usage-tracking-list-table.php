<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

function edd_usage_tracking_log_list() {
	?>
	<style>
		th.column-url { width: 150px; }
	</style>
	<?php
	$logs_table = new EDD_Site_Tracking_Logs_Table();
	$logs_table->prepare_items();
	?>
	<form id="edd-logs-filter" method="get" action="<?php echo admin_url( 'edit.php?post_type=download&page=edd-reports&tab=logs' ); ?>">
		<?php
		$logs_table->display();
		?>
		<input type="hidden" name="post_type" value="download" />
		<input type="hidden" name="page" value="edd-usage-tracking" />
		<input type="hidden" name="tab" value="logs" />
	</form>
	<?php
}
add_action( 'edd_usage_tracking_tab_logs', 'edd_usage_tracking_log_list' );

/**
 * EDD_Site_Tracking_Logs_Table Class
 *
 * Renders the file downloads log view
 *
 * @since 1.4
 */
class EDD_Site_Tracking_Logs_Table extends WP_List_Table {

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
			'singular'  => edd_get_label_singular(),
			'plural'    => edd_get_label_plural(),
			'ajax'      => false,
		) );

		$this->base = admin_url( 'edit.php?post_type=download&page=edd-usage-tracking&tab=logs' );

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
			'checkin_date'   => __( 'Date', 'edd-usage-tracking' ),
		);
		return $columns;
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
		$sites_base = admin_url( 'edit.php?post_type=download&page=edd-usage-tracking&tab=sites' );
		$site_url   = untrailingslashit( str_replace( array( 'http://', 'https://' ), '', $item['url'] ) );

		$actions = array();
		$actions['view_logs']    = sprintf( '<a href="%s" >Logs</a>', add_query_arg( 'site_id', $item['site_id'], $this->base ) );
		$actions['view_profile'] = sprintf( '<a href="%s" >Profile</a>', add_query_arg( 'site_id', $item['site_id'], $sites_base ) );
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

	/**
	 * Outputs the log views
	 *
	 * @access public
	 * @since 1.4
	 * @return void
	 */
	public function bulk_actions( $which = '' ) {
		// These aren't really bulk actions but this outputs the markup in the right place
	}

	private function log_args() {
		$paged     = $this->get_paged();
		$offset    = 1 == $paged ? 0 : ( $paged - 1 ) * $this->per_page;

		$args      = array(
			'offset' => $offset,
			'number' => $this->per_page,
		);

		if ( ! empty( $_GET['site_id'] ) ) {
			$args['site_id'] = absint( $_GET['site_id'] );
		}

		return $args;
	}
	/**
	 * Gets the log entries for the current view
	 *
	 * @access public
	 * @since 1.4
	 * @global object $edd_logs EDD Logs Object
	 * @return array $logs_data Array of all the Log entires
	 */
	function get_logs() {

		$logs_data = array();

		$args = $this->log_args();

		$logs_db  = new IBX_EDD_Usage_Tracking_Logs_DB;
		$sites_db = new IBX_EDD_Usage_Tracking_Sites_DB;

		$logs = $logs_db->get_logs( $args );

		foreach ( $logs as $key => $log ) {
			$logs[ $key ]['url']      = $sites_db->get_column( 'url', $log['site_id'] );
			$active_count             = count( json_decode( $log['active_plugins'] ) );
			$total                    = count( json_decode( $log['active_plugins'] ) ) + count( json_decode( $log['inactive_plugins'] ) );
			$logs[ $key ]['plugins'] = '<span style="color:green;""><strong>' . $active_count . '</strong></span> / <span style="color:gray;">' . $total . '</span>';
		}


		return $logs;
	}

	function total_count() {
		$args = $this->log_args();
		$args['posts_per_page'] = -1;

		unset( $args['paged'] );

		$logs_db  = new IBX_EDD_Usage_Tracking_Logs_DB;
		return $logs_db->count( $args );
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
		global $edd_logs;

		$columns               = $this->get_columns();
		$hidden                = array(); // No hidden columns
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$current_page          = $this->get_pagenum();
		$this->items           = $this->get_logs();
		$logs_db               = new IBX_EDD_Usage_Tracking_Logs_DB();
		$total_items           = $this->total_count();
		$this->set_pagination_args( array(
				'total_items'  => $total_items,
				'per_page'     => $this->per_page,
				'total_pages'  => ceil( $total_items / $this->per_page )
			)
		);
	}
}
