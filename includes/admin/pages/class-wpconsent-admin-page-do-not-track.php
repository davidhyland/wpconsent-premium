<?php
/**
 * Do Not Track admin page.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPConsent_Admin_Page_Do_Not_Track
 */
class WPConsent_Admin_Page_Do_Not_Track extends WPConsent_Admin_Page {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	public $page_slug = 'wpconsent-do-not-track';

	/**
	 * Default view.
	 *
	 * @var string
	 */
	public $view = 'requests';

	/**
	 * Call this just to set the page title translatable.
	 */
	public function __construct() {
		$this->page_title = __( 'Do Not Track', 'wpconsent-cookies-banner-privacy-suite' );
		$this->menu_title = __( 'Do Not Track', 'wpconsent-cookies-banner-privacy-suite' );
		parent::__construct();
	}

	/**
	 * Page specific Hooks.
	 *
	 * @return void
	 */
	public function page_hooks() {
		$this->views = array(
			'requests'      => __( 'Requests', 'wpconsent-cookies-banner-privacy-suite' ),
			'configuration' => __( 'Configuration', 'wpconsent-cookies-banner-privacy-suite' ),
			'export'        => __( 'Export', 'wpconsent-cookies-banner-privacy-suite' ),
		);
	}

	/**
	 * Override the output method so we can add our form markup for this page.
	 *
	 * @return void
	 */
	public function output() {
		$this->output_header();
		?>
		<div class="wpconsent-content">
			<div class="wpconsent-blur-area">
				<?php
				$this->output_content();
				do_action( "wpconsent_admin_page_content_{$this->page_slug}", $this );
				?>
			</div>
			<?php
			echo WPConsent_Admin_page::get_upsell_box( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html__( 'Do Not Track Addon is a premium feature', 'wpconsent-cookies-banner-privacy-suite' ),
				'<p>' . esc_html__( 'Upgrade to WPConsent Plus or higher plans today and improve the way you manage "Do Not Track or Sell My Information" requests.', 'wpconsent-cookies-banner-privacy-suite' ) . '</p>',
				array(
					'text' => esc_html__( 'Upgrade to PRO and Unlock "Do Not Track"', 'wpconsent-cookies-banner-privacy-suite' ),
					'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'do-not-track-page', 'main' ) ),
				),
				array(
					'text' => esc_html__( 'Learn more about all the features', 'wpconsent-cookies-banner-privacy-suite' ),
					'url'  => esc_url( wpconsent_utm_url( 'https://wpconsent.com/lite/', 'do-not-track-page', 'features' ) ),
				),
				array(
					esc_html__( 'Customizable requests form', 'wpconsent-cookies-banner-privacy-suite' ),
					esc_html__( 'Easily export to CSV', 'wpconsent-cookies-banner-privacy-suite' ),
					esc_html__( 'Mark requests as processed', 'wpconsent-cookies-banner-privacy-suite' ),
					esc_html__( 'Avoid Spam with an easy configuration', 'wpconsent-cookies-banner-privacy-suite' ),
					esc_html__( '1-click Do Not Track page creation', 'wpconsent-cookies-banner-privacy-suite' ),
					esc_html__( 'Self-Hosted records for compliance proof', 'wpconsent-cookies-banner-privacy-suite' ),
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * The page output based on the view.
	 *
	 * @return void
	 */
	public function output_content() {
		if ( method_exists( $this, 'output_view_' . $this->view ) ) {
			call_user_func( array( $this, 'output_view_' . $this->view ) );
		}
	}

	/**
	 * For this page we output a menu.
	 *
	 * @return void
	 */
	public function output_header_bottom() {
		?>
		<ul class="wpconsent-admin-tabs">
			<?php
			foreach ( $this->views as $slug => $label ) {
				$class = $this->view === $slug ? 'active' : '';
				?>
				<li>
					<a href="<?php echo esc_url( $this->get_view_link( $slug ) ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
				</li>
			<?php } ?>
		</ul>
		<?php
	}

	/**
	 * Output the requests view.
	 *
	 * @return void
	 */
	protected function output_view_requests() {
		// Output a dummy table that looks exactly like the one in the addon.
		?>
		<div class="wpconsent-admin-content-section">
			<h2><?php esc_html_e( 'Do Not Track Requests', 'wpconsent-cookies-banner-privacy-suite' ); ?></h2>
			<p><?php esc_html_e( 'View and manage your Do Not Track requests.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>

			<?php settings_errors( 'wpconsent_dnt_messages' ); ?>

			<div class="wpconsent-dnt-requests-table">
				<form method="post">
					<input type="hidden" name="page" value="<?php echo esc_attr( $this->page_slug ); ?>"/>
					<input type="hidden" name="view" value="requests"/>

					<div class="tablenav top">
						<div class="alignleft actions bulkactions">
							<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'wpconsent-cookies-banner-privacy-suite' ); ?></label>
							<select name="action" id="bulk-action-selector-top">
								<option value="-1"><?php esc_html_e( 'Bulk Actions', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
								<option value="mark_processed"><?php esc_html_e( 'Mark as Processed', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
							</select>
							<input type="submit" id="doaction" class="button action" value="<?php esc_attr_e( 'Apply', 'wpconsent-cookies-banner-privacy-suite' ); ?>">
						</div>
						<div class="tablenav-pages">
							<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s item', '%s items', 10, 'wpconsent-cookies-banner-privacy-suite' ), number_format_i18n( 10 ) ) ); ?></span>
						</div>
						<br class="clear">
					</div>

					<table class="wp-list-table widefat fixed striped">
						<thead>
						<tr>
							<td id="cb" class="manage-column column-cb check-column">
								<input id="cb-select-all-1" type="checkbox">
							</td>
							<th scope="col" class="manage-column column-request_id"><?php esc_html_e( 'ID', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
							<th scope="col" class="manage-column column-name"><?php esc_html_e( 'Name', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
							<th scope="col" class="manage-column column-email"><?php esc_html_e( 'Email', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
							<th scope="col" class="manage-column column-location"><?php esc_html_e( 'Location', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
							<th scope="col" class="manage-column column-request_status"><?php esc_html_e( 'Status', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
							<th scope="col" class="manage-column column-created_at"><?php esc_html_e( 'Date', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
						</tr>
						</thead>
						<tbody>
						<?php
						// Dummy data for the table.
						$dummy_data = array(
							array(
								'request_id'     => 1,
								'first_name'     => 'John',
								'last_name'      => 'Doe',
								'email'          => 'john.doe@example.com',
								'city'           => 'New York',
								'state'          => 'NY',
								'country'        => 'USA',
								'request_status' => 'received',
								'created_at'     => date( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
							),
							array(
								'request_id'     => 2,
								'first_name'     => 'Jane',
								'last_name'      => 'Smith',
								'email'          => 'jane.smith@example.com',
								'city'           => 'London',
								'state'          => '',
								'country'        => 'UK',
								'request_status' => 'confirmed',
								'created_at'     => date( 'Y-m-d H:i:s', strtotime( '-5 days' ) ),
							),
							array(
								'request_id'     => 3,
								'first_name'     => 'Robert',
								'last_name'      => 'Johnson',
								'email'          => 'robert.johnson@example.com',
								'city'           => 'Paris',
								'state'          => '',
								'country'        => 'France',
								'request_status' => 'processed',
								'created_at'     => date( 'Y-m-d H:i:s', strtotime( '-10 days' ) ),
								'processed_at'   => date( 'Y-m-d H:i:s', strtotime( '-8 days' ) ),
							),
							array(
								'request_id'     => 4,
								'first_name'     => 'Maria',
								'last_name'      => 'Garcia',
								'email'          => 'maria.garcia@example.com',
								'city'           => 'Madrid',
								'state'          => '',
								'country'        => 'Spain',
								'request_status' => 'received',
								'created_at'     => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
							),
							array(
								'request_id'     => 5,
								'first_name'     => 'Michael',
								'last_name'      => 'Brown',
								'email'          => 'michael.brown@example.com',
								'city'           => 'Sydney',
								'state'          => 'NSW',
								'country'        => 'Australia',
								'request_status' => 'processed',
								'created_at'     => date( 'Y-m-d H:i:s', strtotime( '-15 days' ) ),
								'processed_at'   => date( 'Y-m-d H:i:s', strtotime( '-12 days' ) ),
							),
							array(
								'request_id'     => 6,
								'first_name'     => 'Emma',
								'last_name'      => 'Wilson',
								'email'          => 'emma.wilson@example.com',
								'city'           => 'Toronto',
								'state'          => 'ON',
								'country'        => 'Canada',
								'request_status' => 'received',
								'created_at'     => date( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
							),
							array(
								'request_id'     => 7,
								'first_name'     => 'David',
								'last_name'      => 'Lee',
								'email'          => 'david.lee@example.com',
								'city'           => 'Tokyo',
								'state'          => '',
								'country'        => 'Japan',
								'request_status' => 'confirmed',
								'created_at'     => date( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
							),
							array(
								'request_id'     => 8,
								'first_name'     => 'Sophia',
								'last_name'      => 'Martinez',
								'email'          => 'sophia.martinez@example.com',
								'city'           => 'Berlin',
								'state'          => '',
								'country'        => 'Germany',
								'request_status' => 'processed',
								'created_at'     => date( 'Y-m-d H:i:s', strtotime( '-20 days' ) ),
								'processed_at'   => date( 'Y-m-d H:i:s', strtotime( '-18 days' ) ),
							),
							array(
								'request_id'     => 9,
								'first_name'     => 'James',
								'last_name'      => 'Taylor',
								'email'          => 'james.taylor@example.com',
								'city'           => 'Dublin',
								'state'          => '',
								'country'        => 'Ireland',
								'request_status' => 'received',
								'created_at'     => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
							),
							array(
								'request_id'     => 10,
								'first_name'     => 'Olivia',
								'last_name'      => 'Anderson',
								'email'          => 'olivia.anderson@example.com',
								'city'           => 'Stockholm',
								'state'          => '',
								'country'        => 'Sweden',
								'request_status' => 'confirmed',
								'created_at'     => date( 'Y-m-d H:i:s', strtotime( '-4 days' ) ),
							),
						);

						foreach ( $dummy_data as $item ) :
							$status_labels = array(
								'received'  => __( 'Received', 'wpconsent-cookies-banner-privacy-suite' ),
								'confirmed' => __( 'Confirmed', 'wpconsent-cookies-banner-privacy-suite' ),
								'processed' => __( 'Processed', 'wpconsent-cookies-banner-privacy-suite' ),
							);

							$status_text = isset( $status_labels[ $item['request_status'] ] ) ? $status_labels[ $item['request_status'] ] : $item['request_status'];

							// Add processed date if available.
							if ( 'processed' === $item['request_status'] && isset( $item['processed_at'] ) ) {
								$processed_at = date_i18n( get_option( 'date_format' ), strtotime( $item['processed_at'] ) );
								$status_text  .= ' <span class="description">' . sprintf(
									/* translators: %s: date */
										__( 'on %s', 'wpconsent-cookies-banner-privacy-suite' ),
										$processed_at
									) . '</span>';
							}

							// Build location string.
							$location = array();
							if ( ! empty( $item['city'] ) ) {
								$location[] = $item['city'];
							}
							if ( ! empty( $item['state'] ) ) {
								$location[] = $item['state'];
							}
							if ( ! empty( $item['country'] ) ) {
								$location[] = $item['country'];
							}
							$location_text = implode( ', ', $location );

							// Row actions for non-processed items.
							$row_actions = '';
							if ( 'processed' !== $item['request_status'] ) {
								$row_actions = '<div class="row-actions"><span class="mark_processed"><a href="#">' . __( 'Mark as Processed', 'wpconsent-cookies-banner-privacy-suite' ) . '</a></span></div>';
							}
							?>
							<tr>
								<th scope="row" class="check-column">
									<input type="checkbox" name="request_id[]" value="<?php echo esc_attr( $item['request_id'] ); ?>">
								</th>
								<td class="request_id column-request_id"><?php echo esc_html( $item['request_id'] ); ?></td>
								<td class="name column-name">
									<?php echo esc_html( $item['first_name'] . ' ' . $item['last_name'] ); ?>
									<?php echo wp_kses_post( $row_actions ); ?>
								</td>
								<td class="email column-email"><?php echo esc_html( $item['email'] ); ?></td>
								<td class="location column-location"><?php echo esc_html( $location_text ); ?></td>
								<td class="request_status column-request_status"><?php echo wp_kses_post( $status_text ); ?></td>
								<td class="created_at column-created_at"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item['created_at'] ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
						<tfoot>
						<tr>
							<td class="manage-column column-cb check-column">
								<input id="cb-select-all-2" type="checkbox">
							</td>
							<th scope="col" class="manage-column column-request_id"><?php esc_html_e( 'ID', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
							<th scope="col" class="manage-column column-name"><?php esc_html_e( 'Name', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
							<th scope="col" class="manage-column column-email"><?php esc_html_e( 'Email', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
							<th scope="col" class="manage-column column-location"><?php esc_html_e( 'Location', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
							<th scope="col" class="manage-column column-request_status"><?php esc_html_e( 'Status', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
							<th scope="col" class="manage-column column-created_at"><?php esc_html_e( 'Date', 'wpconsent-cookies-banner-privacy-suite' ); ?></th>
						</tr>
						</tfoot>
					</table>

					<div class="tablenav bottom">
						<div class="alignleft actions bulkactions">
							<label for="bulk-action-selector-bottom" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'wpconsent-cookies-banner-privacy-suite' ); ?></label>
							<select name="action2" id="bulk-action-selector-bottom">
								<option value="-1"><?php esc_html_e( 'Bulk Actions', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
								<option value="mark_processed"><?php esc_html_e( 'Mark as Processed', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
							</select>
							<input type="submit" id="doaction2" class="button action" value="<?php esc_attr_e( 'Apply', 'wpconsent-cookies-banner-privacy-suite' ); ?>">
						</div>
						<div class="tablenav-pages">
							<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s item', '%s items', 10, 'wpconsent-cookies-banner-privacy-suite' ), number_format_i18n( 10 ) ) ); ?></span>
						</div>
						<br class="clear">
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the configuration view.
	 *
	 * @return void
	 */
	protected function output_view_configuration() {
		// Display a preview of the configuration form from the addon

		// Available fields that would be in the form
		$available_fields = array(
			'first_name' => __( 'First Name', 'wpconsent-cookies-banner-privacy-suite' ),
			'last_name'  => __( 'Last Name', 'wpconsent-cookies-banner-privacy-suite' ),
			'email'      => __( 'Email', 'wpconsent-cookies-banner-privacy-suite' ),
			'address'    => __( 'Address', 'wpconsent-cookies-banner-privacy-suite' ),
		);

		// Required fields that cannot be .
		$required_fields = array( 'first_name', 'last_name', 'email' );

		?>
		<div class="wpconsent-admin-content-section">
			<h2><?php esc_html_e( 'Do Not Track Form Configuration', 'wpconsent-cookies-banner-privacy-suite' ); ?></h2>
			<p><?php esc_html_e( 'Configure the do not track form that will be displayed to your users.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>

			<form method="post" action="">
				<div class="wpconsent-admin-settings-section">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="dnt_page_id"><?php esc_html_e( 'Do Not Track Page', 'wpconsent-cookies-banner-privacy-suite' ); ?></label>
							</th>
							<td>
								<div class="wpconsent-inline-select-group">
									<select id="dnt_page_id" name="dnt_page_id" class="regular-text">
										<option value="0"><?php esc_html_e( 'Choose Page', 'wpconsent-cookies-banner-privacy-suite' ); ?></option>
										<?php
										$pages = get_pages(
											array(
												'number'  => 20,
												'orderby' => 'title',
												'order'   => 'ASC',
											)
										);

										foreach ( $pages as $page ) {
											echo '<option value="' . esc_attr( $page->ID ) . '">' . esc_html( $page->post_title ) . '</option>';
										}
										?>
									</select>
								</div>
								<p>
									<button type="button" class="button button-secondary">
										<?php esc_html_e( 'Generate Do Not Track Page', 'wpconsent-cookies-banner-privacy-suite' ); ?>
									</button>
								</p>
								<p class="description">
									<?php esc_html_e( 'Select the page where users can submit Do Not Track requests. This page should contain the [wpconsent_do_not_track_form] shortcode.', 'wpconsent-cookies-banner-privacy-suite' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="wpconsent-admin-settings-section">
					<h3><?php esc_html_e( 'Form Fields', 'wpconsent-cookies-banner-privacy-suite' ); ?></h3>
					<p><?php esc_html_e( 'Configure which fields to display in the form and their labels.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="dnt_submit_text"><?php esc_html_e( 'Submit Button Text', 'wpconsent-cookies-banner-privacy-suite' ); ?></label>
							</th>
							<td>
								<input type="text" id="dnt_submit_text" name="dnt_submit_text" value="<?php echo esc_attr__( 'Submit Request', 'wpconsent-cookies-banner-privacy-suite' ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'The text for the form submit button.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
							</td>
						</tr>
						<?php
						foreach ( $available_fields as $field_key => $default_label ) {
							$is_core_field = in_array( $field_key, $required_fields, true );
							?>
							<tr>
								<th scope="row">
									<?php echo esc_html( $default_label ); ?>
								</th>
								<td>
									<fieldset>
										<legend class="screen-reader-text"><?php echo esc_html( $default_label ); ?></legend>
										<label>
											<input type="checkbox" value="1" <?php checked( true, $is_core_field ); ?> >
											<?php esc_html_e( 'Enable this field', 'wpconsent-cookies-banner-privacy-suite' ); ?>
											<?php if ( $is_core_field ) : ?>
												<span class="description"><?php esc_html_e( '(Required field, cannot be )', 'wpconsent-cookies-banner-privacy-suite' ); ?></span>
											<?php endif; ?>
										</label>
										<br>
										<label>
											<input type="checkbox" value="1" <?php checked( true, $is_core_field ); ?> >
											<?php esc_html_e( 'Make this field required', 'wpconsent-cookies-banner-privacy-suite' ); ?>
											<?php if ( $is_core_field ) : ?>
												<span class="description"><?php esc_html_e( '(Always required)', 'wpconsent-cookies-banner-privacy-suite' ); ?></span>
											<?php endif; ?>
										</label>
										<br>
										<label>
											<?php esc_html_e( 'Field Label:', 'wpconsent-cookies-banner-privacy-suite' ); ?>
											<input type="text" value="<?php echo esc_attr( $default_label ); ?>" class="regular-text">
										</label>
									</fieldset>
								</td>
							</tr>
						<?php } ?>
					</table>
				</div>

				<div class="wpconsent-admin-settings-section">
					<h3><?php esc_html_e( 'Spam Protection', 'wpconsent-cookies-banner-privacy-suite' ); ?></h3>
					<p><?php esc_html_e( 'You can use the CAPTCHA settings in WPForms to automatically protect this form.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
				</div>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'wpconsent-cookies-banner-privacy-suite' ); ?>">
				</p>
			</form>
		</div>
		<?php
	}


	/**
	 * Output the export view.
	 *
	 * @return void
	 */
	public function output_view_export() {
		?>
		<div class="wpconsent-admin-content-section wpconsent-admin-content-section-dnt-export">
			<h2><?php esc_html_e( 'Export Do Not Track Requests', 'wpconsent-cookies-banner-privacy-suite' ); ?></h2>
			<p><?php esc_html_e( 'Export your Do Not Track requests data in CSV format.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>

			<?php settings_errors( 'wpconsent_dnt_messages' ); ?>

			<form method="post" action="" id="wpconsent-dnt-export-form">
				<?php wp_nonce_field( 'wpconsent_dnt_export_start', 'wpconsent_dnt_export_nonce' ); ?>

				<div class="wpconsent-export-section">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="export-date-from"><?php esc_html_e( 'From:', 'wpconsent-cookies-banner-privacy-suite' ); ?></label>
							</th>
							<td>
								<input type="date" id="export-date-from" name="date_from" class="wpconsent-date-input">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="export-date-to"><?php esc_html_e( 'To:', 'wpconsent-cookies-banner-privacy-suite' ); ?></label>
							</th>
							<td>
								<input type="date" id="export-date-to" name="date_to" class="wpconsent-date-input">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="export-only-not-processed"><?php esc_html_e( 'Export Options:', 'wpconsent-cookies-banner-privacy-suite' ); ?></label>
							</th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Export Options', 'wpconsent-cookies-banner-privacy-suite' ); ?></legend>
									<label for="export-only-not-processed">
										<input type="checkbox" id="export-only-not-processed" name="export_only_not_processed" value="1">
										<?php esc_html_e( 'Export only "not processed" entries', 'wpconsent-cookies-banner-privacy-suite' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'If checked, only entries that have not been marked as processed will be exported.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>

									<label for="mark-as-processed">
										<input type="checkbox" id="mark-as-processed" name="mark_as_processed" value="1">
										<?php esc_html_e( 'Mark exported data as processed', 'wpconsent-cookies-banner-privacy-suite' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'If checked, all exported entries will be automatically marked as processed.', 'wpconsent-cookies-banner-privacy-suite' ); ?></p>
								</fieldset>
							</td>
						</tr>
					</table>

					<div class="wpconsent-metabox-form-row">
						<button id="wpconsent-dnt-export" class="button button-primary" type="button">
							<?php esc_html_e( 'Export', 'wpconsent-cookies-banner-privacy-suite' ); ?>
						</button>
						<div id="wpconsent-dnt-export-progress" style="display: none;">
							<div class="wpconsent-progress-bar">
								<div class="wpconsent-progress-bar-inner"></div>
							</div>
							<div class="wpconsent-progress-status">
								<?php esc_html_e( 'Processing...', 'wpconsent-cookies-banner-privacy-suite' ); ?>
								<span class="wpconsent-progress-percentage">0%</span>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
	}
}
