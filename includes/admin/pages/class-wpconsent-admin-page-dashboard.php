<?php
/**
 * WPConsent Admin Page Dashboard.
 *
 * @package WPConsent
 */

/**
 * Dashboard page class.
 */
class WPConsent_Admin_Page_Dashboard extends WPConsent_Admin_Page {

	use WPConsent_Banner_Preview;

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	public $page_slug = 'wpconsent';

	/**
	 * Call this just to set the page title translatable.
	 */
	public function __construct() {
		$this->page_title = __( 'Dashboard', 'wpconsent-cookies-banner-privacy-suite' );
		parent::__construct();
	}

	/**
	 * Page hooks.
	 *
	 * @return void
	 */
	public function page_hooks() {
		add_filter( 'wpconsent_admin_js_data', array( $this, 'banner_preview_scripts' ) );
	}

	/**
	 * Page content.
	 *
	 * @return void
	 */
	public function output_content() {
		$this->get_started_checklist();
		?>
		<div class="wpconsent-dashboard-box-row-wrapper">
			<?php
			// Add half-width class to both boxes
			echo '<div class="wpconsent-dashboard-box-half">';
			$this->scan_results_box();
			echo '</div>';

			echo '<div class="wpconsent-dashboard-box-half">';
			$this->banner_preview_box();
			echo '</div>';
			?>
		</div>
		<?php
	}

