<?php
/**
 * IAB TCF Categories replacement handler for WPConsent Pro.
 *
 * @package WPConsent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPConsent_IAB_TCF_Categories
 *
 * Handles replacement of regular categories with IAB TCF purposes when IAB TCF is enabled.
 */
class WPConsent_IAB_TCF {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'wpconsent_get_categories', array( $this, 'maybe_replace_categories_with_iab_purposes' ), 10, 1 );
		add_filter( 'wpconsent_show_category_edit_button', array( $this, 'hide_category_edit_button' ), 10, 3 );
		add_filter( 'wpconsent_show_category_delete_button', array( $this, 'hide_category_delete_button' ), 10, 3 );
		add_filter( 'wpconsent_get_cookies_from_cache', array( $this, 'maybe_inject_iab_vendors_to_cache' ), 10, 1 );

		add_action( 'init', array( $this, 'init_iab_enabled' ) );
	}

	/**
	 * Initialize the IAB TCF filters and components that need to run when IAB TCF is enabled.
	 *
	 * This method checks if IAB TCF is enabled and initializes the frontend class.
	 */
	public function init_iab_enabled() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		new WPConsent_IAB_TCF_Frontend();

		// Ensure manual toggle services is always enabled when IAB TCF is active.
		add_filter( 'wpconsent_get_option_manual_toggle_services', '__return_true' );

		// Force banner layout to modal when IAB TCF is enabled.
		add_filter( 'wpconsent_get_option_banner_layout', array( $this, 'force_modal_layout' ) );
	}

	/**
	 * Replace categories with IAB purposes if IAB TCF is enabled.
	 *
	 * @param array $categories The original categories array.
	 *
	 * @return array Modified categories array or original if IAB TCF is not enabled.
	 */
	public function maybe_replace_categories_with_iab_purposes( $categories ) {
		// Check if IAB TCF is enabled.
		if ( ! $this->is_enabled() ) {
			return $categories;
		}

		// Check if IAB TCF vendors class is available.
		if ( ! class_exists( 'WPConsent_IAB_TCF_Vendors' ) ) {
			return $categories;
		}

		$iab_vendors = WPConsent_IAB_TCF_Vendors::get_instance();
		if ( ! $iab_vendors->is_available() ) {
			return $categories;
		}

		// Get IAB purposes.
		$purposes = $iab_vendors->get_purposes();
		if ( empty( $purposes ) ) {
			return $categories;
		}

		// Convert IAB purposes to category format.
		$iab_categories = $this->convert_iab_purposes_to_categories( $purposes );

		return $iab_categories;
	}

	/**
	 * Check if IAB TCF is enabled.
	 *
	 * @return bool True if IAB TCF is enabled, false otherwise.
	 */
	public function is_enabled() {
		return wpconsent()->settings->get_option( 'iab_tcf_frontend_enabled', false );
	}

	/**
	 * Force banner layout to modal when IAB TCF is enabled.
	 *
	 * @param string $layout The current banner layout.
	 *
	 * @return string Always returns 'modal' when IAB TCF is enabled.
	 */
	public function force_modal_layout( $layout ) {
		return 'modal';
	}

	/**
	 * Convert IAB purposes to WPConsent category format.
	 * Includes vendor counts per purpose as required by TCF Policy C(c)(II) and D(c)(IV).
	 *
	 * Marketing and statistics categories are preserved and displayed separately from IAB purposes
	 * as non-TCF categories, just like the essential category.
	 *
	 * @param array $purposes IAB purposes array.
	 *
	 * @return array Categories array in WPConsent format.
	 */
	private function convert_iab_purposes_to_categories( $purposes ) {
		$categories = array();

		// Preserve all regular categories (essential, marketing, statistics, etc.).
		// We need to temporarily remove our filter to get the actual regular categories with their IDs.
		remove_filter( 'wpconsent_get_categories', array( $this, 'maybe_replace_categories_with_iab_purposes' ), 10 );
		$regular_categories = wpconsent()->cookies->get_categories();
		add_filter( 'wpconsent_get_categories', array( $this, 'maybe_replace_categories_with_iab_purposes' ), 10, 1 );

		// Include all regular categories as non-TCF categories.
		foreach ( $regular_categories as $category_slug => $category ) {
			$categories[ $category_slug ] = array_merge(
				$category,
				array( 'is_iab_tcf' => false )  // Mark as non-IAB TCF category.
			);
		}

		// Get vendor counts for each purpose.
		$purpose_vendor_counts = $this->get_purpose_vendor_counts();

		// Convert IAB purposes to categories.
		foreach ( $purposes as $purpose_id => $purpose ) {
			$slug = 'iab_purpose_' . $purpose_id;

			// Build category name with vendor count.
			$purpose_name = isset( $purpose['name'] ) ? $purpose['name'] : 'IAB Purpose ' . $purpose_id;

			// Add vendor count to name if available.
			if ( isset( $purpose_vendor_counts[ $purpose_id ] ) ) {
				$counts = $purpose_vendor_counts[ $purpose_id ];
				$vendor_count_parts = array();

				if ( $counts['consent'] > 0 ) {
					/* translators: %d: number of vendors */
					$vendor_count_parts[] = sprintf( _n( '%d seeking consent', '%d seeking consent', $counts['consent'], 'wpconsent-cookies-banner-privacy-suite' ), $counts['consent'] );
				}
				if ( $counts['legInt'] > 0 ) {
					/* translators: %d: number of vendors */
					$vendor_count_parts[] = sprintf( _n( '%d using legitimate interest', '%d using legitimate interest', $counts['legInt'], 'wpconsent-cookies-banner-privacy-suite' ), $counts['legInt'] );
				}

				if ( ! empty( $vendor_count_parts ) ) {
					$vendor_count_text = implode( ', ', $vendor_count_parts );
					/* translators: 1: purpose name, 2: number of vendors and their legal basis */
					$purpose_name = sprintf(
						__( '%1$s (%2$s)', 'wpconsent-cookies-banner-privacy-suite' ),
						$purpose_name,
						$vendor_count_text
					);
				}
			}

			$categories[ $slug ] = array(
				'name'        => $purpose_name,
				'description'   => isset( $purpose['description'] ) ? $purpose['description'] : '',
				'illustrations' => isset( $purpose['illustrations'] ) ? $purpose['illustrations'] : array(),
				'required'    => 0, // IAB purposes are not required by default.
				'id'          => - $purpose_id, // Use negative IDs to distinguish from regular categories.
				'is_iab_tcf'  => true, // This is an IAB TCF purpose.
			);
		}

		return $categories;
	}

	/**
	 * Get vendor counts for each purpose (consent and legitimate interest).
	 *
	 * @return array Associative array of purpose IDs to vendor count arrays.
	 */
	private function get_purpose_vendor_counts() {
		// Get enabled vendor IDs from settings.
		$selected_vendors = wpconsent()->settings->get_option( 'iab_tcf_selected_vendors', array() );
		if ( empty( $selected_vendors ) ) {
			return array();
		}

		// Get IAB vendors instance.
		$iab_vendors = WPConsent_IAB_TCF_Vendors::get_instance();
		if ( ! $iab_vendors->is_available() ) {
			return array();
		}

		// Get all vendors data.
		$all_vendors = $iab_vendors->get_vendors();
		if ( empty( $all_vendors ) ) {
			return array();
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
					$purpose_vendor_counts[ $purpose_id ]['consent']++;
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
					$purpose_vendor_counts[ $purpose_id ]['legInt']++;
				}
			}
		}

		return $purpose_vendor_counts;
	}

	/**
	 * Hide edit button for IAB TCF categories.
	 *
	 * @param bool   $show_button Whether to show the edit button.
	 * @param string $slug Category slug.
	 * @param array  $category Category data.
	 *
	 * @return bool Whether to show the edit button.
	 */
	public function hide_category_edit_button( $show_button, $slug, $category ) {
		// If IAB TCF is not enabled, don't modify the button visibility.
		if ( ! $this->is_enabled() ) {
			return $show_button;
		}

		// Hide edit button for all IAB purpose categories and essential category when IAB TCF is enabled.
		if ( strpos( $slug, 'iab_purpose_' ) === 0 || $slug === 'essential' ) {
			return false;
		}

		return $show_button;
	}

	/**
	 * Hide delete button for IAB TCF categories.
	 *
	 * @param bool   $show_button Whether to show the delete button.
	 * @param string $slug Category slug.
	 * @param array  $category Category data.
	 *
	 * @return bool Whether to show the delete button.
	 */
	public function hide_category_delete_button( $show_button, $slug, $category ) {
		// If IAB TCF is not enabled, don't modify the button visibility.
		if ( ! $this->is_enabled() ) {
			return $show_button;
		}

		// Hide delete button for all IAB purpose categories and essential category when IAB TCF is enabled.
		if ( strpos( $slug, 'iab_purpose_' ) === 0 || $slug === 'essential' ) {
			return false;
		}

		return $show_button;
	}


	/**
	 * Extract IAB purpose ID from category ID (if it's an IAB purpose category).
	 *
	 * @param int $category_id The category ID.
	 *
	 * @return int|false Purpose ID or false if not an IAB purpose category.
	 */
	private function extract_purpose_id_from_category( $category_id ) {
		// IAB purposes have negative IDs, so check if this is a negative ID.
		if ( $category_id < 0 ) {
			// Convert negative ID back to positive purpose ID.
			return abs( $category_id );
		}

		return false;
	}


	/**
	 * Transform cache structure to use IAB purposes and inject vendors when IAB TCF is enabled.
	 *
	 * @param array $cached_cookies The cached cookies array.
	 *
	 * @return array Modified cache array with IAB purposes and vendors injected.
	 */
	public function maybe_inject_iab_vendors_to_cache( $cached_cookies ) {
		// If IAB TCF is not enabled, return cache as-is.
		if ( ! $this->is_enabled() ) {
			return $cached_cookies;
		}

		// Check if IAB TCF vendors class is available.
		if ( ! class_exists( 'WPConsent_IAB_TCF_Vendors' ) ) {
			return $cached_cookies;
		}

		$iab_vendors = WPConsent_IAB_TCF_Vendors::get_instance();
		if ( ! $iab_vendors->is_available() ) {
			return $cached_cookies;
		}

		// Get IAB purposes.
		$purposes = $iab_vendors->get_purposes();
		if ( empty( $purposes ) ) {
			return $cached_cookies;
		}

		// First, transform the cache structure to use IAB purposes instead of regular categories.
		$iab_cache = $this->transform_cache_to_iab_purposes( $cached_cookies, $purposes );

		// Then, add IAB vendors as services to each appropriate purpose.
		$iab_cache = $this->inject_vendors_into_iab_cache( $iab_cache, $iab_vendors, $purposes );

		return $iab_cache;
	}

	/**
	 * Transform the cache structure to use IAB purposes alongside regular categories.
	 *
	 * Marketing and statistics categories are preserved as non-TCF categories and displayed
	 * separately from IAB TCF purposes, just like the essential category.
	 *
	 * @param array $cached_cookies The original cached cookies array.
	 * @param array $purposes The IAB purposes array.
	 *
	 * @return array Transformed cache with IAB purposes and preserved regular categories.
	 */
	private function transform_cache_to_iab_purposes( $cached_cookies, $purposes ) {
		$iab_cache = array();

		// Get regular categories to identify which ones to preserve.
		remove_filter( 'wpconsent_get_categories', array( $this, 'maybe_replace_categories_with_iab_purposes' ), 10 );
		$regular_categories = wpconsent()->cookies->get_categories();
		add_filter( 'wpconsent_get_categories', array( $this, 'maybe_replace_categories_with_iab_purposes' ), 10, 1 );

		// Preserve all regular categories (essential, marketing, statistics, etc.).
		// These will be displayed as non-TCF categories alongside IAB purposes.
		foreach ( $regular_categories as $category_slug => $category ) {
			$category_id = $category['id'];
			if ( isset( $cached_cookies[ $category_id ] ) ) {
				$iab_cache[ $category_id ] = $cached_cookies[ $category_id ];
			}
		}

		// Create IAB purpose categories without mapping any cookies/services to them.
		// IAB purposes will only contain IAB vendors, not regular cookies/services.
		foreach ( $purposes as $purpose_id => $purpose ) {
			$iab_category_id = - $purpose_id; // Use negative IDs for IAB purposes.

			// Initialize the IAB purpose category with empty cookies and services.
			$iab_cache[ $iab_category_id ] = array(
				'cookies'  => array(),
				'services' => array(),
			);
		}

		return $iab_cache;
	}

	/**
	 * Inject IAB vendors as services into the IAB cache structure.
	 *
	 * @param array                     $iab_cache The IAB cache structure.
	 * @param WPConsent_IAB_TCF_Vendors $iab_vendors The IAB vendors instance.
	 * @param array                     $purposes The IAB purposes array.
	 *
	 * @return array Modified cache with vendors injected.
	 */
	private function inject_vendors_into_iab_cache( $iab_cache, $iab_vendors, $purposes ) {
		// Get selected vendors from admin settings.
		$selected_vendor_ids = wpconsent()->settings->get_option( 'iab_tcf_selected_vendors', array() );
		if ( empty( $selected_vendor_ids ) ) {
			return $iab_cache;
		}

		// Get all vendors data.
		$all_vendors = $iab_vendors->get_vendors();
		if ( empty( $all_vendors ) ) {
			return $iab_cache;
		}

		// Add "View Vendors list" link to each relevant IAB purpose category instead of individual vendors.
		foreach ( $iab_cache as $category_id => $category_data ) {
			// Check if this is an IAB purpose category (negative ID).
			$purpose_id = $this->extract_purpose_id_from_category( $category_id );
			if ( ! $purpose_id ) {
				continue;
			}

			// Check if there are vendors that support this purpose.
			$has_vendors_for_purpose = false;
			foreach ( $selected_vendor_ids as $vendor_id ) {
				$vendor_id = (int) $vendor_id;
				if ( ! isset( $all_vendors[ $vendor_id ] ) ) {
					continue;
				}

				$vendor = $all_vendors[ $vendor_id ];

				// Check if this vendor supports the current purpose.
				if ( $this->vendor_supports_purpose( $vendor, $purpose_id ) ) {
					$has_vendors_for_purpose = true;
					break;
				}
			}

			// Only add the "View Vendors list" link if there are vendors for this purpose.
			if ( $has_vendors_for_purpose ) {
				// Add a special service that represents the "View Vendors list" link.
				$vendor_link_slug = 'iab_vendors_link_' . $purpose_id;
				$iab_cache[ $category_id ]['services'][ $vendor_link_slug ] = array(
					'name'        => __( 'View Vendors list', 'wpconsent-cookies-banner-privacy-suite' ),
					'description' => __( 'Click to view and manage vendors that process data for this purpose.', 'wpconsent-cookies-banner-privacy-suite' ),
					'service_url' => '',
					'cookies'     => array(),
					'is_vendors_link' => true, // Special flag to identify this as a vendors link.
					'purpose_id'      => $purpose_id,
				);
			}
		}

		return $iab_cache;
	}


	/**
	 * Check if a vendor supports a specific purpose.
	 *
	 * @param array $vendor The vendor data.
	 * @param int   $purpose_id The purpose ID.
	 *
	 * @return bool True if vendor supports the purpose, false otherwise.
	 */
	private function vendor_supports_purpose( $vendor, $purpose_id ) {
		// Check in purposes array.
		if ( isset( $vendor['purposes'] ) && in_array( $purpose_id, $vendor['purposes'], true ) ) {
			return true;
		}

		// Check in legitimate interests array.
		if ( isset( $vendor['legIntPurposes'] ) && in_array( $purpose_id, $vendor['legIntPurposes'], true ) ) {
			return true;
		}

		return false;
	}
}
