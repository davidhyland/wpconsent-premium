<?php
/**
 * Admin page for displaying consent logs.
 *
 * @package WPConsent
 */

/**
 * Consent logs page class.
 */
class WPConsent_Admin_Page_Consent_Logs_Pro extends WPConsent_Admin_Page_Consent_Logs {
	/**
	 * Page specific hooks.
	 *
	 * @return void
	 */
	public function page_hooks() {
		add_action( 'admin_init', array( $this, 'setup_list_table' ) );
		add_action( 'admin_init', array( $this, 'maybe_capture_filter' ) );
		$this->views = array(
			'logs'   => __( 'Consent Logs', 'wpconsent-premium' ),
			'export' => __( 'Export', 'wpconsent-premium' ),
		);
	}

	/**
	 * If the referer is set, remove and redirect.
	 *
	 * @return void
	 */
	public function maybe_capture_filter() {
		if ( ! empty( $_REQUEST['_wp_http_referer'] ) && isset( $_SERVER['REQUEST_URI'] ) && isset( $_REQUEST['filter_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect(
				remove_query_arg(
					array(
						'_wp_http_referer',
						'_wpnonce',
					),
					wp_unslash( $_SERVER['REQUEST_URI'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				)
			);
			exit;
		}
		if ( ! empty( $_REQUEST['_wp_http_referer'] ) && isset( $_SERVER['REQUEST_URI'] ) && isset( $_REQUEST['filter_clear'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect(
				add_query_arg(
					'page',
					'wpconsent-consent-logs',
					$this->admin_url( 'admin.php' )
				)
			);

			exit;
		}
	}

	/**
	 * Setup the list table.
	 *
	 * @return void
	 */
	public function setup_list_table() {
		require_once WPCONSENT_PLUGIN_PATH . 'includes/pro/admin/pages/class-wpconsent-consent-logs-list-table.php';
		$this->list_table = new WPConsent_Consent_Logs_List_Table();
	}

	/**
	 * Output the page content.
	 *
	 * @return void
	 */
	public function output_content() {
		if ( method_exists( $this, 'output_view_' . $this->view ) ) {
			call_user_func( array( $this, 'output_view_' . $this->view ) );
		}
	}

	/**
	 * Output the logs view.
	 *
	 * @return void
	 */
	public function output_view_logs() {
		// Check if Consent Logs option is disabled
		$records_of_consent_enabled = wpconsent()->settings->get_option( 'records_of_consent', false );

		if ( ! $records_of_consent_enabled ) {
			// Display notice with link to settings
			$settings_url = add_query_arg(
				array(
					'page' => 'wpconsent-cookies',
				),
				admin_url( 'admin.php' )
			) . '#records_of_consent';

			echo '<div class="notice notice-warning" style="margin-left: 0; margin-right: 0;"><p>';
			printf(
				/* translators: %s is the URL to the settings page */
				esc_html__( 'Consent Logs are currently disabled. To enable this feature, please visit the %s.', 'wpconsent-premium' ),
				'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'settings page', 'wpconsent-premium' ) . '</a>'
			);
			echo '</p></div>';
		}
		?>
		<form id="consent-logs-filter" method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( $this->page_slug ); ?>"/>
			<?php
			$this->list_table->prepare_items();
			$this->list_table->search_box(
				__( 'Search Logs', 'wpconsent-cookies-banner-privacy-suite' ),
				'wpconsent-consent-logs'
			);
			$this->list_table->display();
			?>
		</form>
		<?php
	}

	/**
	 * Output the export view.
	 *
	 * @return void
	 */
	public function output_view_export() {
		?>
		<form method="post" action="<?php echo esc_url( $this->get_page_action_url() ); ?>">
			<?php
			$this->metabox(
				__( 'Export Consent Logs', 'wpconsent-premium' ),
				$this->get_export_input()
			);
			?>
		</form>
		<?php
	}
}
