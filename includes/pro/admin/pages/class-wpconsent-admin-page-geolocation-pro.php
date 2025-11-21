<?php
/**
 * Admin page used to Manage Geolocation with Location Groups.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Admin_Page_Geolocation_Pro
 */
class WPConsent_Admin_Page_Geolocation_Pro extends WPConsent_Admin_Page_Geolocation {

	use WPConsent_Input_Select;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function page_hooks() {
		add_filter( 'wpconsent_admin_js_data', array( $this, 'add_geolocation_js_data' ) );
	}

	/**
	 * Add geolocation data to the wpconsent JS object.
	 *
	 * @param array $data The existing JS data.
	 *
	 * @return array The modified JS data.
	 */
	public function add_geolocation_js_data( $data ) {
		// Only add this data on the geolocation page.
		$current_screen = get_current_screen();
		if ( isset( $current_screen->id ) && strpos( $current_screen->id, $this->page_slug ) !== false ) {
			$data['geolocationGroups'] = array(
				'nonce'   => wp_create_nonce( 'wpconsent_geolocation_groups' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'strings' => array(
					'confirmDelete'     => __( 'Are you sure you want to delete this location group?', 'wpconsent-premium' ),
					'groupNameRequired' => __( 'Group name is required.', 'wpconsent-premium' ),
					'locationsRequired' => __( 'At least one location must be selected.', 'wpconsent-premium' ),
					'savingGroup'       => __( 'Saving group...', 'wpconsent-premium' ),
					'deletingGroup'     => __( 'Deleting group...', 'wpconsent-premium' ),
					'errorSaving'       => __( 'Error saving group. Please try again.', 'wpconsent-premium' ),
					'errorDeleting'     => __( 'Error deleting group. Please try again.', 'wpconsent-premium' ),
					'confirmCreateRule' => __( 'Are you sure you want to create this predefined rule?', 'wpconsent-premium' ),
					'creatingRule'      => __( 'Creating predefined rule...', 'wpconsent-premium' ),
					'errorCreatingRule' => __( 'Error creating predefined rule. Please try again.', 'wpconsent-premium' ),
				),
			);
		}

		return $data;
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
			<div class="wpconsent-geolocation-container">
				<?php $this->output_location_groups_management(); ?>
			</div>
		</div>
		<?php
		$this->output_location_group_modal();
	}

	/**
	 * Output the location group modal.
	 *
	 * @return void
	 */
	public function output_location_group_modal() {
		?>
		<div class="wpconsent-modal" id="wpconsent-modal-location-group">
			<div class="wpconsent-modal-inner">
				<div class="wpconsent-modal-header">
					<h2><?php echo esc_html__( 'Add New Location Group', 'wpconsent-premium' ); ?></h2>
					<button class="wpconsent-modal-close wpconsent-button wpconsent-button-just-icon" type="button">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="wpconsent-modal-content">
					<?php echo $this->get_add_group_form_content(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the content for the add/edit group form.
	 *
	 * @return string
	 */
	public function get_add_group_form_content() {
		ob_start();
		?>
		<form id="wpconsent-location-group-form" class="wpconsent-location-group-form">
			<?php wp_nonce_field( 'wpconsent_geolocation_groups', 'nonce' ); ?>
			<input type="hidden" name="group_id" id="wpconsent-group-id" value="">

			<?php
			$this->metabox_row(
				__( 'Group Name', 'wpconsent-premium' ),
				$this->get_input_text(
					'group_name',
					'',
					__( 'Enter a descriptive name for this location group (e.g., "European Union + Canada + California").', 'wpconsent-premium' )
				),
				'group_name'
			);

			$this->metabox_row(
				__( 'Select Locations', 'wpconsent-premium' ),
				$this->get_mixed_location_selector(),
				'selected_locations'
			);

			$this->metabox_row_separator();

			$this->metabox_row(
				__( 'Block Scripts Before Consent', 'wpconsent-premium' ),
				$this->get_checkbox_toggle(
					false,
					'enable_script_blocking',
					__( 'Automatically block tracking scripts until user gives consent.', 'wpconsent-premium' ),
					1
				),
				'enable_script_blocking'
			);

			$this->metabox_row(
				__( 'Show Consent Banner', 'wpconsent-premium' ),
				$this->get_checkbox_toggle(
					true,
					'show_banner',
					__( 'Display the consent banner for visitors from these locations.', 'wpconsent-premium' ),
					1
				),
				'show_banner'
			);

			$this->metabox_row(
				__( 'Show Settings Button', 'wpconsent-premium' ),
				$this->get_checkbox_toggle(
					true,
					'enable_consent_floating',
					__( 'Show the consent preferences/settings button.', 'wpconsent-premium' ),
					1
				),
				'enable_consent_floating'
			);

			$this->metabox_row(
				__( 'Consent Mode', 'wpconsent-premium' ),
				$this->get_consent_mode_selector(),
				'consent_mode'
			);
			?>

			<div class="wpconsent-form-actions">
				<button type="submit" class="wpconsent-button wpconsent-save-group">
					<?php esc_html_e( 'Save Location Group', 'wpconsent-premium' ); ?>
				</button>
				<button type="button" class="wpconsent-button wpconsent-button-secondary wpconsent-cancel-edit" style="display: none;">
					<?php esc_html_e( 'Cancel', 'wpconsent-premium' ); ?>
				</button>
			</div>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the mixed location selector (continents, countries, and US states).
	 *
	 * @return string
	 */
	public function get_mixed_location_selector() {
		ob_start();
		?>
		<div id="wpconsent-mixed-location-selector">
			<div class="wpconsent-location-search">
				<input type="text" id="wpconsent-location-search" placeholder="<?php esc_attr_e( 'Search locations...', 'wpconsent-premium' ); ?>" class="wpconsent-regular-text">
			</div>

			<div class="wpconsent-location-tools">
				<button type="button" class="wpconsent-button wpconsent-expand-all">
					<?php esc_html_e( 'Expand All', 'wpconsent-premium' ); ?>
				</button>
				<button type="button" class="wpconsent-button wpconsent-collapse-all">
					<?php esc_html_e( 'Collapse All', 'wpconsent-premium' ); ?>
				</button>
				<button type="button" class="wpconsent-button wpconsent-clear-all">
					<?php esc_html_e( 'Clear All', 'wpconsent-premium' ); ?>
				</button>
			</div>

			<div class="wpconsent-hierarchical-selector">
				<?php echo $this->get_hierarchical_location_selector(); ?>
			</div>

			<div class="wpconsent-selected-locations">
				<h4><?php esc_html_e( 'Selected Locations:', 'wpconsent-premium' ); ?></h4>
				<div id="wpconsent-selected-locations-list" class="wpconsent-selected-locations-list">
					<p class="wpconsent-no-selections"><?php esc_html_e( 'No locations selected yet.', 'wpconsent-premium' ); ?></p>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get continents selector.
	 *
	 * @return string
	 */
	public function get_continents_selector() {
		$continents     = wpconsent()->geolocation->get_continents();
		$used_locations = $this->get_used_locations();

		ob_start();
		?>
		<div class="wpconsent-location-grid">
			<?php foreach ( $continents as $code => $continent ) : ?>
				<?php
				$is_used  = isset( $used_locations[ 'continent:' . $code ] );
				$disabled = $is_used ? 'disabled' : '';
				$class    = $is_used ? 'wpconsent-location-used' : '';
				?>
				<label class="wpconsent-location-item <?php echo esc_attr( $class ); ?>">
					<label class="wpconsent-checkbox-toggle">
						<input type="checkbox"
						       name="continents[]"
						       value="<?php echo esc_attr( $code ); ?>"
						       data-name="<?php echo esc_attr( $continent['name'] ); ?>"
						       data-type="continent"
							<?php echo esc_attr( $disabled ); ?>>
						<span class="wpconsent-checkbox-toggle-slider"></span>
					</label>
					<span class="wpconsent-location-name"><?php echo esc_html( $continent['name'] ); ?></span>
					<?php if ( $is_used ) : ?>
						<span class="wpconsent-location-used-indicator"><?php esc_html_e( '(Used)', 'wpconsent-premium' ); ?></span>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get countries selector.
	 *
	 * @return string
	 */
	public function get_countries_selector() {
		$countries      = wpconsent()->geolocation->get_countries();
		$used_locations = $this->get_used_locations();

		ob_start();
		?>
		<div class="wpconsent-countries-search">
			<input type="text" id="wpconsent-countries-search" placeholder="<?php esc_attr_e( 'Search countries...', 'wpconsent-premium' ); ?>" class="wpconsent-regular-text">
		</div>
		<div class="wpconsent-location-grid wpconsent-countries-grid">
			<?php foreach ( $countries as $code => $name ) : ?>
				<?php
				$is_used  = isset( $used_locations[ 'country:' . $code ] );
				$disabled = $is_used ? 'disabled' : '';
				$class    = $is_used ? 'wpconsent-location-used' : '';
				?>
				<label class="wpconsent-location-item <?php echo esc_attr( $class ); ?>" data-country="<?php echo esc_attr( strtolower( $name ) ); ?>">
					<label class="wpconsent-checkbox-toggle">
						<input type="checkbox"
						       name="countries[]"
						       value="<?php echo esc_attr( $code ); ?>"
						       data-name="<?php echo esc_attr( $name ); ?>"
						       data-type="country"
							<?php echo esc_attr( $disabled ); ?>>
						<span class="wpconsent-checkbox-toggle-slider"></span>
					</label>
					<span class="wpconsent-location-name"><?php echo esc_html( $name ); ?></span>
					<?php if ( $is_used ) : ?>
						<span class="wpconsent-location-used-indicator"><?php esc_html_e( '(Used)', 'wpconsent-premium' ); ?></span>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get US states selector.
	 *
	 * @return string
	 */
	public function get_us_states_selector() {
		$states         = $this->get_us_states();
		$used_locations = $this->get_used_locations();

		ob_start();
		?>
		<div class="wpconsent-location-grid">
			<?php foreach ( $states as $code => $name ) : ?>
				<?php
				$is_used  = isset( $used_locations[ 'us_state:' . $code ] );
				$disabled = $is_used ? 'disabled' : '';
				$class    = $is_used ? 'wpconsent-location-used' : '';
				?>
				<label class="wpconsent-location-item <?php echo esc_attr( $class ); ?>">
					<label class="wpconsent-checkbox-toggle">
						<input type="checkbox"
						       name="us_states[]"
						       value="<?php echo esc_attr( $code ); ?>"
						       data-name="<?php echo esc_attr( $name ); ?>"
						       data-type="us_state"
							<?php echo esc_attr( $disabled ); ?>>
						<span class="wpconsent-checkbox-toggle-slider"></span>
					</label>
					<span class="wpconsent-location-name"><?php echo esc_html( $name ); ?></span>
					<?php if ( $is_used ) : ?>
						<span class="wpconsent-location-used-indicator"><?php esc_html_e( '(Used)', 'wpconsent-premium' ); ?></span>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the hierarchical location selector.
	 *
	 * @return string
	 */
	public function get_hierarchical_location_selector() {
		$continents     = wpconsent()->geolocation->get_continents();
		$all_countries  = wpconsent()->geolocation->get_countries();
		$us_states      = $this->get_us_states();
		$used_locations = $this->get_used_locations();

		// Group countries by continent
		$countries_by_continent = array();
		foreach ( $continents as $continent_code => $continent ) {
			$countries_by_continent[ $continent_code ] = array();

			// Add countries that belong to this continent
			if ( isset( $continent['countries'] ) && is_array( $continent['countries'] ) ) {
				foreach ( $continent['countries'] as $country_code ) {
					if ( isset( $all_countries[ $country_code ] ) ) {
						$countries_by_continent[ $continent_code ][ $country_code ] = $all_countries[ $country_code ];
					}
				}
			}
		}

		ob_start();
		?>
		<div class="wpconsent-hierarchical-tree">
			<?php foreach ( $continents as $continent_code => $continent ) : ?>
				<?php
				$continent_key      = 'continent:' . $continent_code;
				$is_continent_used  = isset( $used_locations[ $continent_key ] );
				$continent_disabled = $is_continent_used ? 'disabled' : '';
				$continent_class    = $is_continent_used ? 'wpconsent-location-used' : '';
				?>
				<div class="wpconsent-tree-item wpconsent-tree-continent <?php echo esc_attr( $continent_class ); ?>" data-search-text="<?php echo esc_attr( strtolower( $continent['name'] ) ); ?>">
					<div class="wpconsent-tree-item-header">
						<span class="wpconsent-tree-toggle dashicons dashicons-arrow-right"></span>
						<div class="wpconsent-tree-label">
							<label class="wpconsent-checkbox-toggle">
								<input type="checkbox"
								       name="continents[]"
								       value="<?php echo esc_attr( $continent_code ); ?>"
								       data-name="<?php echo esc_attr( $continent['name'] ); ?>"
								       data-type="continent"
								       class="wpconsent-continent-checkbox"
									<?php echo esc_attr( $continent_disabled ); ?>>
								<span class="wpconsent-checkbox-toggle-slider"></span>
							</label>
							<span class="wpconsent-location-name"><?php echo esc_html( $continent['name'] ); ?></span>
							<?php if ( $is_continent_used ) : ?>
								<span class="wpconsent-location-used-indicator"><?php esc_html_e( '(Used)', 'wpconsent-premium' ); ?></span>
							<?php endif; ?>
						</div>
					</div>

					<div class="wpconsent-tree-children" style="display: none;">
						<?php if ( ! empty( $countries_by_continent[ $continent_code ] ) ) : ?>
							<?php foreach ( $countries_by_continent[ $continent_code ] as $country_code => $country_name ) : ?>
								<?php
								$country_key      = 'country:' . $country_code;
								$is_country_used  = isset( $used_locations[ $country_key ] );
								$country_disabled = $is_country_used ? 'disabled' : '';
								$country_class    = $is_country_used ? 'wpconsent-location-used' : '';

								// Special handling for United States to show states
								$has_states = ( $country_code === 'US' );
								?>
								<div class="wpconsent-tree-item wpconsent-tree-country <?php echo esc_attr( $country_class ); ?>" data-search-text="<?php echo esc_attr( strtolower( $country_name ) ); ?>" data-continent="<?php echo esc_attr( $continent_code ); ?>">
									<div class="wpconsent-tree-item-header">
										<?php if ( $has_states ) : ?>
											<span class="wpconsent-tree-toggle dashicons dashicons-arrow-right"></span>
										<?php else : ?>
											<span class="wpconsent-tree-spacer"></span>
										<?php endif; ?>

										<div class="wpconsent-tree-label">
											<label class="wpconsent-checkbox-toggle">
												<input type="checkbox"
												       name="countries[]"
												       value="<?php echo esc_attr( $country_code ); ?>"
												       data-name="<?php echo esc_attr( $country_name ); ?>"
												       data-type="country"
												       data-continent="<?php echo esc_attr( $continent_code ); ?>"
												       class="wpconsent-country-checkbox"
													<?php echo esc_attr( $country_disabled ); ?>>
												<span class="wpconsent-checkbox-toggle-slider"></span>
											</label>
											<span class="wpconsent-location-name"><?php echo esc_html( $country_name ); ?></span>
											<?php if ( $is_country_used ) : ?>
												<span class="wpconsent-location-used-indicator"><?php esc_html_e( '(Used)', 'wpconsent-premium' ); ?></span>
											<?php endif; ?>
										</div>
									</div>

									<?php if ( $has_states ) : ?>
										<div class="wpconsent-tree-children" style="display: none;">
											<?php foreach ( $us_states as $state_code => $state_name ) : ?>
												<?php
												$state_key      = 'us_state:' . $state_code;
												$is_state_used  = isset( $used_locations[ $state_key ] );
												$state_disabled = $is_state_used ? 'disabled' : '';
												$state_class    = $is_state_used ? 'wpconsent-location-used' : '';
												?>
												<div class="wpconsent-tree-item wpconsent-tree-state <?php echo esc_attr( $state_class ); ?>" data-search-text="<?php echo esc_attr( strtolower( $state_name ) ); ?>">
													<div class="wpconsent-tree-item-header">
														<span class="wpconsent-tree-spacer"></span>
														<span class="wpconsent-tree-spacer"></span>

														<div class="wpconsent-tree-label">
															<label class="wpconsent-checkbox-toggle">
																<input type="checkbox"
																       name="us_states[]"
																       value="<?php echo esc_attr( $state_code ); ?>"
																       data-name="<?php echo esc_attr( $state_name ); ?>"
																       data-type="us_state"
																       class="wpconsent-state-checkbox"
																	<?php echo esc_attr( $state_disabled ); ?>>
																<span class="wpconsent-checkbox-toggle-slider"></span>
															</label>
															<span class="wpconsent-location-name"><?php echo esc_html( $state_name ); ?></span>
															<?php if ( $is_state_used ) : ?>
																<span class="wpconsent-location-used-indicator"><?php esc_html_e( '(Used)', 'wpconsent-premium' ); ?></span>
															<?php endif; ?>
														</div>
													</div>
												</div>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the consent mode selector.
	 *
	 * @return string
	 */
	public function get_consent_mode_selector() {
		$options = array(
			'optin'  => __( 'Opt-in (user must actively consent)', 'wpconsent-premium' ),
			'optout' => __( 'Opt-out (consent assumed unless rejected)', 'wpconsent-premium' ),
		);

		return $this->select(
			'consent_mode',
			$options,
			'optin',
			false,
			'consent_mode',
			'',
			'',
			''
		);
	}

	/**
	 * Get US states array.
	 *
	 * @return array
	 */
	public function get_us_states() {
		return array(
			'AL' => __( 'Alabama', 'wpconsent-premium' ),
			'AK' => __( 'Alaska', 'wpconsent-premium' ),
			'AZ' => __( 'Arizona', 'wpconsent-premium' ),
			'AR' => __( 'Arkansas', 'wpconsent-premium' ),
			'CA' => __( 'California', 'wpconsent-premium' ),
			'CO' => __( 'Colorado', 'wpconsent-premium' ),
			'CT' => __( 'Connecticut', 'wpconsent-premium' ),
			'DE' => __( 'Delaware', 'wpconsent-premium' ),
			'FL' => __( 'Florida', 'wpconsent-premium' ),
			'GA' => __( 'Georgia', 'wpconsent-premium' ),
			'HI' => __( 'Hawaii', 'wpconsent-premium' ),
			'ID' => __( 'Idaho', 'wpconsent-premium' ),
			'IL' => __( 'Illinois', 'wpconsent-premium' ),
			'IN' => __( 'Indiana', 'wpconsent-premium' ),
			'IA' => __( 'Iowa', 'wpconsent-premium' ),
			'KS' => __( 'Kansas', 'wpconsent-premium' ),
			'KY' => __( 'Kentucky', 'wpconsent-premium' ),
			'LA' => __( 'Louisiana', 'wpconsent-premium' ),
			'ME' => __( 'Maine', 'wpconsent-premium' ),
			'MD' => __( 'Maryland', 'wpconsent-premium' ),
			'MA' => __( 'Massachusetts', 'wpconsent-premium' ),
			'MI' => __( 'Michigan', 'wpconsent-premium' ),
			'MN' => __( 'Minnesota', 'wpconsent-premium' ),
			'MS' => __( 'Mississippi', 'wpconsent-premium' ),
			'MO' => __( 'Missouri', 'wpconsent-premium' ),
			'MT' => __( 'Montana', 'wpconsent-premium' ),
			'NE' => __( 'Nebraska', 'wpconsent-premium' ),
			'NV' => __( 'Nevada', 'wpconsent-premium' ),
			'NH' => __( 'New Hampshire', 'wpconsent-premium' ),
			'NJ' => __( 'New Jersey', 'wpconsent-premium' ),
			'NM' => __( 'New Mexico', 'wpconsent-premium' ),
			'NY' => __( 'New York', 'wpconsent-premium' ),
			'NC' => __( 'North Carolina', 'wpconsent-premium' ),
			'ND' => __( 'North Dakota', 'wpconsent-premium' ),
			'OH' => __( 'Ohio', 'wpconsent-premium' ),
			'OK' => __( 'Oklahoma', 'wpconsent-premium' ),
			'OR' => __( 'Oregon', 'wpconsent-premium' ),
			'PA' => __( 'Pennsylvania', 'wpconsent-premium' ),
			'RI' => __( 'Rhode Island', 'wpconsent-premium' ),
			'SC' => __( 'South Carolina', 'wpconsent-premium' ),
			'SD' => __( 'South Dakota', 'wpconsent-premium' ),
			'TN' => __( 'Tennessee', 'wpconsent-premium' ),
			'TX' => __( 'Texas', 'wpconsent-premium' ),
			'UT' => __( 'Utah', 'wpconsent-premium' ),
			'VT' => __( 'Vermont', 'wpconsent-premium' ),
			'VA' => __( 'Virginia', 'wpconsent-premium' ),
			'WA' => __( 'Washington', 'wpconsent-premium' ),
			'WV' => __( 'West Virginia', 'wpconsent-premium' ),
			'WI' => __( 'Wisconsin', 'wpconsent-premium' ),
			'WY' => __( 'Wyoming', 'wpconsent-premium' ),
			'DC' => __( 'District of Columbia', 'wpconsent-premium' ),
		);
	}

	/**
	 * Get location groups from settings.
	 *
	 * @return array
	 */
	public function get_location_groups() {
		return wpconsent()->settings->get_option( 'geolocation_groups', array() );
	}

	/**
	 * Get used locations across all groups.
	 *
	 * @param string $exclude_group_id Group ID to exclude from the check.
	 *
	 * @return array
	 */
	public function get_used_locations( $exclude_group_id = '' ) {
		$location_groups = $this->get_location_groups();
		$used_locations  = array();

		foreach ( $location_groups as $group_id => $group ) {
			if ( $group_id === $exclude_group_id ) {
				continue;
			}

			foreach ( $group['locations'] as $location ) {
				$key                    = $location['type'] . ':' . $location['code'];
				$used_locations[ $key ] = true;
			}
		}

		return $used_locations;
	}

	/**
	 * Format locations for display.
	 *
	 * @param array $locations Array of location data.
	 *
	 * @return string
	 */
	public function format_locations_display( $locations ) {
		$display_parts = array();

		// Add selected locations.
		foreach ( $locations as $location ) {
			if ( isset( $location['name'] ) ) {
				// Use the stored name if available.
				$display_parts[] = $location['name'];
			} else if ( 'country' === $location['type'] ) {
				// For countries, get the name from the code.
				$display_parts[] = $this->get_location_name( $location['type'], $location['code'] );
			} else {
				// Fallback to code if name is not available.
				$display_parts[] = $location['code'];
			}
		}

		return implode( ', ', $display_parts );
	}

	/**
	 * Get location name by type and code.
	 *
	 * @param string $type Location type.
	 * @param string $code Location code.
	 *
	 * @return string
	 */
	public function get_location_name( $type, $code ) {
		switch ( $type ) {
			case 'continent':
				$continents = wpconsent()->geolocation->get_continents();

				return $continents[ $code ]['name'] ?? $code;

			case 'country':
				$countries = wpconsent()->geolocation->get_countries();

				return $countries[ $code ] ?? $code;

			case 'us_state':
				$states = $this->get_us_states();

				return $states[ $code ] ?? $code;

			default:
				return $code;
		}
	}
}