	/**
	 * Dashboard fields.
	 *
	 * @return void
	 */
	public function get_started_checklist() {
		$fields = array(
			array(
				'title'              => esc_html__( 'Website Scan', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'        => esc_html__( 'Scan your website for known scripts and automatically configure cookies and info.', 'wpconsent-cookies-banner-privacy-suite' ),
				'checked'            => ! empty( wpconsent()->scanner->get_scan_data() ),
				'url'                => $this->get_page_url( 'wpconsent-scanner' ),
				'button_text'        => esc_html__( 'Scan Website', 'wpconsent-cookies-banner-privacy-suite' ),
				'button_text_active' => esc_html__( 'View Scan Results', 'wpconsent-cookies-banner-privacy-suite' ),
			),
			array(
				'title'              => esc_html__( 'Cookie Banner', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'        => esc_html__( 'Configure how the cookie banner looks and enable displaying it on the frontend of your website.', 'wpconsent-cookies-banner-privacy-suite' ),
				'checked'            => wpconsent()->settings->get_option( 'enable_consent_banner' ),
				'url'                => $this->get_page_url( 'wpconsent-banner' ),
				'button_text'        => esc_html__( 'Activate Banner', 'wpconsent-cookies-banner-privacy-suite' ),
				'button_text_active' => esc_html__( 'Active', 'wpconsent-cookies-banner-privacy-suite' ),
			),
			array(
				'title'              => esc_html__( 'Automatic Script Blocking', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'        => esc_html__( 'Prevent known tracking scripts from adding cookies on your website before consent is given.', 'wpconsent-cookies-banner-privacy-suite' ),
				'checked'            => wpconsent()->settings->get_option( 'enable_script_blocking' ),
				'url'                => $this->get_page_url( 'wpconsent-cookies' ),
				'button_text'        => esc_html__( 'Enable', 'wpconsent-cookies-banner-privacy-suite' ),
				'button_text_active' => esc_html__( 'Enabled', 'wpconsent-cookies-banner-privacy-suite' ),
			),
			array(
				'title'              => esc_html__( 'Cookie Policy', 'wpconsent-cookies-banner-privacy-suite' ),
				'description'        => esc_html__( 'Configure your site\'s Cookie Policy to inform users about how cookies are used, stored, and managed.', 'wpconsent-cookies-banner-privacy-suite' ),
				'checked'            => wpconsent()->settings->get_option( 'cookie_policy_page' ),
				'url'                => $this->get_page_url( 'wpconsent-cookies' ) . '#cookie-policy-input',
				'button_text'        => esc_html__( 'Configure', 'wpconsent-cookies-banner-privacy-suite' ),
				'button_text_active' => esc_html__( 'Configured', 'wpconsent-cookies-banner-privacy-suite' ),
			),
		);
		?>
		<div class="wpconsent-dashboard-box wpconsent-dashboard-box-large">
			<div class="wpconsent-dashboard-box-title">
				<h2><?php esc_html_e( 'Get Started with WPConsent', 'wpconsent-cookies-banner-privacy-suite' ); ?></h2>
				<p><?php esc_html_e( 'Welcome to WPConsent! Get started by configuring the main features below.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
			</div>
			<div class="wpconsent-dashboard-box-content">
				<?php foreach ( $fields as $field ) :
					$button_text = $field['checked'] ? $field['button_text_active'] : $field['button_text'];
					?>
					<div class="wpconsent-dashboard-box-row">
						<div class="wpconsent-dashboard-box-row-checkbox">
							<span class="wpconsent-faux-checkbox <?php echo $field['checked'] ? 'wpconsent-checked' : ''; ?>"></span>
						</div>
						<div class="wpconsent-dashboard-box-row-content">
							<h3><?php echo esc_html( $field['title'] ); ?></h3>
							<p><?php echo esc_html( $field['description'] ); ?></p>
						</div>
						<div class="wpconsent-dashboard-box-row-actions">
							<a href="<?php echo esc_url( $field['url'] ); ?>" class="wpconsent-button wpconsent-button-secondary"><?php echo esc_html( $button_text ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Scan results box. Use the same data as the scan results page.
	 *
	 * @return void
	 */
	public function scan_results_box() {
		$scan_data = wpconsent()->scanner->get_scan_data();

		// Calculate stats similar to scanner page.
		$scripts_count = 0;
		$cookies_count = 0;
		$date          = __( 'Not yet scanned', 'wpconsent-cookies-banner-privacy-suite' );
		if ( ! empty( $scan_data ) ) {
			foreach ( $scan_data['data']['scripts'] as $services ) {
				$scripts_count += count( $services );
				foreach ( $services as $service ) {
					$cookies_count += count( $service['cookies'] );
				}
			}

			$date = date_i18n( get_option( 'date_format' ), strtotime( $scan_data['date'] ) );
		}
		?>
		<div class="wpconsent-dashboard-box">
			<div class="wpconsent-dashboard-box-title">
				<h2><?php esc_html_e( 'Scan Report Summary', 'wpconsent-cookies-banner-privacy-suite' ); ?></h2>
			</div>
			<div class="wpconsent-dashboard-box-content">
				<div class="wpconsent-scan-overview">
					<div class="wpconsent-scan-overview-stat">
						<h3><?php esc_html_e( 'Services Detected', 'wpconsent-cookies-banner-privacy-suite' ); ?></h3>
						<p><?php echo esc_html( $scripts_count ); ?></p>
					</div>
					<div class="wpconsent-scan-overview-stat">
						<h3><?php esc_html_e( 'Cookies In Use', 'wpconsent-cookies-banner-privacy-suite' ); ?></h3>
						<p><?php echo esc_html( $cookies_count ); ?></p>
					</div>
					<div class="wpconsent-scan-overview-stat">
						<h3><?php esc_html_e( 'Last Successful Scan', 'wpconsent-cookies-banner-privacy-suite' ); ?></h3>
						<p><?php echo esc_html( $date ); ?></p>
					</div>
					<div class="wpconsent-scan-overview-stat">
						<h3><?php esc_html_e( 'Cookie Status', 'wpconsent-cookies-banner-privacy-suite' ); ?></h3>
						<p>
							<span class="wpconsent-faux-checkbox <?php echo isset( $scan_data['configured'] ) ? 'wpconsent-checked' : ''; ?>"></span>
						</p>
					</div>
				</div>
			</div>
			<div class="wpconsent-dashboard-box-actions">
				<a href="<?php echo esc_url( $this->get_page_url( 'wpconsent-scanner' ) ); ?>" class="wpconsent-button wpconsent-button-primary"><?php esc_html_e( 'Scan Your Website', 'wpconsent-cookies-banner-privacy-suite' ); ?></a>
				<?php if ( ! empty( $scan_data ) ) { ?>
					<a href="<?php echo esc_url( $this->get_page_url( 'wpconsent-scanner' ) ); ?>" class="wpconsent-button wpconsent-button-secondary"><?php esc_html_e( 'View Full Report', 'wpconsent-cookies-banner-privacy-suite' ); ?></a>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Banner preview box.
	 *
	 * @return void
	 */
	public function banner_preview_box() {
		?>
		<div class="wpconsent-dashboard-box">
			<div class="wpconsent-dashboard-box-title">
				<h2><?php esc_html_e( 'Consent Banner', 'wpconsent-cookies-banner-privacy-suite' ); ?></h2>
			</div>
			<div class="wpconsent-dashboard-box-content">
				<div class="wpconsent-banner-preview-wrapper">
					<?php wpconsent()->banner->output_banner(); ?>
				</div>
			</div>
			<div class="wpconsent-dashboard-box-actions">
				<a href="<?php echo esc_url( $this->get_page_url( 'wpconsent-banner' ) ); ?>" class="wpconsent-button wpconsent-button-secondary">
					<?php esc_html_e( 'Customize Banner', 'wpconsent-cookies-banner-privacy-suite' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

}
