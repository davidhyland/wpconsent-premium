<?php
/**
 * Class WPConsent_IAB_TCF_Frontend
 *
 * This class handles the frontend integration for IAB TCF (Transparency and Consent Framework).
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WPConsent_IAB_TCF_Frontend {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hook into the banner classes filter to add IAB TCF class.
		add_filter( 'wpconsent_banner_classes', array( $this, 'add_iab_tcf_class' ) );
		// Hook into the preferences modal classes filter to add IAB TCF class.
		add_filter( 'wpconsent_preferences_modal_classes', array( $this, 'add_iab_tcf_class' ) );
		// Hook into the service data attributes filter to add IAB vendor data.
		add_filter( 'wpconsent_service_attrs', array( $this, 'add_iab_vendor_data_attrs' ), 10, 4 );
		// Hook into preferences filters to inject tabbed interface.
		add_filter( 'wpconsent_preferences_after_description', array( $this, 'add_tc_string_storage_disclosure' ), 5 );
		add_filter( 'wpconsent_preferences_after_description', array( $this, 'add_tcf_tabs_header' ) );
		add_filter( 'wpconsent_preferences_after_cookies', array( $this, 'add_tcf_vendors_tab' ) );
		// Hook into category grouping to separate IAB TCF purposes from non-TCF categories.
		add_filter( 'wpconsent_preferences_category_groups', array( $this, 'group_categories_by_tcf_status' ), 10, 2 );
		// Hook into category description to add IAB TCF illustrations.
		add_filter( 'wpconsent_after_category_description', array( $this, 'add_iab_illustrations' ), 10, 3 );
		// Hook into preferences buttons filter to add Reject All button (TCF Policy C(f) compliance).
		add_filter( 'wpconsent_preferences_before_accept_button', array( $this, 'add_reject_all_button' ) );
		// Hook into the frontend JS data filter to add IAB TCF data.
		add_filter( 'wpconsent_frontend_js_data', array( $this, 'add_iab_tcf_data_to_frontend_js' ) );
		// Hook into the banner message filter to enforce IAB TCF banner message.
		add_filter( 'wpconsent_get_option_banner_message', array( $this, 'enforce_iab_tcf_banner_message' ) );
		// Hook into button text filters to enforce IAB TCF button texts.
		add_filter( 'wpconsent_get_option_accept_button_text', array( $this, 'enforce_accept_button_text' ) );
		add_filter( 'wpconsent_get_option_cancel_button_text', array( $this, 'enforce_cancel_button_text' ) );
		add_filter( 'wpconsent_get_option_preferences_button_text', array( $this, 'enforce_preferences_button_text' ) );
		add_filter( 'wpconsent_get_option_save_preferences_button_text', array(
			$this,
			'enforce_save_preferences_button_text'
		) );
		add_filter( 'wpconsent_get_option_preferences_panel_description', array(
			$this,
			'enforce_iab_tcf_preferences_description'
		) );
		add_filter( 'wpconsent_get_option_enable_consent_floating', array( $this, 'enforce_floating_button' ) );
		add_filter( 'wpconsent_get_option_banner_message', array( $this, 'add_vendors_link_to_banner' ) );
		// Hook into the banner rendering to add purposes/features list.
		add_filter( 'wpconsent_after_banner_message', array( $this, 'add_tcf_purposes_list_to_banner' ) );
	}

	/**
	 * Add IAB TCF class to banner holder classes when TCF is enabled and available.
	 *
	 * @param array $classes The existing banner classes.
	 *
	 * @return array Modified banner classes with IAB TCF class if applicable.
	 */
	public function add_iab_tcf_class( $classes ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $classes;
		}

		// Add the IAB TCF class.
		$classes[] = 'wpconsent-iab-tcf';

		return $classes;
	}

	/**
	 * Group categories by IAB TCF status.
	 *
	 * Separates IAB TCF purposes from non-TCF categories into distinct groups
	 * with appropriate section headings and descriptions (IAB TCF Policy B(e) & C(e)).
	 *
	 * @param array $groups Default groups (single group with all categories).
	 * @param array $categories All available categories.
	 *
	 * @return array Modified groups array with IAB TCF and non-TCF categories separated.
	 */
	public function group_categories_by_tcf_status( $groups, $categories ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $groups;
		}

		// Separate categories by IAB TCF status.
		$non_tcf_categories = array();
		$iab_tcf_purposes   = array();

		foreach ( $categories as $category_slug => $category ) {
			if ( ! empty( $category['is_iab_tcf'] ) ) {
				$iab_tcf_purposes[ $category_slug ] = $category;
			} else {
				$non_tcf_categories[ $category_slug ] = $category;
			}
		}

		// Build groups array.
		$new_groups = array();

		// Add non-TCF categories group if there are any.
		if ( ! empty( $non_tcf_categories ) ) {
			$new_groups[] = array(
				'title'       => __( 'Non-IAB TCF Categories', 'wpconsent-cookies-banner-privacy-suite' ),
				'description' => __( 'These categories are not part of the IAB Transparency & Consent Framework.', 'wpconsent-cookies-banner-privacy-suite' ),
				'css_class'   => 'wpconsent-non-tcf-section',
				'categories'  => $non_tcf_categories,
			);
		}

		// Add IAB TCF purposes group if there are any.
		if ( ! empty( $iab_tcf_purposes ) ) {
			$new_groups[] = array(
				'title'       => __( 'IAB TCF Purposes', 'wpconsent-cookies-banner-privacy-suite' ),
				'description' => __( 'These purposes are part of the IAB Transparency & Consent Framework.', 'wpconsent-cookies-banner-privacy-suite' ),
				'css_class'   => 'wpconsent-iab-tcf-section',
				'categories'  => $iab_tcf_purposes,
			);
		}

		return $new_groups;
	}

	/**
	 * Add IAB TCF illustrations after category description.
	 *
	 * Displays IAB TCF illustrations for purposes that have them,
	 * as required by IAB TCF Policy B(b).
	 *
	 * @param string $content Existing content.
	 * @param string $category_slug The category slug.
	 * @param array  $category The category data.
	 *
	 * @return string Content with illustrations if available.
	 */
	public function add_iab_illustrations( $content, $category_slug, $category ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $content;
		}

		// Check if this category has illustrations.
		if ( empty( $category['illustrations'] ) || ! is_array( $category['illustrations'] ) ) {
			return $content;
		}

		$html = '<div class="wpconsent-iab-illustrations">';
		$html .= '<p class="wpconsent-iab-illustrations-label"><strong>' . esc_html__( 'Examples:', 'wpconsent-cookies-banner-privacy-suite' ) . '</strong></p>';
		foreach ( $category['illustrations'] as $illustration ) {
			$html .= '<p class="wpconsent-iab-illustration">' . wp_kses_post( $illustration ) . '</p>';
		}
		$html .= '</div>';

		return $content . $html;
	}

	/**
	 * Add TC String storage disclosure to preferences modal.
	 *
	 * IAB TCF Policy D(c)(VII)c requires CMPs to disclose on the secondary layer
	 * how consent signals (TC String) are stored and for how long.
	 *
	 * @param string $content Existing content.
	 *
	 * @return string Content with TC String storage disclosure if TCF is enabled.
	 */
	public function add_tc_string_storage_disclosure( $content ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $content;
		}

		$disclosure = '<div class="wpconsent-tc-string-storage-disclosure">';
		$disclosure .= '<p class="wpconsent-storage-info">';
		$disclosure .= esc_html__( 'The choices you make regarding the purposes and entities listed in this notice are saved in your browser\'s localStorage and a cookie named "wpconsent_tcstring" for a maximum duration of 12 months.', 'wpconsent-cookies-banner-privacy-suite' );
		$disclosure .= '</p>';
		$disclosure .= '</div>';

		return $content . $disclosure;
	}

	/**
	 * Add IAB vendor data attributes to service elements.
	 *
	 * @param array  $attrs Array of attributes to add to the service element.
	 * @param array  $service Service data array.
	 * @param string $service_slug Service slug.
	 * @param string $category_slug Category slug.
	 *
	 * @return array Modified attributes array with IAB vendor data if applicable.
	 */
	public function add_iab_vendor_data_attrs( $attrs, $service, $service_slug, $category_slug ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $attrs;
		}

		// Check if this service is a "View Vendors list" link.
		if ( ! empty( $service['is_vendors_link'] ) ) {
			$attrs['data-vendors-link'] = 'true';
			$attrs['data-purpose-id']   = $service['purpose_id'];
			$attrs['class']             .= ' wpconsent-vendors-link-service';

			return $attrs;
		}

		// Check if this service has IAB vendor data.
		if ( ! empty( $service['vendor_data'] ) ) {
			$attrs['data-iab-vendor']  = 'true';
			$attrs['data-vendor-id']   = $service['vendor_data']['vendor_id'];
			$attrs['data-vendor-data'] = wp_json_encode( $service['vendor_data'] );
		}

		return $attrs;
	}

	/**
	 * Add TCF tabs header after description.
	 *
	 * @param string $content Existing content.
	 *
	 * @return string Content with tabs header if TCF is enabled.
	 */
	public function add_tcf_tabs_header( $content ) {
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $content;
		}

		$html = '<div class="wpconsent-tcf-tabs-container">';
		$html .= '<div class="wpconsent-tcf-tabs-nav">';
		$html .= '<button class="wpconsent-tcf-tab-button wpconsent-tcf-tab-active" data-tab="purposes">' . esc_html__( 'Purposes', 'wpconsent-cookies-banner-privacy-suite' ) . '</button>';
		$html .= '<button class="wpconsent-tcf-tab-button" data-tab="features">' . esc_html__( 'Features', 'wpconsent-cookies-banner-privacy-suite' ) . '</button>';
		$html .= '<button class="wpconsent-tcf-tab-button" data-tab="vendors">' . esc_html__( 'Vendors', 'wpconsent-cookies-banner-privacy-suite' ) . '</button>';
		$html .= '</div>';
		$html .= '<div class="wpconsent-tcf-tab-content wpconsent-tcf-tab-purposes wpconsent-tcf-tab-active">';

		return $content . $html;
	}

	/**
	 * Add TCF features and vendors tabs after cookies section.
	 *
	 * @param string $content Existing content.
	 *
	 * @return string Content with features and vendors tabs if TCF is enabled.
	 */
	public function add_tcf_vendors_tab( $content ) {
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $content;
		}

		$html = '';

		// Add Special Purposes section within Purposes tab (before closing it).
		$html .= '<div class="wpconsent-tcf-special-purposes-section">';
		$html .= '<div class="wpconsent-tcf-special-purposes-list" id="wpconsent-tcf-special-purposes-list">';
		$html .= '<!-- Special purposes list will be populated by JavaScript -->';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '</div>'; // Close purposes tab content

		// Add Features tab (includes both Features and Special Features).
		$html .= '<div class="wpconsent-tcf-tab-content wpconsent-tcf-tab-features">';
		$html .= '<div class="wpconsent-tcf-features-list" id="wpconsent-tcf-features-list">';
		$html .= '<!-- Features list will be populated by JavaScript -->';
		$html .= '</div>';
		$html .= '<div class="wpconsent-tcf-special-features-list" id="wpconsent-tcf-special-features-list">';
		$html .= '<!-- Special features list will be populated by JavaScript -->';
		$html .= '</div>';
		$html .= '</div>'; // Close features tab content

		// Add Vendors tab
		$html .= '<div class="wpconsent-tcf-tab-content wpconsent-tcf-tab-vendors">';
		$html .= '<div class="wpconsent-tcf-vendor-search">';
		$html .= '<div class="wpconsent-vendor-search-container">';
		$html .= '<input type="text" id="wpconsent-vendor-search" placeholder="' . esc_attr__( 'Search vendors...', 'wpconsent-cookies-banner-privacy-suite' ) . '" class="wpconsent-vendor-search-input">';
		$html .= '<button type="button" id="wpconsent-vendor-filter-btn" class="wpconsent-vendor-filter-button" aria-label="' . esc_attr__( 'Filter by purposes', 'wpconsent-cookies-banner-privacy-suite' ) . '">';
		$html .= '<span class="wpconsent-filter-icon">⚟</span>';
		$html .= '</button>';
		$html .= '</div>';
		$html .= '<div class="wpconsent-vendor-filter-dropdown" id="wpconsent-vendor-filter-dropdown" style="display: none;">';
		$html .= '<div class="wpconsent-filter-header">';
		$html .= '<h4>' . esc_html__( 'Filter Vendors', 'wpconsent-cookies-banner-privacy-suite' ) . '</h4>';
		$html .= '<button type="button" class="wpconsent-filter-clear" id="wpconsent-filter-clear">' . esc_html__( 'Clear All', 'wpconsent-cookies-banner-privacy-suite' ) . '</button>';
		$html .= '</div>';
		$html .= '<div class="wpconsent-filter-purposes" id="wpconsent-filter-purposes">';
		$html .= '<!-- Purpose checkboxes will be populated by JavaScript -->';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<div class="wpconsent-active-filters" id="wpconsent-active-filters">';
		$html .= '<!-- Active filters will be displayed here -->';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<div class="wpconsent-tcf-vendors-list" id="wpconsent-tcf-vendors-list">';
		$html .= '<!-- Vendors list will be populated by JavaScript -->';
		$html .= '</div>';
		$html .= '</div>'; // Close vendors tab content
		$html .= '</div>'; // Close tabs container

		return $content . $html;
	}

	/**
	 * Add Reject All button to preferences modal footer.
	 *
	 * This ensures compliance with TCF V2 Policy C(f) by providing an equivalent
	 * call to action to withdraw consent in the resurfaced preferences modal,
	 * just as "Accept All" is provided to give consent.
	 *
	 * @param string $content The existing content before the Accept All button.
	 *
	 * @return string Content with Reject All button if TCF is enabled.
	 */
	public function add_reject_all_button( $content ) {
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $content;
		}

		$cancel_button_text = wpconsent()->settings->get_option( 'cancel_button_text', esc_html__( 'Reject All', 'wpconsent-cookies-banner-privacy-suite' ) );

		$html = '<button class="wpconsent-cancel-cookies wpconsent-banner-button">' . esc_html( $cancel_button_text ) . '</button>';

		return $content . $html;
	}

	/**
	 * Add IAB TCF data to frontend JS localized data.
	 *
	 * @param array $data The existing frontend JS data.
	 *
	 * @return array Modified frontend JS data with IAB TCF data.
	 */
	public function add_iab_tcf_data_to_frontend_js( $data ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->settings->get_option( 'iab_tcf_frontend_enabled' ) ) {
			return $data;
		}

		// Add IAB TCF GVL configuration for frontend.
		$upload_dir              = wp_upload_dir();
		$data['iab_tcf_baseurl'] = $upload_dir['baseurl'] . '/wpconsent/cache/';

		// Get enabled vendor IDs from settings.
		$selected_vendors                = wpconsent()->settings->get_option( 'iab_tcf_selected_vendors', array() );
		$data['iab_tcf_enabled_vendors'] = array_map( 'intval', $selected_vendors );

		$data['iab_tcf_cmp_id']           = 482;
		$data['iab_tcf_cmp_version']      = wpconsent()->settings->get_option( 'iab_tcf_cmp_version', 1 );
		$data['iab_tcf_service_specific'] = wpconsent()->settings->get_option( 'iab_tcf_service_specific', false );

		// Add language from site locale (convert 'en_US' to 'EN', etc.).
		$locale   = get_locale();
		$language = 'EN'; // Default to English.
		if ( ! empty( $locale ) ) {
			// Extract first two characters and uppercase (e.g., 'en_US' -> 'EN').
			$language = strtoupper( substr( $locale, 0, 2 ) );
		}
		$data['iab_tcf_language'] = $language;

		// Add translations for JavaScript.
		$data['iab_tcf_translations'] = array(
			'Purposes:'                                                       => __( 'Purposes:', 'wpconsent-cookies-banner-privacy-suite' ),
			'Legitimate Interest Purposes:'                                   => __( 'Legitimate Interest Purposes:', 'wpconsent-cookies-banner-privacy-suite' ),
			'Special Purposes'                                                => __( 'Special Purposes', 'wpconsent-cookies-banner-privacy-suite' ),
			'Special Purposes:'                                               => __( 'Special Purposes:', 'wpconsent-cookies-banner-privacy-suite' ),
			'special_purposes_description'                                    => __( 'Special purposes are processing activities that do not require your consent. They are essential for the functioning of the service, such as security, fraud prevention, and technical delivery.', 'wpconsent-cookies-banner-privacy-suite' ),
			'View vendors using this special purpose'                         => __( 'View vendors using this special purpose', 'wpconsent-cookies-banner-privacy-suite' ),
			'Features'                                                        => __( 'Features', 'wpconsent-cookies-banner-privacy-suite' ),
			'Features:'                                                       => __( 'Features:', 'wpconsent-cookies-banner-privacy-suite' ),
			'features_description'                                            => __( 'Features are technical capabilities that vendors use to support their data processing purposes. These features do not require separate consent.', 'wpconsent-cookies-banner-privacy-suite' ),
			'Special Features'                                                => __( 'Special Features', 'wpconsent-cookies-banner-privacy-suite' ),
			'Special Features:'                                               => __( 'Special Features:', 'wpconsent-cookies-banner-privacy-suite' ),
			'special_features_description'                                    => __( 'Special features are data processing practices that require your explicit consent. You can object to their use by the vendors listed here.', 'wpconsent-cookies-banner-privacy-suite' ),
			'Cookie Storage:'                                                 => __( 'Cookie Storage:', 'wpconsent-cookies-banner-privacy-suite' ),
			'Data Processing:'                                                => __( 'Data Processing:', 'wpconsent-cookies-banner-privacy-suite' ),
			'Data Retention:'                                                 => __( 'Data Retention:', 'wpconsent-cookies-banner-privacy-suite' ),
			'Up to'                                                           => __( 'Up to', 'wpconsent-cookies-banner-privacy-suite' ),
			'days'                                                            => __( 'days', 'wpconsent-cookies-banner-privacy-suite' ),
			'Standard'                                                        => __( 'Standard', 'wpconsent-cookies-banner-privacy-suite' ),
			'duration refreshes from your last interaction with the property' => __( 'duration refreshes from your last interaction with the property', 'wpconsent-cookies-banner-privacy-suite' ),
			'May use non-cookie methods for data collection and processing'   => __( 'May use non-cookie methods for data collection and processing', 'wpconsent-cookies-banner-privacy-suite' ),
			'For more information, see their'                                 => __( 'For more information, see their', 'wpconsent-cookies-banner-privacy-suite' ),
			'privacy policy'                                                  => __( 'privacy policy', 'wpconsent-cookies-banner-privacy-suite' ),
			'View their'                                                      => __( 'View their', 'wpconsent-cookies-banner-privacy-suite' ),
			'legitimate interest disclosure'                                  => __( 'legitimate interest disclosure', 'wpconsent-cookies-banner-privacy-suite' ),
			'device storage disclosure'                                       => __( 'device storage disclosure', 'wpconsent-cookies-banner-privacy-suite' ),
			'for details about data storage'                                  => __( 'for details about data storage', 'wpconsent-cookies-banner-privacy-suite' ),
			'Active filters:'                                                 => __( 'Active filters:', 'wpconsent-cookies-banner-privacy-suite' ),
			'Processes:'                                                      => __( 'Processes:', 'wpconsent-cookies-banner-privacy-suite' ),
			'unique identifiers, browsing data, device information'           => __( 'unique identifiers, browsing data, device information', 'wpconsent-cookies-banner-privacy-suite' ),
			'Scope:'                                                          => __( 'Scope:', 'wpconsent-cookies-banner-privacy-suite' ),
			'Service-specific processing'                                     => __( 'Service-specific processing', 'wpconsent-cookies-banner-privacy-suite' ),
			'Examples:'                                                       => __( 'Examples:', 'wpconsent-cookies-banner-privacy-suite' ),
			'retention:'                                                      => __( 'retention:', 'wpconsent-cookies-banner-privacy-suite' ),
			'Data Categories:'                                                => __( 'Data Categories:', 'wpconsent-cookies-banner-privacy-suite' ),
		);

		// Add publisher restrictions data.
		$publisher_restrictions                 = wpconsent()->settings->get_option( 'iab_tcf_publisher_restrictions', array() );
		$data['iab_tcf_publisher_restrictions'] = $this->prepare_publisher_restrictions_for_frontend( $publisher_restrictions );

		// Add publisher declarations data.
		$publisher_declarations                 = wpconsent()->settings->get_option( 'iab_tcf_publisher_declarations', array() );
		$data['iab_tcf_publisher_declarations'] = $this->prepare_publisher_declarations_for_frontend( $publisher_declarations );

		return $data;
	}

	/**
	 * Get language-specific URL from vendor's urls array.
	 *
	 * @param array  $vendor The vendor data.
	 * @param string $url_type The URL type ('privacy' or 'legIntClaim').
	 * @param string $language The language code (default: 'en').
	 *
	 * @return string The URL or empty string if not found.
	 */
	private function get_vendor_language_url( $vendor, $url_type, $language = 'en' ) {
		if ( empty( $vendor['urls'] ) || ! is_array( $vendor['urls'] ) ) {
			return '';
		}

		// First, try to find exact language match.
		foreach ( $vendor['urls'] as $url_entry ) {
			if ( isset( $url_entry['langId'] ) && $url_entry['langId'] === $language && ! empty( $url_entry[ $url_type ] ) ) {
				return $url_entry[ $url_type ];
			}
		}

		// If no exact match, try English as fallback.
		if ( 'en' !== $language ) {
			foreach ( $vendor['urls'] as $url_entry ) {
				if ( isset( $url_entry['langId'] ) && 'en' === $url_entry['langId'] && ! empty( $url_entry[ $url_type ] ) ) {
					return $url_entry[ $url_type ];
				}
			}
		}

		// If still no match, return first available URL of the requested type.
		foreach ( $vendor['urls'] as $url_entry ) {
			if ( ! empty( $url_entry[ $url_type ] ) ) {
				return $url_entry[ $url_type ];
			}
		}

		return '';
	}

	/**
	 * Add vendors list link to the end of banner message when IAB TCF is enabled.
	 *
	 * @param string $message The current banner message.
	 *
	 * @return string The banner message with vendors link appended.
	 */
	public function add_vendors_link_to_banner( $message ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $message;
		}

		// Add the vendors list button at the end of the message.
		$vendors_link = ' <button type="button" class="wpconsent-view-vendors-link" id="wpconsent-view-vendors-link">' . esc_html__( 'View list of vendors', 'wpconsent-cookies-banner-privacy-suite' ) . '</button>';

		return $message . $vendors_link;
	}

	/**
	 * Enforce IAB TCF banner message when IAB TCF is enabled.
	 *
	 * This method overrides the banner message setting to enforce a TCF v2 compliant message.
	 * The purposes and special features are displayed in a separate column via the
	 * add_tcf_purposes_list_to_banner() method.
	 *
	 * Includes compliance information for:
	 * - Check #8: Consent scope information (service-specific).
	 * - Check #9: Consent withdrawal information.
	 * - Check #17: Legitimate interest information (first layer).
	 * - Check #18: Right to object information (first layer).
	 *
	 * @param string $message The current banner message from settings.
	 *
	 * @return string The enforced IAB TCF banner message or original message if IAB TCF is not enabled.
	 */
	public function enforce_iab_tcf_banner_message( $message ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $message;
		}

		// Build the base message.
		$banner_message = __( 'We and our [number_of_vendors] partners use cookies and other tracking technologies to store and access information on your device and to process your personal data.

With your consent, we and our partners may process personal data such as unique identifiers, browsing data, and precise geolocation data, including through device scanning. You can accept or reject all by clicking the buttons below, or customize your choices by clicking "Manage Settings".', 'wpconsent-cookies-banner-privacy-suite' );

		// Add legitimate interest notice if applicable (Check #17 & #18 compliance).
		if ( $this->has_legitimate_interest_vendors() ) {
			$banner_message .= "\n\n" . __( 'Some of our partners process your data based on their legitimate interest. You have the right to object to this processing. You can manage these settings by clicking "Manage Settings" and reviewing the legitimate interest options for each purpose and vendor.', 'wpconsent-cookies-banner-privacy-suite' );
		}

		// Add consent scope and withdrawal information (Check #8 & #9 compliance).
		$banner_message .= "\n\n" . __( 'This consent applies to this website only. You can withdraw or change your consent at any time by clicking the floating "Cookie Preferences" button at the bottom-left corner of your screen.', 'wpconsent-cookies-banner-privacy-suite' );

		return $banner_message;
	}

	/**
	 * Enforce "Accept All" button text when IAB TCF is enabled.
	 *
	 * @param string $text The current button text from settings.
	 *
	 * @return string The enforced button text or original text if IAB TCF is not enabled.
	 */
	public function enforce_accept_button_text( $text ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $text;
		}

		// Return the fixed button text.
		return __( 'Accept All', 'wpconsent-cookies-banner-privacy-suite' );
	}

	/**
	 * Enforce "Reject All" button text when IAB TCF is enabled.
	 *
	 * @param string $text The current button text from settings.
	 *
	 * @return string The enforced button text or original text if IAB TCF is not enabled.
	 */
	public function enforce_cancel_button_text( $text ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $text;
		}

		// Return the fixed button text.
		return __( 'Reject All', 'wpconsent-cookies-banner-privacy-suite' );
	}

	/**
	 * Enforce "Manage Settings" button text when IAB TCF is enabled.
	 *
	 * @param string $text The current button text from settings.
	 *
	 * @return string The enforced button text or original text if IAB TCF is not enabled.
	 */
	public function enforce_preferences_button_text( $text ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $text;
		}

		// Return the fixed button text.
		return __( 'Manage Settings', 'wpconsent-cookies-banner-privacy-suite' );
	}

	/**
	 * Enforce "Save Settings" button text in preferences modal when IAB TCF is enabled.
	 *
	 * @param string $text The current button text from settings.
	 *
	 * @return string The enforced button text or original text if IAB TCF is not enabled.
	 */
	public function enforce_save_preferences_button_text( $text ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $text;
		}

		// Return the fixed button text.
		return __( 'Save Settings', 'wpconsent-cookies-banner-privacy-suite' );
	}

	/**
	 * Enforce IAB TCF-compliant preferences panel description when IAB TCF is enabled.
	 *
	 * Per IAB TCF transparency requirements, the preferences panel should clearly
	 * communicate what users are managing and their ability to make granular choices.
	 *
	 * @param string $description The current description from settings.
	 *
	 * @return string The enforced description or original description if IAB TCF is not enabled.
	 */
	public function enforce_iab_tcf_preferences_description( $description ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $description;
		}

		// Return the IAB TCF-compliant description.
		// This message:
		// 1. Clarifies what users are managing (consent choices for purposes and vendors).
		// 2. Mentions granular control availability.
		// 3. Reinforces their ability to grant or withdraw consent.
		// 4. Complies with IAB TCF transparency requirements.
		return __( 'Review and customize your consent choices for purposes and vendors. You can grant or withdraw consent for each purpose and vendor individually, or use the buttons below to accept or reject all.', 'wpconsent-cookies-banner-privacy-suite' );
	}

	/**
	 * Enforce floating button to be enabled when IAB TCF is active.
	 *
	 * IAB TCF Policy C(f) requires easy resurfacing of the consent UI
	 * for users to review and change their preferences at any time.
	 * The floating button provides this persistent access point.
	 *
	 * @param bool $enabled The current floating button enabled setting.
	 *
	 * @return bool True when IAB TCF is enabled, original value otherwise.
	 */
	public function enforce_floating_button( $enabled ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $enabled;
		}

		// Always enable floating button for IAB TCF compliance (Policy C(f) requirement).
		return true;
	}

	/**
	 * Add TCF purposes and special features list to the banner.
	 *
	 * Creates a scrollable column displaying all purposes and special features
	 * used by enabled vendors, as required by TCF Policy C(b)(IV) and C(b)(V).
	 *
	 * @param string $content The existing banner content.
	 *
	 * @return string Modified content with purposes/features list added.
	 */
	public function add_tcf_purposes_list_to_banner( $content ) {
		// Check if IAB TCF is enabled.
		if ( ! wpconsent()->iab_tcf->is_enabled() ) {
			return $content;
		}

		// Get purposes and special features HTML.
		$purposes_html         = $this->get_purposes_list_html();
		$special_features_html = $this->get_special_features_list_html();

		// If nothing to display, return empty.
		if ( empty( $purposes_html ) && empty( $special_features_html ) ) {
			return $content;
		}

		// Build the HTML for inline purposes/features display.
		$html = '';

		if ( ! empty( $purposes_html ) ) {
			$html .= '<p class="wpconsent-tcf-purposes-inline">';
			$html .= '<strong>' . esc_html__( 'Data Processing Purposes:', 'wpconsent-cookies-banner-privacy-suite' ) . '</strong> ';
			$html .= $purposes_html;
			$html .= '</p>';
		}

		if ( ! empty( $special_features_html ) ) {
			$html .= '<p class="wpconsent-tcf-special-features-inline">';
			$html .= '<strong>' . esc_html__( 'Special Features:', 'wpconsent-cookies-banner-privacy-suite' ) . '</strong> ';
			$html .= $special_features_html;
			$html .= '</p>';
		}

		return $content . $html;
	}

	/**
	 * Get formatted HTML list of purposes for the banner.
	 *
	 * Aggregates all unique purposes from enabled vendors and formats them
	 * with standardized IAB names as required by TCF Policy C(b)(IV).
	 * Includes vendor counts per purpose as required by TCF Policy C(c)(II) and D(c)(IV).
	 *
	 * @return string Formatted purposes list as HTML list items.
	 */
	private function get_purposes_list_html() {
		// Get enabled vendor IDs from settings.
		$selected_vendors = wpconsent()->settings->get_option( 'iab_tcf_selected_vendors', array() );
		if ( empty( $selected_vendors ) ) {
			return '';
		}

		// Get IAB vendors instance.
		$iab_vendors = WPConsent_IAB_TCF_Vendors::get_instance();
		if ( ! $iab_vendors->is_available() ) {
			return '';
		}

		// Get all purposes reference data.
		$all_purposes = $iab_vendors->get_purposes();
		if ( empty( $all_purposes ) ) {
			return '';
		}

		// Get all vendors data.
		$all_vendors = $iab_vendors->get_vendors();
		if ( empty( $all_vendors ) ) {
			return '';
		}

		// Count vendors for each purpose (consent and legitimate interest).
		$purpose_vendor_counts = array();
		foreach ( $selected_vendors as $vendor_id ) {
			$vendor_id = (int) $vendor_id;
			if ( ! isset( $all_vendors[ $vendor_id ] ) ) {
				continue;
			}

			$vendor = $all_vendors[ $vendor_id ];

			// Count vendors seeking consent for purposes.
			if ( ! empty( $vendor['purposes'] ) && is_array( $vendor['purposes'] ) ) {
				foreach ( $vendor['purposes'] as $purpose_id ) {
					if ( ! isset( $purpose_vendor_counts[ $purpose_id ] ) ) {
						$purpose_vendor_counts[ $purpose_id ] = array(
							'consent' => 0,
							'legInt'  => 0,
						);
					}
					$purpose_vendor_counts[ $purpose_id ]['consent'] ++;
				}
			}

			// Count vendors using legitimate interest for purposes.
			if ( ! empty( $vendor['legIntPurposes'] ) && is_array( $vendor['legIntPurposes'] ) ) {
				foreach ( $vendor['legIntPurposes'] as $purpose_id ) {
					if ( ! isset( $purpose_vendor_counts[ $purpose_id ] ) ) {
						$purpose_vendor_counts[ $purpose_id ] = array(
							'consent' => 0,
							'legInt'  => 0,
						);
					}
					$purpose_vendor_counts[ $purpose_id ]['legInt'] ++;
				}
			}
		}

		// Sort purpose IDs numerically.
		ksort( $purpose_vendor_counts );

		// Build the formatted list as comma-separated inline text.
		$purpose_items = array();
		foreach ( $purpose_vendor_counts as $purpose_id => $counts ) {
			if ( ! isset( $all_purposes[ $purpose_id ]['name'] ) ) {
				continue;
			}

			$purpose_name = $all_purposes[ $purpose_id ]['name'];

			// Build vendor count text showing consent and/or legitimate interest.
			$vendor_count_parts = array();
			if ( $counts['consent'] > 0 ) {
				/* translators: %d: number of vendors */
				$vendor_count_parts[] = sprintf( _n( '%d seeking consent', '%d seeking consent', $counts['consent'], 'wpconsent-cookies-banner-privacy-suite' ), $counts['consent'] );
			}
			if ( $counts['legInt'] > 0 ) {
				/* translators: %d: number of vendors */
				$vendor_count_parts[] = sprintf( _n( '%d using legitimate interest', '%d using legitimate interest', $counts['legInt'], 'wpconsent-cookies-banner-privacy-suite' ), $counts['legInt'] );
			}

			$vendor_count_text = implode( ', ', $vendor_count_parts );

			/* translators: 1: purpose name, 2: number of vendors and their legal basis */
			$purpose_items[] = sprintf(
				esc_html__( '%1$s (%2$s)', 'wpconsent-cookies-banner-privacy-suite' ),
				esc_html( $purpose_name ),
				esc_html( $vendor_count_text )
			);
		}

		return implode( '; ', $purpose_items );
	}

	/**
	 * Get formatted HTML list of special features for the banner.
	 *
	 * Aggregates all unique special features from enabled vendors and formats them
	 * with standardized IAB names as required by TCF Policy C(b)(V).
	 *
	 * @return string Formatted special features list as HTML list items.
	 */
	private function get_special_features_list_html() {
		// Get enabled vendor IDs from settings.
		$selected_vendors = wpconsent()->settings->get_option( 'iab_tcf_selected_vendors', array() );
		if ( empty( $selected_vendors ) ) {
			return '';
		}

		// Get IAB vendors instance.
		$iab_vendors = WPConsent_IAB_TCF_Vendors::get_instance();
		if ( ! $iab_vendors->is_available() ) {
			return '';
		}

		// Get all special features reference data.
		$all_special_features = $iab_vendors->get_special_features();
		if ( empty( $all_special_features ) ) {
			return '';
		}

		// Get all vendors data.
		$all_vendors = $iab_vendors->get_vendors();
		if ( empty( $all_vendors ) ) {
			return '';
		}

		// Collect unique special feature IDs from enabled vendors.
		$unique_special_feature_ids = array();
		foreach ( $selected_vendors as $vendor_id ) {
			$vendor_id = (int) $vendor_id;
			if ( ! isset( $all_vendors[ $vendor_id ] ) ) {
				continue;
			}

			$vendor = $all_vendors[ $vendor_id ];

			// Add special features.
			if ( ! empty( $vendor['specialFeatures'] ) && is_array( $vendor['specialFeatures'] ) ) {
				$unique_special_feature_ids = array_merge( $unique_special_feature_ids, $vendor['specialFeatures'] );
			}
		}

		// Remove duplicates and sort.
		$unique_special_feature_ids = array_unique( $unique_special_feature_ids );
		sort( $unique_special_feature_ids );

		// If no special features are used, return empty string.
		if ( empty( $unique_special_feature_ids ) ) {
			return '';
		}

		// Build the formatted list as comma-separated inline text.
		$feature_names = array();
		foreach ( $unique_special_feature_ids as $feature_id ) {
			if ( isset( $all_special_features[ $feature_id ]['name'] ) ) {
				$feature_names[] = esc_html( $all_special_features[ $feature_id ]['name'] );
			}
		}

		return implode( '; ', $feature_names );
	}

	/**
	 * Check if any enabled vendors use legitimate interest.
	 *
	 * Used to conditionally add legitimate interest notice to banner message.
	 *
	 * @return bool True if at least one enabled vendor uses legitimate interest, false otherwise.
	 */
	private function has_legitimate_interest_vendors() {
		// Get enabled vendor IDs from settings.
		$selected_vendors = wpconsent()->settings->get_option( 'iab_tcf_selected_vendors', array() );
		if ( empty( $selected_vendors ) ) {
			return false;
		}

		// Get IAB vendors instance.
		$iab_vendors = WPConsent_IAB_TCF_Vendors::get_instance();
		if ( ! $iab_vendors->is_available() ) {
			return false;
		}

		// Get all vendors data.
		$all_vendors = $iab_vendors->get_vendors();
		if ( empty( $all_vendors ) ) {
			return false;
		}

		// Check if any enabled vendor uses legitimate interest.
		foreach ( $selected_vendors as $vendor_id ) {
			$vendor_id = (int) $vendor_id;
			if ( ! isset( $all_vendors[ $vendor_id ] ) ) {
				continue;
			}

			$vendor = $all_vendors[ $vendor_id ];

			// Check if vendor has any legitimate interest purposes.
			if ( ! empty( $vendor['legIntPurposes'] ) && is_array( $vendor['legIntPurposes'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Prepare publisher restrictions data for frontend JavaScript.
	 *
	 * Ensures all IDs are properly typed as integers for JavaScript consumption.
	 *
	 * @param array $restrictions Raw publisher restrictions from database.
	 *
	 * @return array Processed restrictions data ready for frontend use.
	 */
	private function prepare_publisher_restrictions_for_frontend( $restrictions ) {
		if ( empty( $restrictions ) || ! is_array( $restrictions ) ) {
			return array(
				'global'  => array(),
				'vendors' => array(),
			);
		}

		$prepared = array(
			'global'  => array(),
			'vendors' => array(),
		);

		// Process global restrictions.
		if ( ! empty( $restrictions['global'] ) && is_array( $restrictions['global'] ) ) {
			$global = $restrictions['global'];

			// Process disallow_li_purposes.
			if ( ! empty( $global['disallow_li_purposes'] ) && is_array( $global['disallow_li_purposes'] ) ) {
				$purposes = $global['disallow_li_purposes'];

				// Check if this is "all" purposes.
				if ( in_array( 'all', $purposes, true ) ) {
					$prepared['global']['disallow_li_purposes'] = array( 'all' );
				} else {
					// Convert to integers.
					$prepared['global']['disallow_li_purposes'] = array_map( 'intval', $purposes );
				}
			}
		}

		// Process per-vendor restrictions.
		if ( ! empty( $restrictions['vendors'] ) && is_array( $restrictions['vendors'] ) ) {
			foreach ( $restrictions['vendors'] as $vendor_id => $vendor_restrictions ) {
				$vendor_id_int = (int) $vendor_id;

				if ( empty( $vendor_restrictions ) || ! is_array( $vendor_restrictions ) ) {
					continue;
				}

				$prepared['vendors'][ $vendor_id_int ] = array();

				// Process disallowed_purposes.
				if ( ! empty( $vendor_restrictions['disallowed_purposes'] ) && is_array( $vendor_restrictions['disallowed_purposes'] ) ) {
					$prepared['vendors'][ $vendor_id_int ]['disallowed_purposes'] = array_map( 'intval', $vendor_restrictions['disallowed_purposes'] );
				}

				// Process require_consent_for_li.
				if ( ! empty( $vendor_restrictions['require_consent_for_li'] ) && is_array( $vendor_restrictions['require_consent_for_li'] ) ) {
					$prepared['vendors'][ $vendor_id_int ]['require_consent_for_li'] = array_map( 'intval', $vendor_restrictions['require_consent_for_li'] );
				}

				// Process disallowed_special_purposes.
				if ( ! empty( $vendor_restrictions['disallowed_special_purposes'] ) && is_array( $vendor_restrictions['disallowed_special_purposes'] ) ) {
					$prepared['vendors'][ $vendor_id_int ]['disallowed_special_purposes'] = array_map( 'intval', $vendor_restrictions['disallowed_special_purposes'] );
				}

				// Remove vendor entry if empty.
				if ( empty( $prepared['vendors'][ $vendor_id_int ] ) ) {
					unset( $prepared['vendors'][ $vendor_id_int ] );
				}
			}
		}

		return $prepared;
	}

	/**
	 * Prepare publisher declarations data for frontend JavaScript.
	 *
	 * Ensures all IDs are properly typed as integers for JavaScript consumption.
	 *
	 * @param array $declarations Raw publisher declarations from database.
	 *
	 * @return array Processed declarations data ready for frontend use.
	 */
	private function prepare_publisher_declarations_for_frontend( $declarations ) {
		if ( empty( $declarations ) || ! is_array( $declarations ) ) {
			return array(
				'purposes_consent'         => array(),
				'purposes_li_transparency' => array(),
			);
		}

		$prepared = array(
			'purposes_consent'         => array(),
			'purposes_li_transparency' => array(),
		);

		// Process purposes (consent).
		if ( ! empty( $declarations['purposes_consent'] ) && is_array( $declarations['purposes_consent'] ) ) {
			$prepared['purposes_consent'] = array_map( 'intval', $declarations['purposes_consent'] );
		}

		// Process purposes (legitimate interest).
		if ( ! empty( $declarations['purposes_li_transparency'] ) && is_array( $declarations['purposes_li_transparency'] ) ) {
			$prepared['purposes_li_transparency'] = array_map( 'intval', $declarations['purposes_li_transparency'] );
		}

		return $prepared;
	}
}
