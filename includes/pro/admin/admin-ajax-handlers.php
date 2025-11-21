<?php
/**
 * Admin ajax handlers for the Pro version of the plugin.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_wpconsent_verify_license', 'wpconsent_verify_license' );
add_action( 'wp_ajax_wpconsent_deactivate_license', 'wpconsent_deactivate_license' );
add_action( 'wp_ajax_wpconsent_export_start', 'wpconsent_handle_export_start' );
add_action( 'wp_ajax_wpconsent_export_batch', 'wpconsent_handle_export_batch' );
add_action( 'wp_ajax_wpconsent_export_download', 'wpconsent_handle_export_download' );

add_action( 'wp_ajax_wpconsent_switch_language', 'wpconsent_ajax_switch_language' );
add_action( 'wp_ajax_wpconsent_get_services_library', 'wpconsent_ajax_get_services_library' );
add_action( 'wp_ajax_wpconsent_import_service_from_library', 'wpconsent_ajax_import_service_from_library' );

add_action( 'wp_ajax_wpconsent_install_addon', 'wpconsent_install_addon' );
add_action( 'wp_ajax_wpconsent_save_location_group', 'wpconsent_save_location_group' );
add_action( 'wp_ajax_wpconsent_delete_location_group', 'wpconsent_delete_location_group' );
add_action( 'wp_ajax_wpconsent_get_available_locations', 'wpconsent_get_available_locations' );
add_action( 'wp_ajax_wpconsent_create_predefined_rule', 'wpconsent_create_predefined_rule' );

add_action( 'wp_ajax_wpconsent_add_script', 'wpconsent_ajax_add_script' );
add_action( 'wp_ajax_wpconsent_edit_script', 'wpconsent_ajax_edit_script' );
add_action( 'wp_ajax_wpconsent_delete_script', 'wpconsent_ajax_delete_script' );

add_action( 'wp_ajax_wpconsent_start_translation', 'wpconsent_ajax_start_translation' );
add_action( 'wp_ajax_wpconsent_check_translation_progress', 'wpconsent_ajax_check_translation_progress' );
add_action( 'wp_ajax_wpconsent_reset_translation', 'wpconsent_ajax_reset_translation' );

add_action( 'wp_ajax_wpconsent_delete_roc_start', 'wpconsent_handle_delete_roc_start' );
add_action( 'wp_ajax_wpconsent_delete_roc_batch', 'wpconsent_handle_delete_roc_batch' );

/**
 * Verify license via Ajax.
 *
 * @since 1.0.0
 */
function wpconsent_verify_license() {

	// Run a security check.
	check_ajax_referer( 'wpconsent_admin' );

	// Check for permissions.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error();
	}

	// Check for license key.
	if ( empty( $_POST['license'] ) ) {
		wp_send_json_error( esc_html__( 'Please enter a license key.', 'wpconsent-premium' ) );
	}
	$multisite = isset( $_POST['multisite'] ) && boolval( $_POST['multisite'] );

	wpconsent()->license->verify_key( sanitize_text_field( wp_unslash( $_POST['license'] ) ), true, $multisite );
}

/**
 * Deactivate license via Ajax.
 *
 * @since 1.0.0
 */
function wpconsent_deactivate_license() {
	// Run a security check.
	check_ajax_referer( 'wpconsent_admin' );

	// Check for permissions.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error();
	}

	$force = isset( $_POST['force'] ) && 'true' === $_POST['force'];

	$multisite = isset( $_POST['multisite'] ) && boolval( $_POST['multisite'] );

	wpconsent()->license->deactivate_key( true, $force, $multisite );
}

/**
 * Handle initial export request via Ajax.
 *
 * @throws Exception If an error occurs during the export process.
 * @since 1.0.0
 */
