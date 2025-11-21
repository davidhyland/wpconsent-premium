<?php
/**
 * List table for consent logs.
 *
 * @package WPConsent
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class WPConsent_Consent_Logs_List_Table
 */
class WPConsent_Consent_Logs_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'consent_log',
				'plural'   => 'consent_logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'user_id'      => __( 'User', 'wpconsent-premium' ),
			'ip_address'   => __( 'IP Address', 'wpconsent-premium' ),
			'consent_data' => __( 'Consent Details', 'wpconsent-premium' ),
			'created_at'   => __( 'Date', 'wpconsent-premium' ),
		);
	}

	/**
	 * Prepare items for table.
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;

		$per_page = 20;
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Table name is safe as it is pulled from wpdb and not user input, and is escaped.
		$table_name    = esc_sql( $wpdb->prefix . 'wpconsent_consent_logs' );
		$where_clauses = array();
		$where_values  = array();

		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $search ) ) {
			$ip_search = $this->process_ip_search( $search );
			if ( $ip_search ) {
				$ip_where        = $this->build_ip_where_clause( $ip_search );
				$where_clauses[] = 'ip_address LIKE %s';
				$where_values[]  = $ip_search;
			}
		}

		$date_range = isset( $_GET['date_range'] ) ? sanitize_text_field( wp_unslash( $_GET['date_range'] ) ) : '';  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $date_range ) ) {
			$date_parts = explode( ' to ', $date_range );
			$start_date = isset( $date_parts[0] ) ? $date_parts[0] : '';
			$end_date   = isset( $date_parts[1] ) ? $date_parts[1] : $start_date;

			if ( ! empty( $start_date ) ) {
				$where_clauses[] = 'created_at >= %s';
				$where_values[]  = $start_date . ' 00:00:00';
			}

			if ( ! empty( $end_date ) ) {
				$where_clauses[] = 'created_at <= %s';
				$where_values[]  = $end_date . ' 23:59:59';
			}
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// Count total items with filters applied.
		$count_query = "SELECT COUNT(*) FROM $table_name $where_sql";
		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( $count_query, $where_values );
		}
		$total_items = $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$paged = $this->get_pagenum();

		$query      = "SELECT * FROM $table_name $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$query_args = array_merge( $where_values, array( $per_page, ( $paged - 1 ) * $per_page ) );

		$this->items = $wpdb->get_results(  // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( $query, $query_args ),
			ARRAY_A
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
			'user_id'    => array( 'user_id', false ),
		);
	}

	/**
	 * Column default.
	 *
	 * @param array  $item Item data.
	 * @param string $column_name Column name.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'user_id':
				$user = get_user_by( 'ID', $item['user_id'] );

				return $user ? esc_html( $user->user_login ) : __( 'Guest', 'wpconsent-premium' );
			case 'ip_address':
				$country_code = isset( $item['country_code'] ) ? $item['country_code'] : null;

				// If country code is missing, try to fetch it.
				if ( empty( $country_code ) ) {
					$country_code = $this->fetch_and_store_country_code( $item['consent_id'], $item['ip_address'] );
				}

				return $this->format_ip_with_flag( $item['ip_address'], $country_code );
			case 'consent_data':
				$consent_data = json_decode( $item['consent_data'], true );

				return $this->format_consent_data( $consent_data );
			case 'created_at':
				return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['created_at'] ) ) );
			default:
				return esc_html( $item[ $column_name ] );
		}
	}

	/**
	 * Display the search box.
	 *
	 * @param string $text     The 'submit' button label.
	 * @param string $input_id ID attribute value for the search input field.
	 */
	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$input_id    = $input_id . '-search-input';
		$search_term = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>">
				<?php echo esc_html( $text ); ?>:
			</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s"  value="<?php echo esc_attr( $search_term ); ?>" placeholder="<?php esc_attr_e( 'IP Search', 'wpconsent-premium' ); ?>" />
			<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
		</p>
		<?php
	}

	/**
	 * Process IP address search term.
	 *
	 * @param string $search_term The search term.
	 * @return string|false The processed IP search term or false if invalid.
	 */
	private function process_ip_search( $search_term ) {
		$ip = trim( $search_term );

		// Check for IPv4 format.
		if ( preg_match( '/^(\d{1,3}\.){0,3}\d{1,3}$/', $ip ) ) {
			$parts = explode( '.', $ip );

			$parts_count = count( $parts );
			while ( $parts_count < 4 ) {
				++$parts_count;
				$parts[] = '%';
			}
			$parts[3] = '%';

			return implode( '.', $parts );
		}

		// Check for IPv6 format.
		if ( preg_match( '/^([0-9a-fA-F]{1,4}:){0,7}[0-9a-fA-F]{0,4}$/', $ip ) ) {
			$parts = explode( ':', $ip );

			// Pad partial IPv6 with wildcards.
			$parts_count = count( $parts );
			while ( $parts_count < 8 ) {
				$parts[] = '%';
				++$parts_count;
			}

			for ( $i = 3; $i < 8; $i++ ) {
				$parts[ $i ] = '%';
			}

			return implode( ':', $parts );
		}
		return false;
	}

	/**
	 * Build the WHERE clause for IP search
	 *
	 * @param string $ip_pattern The processed IP pattern.
	 * @return array Array with SQL clause and value
	 */
	private function build_ip_where_clause( $ip_pattern ) {
		// Check if it's IPv4 or IPv6 pattern.
		if ( strpos( $ip_pattern, '.' ) !== false ) {
			return array(
				'clause' => 'ip_address LIKE %s',
				'value'  => $ip_pattern,
			);
		} else {
			return array(
				'clause' => 'ip_address LIKE %s',
				'value'  => $ip_pattern,
			);
		}
	}

	/**
	 * Format IP address with country flag.
	 *
	 * @param string $ip_address The IP address.
	 * @param string $country_code The country code.
	 *
	 * @return string
	 */
	private function format_ip_with_flag( $ip_address, $country_code ) {
		if ( ! empty( $country_code ) ) {
			$country_code = strtolower( $country_code );
			$country_name = $this->get_country_name( $country_code );

			// If the code is XX, we don't want to show a flag.
			if ( 'xx' === $country_code ) {
				return esc_html( $ip_address );
			}

			// Output a span with our sprite-based flag classes.
			$flag_html = sprintf(
				'<span class="wpconsent-flag wpconsent-flag-%1$s" title="%2$s" aria-hidden="true"></span>',
				esc_attr( $country_code ),
				esc_attr( $country_name )
			);
			return $flag_html . esc_html( $ip_address );
		}
		return esc_html( $ip_address );
	}

	/**
	 * Get country name from country code.
	 *
	 * @param string $country_code The country code.
	 *
	 * @return string
	 */
	private function get_country_name( $country_code ) {
		$countries = WPConsent_Geolocation::get_countries();
		return isset( $countries[ strtoupper( $country_code ) ] ) ? $countries[ strtoupper( $country_code ) ] : $country_code;
	}

	/**
	 * Fetch and store country code for an IP address.
	 *
	 * @param int    $consent_id The consent log ID.
	 * @param string $ip_address The IP address.
	 *
	 * @return string|null
	 */
	private function fetch_and_store_country_code( $consent_id, $ip_address ) {
		// Create an instance of the consent log class to use its method.
		$country_code = wpconsent()->consent_log->get_country_code( $ip_address );

		// If we got a country code, update the record.
		if ( ! empty( $country_code ) ) {
			global $wpdb;
			$wpdb->update(  // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prefix . 'wpconsent_consent_logs',
				array( 'country_code' => $country_code ),
				array( 'consent_id' => $consent_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		return $country_code;
	}

	/**
	 * Format consent data for display.
	 *
	 * @param array $consent_data Consent data array.
	 *
	 * @return string
	 */
	private function format_consent_data( $consent_data ) {
		if ( ! is_array( $consent_data ) ) {
			return '';
		}

		$main_categories = array( 'essential', 'statistics', 'marketing' );
		$main_output     = '';
		$services        = array();

		// Check for GPC status first.
		if ( isset( $consent_data['respect_gpc'] ) ) {
			$status       = $consent_data['respect_gpc'];
			$main_output .= sprintf(
				'<li><strong>%1$s:</strong> %2$s</li>',
				esc_html__( 'GPC', 'wpconsent-premium' ),
				esc_html( $status ? __( 'Acknowledged', 'wpconsent-premium' ) : __( 'User Overridden', 'wpconsent-premium' ) )
			);
		}

		foreach ( $consent_data as $category => $status ) {
			if ( in_array( $category, $main_categories, true ) ) {
				$main_output .= sprintf(
					'<li><strong>%1$s:</strong> %2$s</li>',
					esc_html( $category ),
					esc_html( $status ? __( 'Accepted', 'wpconsent-premium' ) : __( 'Declined', 'wpconsent-premium' ) )
				);
			} elseif ( 'respect_gpc' !== $category ) { // Exclude respect_gpc from services since we handled it above.
				$services[ $category ] = $status;
			}
		}

		$output = '<ul class="wpconsent-consent-data wpconsent-show-hidden-container">' . $main_output;

		if ( ! empty( $services ) ) {
			$output .= '<li>';
			$output .= '<button type="button" class="wpconsent-button wpconsent-button-text wpconsent-show-hidden" data-target=".wpconsent-consent-services" data-hide-label="' . esc_attr__( 'Hide Services', 'wpconsent-premium' ) . '">' . esc_html__( 'View Services', 'wpconsent-premium' ) . '</button>';
			$output .= '</li>';
			$output .= '<ul class="wpconsent-consent-services wpconsent-hidden-preview">';
			foreach ( $services as $service => $status ) {
				$output .= sprintf(
					'<li><strong>%1$s:</strong> %2$s</li>',
					esc_html( $service ),
					esc_html( $status ? __( 'Accepted', 'wpconsent-premium' ) : __( 'Declined', 'wpconsent-premium' ) )
				);
			}
			$output .= '</ul>'; // .wpconsent-consent-services.
		}

		$output .= '</ul>';

		return $output;
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination.
	 *
	 * @param string $which The location of the table navigation: 'top' or 'bottom'.
	 *
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			echo '<div class="actions alignleft">';
			$this->date_range_dropdown();

			submit_button( __( 'Filter', 'wpconsent-premium' ), '', 'filter_action', false, array( 'id' => 'wpconsent-filter-submit' ) );

			if ( isset( $_GET['filter_action'] ) || ! empty( $_GET['date_range'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				echo '&nbsp;';
				printf(
					'<a href="%s" class="button" id="wpconsent-filter-clear">%s</a>',
					esc_url( add_query_arg( 'page', 'wpconsent-consent-logs', admin_url( 'admin.php' ) ) ),
					esc_html__( 'Clear', 'wpconsent-premium' )
				);
			}
			echo '</div>';
		}
	}

	/**
	 * Display the date range filter dropdown.
	 *
	 * @return void
	 */
	protected function date_range_dropdown() {
		$date_range = isset( $_GET['date_range'] ) ? sanitize_text_field( wp_unslash( $_GET['date_range'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<label for="filter-by-date-range" class="screen-reader-text"><?php esc_html_e( 'Filter by date range', 'wpconsent-premium' ); ?></label>
		<input type="text" 
			id="filter-by-date-range" 
			name="date_range" 
			class="wpconsent-date-range" 
			placeholder="<?php esc_attr_e( 'Filter by date range', 'wpconsent-premium' ); ?>" 
			value="<?php echo esc_attr( $date_range ); ?>" 
			autocomplete="off" />
		<?php
	}
}