function wpconsent_handle_export_start() {
	try {
		check_ajax_referer( 'wpconsent_export_start', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

		// Get count from database.
		global $wpdb;
		// table name is safe as it is pulled from wpdb and not user input, and is escaped.
		$table_name = esc_sql( $wpdb->prefix . 'wpconsent_consent_logs' );

		$total_records = $wpdb->get_var(  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$table_name}
				WHERE created_at >= %s
				AND created_at <= %s",
				$date_from . ' 00:00:00',
				$date_to . ' 23:59:59'
			)
		);

		if ( null === $total_records ) {
			throw new Exception( 'Failed to get records count: ' . $wpdb->last_error );
		}

		$request_id = wp_generate_password( 32, false );
		$handler    = new WPConsent_Export_Handler();

		// Initialize progress using options instead of transients.
		$handler->update_progress(
			$request_id,
			WPConsent_Export_Handler::STATUS_PENDING,
			array(
				'date_from'     => $date_from,
				'date_to'       => $date_to,
				'total_records' => (int) $total_records,
				'processed'     => 0,
			)
		);

		wp_send_json_success(
			array(
				'request_id'    => $request_id,
				'total_records' => (int) $total_records,
				'batch_size'    => WPConsent_Export_Handler::BATCH_SIZE,
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}

/**
 * Handle batch processing of export data.
 *
 * @since 1.0.0
 */
function wpconsent_handle_export_batch() {
	try {
		check_ajax_referer( 'wpconsent_export_start', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$request_id = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
		$batch      = isset( $_POST['batch'] ) ? intval( $_POST['batch'] ) : 0;
		$last_id    = isset( $_POST['last_id'] ) ? intval( $_POST['last_id'] ) : 0;

		$handler  = new WPConsent_Export_Handler();
		$progress = $handler->get_progress( $request_id );

		if ( ! $progress ) {
			wp_send_json_error(
				array(
					'message' => 'Export session expired',
					'code'    => 'session_expired',
				)
			);
		}

		if ( WPConsent_Export_Handler::STATUS_FAILED === $progress['status'] ) {
			wp_send_json_error(
				array(
					'message' => $progress['error'] ?? 'Export failed',
					'code'    => 'export_failed',
				)
			);
		}

		$result = $handler->process_batch( $request_id, $batch, $last_id, $progress );

		$processed = ( $batch - 1 ) * WPConsent_Export_Handler::BATCH_SIZE + $result['processed'];
		$is_last   = $processed >= $progress['total_records'];

		if ( $is_last ) {
			try {
				$handler->merge_batch_files( $request_id, $batch );
			} catch ( Exception $e ) {
				wp_send_json_error(
					array(
						'message' => 'Failed to merge export files: ' . $e->getMessage(),
						'code'    => 'merge_failed',
					)
				);
			}
		}

		wp_send_json_success(
			array(
				'batch'           => $batch,
				'total_processed' => $processed,
				'total_records'   => $progress['total_records'],
				'last_id'         => $result['last_id'],
				'is_last'         => $is_last,
				'status'          => $is_last ? WPConsent_Export_Handler::STATUS_READY : WPConsent_Export_Handler::STATUS_PROCESSING,
			)
		);

	} catch ( Exception $e ) {
		error_log( 'Export batch failed: ' . $e->getMessage() . ' Batch: ' . ( isset( $batch ) ? $batch : 0 ) . ' Last ID: ' . ( isset( $last_id ) ? $last_id : 0 ) );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		wp_send_json_error(
			array(
				'message' => $e->getMessage(),
				'code'    => 'batch_failed',
			)
		);
	}
}

/**
 * Handle the final download of the exported file.
 *
 * @since 1.0.0
 */
function wpconsent_handle_export_download() {
	try {
		check_ajax_referer( 'wpconsent_export_start', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$request_id = isset( $_GET['request_id'] ) ? sanitize_text_field( wp_unslash( $_GET['request_id'] ) ) : '';
		$handler    = new WPConsent_Export_Handler();
		$progress   = $handler->get_progress( $request_id );

		if ( ! $progress || WPConsent_Export_Handler::STATUS_READY !== $progress['status'] ) {
			wp_die( 'Export not ready or expired' );
		}

		$file_path = $progress['file_path'];
		if ( ! file_exists( $file_path ) ) {
			wp_die( 'Export file not found' );
		}

		// Disable output buffering.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// Set headers.
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="consent-logs-' . gmdate( 'Y-m-d_H-i-s' ) . '.csv"' );
		header( 'Content-Length: ' . filesize( $file_path ) );

		// Stream file in chunks.
		$handle = fopen( $file_path, 'rb' );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		while ( ! feof( $handle ) ) {
			// We output raw data from the pre-generated CSV file, no need to escape as it would corrupt.
			echo fread( $handle, WPConsent_Export_Handler::CHUNK_SIZE );  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fread
			flush();
		}
		fclose( $handle );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// Clean up.
		$handler->cleanup_export( $request_id );
		exit;

	} catch ( Exception $e ) {
		wp_die( 'Export download failed: ' . esc_html( $e->getMessage() ) );
	}
}

/**
 * Switch the admin language via AJAX.
 *
 * @return void
 */
function wpconsent_ajax_switch_language() {
	check_ajax_referer( 'wpconsent_admin', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'You do not have permission to perform this action.', 'wpconsent-premium' ),
			)
		);
	}

	$language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '';

	if ( empty( $language ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'No language specified.', 'wpconsent-premium' ),
			)
		);
	}

	// Get currently enabled languages.
	$enabled_languages = (array) wpconsent()->settings->get_option( 'enabled_languages', array() );
	// Use the get_plugin_locale method from WPConsent_Multilanguage to get the correct locale.
	$default_language    = wpconsent()->multilanguage->get_plugin_locale();
	$enabled_languages[] = $default_language;

	// Check if the language is enabled.
	if ( ! in_array( $language, $enabled_languages, true ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'This language is not enabled.', 'wpconsent-premium' ),
			)
		);
	}

	// Save the selected language to user meta.
	update_user_meta( get_current_user_id(), 'wpconsent_admin_language', $language );

	wp_send_json_success(
		array(
			'message' => esc_html__( 'Language switched successfully.', 'wpconsent-premium' ),
		)
	);
}

/**
 * Get services from the library via AJAX.
 *
 * @return void
 */
function wpconsent_ajax_get_services_library() {
	check_ajax_referer( 'wpconsent_admin', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'You do not have permission to perform this action.', 'wpconsent-premium' ),
			)
		);
	}

	// Get services from the library.
	$services = wpconsent()->services_library->get_data();

	if ( empty( $services ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'No services found in the library.', 'wpconsent-premium' ),
			)
		);
	}

	// Check which services are already added to the website.
	$added_services = array();

	// Get all services that have the _wpconsent_source_slug meta.
	$terms = get_terms(
		array(
			'taxonomy'   => 'wpconsent_category',
			'hide_empty' => false,
			'meta_query' => array(
				array(
					'key'     => '_wpconsent_source_slug',
					'compare' => 'EXISTS',
				),
			),
		)
	);

	// Create a map of source slugs to term IDs.
	foreach ( $terms as $term ) {
		$source_slug = get_term_meta( $term->term_id, '_wpconsent_source_slug', true );
		if ( ! empty( $source_slug ) ) {
			$added_services[ $source_slug ] = $term->term_id;
		}
	}

	// Add the 'added' flag to services that are already added.
	foreach ( $services as $key => $service ) {
		$services[ $key ]['added'] = isset( $added_services[ $key ] );
		if ( isset( $added_services[ $key ] ) ) {
			$services[ $key ]['term_id'] = $added_services[ $key ];
		}
	}

	// Get categories to include in the response.
	$categories = wpconsent()->cookies->get_categories();

	// Send both services and categories in the response.
	wp_send_json_success(
		array(
			'services'   => $services,
			'categories' => $categories
		)
	);
}

/**
 * Import a service from the library via AJAX.
 */
function wpconsent_ajax_import_service_from_library() {
	check_ajax_referer( 'wpconsent_admin', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'You do not have permission to perform this action.', 'wpconsent-premium' ),
			)
		);
	}

	$service_key = isset( $_POST['service_key'] ) ? sanitize_text_field( wp_unslash( $_POST['service_key'] ) ) : '';
	$category_id = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;

	if ( empty( $service_key ) || empty( $category_id ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Missing service or category.', 'wpconsent-premium' ) ) );
	}

	// Fetch the full service data using WPConsent_Services
	$services_api  = WPConsent_Services::get_instance();
	$full_services = $services_api->get_services( array( $service_key ) );
	if ( empty( $full_services[ $service_key ] ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Service not found in API.', 'wpconsent-premium' ) ) );
	}
	$service_data = $full_services[ $service_key ];

	// Add the service to the database
	$service_id = wpconsent()->cookies->add_service(
		$service_data['label'],
		$category_id,
		$service_data['description'],
		isset( $service_data['service_url'] ) ? $service_data['service_url'] : ''
	);

	if ( ! $service_id ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Failed to import service.', 'wpconsent-premium' ) ) );
	}

	// Mark as auto added.
	update_term_meta( $service_id, '_wpconsent_auto_added', true );
	update_term_meta( $service_id, '_wpconsent_source_slug', $service_key );

	// Add the cookies for this service.
	$imported_cookies = array();
	if ( ! empty( $service_data['cookies'] ) ) {
		foreach ( $service_data['cookies'] as $cookie => $cookie_data ) {
			$cookie_id = wpconsent()->cookies->add_cookie(
				$cookie,
				$cookie,
				isset( $cookie_data['description'] ) ? $cookie_data['description'] : '',
				$service_id,
				isset( $cookie_data['duration'] ) ? $cookie_data['duration'] : ''
			);
			if ( $cookie_id ) {
				update_post_meta( $cookie_id, '_wpconsent_auto_added', true );
				update_post_meta( $cookie_id, '_wpconsent_source_slug', $cookie );

				// Add cookie to the list of imported cookies
				$imported_cookies[] = array(
					'id'          => $cookie_id,
					'name'        => $cookie,
					'cookie_id'   => $cookie,
					'description' => isset( $cookie_data['description'] ) ? $cookie_data['description'] : '',
					'duration'    => isset( $cookie_data['duration'] ) ? $cookie_data['duration'] : ''
				);
			}
		}
	}

	wp_send_json_success( array(
		'id'          => $service_id,
		'name'        => $service_data['label'],
		'description' => $service_data['description'],
		'service_url' => isset( $service_data['service_url'] ) ? $service_data['service_url'] : '',
		'cookies'     => $imported_cookies
	) );
}

/**
 * Install an addon via AJAX.
 *
 * @since 1.0.0
 */
function wpconsent_install_addon() {
	check_ajax_referer( 'wpconsent_admin' );

	if ( ! wpconsent()->addons->can_install() ) {
		wp_send_json_error(
			array(
				'message' => __( 'You do not have permission to install addons.', 'wpconsent-premium' ),
			)
		);
	}

	$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';

	if ( empty( $slug ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'No addon slug provided.', 'wpconsent-premium' ),
			)
		);
	}

	$multisite = isset( $_POST['multisite'] ) && boolval( $_POST['multisite'] );

	$addon = wpconsent()->addons->install_addon( $slug, $multisite );

	if ( false === $addon ) {
		wp_send_json_error(
			array(
				'message' => __( 'Could not install addon.', 'wpconsent-premium' ),
			)
		);
	}

	wp_send_json_success(
		array(
			'message' => __( 'Addon installed successfully.', 'wpconsent-premium' ),
			'slug'    => $slug,
		)
	);
}

/**
 * Add a new script via AJAX.
 *
 * @return void
 */
function wpconsent_ajax_add_script() {
	check_admin_referer( 'wpconsent_manage_script', 'wpconsent_manage_script_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'You do not have permission to add scripts.', 'wpconsent-premium' ),
			)
		);
	}

	$script_category = isset( $_POST['script_category'] ) ? sanitize_text_field( wp_unslash( $_POST['script_category'] ) ) : '';
	$script_service  = isset( $_POST['script_service'] ) ? sanitize_text_field( wp_unslash( $_POST['script_service'] ) ) : '';
	$script_type     = isset( $_POST['script_type'] ) ? sanitize_text_field( wp_unslash( $_POST['script_type'] ) ) : '';

	// Handle the different field names based on script type.
	if ( 'script' === $script_type ) {
		$script_tag              = isset( $_POST['script_tag'] ) ? sanitize_textarea_field( wp_unslash( $_POST['script_tag'] ) ) : '';
		$script_blocked_elements = isset( $_POST['script_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['script_keywords'] ) ) : '';
	} elseif ( 'iframe' === $script_type ) {
		$script_tag              = isset( $_POST['iframe_tag'] ) ? sanitize_textarea_field( wp_unslash( $_POST['iframe_tag'] ) ) : '';
		$script_blocked_elements = isset( $_POST['iframe_blocked_elements'] ) ? sanitize_text_field( wp_unslash( $_POST['iframe_blocked_elements'] ) ) : '';
	}

	if ( empty( $script_category ) || empty( $script_service ) || empty( $script_type ) || empty( $script_tag ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Script category, service, type, and tag are required.', 'wpconsent-premium' ),
			)
		);
	}

	$result = wpconsent()->cookies->add_script( $script_category, $script_service, $script_type, $script_tag, $script_blocked_elements );

	if ( $result ) {
		wp_send_json_success(
			array(
				'message'          => esc_html__( 'Script added successfully.', 'wpconsent-premium' ),
				'id'               => $result['id'],
				'category'         => $result['category'],
				'service'          => $result['service'],
				'type'             => $result['type'],
				'tag'              => $result['tag'],
				'blocked_elements' => $result['blocked_elements'],
			)
		);
	} else {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Failed to add script.', 'wpconsent-premium' ),
			)
		);
	}
}

/**
 * Edit an existing script via AJAX.
 *
 * @return void
 */
function wpconsent_ajax_edit_script() {
	check_admin_referer( 'wpconsent_manage_script', 'wpconsent_manage_script_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'You do not have permission to edit scripts.', 'wpconsent-premium' ),
			)
		);
	}

	$script_id       = isset( $_POST['script_id'] ) ? sanitize_text_field( wp_unslash( $_POST['script_id'] ) ) : '';
	$script_category = isset( $_POST['script_category'] ) ? sanitize_text_field( wp_unslash( $_POST['script_category'] ) ) : '';
	$script_service  = isset( $_POST['script_service'] ) ? sanitize_text_field( wp_unslash( $_POST['script_service'] ) ) : '';
	$script_type     = isset( $_POST['script_type'] ) ? sanitize_text_field( wp_unslash( $_POST['script_type'] ) ) : '';

	// Handle the different field names based on script type.
	if ( 'script' === $script_type ) {
		$script_tag              = isset( $_POST['script_tag'] ) ? wp_kses_post( wp_unslash( $_POST['script_tag'] ) ) : '';
		$script_blocked_elements = isset( $_POST['script_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['script_keywords'] ) ) : '';
	} elseif ( 'iframe' === $script_type ) {
		$script_tag              = isset( $_POST['iframe_tag'] ) ? wp_kses_post( wp_unslash( $_POST['iframe_tag'] ) ) : '';
		$script_blocked_elements = isset( $_POST['iframe_blocked_elements'] ) ? sanitize_text_field( wp_unslash( $_POST['iframe_blocked_elements'] ) ) : '';
	}

	if ( empty( $script_id ) || empty( $script_category ) || empty( $script_service ) || empty( $script_type ) || empty( $script_tag ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Script ID, category, service, type, and tag are required.', 'wpconsent-premium' ),
			)
		);
	}

	$modified = wpconsent()->cookies->modify_script( $script_id, $script_category, $script_service, $script_type, $script_tag, $script_blocked_elements );

	if ( $modified ) {
		wp_send_json_success(
			array(
				'message'          => esc_html__( 'Script updated successfully.', 'wpconsent-premium' ),
				'id'               => $script_id,
				'category'         => $script_category,
				'service'          => $script_service,
				'type'             => $script_type,
				'tag'              => $script_tag,
				'blocked_elements' => $script_blocked_elements,
			)
		);
	} else {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Failed to update script.', 'wpconsent-premium' ),
			)
		);
	}
}

/**
 * Delete a script via AJAX.
 *
 * @return void
 */
function wpconsent_ajax_delete_script() {
	check_admin_referer( 'wpconsent_manage_script', 'wpconsent_manage_script_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'You do not have permission to delete scripts.', 'wpconsent-premium' ),
			)
		);
	}

	$script_id = isset( $_POST['script_id'] ) ? sanitize_text_field( wp_unslash( $_POST['script_id'] ) ) : '';

	if ( empty( $script_id ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Script ID is required.', 'wpconsent-premium' ),
			)
		);
	}

	$deleted = wpconsent()->cookies->delete_script( $script_id );

	if ( $deleted ) {
		wp_send_json_success(
			array(
				'message'   => esc_html__( 'Script deleted successfully.', 'wpconsent-premium' ),
				'script_id' => $script_id,
			)
		);
	} else {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Failed to delete script.', 'wpconsent-premium' ),
			)
		);
	}
}

/**
 * AJAX handler for saving location groups.
 *
 * @return void
 * @since 1.0.0
 */
function wpconsent_save_location_group() {
	check_ajax_referer( 'wpconsent_geolocation_groups', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wpconsent-premium' ) ) );
	}

	// Sanitize the group data.
	$group_data = wpconsent_sanitize_group_data( $_POST );

	if ( empty( $group_data['name'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Group name is required.', 'wpconsent-premium' ) ) );
	}

	if ( empty( $group_data['locations'] ) ) {
		wp_send_json_error( array( 'message' => __( 'At least one location must be selected.', 'wpconsent-premium' ) ) );
	}

	$location_groups = (array) wpconsent()->settings->get_option( 'geolocation_groups', array() );
	$group_id        = ! empty( $group_data['group_id'] ) ? $group_data['group_id'] : uniqid( 'group_' );

	// Check for location conflicts.
	$conflicts = wpconsent_check_location_conflicts( $group_data['locations'], $group_id, $location_groups );
	if ( ! empty( $conflicts ) ) {
		wp_send_json_error( array( 'message' => __( 'Some selected locations are already used in other groups.', 'wpconsent-premium' ) ) );
	}

	$location_groups[ $group_id ] = $group_data;

	wpconsent()->settings->update_option( 'geolocation_groups', $location_groups );

	// Check if we need to add the geolocation cookie.
	wpconsent()->geolocation->maybe_add_geolocation_cookie();

	wp_send_json_success( array(
		'message' => __( 'Location group saved successfully.', 'wpconsent-premium' ),
		'group'   => $location_groups[ $group_id ],
		'groupId' => $group_id,
	) );
}

/**
 * AJAX handler for deleting location groups.
 *
 * @return void
 * @since 1.0.0
 */
function wpconsent_delete_location_group() {
	check_ajax_referer( 'wpconsent_geolocation_groups', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wpconsent-premium' ) ) );
	}

	$group_id = sanitize_text_field( $_POST['group_id'] ?? '' );

	if ( empty( $group_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Group ID is required.', 'wpconsent-premium' ) ) );
	}

	$location_groups = wpconsent()->settings->get_option( 'geolocation_groups', array() );

	if ( ! isset( $location_groups[ $group_id ] ) ) {
		wp_send_json_error( array( 'message' => __( 'Location group not found.', 'wpconsent-premium' ) ) );
	}

	unset( $location_groups[ $group_id ] );
	wpconsent()->settings->update_option( 'geolocation_groups', $location_groups );

	wp_send_json_success( array( 'message' => __( 'Location group deleted successfully.', 'wpconsent-premium' ) ) );
}

/**
 * AJAX handler for getting available locations.
 *
 * @return void
 * @since 1.0.0
 */
function wpconsent_get_available_locations() {
	check_ajax_referer( 'wpconsent_geolocation_groups', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wpconsent-premium' ) ) );
	}

	$group_id        = ! empty( $_POST['group_id'] ) ? sanitize_text_field( wp_unslash( $_POST['group_id'] ) ) : '';
	$used_locations  = wpconsent_get_used_locations( $group_id );
	$location_groups = wpconsent()->settings->get_option( 'geolocation_groups', array() );

	// Get the group settings for the group being edited.
	$group_settings = array();
	if ( ! empty( $group_id ) ) {
		if ( isset( $location_groups[ $group_id ] ) ) {
			$group_settings = $location_groups[ $group_id ];
		}
	}

	wp_send_json_success(
		array(
			'used_locations' => $used_locations,
			'group_settings' => $group_settings,
		)
	);
}

/**
 * AJAX handler for creating predefined rule location groups.
 *
 * @return void
 * @since 1.0.0
 */
function wpconsent_create_predefined_rule() {
	check_ajax_referer( 'wpconsent_geolocation_groups', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wpconsent-premium' ) ) );
	}

	$rule_type = ! empty( $_POST['rule_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_type'] ) ) : '';

	if ( empty( $rule_type ) ) {
		wp_send_json_error( array( 'message' => __( 'Rule type is required.', 'wpconsent-premium' ) ) );
	}

	$rule_config = wpconsent()->geolocation->get_predefined_rule_config( $rule_type );

	if ( ! $rule_config ) {
		wp_send_json_error( array( 'message' => __( 'Invalid rule type.', 'wpconsent-premium' ) ) );
	}

	// Get existing location groups.
	$location_groups = wpconsent()->settings->get_option( 'geolocation_groups', array() );

	// Create a unique ID for the new group.
	$group_id = uniqid( 'group_' );

	// Get location name based on type and code.
	$location_name = wpconsent_get_location_name( $rule_config['locations']['type'], $rule_config['locations']['code'] );

	// Check for location conflicts.
	$locations = array(
		array(
			'type' => $rule_config['locations']['type'],
			'code' => $rule_config['locations']['code'],
			'name' => $location_name,
		),
	);

	$conflicts = wpconsent_check_location_conflicts( $locations, $group_id, $location_groups );
	if ( ! empty( $conflicts ) ) {
		wp_send_json_error( array( 'message' => __( 'This location is already used in another group.', 'wpconsent-premium' ) ) );
	}

	$rule_config['locations'] = $locations;

	// Create the new location group.
	$location_groups[ $group_id ] = $rule_config;

	// Save the updated location groups.
	wpconsent()->settings->update_option( 'geolocation_groups', $location_groups );

	// Check if we need to add the geolocation cookie.
	wpconsent()->geolocation->maybe_add_geolocation_cookie();

	wp_send_json_success(
		array(
			'message'  => __( 'Predefined rule created successfully.', 'wpconsent-premium' ),
			'group'    => $location_groups[ $group_id ],
			'group_id' => $group_id,
		)
	);
}

/**
 * Get used locations across all groups.
 *
 * @param string $exclude_group_id Group ID to exclude from the check.
 *
 * @return array
 */
function wpconsent_get_used_locations( $exclude_group_id = '' ) {
	$location_groups = wpconsent()->settings->get_option( 'geolocation_groups', array() );
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
 * Check for location conflicts.
 *
 * @param array  $locations Array of location data.
 * @param string $group_id Current group ID (for edit mode).
 *
 * @return array Array of conflicting locations.
 */
function wpconsent_check_location_conflicts( $locations, $group_id ) {
	$conflicts      = array();
	$used_locations = wpconsent_get_used_locations( $group_id );

	foreach ( $locations as $location ) {
		$key = $location['type'] . ':' . $location['code'];
		if ( isset( $used_locations[ $key ] ) ) {
			$conflicts[] = $location;
		}
	}

	return $conflicts;
}

/**
 * Get US states array.
 *
 * @return array
 */
function wpconsent_get_us_states() {
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
 * Get location name by type and code.
 *
 * @param string $type Location type.
 * @param string $code Location code.
 *
 * @return string
 */
function wpconsent_get_location_name( $type, $code ) {
	switch ( $type ) {
		case 'continent':
			$continents = wpconsent()->geolocation->get_continents();

			return $continents[ $code ]['name'] ?? $code;

		case 'country':
			$countries = wpconsent()->geolocation->get_countries();

			return $countries[ $code ] ?? $code;

		case 'us_state':
			$states = wpconsent_get_us_states();

			return $states[ $code ] ?? $code;

		default:
			return $code;
	}
}


/**
 * Sanitize group data for saving.
 *
 * @param array $data Raw group data from the request.
 *
 * @return array Sanitized group data.
 */
function wpconsent_sanitize_group_data( $data ) {

	$consent_mode = ! empty( $data['consent_mode'] ) ? sanitize_text_field( wp_unslash( $data['consent_mode'] ) ) : 'optin';
	// Let's make sure consent mode is one of the allowed values.
	$allowed_modes = array( 'optin', 'optout' );
	if ( ! in_array( $consent_mode, $allowed_modes, true ) ) {
		$consent_mode = 'optin'; // Default to opt-in if invalid.
	}

	$sanitized = array(
		'group_id'                 => ! empty( $data['group_id'] ) ? sanitize_text_field( wp_unslash( $data['group_id'] ) ) : '',
		'name'                     => ! empty( $data['group_name'] ) ? sanitize_text_field( wp_unslash( $data['group_name'] ) ) : '',
		'locations'                => array(),
		'enable_script_blocking'   => ! empty( $data['enable_script_blocking'] ),
		'show_banner'              => ! empty( $data['show_banner'] ),
		'enable_consent_floating'  => ! empty( $data['enable_consent_floating'] ),
		'manual_toggle_services'   => ! empty( $data['manual_toggle_services'] ),
		'consent_mode'             => $consent_mode,
		'customize_banner_buttons' => ! empty( $data['customize_banner_buttons'] ),
		'customize_banner_message' => ! empty( $data['customize_banner_message'] ),
	);

	// Handle button customization fields based on customize_banner_buttons toggle.
	if ( $sanitized['customize_banner_buttons'] ) {
		// Save button customization fields if they exist in the form data.
		$sanitized['accept_button_text']      = ! empty( $data['accept_button_text'] ) ? sanitize_text_field( wp_unslash( $data['accept_button_text'] ) ) : '';
		$sanitized['cancel_button_text']      = ! empty( $data['cancel_button_text'] ) ? sanitize_text_field( wp_unslash( $data['cancel_button_text'] ) ) : '';
		$sanitized['preferences_button_text'] = ! empty( $data['preferences_button_text'] ) ? sanitize_text_field( wp_unslash( $data['preferences_button_text'] ) ) : '';

		$sanitized['accept_button_enabled']      = ! empty( $data['accept_button_enabled'] );
		$sanitized['cancel_button_enabled']      = ! empty( $data['cancel_button_enabled'] );
		$sanitized['preferences_button_enabled'] = ! empty( $data['preferences_button_enabled'] );

		// Sanitize button order.
		$sanitized['button_order'] = array();
		if ( ! empty( $data['button_order'] ) && is_array( $data['button_order'] ) ) {
			foreach ( $data['button_order'] as $button_id ) {
				$button_id = sanitize_text_field( $button_id );
				if ( in_array( $button_id, array( 'accept', 'cancel', 'preferences' ), true ) ) {
					$sanitized['button_order'][] = $button_id;
				}
			}
		}
		// Default order if none provided.
		if ( empty( $sanitized['button_order'] ) ) {
			$sanitized['button_order'] = array( 'accept', 'cancel', 'preferences' );
		}
	} else {
		// Reset all button-related options to defaults when customize_banner_buttons is disabled.
		$sanitized['accept_button_text']         = '';
		$sanitized['cancel_button_text']         = '';
		$sanitized['preferences_button_text']    = '';
		$sanitized['accept_button_enabled']      = true;
		$sanitized['cancel_button_enabled']      = true;
		$sanitized['preferences_button_enabled'] = true;
		$sanitized['button_order']               = array( 'accept', 'cancel', 'preferences' );
	}

	// Handle banner message customization fields based on customize_banner_message toggle.
	if ( $sanitized['customize_banner_message'] ) {
		// Save banner message customization field if it exists in the form data.
		$sanitized['banner_message'] = ! empty( $data['banner_message'] ) ? sanitize_textarea_field( wp_unslash( $data['banner_message'] ) ) : '';
	} else {
		// Reset banner message to default when customize_banner_message is disabled.
		$sanitized['banner_message'] = '';
	}

	// Sanitize locations data from mixed inputs.
	$location_types = array(
		'continents' => 'continent',
		'countries'  => 'country',
		'us_states'  => 'us_state',
	);

	foreach ( $location_types as $input_name => $location_type ) {
		if ( ! empty( $data[ $input_name ] ) && is_array( $data[ $input_name ] ) ) {
			foreach ( $data[ $input_name ] as $location_code ) {
				$location_code = sanitize_text_field( $location_code );
				if ( ! empty( $location_code ) ) {
					$location_data = array(
						'code' => $location_code,
						'type' => $location_type,
					);

					// Only include name for non-country locations to save space.
					if ( 'country' !== $location_type ) {
						$location_data['name'] = wpconsent_get_location_name( $location_type, $location_code );
					}

					$sanitized['locations'][] = $location_data;
				}
			}
		}
	}

	return $sanitized;
}

/**
 * Start translation process via AJAX.
 *
 * @return void
 */
function wpconsent_ajax_start_translation() {
	check_ajax_referer( 'wpconsent_admin', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'You do not have permission to perform this action.', 'wpconsent-premium' ),
			)
		);
	}

	$target_locale = isset( $_POST['target_locale'] ) ? sanitize_text_field( wp_unslash( $_POST['target_locale'] ) ) : '';
	$language_name = isset( $_POST['language_name'] ) ? sanitize_text_field( wp_unslash( $_POST['language_name'] ) ) : '';

	if ( empty( $target_locale ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Target language is required.', 'wpconsent-premium' ),
			)
		);
	}

	if ( empty( $language_name ) ) {
		$language_name = $target_locale; // Fallback to locale.
	}

	// Check if translation service is available and language is supported.
	if ( ! isset( wpconsent()->translation_services ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Translation service is not available.', 'wpconsent-premium' ),
			)
		);
	}

	if ( ! wpconsent()->translation_services->is_language_supported( $target_locale ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'This language is not supported for translation.', 'wpconsent-premium' ),
			)
		);
	}

	// Check if any translation is already active.
	if ( wpconsent()->translation_services->is_translation_active() ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Another translation is already in progress. Please wait for it to complete.', 'wpconsent-premium' ),
			)
		);
	}

	// Generate a language-specific translation job ID for proper duplicate prevention.
	$job_id = 'wpconsent_translation_' . $target_locale;

	// Schedule the background translation process using Action Scheduler.
	$scheduled = wpconsent()->translation_services->schedule_translation( $job_id, $target_locale, $language_name );

	if ( ! $scheduled ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Failed to schedule translation job.', 'wpconsent-premium' ),
			)
		);
	}

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s is the target language locale */
				esc_html__( 'Translation process started for %s. You will be notified when complete.', 'wpconsent-premium' ),
				$target_locale
			),
		)
	);
}

/**
 * Check translation progress via AJAX.
 *
 * @return void
 */
function wpconsent_ajax_check_translation_progress() {
	check_ajax_referer( 'wpconsent_admin', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'You do not have permission to perform this action.', 'wpconsent-premium' ),
			)
		);
	}

	if ( ! isset( wpconsent()->translation_services ) ) {
		wp_send_json_success(
			array(
				'translation_active' => false,
				'progress'           => false,
			)
		);
		return;
	}

	$translation_active = wpconsent()->translation_services->is_translation_active();
	$progress           = wpconsent()->translation_services->get_translation_progress();

	wp_send_json_success(
		array(
			'translation_active' => $translation_active,
			'progress'           => $progress,
		)
	);
}

/**
 * Reset stuck translation via AJAX.
 *
 * @return void
 */
function wpconsent_ajax_reset_translation() {
	check_ajax_referer( 'wpconsent_admin', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'You do not have permission to perform this action.', 'wpconsent-premium' ),
			)
		);
	}

	if ( ! isset( wpconsent()->translation_services ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Translation service is not available.', 'wpconsent-premium' ),
			)
		);
	}

	// Reset the translation status.
	$reset = wpconsent()->translation_services->reset_translation_status();

	if ( $reset ) {
		wp_send_json_success(
			array(
				'message' => esc_html__( 'Translation has been reset. You can now start a new translation.', 'wpconsent-premium' ),
			)
		);
	} else {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Failed to reset translation.', 'wpconsent-premium' ),
			)
		);
	}
}

/**
 * Handle initial RoC delete request via Ajax.
 *
 * @throws Exception If an error occurs during the delete process.
 * @since 1.0.0
 *
 * @return void
 */
function wpconsent_handle_delete_roc_start() {
	try {
		check_ajax_referer( 'wpconsent_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$period = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : '';

		$handler        = new WPConsent_Delete_Handler();
		$date_threshold = $handler->get_date_threshold( $period );
		$total_records  = $handler->count_records_to_delete( $date_threshold );

		if ( 0 === $total_records ) {
			wp_send_json_error(
				array(
					'message' => __( 'No records found for the selected period.', 'wpconsent-premium' ),
					'code'    => 'no_records',
				)
			);
		}

		$request_id = 'roc_' . time() . '_' . wp_rand();

		$handler->update_progress(
			$request_id,
			WPConsent_Delete_Handler::STATUS_PENDING,
			array(
				'total_records' => (int) $total_records,
				'deleted_count' => 0,
				'period'        => $period,
			)
		);

		wp_send_json_success(
			array(
				'request_id'     => $request_id,
				'total_records'  => (int) $total_records,
				'date_threshold' => $date_threshold,
			)
		);

	} catch ( Exception $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}

/**
 * Handle batch processing of RoC deletions.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wpconsent_handle_delete_roc_batch() {
	try {
		check_ajax_referer( 'wpconsent_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}

		$request_id     = isset( $_POST['request_id'] ) ? sanitize_text_field( wp_unslash( $_POST['request_id'] ) ) : '';
		$batch_number   = isset( $_POST['batch_number'] ) ? intval( $_POST['batch_number'] ) : 0;
		$date_threshold = isset( $_POST['date_threshold'] ) ? sanitize_text_field( wp_unslash( $_POST['date_threshold'] ) ) : '';

		$handler  = new WPConsent_Delete_Handler();
		$progress = $handler->get_progress( $request_id );

		if ( ! $progress ) {
			wp_send_json_error(
				array(
					'message' => 'Delete session expired',
					'code'    => 'session_expired',
				)
			);
		}

		if ( WPConsent_Delete_Handler::STATUS_FAILED === $progress['status'] ) {
			wp_send_json_error(
				array(
					'message' => $progress['error'] ?? 'Delete failed',
					'code'    => 'delete_failed',
				)
			);
		}

		$deleted       = $handler->delete_batch( $date_threshold, $batch_number );
		$total_deleted = ( $progress['deleted_count'] ?? 0 ) + $deleted;
		$is_last       = $total_deleted >= $progress['total_records'] || 0 === $deleted;

		$handler->update_progress(
			$request_id,
			$is_last ? WPConsent_Delete_Handler::STATUS_COMPLETED : WPConsent_Delete_Handler::STATUS_PROCESSING,
			array( 'deleted_count' => $total_deleted )
		);

		if ( $is_last ) {
			$handler->cleanup_progress( $request_id );
		}

		wp_send_json_success(
			array(
				'batch'         => $batch_number,
				'deleted_count' => $deleted,
				'total_deleted' => $total_deleted,
				'total_records' => $progress['total_records'],
				'is_last'       => $is_last,
				'status'        => $is_last ? WPConsent_Delete_Handler::STATUS_COMPLETED : WPConsent_Delete_Handler::STATUS_PROCESSING,
			)
		);

	} catch ( Exception $e ) {
		error_log( 'Delete batch failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		wp_send_json_error(
			array(
				'message' => $e->getMessage(),
				'code'    => 'batch_failed',
			)
		);
	}
}
