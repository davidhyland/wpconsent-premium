<?php
/**
 * Class used to load data on translation services and supported languages.
 *
 * @package WPConsent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class used to manage translation services and supported languages.
 */
class WPConsent_Translation_Services {

	/**
	 * The base URL used for translation service requests.
	 *
	 * @var string
	 */
	protected $baseurl = 'https://translate.wpconsent.com/';

	/**
	 * The supported languages.
	 *
	 * @var array
	 */
	protected $supported_languages = array();

	/**
	 * The instance of the class.
	 *
	 * @var WPConsent_Translation_Services
	 */
	private static $instance;

	/**
	 * Action Scheduler group name for translation jobs.
	 *
	 * @var string
	 */
	const AS_GROUP = 'wpconsent_translation';

	/**
	 * Default batch size for translation requests.
	 *
	 * @var int
	 */
	const DEFAULT_BATCH_SIZE = 30;

	/**
	 * Returns the instance of the class.
	 *
	 * @return WPConsent_Translation_Services
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new WPConsent_Translation_Services();
		}

		return self::$instance;
	}

	/**
	 * Check if any translation is currently active.
	 *
	 * @return bool
	 */
	public function is_translation_active() {
		$translation_data = get_option( 'wpconsent_translation_status', false );
		return ! empty( $translation_data['active'] );
	}

	/**
	 * Update translation status and progress.
	 *
	 * @param bool  $active Whether translation is active or not.
	 * @param array $progress_data Optional progress data to merge.
	 *
	 * @return void
	 */
	private function update_translation_status( $active, $progress_data = array() ) {
		if ( $active ) {
			// Merge with existing data if translation is active.
			$existing_data    = get_option( 'wpconsent_translation_status', array() );
			$translation_data = array_merge(
				$existing_data,
				array( 'active' => true ),
				$progress_data,
				array( 'updated_at' => time() )
			);
		} else {
			// Clear all data if translation is not active.
			$translation_data = array( 'active' => false );
		}

		update_option( 'wpconsent_translation_status', $translation_data );
	}

	/**
	 * Update translation progress.
	 *
	 * @param string $target_locale The target locale.
	 * @param int    $current_batch Current batch number.
	 * @param int    $total_batches Total batch count.
	 * @param int    $completed_batches Number of successfully completed batches.
	 * @param string $language_name The language display name.
	 *
	 * @return void
	 */
	private function update_translation_progress( $target_locale, $current_batch, $total_batches, $completed_batches, $language_name = '' ) {
		$progress_data = array(
			'target_locale'     => $target_locale,
			'language_name'     => $language_name,
			'current_batch'     => $current_batch,
			'total_batches'     => $total_batches,
			'completed_batches' => $completed_batches,
			'progress_percent'  => $total_batches > 0 ? round( ( $completed_batches / $total_batches ) * 100, 1 ) : 0,
			'success'           => null, // null means still in progress.
		);

		$this->update_translation_status( true, $progress_data );
	}

	/**
	 * Get translation progress data.
	 *
	 * @return array|false Progress data or false if no translation is active.
	 */
	public function get_translation_progress() {
		$translation_data = get_option( 'wpconsent_translation_status', false );

		if ( empty( $translation_data ) ) {
			return false;
		}

		// Return progress data without the 'active' and 'updated_at' flags.
		$progress_data = $translation_data;
		unset( $progress_data['active'] );
		unset( $progress_data['updated_at'] );

		return ! empty( $progress_data ) ? $progress_data : false;
	}

	/**
	 * Set translation as finished.
	 *
	 * @param bool $success Whether the translation was successful.
	 *
	 * @return void
	 */
	private function clear_translation_data( $success = false ) {
		// Get existing progress data and just update active status and success.
		$existing_data            = get_option( 'wpconsent_translation_status', array() );
		$existing_data['active']  = false;
		$existing_data['success'] = $success;

		update_option( 'wpconsent_translation_status', $existing_data );
	}

	/**
	 * Get the configurable batch size for translation requests.
	 * Allows filtering of batch size for performance tuning per environment.
	 *
	 * @return int The batch size to use for translation requests.
	 */
	private function get_batch_size() {
		/**
		 * Filter the translation batch size.
		 *
		 * @since 1.0.0
		 *
		 * @param int $batch_size The default batch size (30).
		 */
		$batch_size = apply_filters( 'wpconsent_translation_batch_size', self::DEFAULT_BATCH_SIZE );

		// Ensure batch size is within reasonable bounds (5-500).
		$batch_size = max( 5, min( 500, absint( $batch_size ) ) );

		return $batch_size;
	}

	/**
	 * Maybe add translation status notices.
	 *
	 * @return void
	 */
	public function maybe_add_translation_notices() {
		// Check if translation is active and show progress notice.
		if ( $this->is_translation_active() ) {
			$progress         = $this->get_translation_progress();
			$progress_percent = isset( $progress['progress_percent'] ) ? $progress['progress_percent'] : 0;

			$message = sprintf(
				'<p>%s <span id="wpconsent-translation-status">(%d%% complete)</span><span class="spinner is-active" style="float: none; margin: 0 8px;"></span><button type="button" class="wpconsent-button-text wpconsent-reset-translation">%s</button></p>',
				__( 'Translation in progress - this may take several minutes.', 'wpconsent-premium' ),
				$progress_percent,
				__( 'Cancel', 'wpconsent-premium' )
			);

			wpconsent()->notice->info(
				$message,
				array(
					'slug'  => 'translation_progress',
					'class' => 'wpconsent-translation-progress-notice',
					'autop' => false,
				)
			);
		}
	}

	/**
	 * Constructor - Initialize Action Scheduler hooks.
	 */
	public function __construct() {
		// Register the translation processor action.
		add_action( 'wpconsent_process_translation', array( $this, 'process_translation' ), 10, 3 );

		// Hook into Action Scheduler completion events for notifications.
		add_action( 'action_scheduler_after_execute', array( $this, 'handle_translation_completion' ), 10, 3 );

		// Hook into admin notices to show translation status.
		add_action( 'wpconsent_admin_notices', array( $this, 'maybe_add_translation_notices' ), 5 );

		// Allow resetting translation flags via filter.
		add_filter( 'wpconsent_reset_translation_flags', array( $this, 'reset_translation_flags' ) );
	}

	/**
	 * Loads the supported languages.
	 *
	 * @return void
	 */
	protected function load_supported_languages() {
		// Let's load the supported languages we have in the local storage first.
		$supported_languages = wpconsent()->file_cache->get( 'translation_supported_languages', DAY_IN_SECONDS );

		// If we don't have cached data, fetch from API.
		if ( ! $supported_languages ) {
			$supported_languages = $this->fetch_supported_languages();

			if ( ! empty( $supported_languages ) ) {
				wpconsent()->file_cache->set( 'translation_supported_languages', $supported_languages );
			}
		}

		$this->supported_languages = $supported_languages;
	}

	/**
	 * Fetches the supported languages from the translation API.
	 *
	 * @return array
	 */
	protected function fetch_supported_languages() {
		$response = wp_remote_get(
			$this->baseurl . 'supported-languages',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return array();
		}

		$data = json_decode( $body, true );

		if ( empty( $data ) || ! isset( $data['languages'] ) || ! is_array( $data['languages'] ) ) {
			return array();
		}

		return $data['languages'];
	}

	/**
	 * Returns the supported languages.
	 *
	 * @return array
	 */
	public function get_supported_languages() {
		if ( empty( $this->supported_languages ) ) {
			$this->load_supported_languages();
		}

		return $this->supported_languages;
	}

	/**
	 * Checks if a language locale is supported for translation.
	 *
	 * @param string $locale The language locale to check.
	 *
	 * @return bool
	 */
	public function is_language_supported( $locale ) {
		$supported_languages = $this->get_supported_languages();

		return isset( $supported_languages[ $locale ] );
	}

	/**
	 * Schedule a translation job using Action Scheduler.
	 *
	 * @param string $job_id        The translation job ID.
	 * @param string $target_locale The target language locale.
	 * @param string $language_name The language display name.
	 *
	 * @return bool True if scheduled successfully, false otherwise.
	 */
	public function schedule_translation( $job_id, $target_locale, $language_name ) {
		// Check if any translation is already active.
		if ( $this->is_translation_active() ) {
			return false;
		}

		// Clear the translation strings cache for this locale to force a fresh rebuild.
		$this->clear_translation_cache( $target_locale );

		// Check if Action Scheduler is available.
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			// Fallback to wp_cron.
			return $this->schedule_with_wp_cron( $job_id, $target_locale, $language_name );
		}

		// Prevent duplicate scheduling.
		$is_already_scheduled = $this->is_translation_scheduled( $job_id );

		if ( $is_already_scheduled ) {
			return false;
		}

		// Mark translation as active before scheduling, and reset progress to prevent showing old data.
		$this->update_translation_status(
			true,
			array(
				'target_locale'     => $target_locale,
				'language_name'     => $language_name,
				'current_batch'     => 0,
				'total_batches'     => 0,
				'completed_batches' => 0,
				'progress_percent'  => 0,
				'success'           => null,
			)
		);

		// Schedule with Action Scheduler - pass arguments as array for individual parameters.
		$action_id = as_schedule_single_action(
			time(),
			'wpconsent_process_translation',
			array( $job_id, $target_locale, $language_name ),
			self::AS_GROUP
		);

		if ( $action_id ) {
			return true;
		}

		// If scheduling failed, clear the active status.
		$this->clear_translation_data();
		return false;
	}

	/**
	 * Check if a translation job is already scheduled.
	 *
	 * @param string $job_id The translation job ID.
	 *
	 * @return bool True if scheduled or running, false otherwise.
	 */
	private function is_translation_scheduled( $job_id ) {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		// Check for pending actions.
		$has_pending = as_has_scheduled_action(
			'wpconsent_process_translation',
			array( $job_id ),
			self::AS_GROUP
		);

		if ( $has_pending ) {
			return true;
		}

		// Also check for currently running actions (in-progress).
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$running_actions = as_get_scheduled_actions(
				array(
					'hook'     => 'wpconsent_process_translation',
					'args'     => array( $job_id ),
					'group'    => self::AS_GROUP,
					'status'   => 'in-progress',
					'per_page' => 1,
				)
			);

			if ( ! empty( $running_actions ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fallback to wp_cron if Action Scheduler is not available.
	 *
	 * @param string $job_id        The translation job ID.
	 * @param string $target_locale The target language locale.
	 * @param string $language_name The language display name.
	 *
	 * @return bool True if scheduled successfully, false otherwise.
	 */
	private function schedule_with_wp_cron( $job_id, $target_locale, $language_name ) {
		// Clear the translation strings cache for this locale to force a fresh rebuild.
		$this->clear_translation_cache( $target_locale );

		// Mark translation as active before scheduling, and reset progress to prevent showing old data.
		$this->update_translation_status(
			true,
			array(
				'target_locale'     => $target_locale,
				'language_name'     => $language_name,
				'current_batch'     => 0,
				'total_batches'     => 0,
				'completed_batches' => 0,
				'progress_percent'  => 0,
				'success'           => null,
			)
		);

		$result = wp_schedule_single_event(
			time(),
			'wpconsent_process_translation',
			array( $job_id, $target_locale, $language_name )
		);

		if ( ! is_wp_error( $result ) && false !== $result ) {
			return true;
		}

		// If scheduling failed, clear the active status.
		$this->clear_translation_data();
		return false;
	}

	/**
	 * Process translation in the background.
	 *
	 * @param string $job_id        The translation job ID.
	 * @param string $target_locale The target language locale.
	 * @param string $language_name The language display name.
	 *
	 * @return bool
	 */
	public function process_translation( $job_id, $target_locale, $language_name ) {
		// Validate required parameters.
		if ( empty( $job_id ) || empty( $target_locale ) || empty( $language_name ) ) {
			$this->clear_translation_data();
			return false;
		}

		try {
			// Build translation payload batches from database.
			$batches = $this->build_translation_batches( $target_locale );

			if ( empty( $batches ) ) {
				$this->clear_translation_data( false );
				$this->notify_user_directly( 'failed', $target_locale, 'No translation data available. All content may already be translated, or there may be no content to translate.', $language_name );
				return false;
			}

			$total_batches = count( $batches );

			// Initialize progress tracking.
			$this->update_translation_progress( $target_locale, 0, $total_batches, 0, $language_name );

			// Process batches with save-as-you-go approach.
			$total_saved    = 0;
			$failed_batches = 0;

			foreach ( $batches as $batch_index => $batch ) {
				$batch_number = $batch_index + 1;

				// Update progress before processing.
				$this->update_translation_progress( $target_locale, $batch_number, $total_batches, $total_saved, $language_name );

				try {
					// Send batch to translation worker.
					$translated_content = $this->send_to_translation_worker( array( $batch ) );

					if ( ! empty( $translated_content ) ) {
						// Save batch immediately.
						$saved = $this->save_translated_content( $translated_content, $target_locale );

						if ( $saved ) {
							++$total_saved;
						} else {
							++$failed_batches;
						}
					} else {
						++$failed_batches;
					}

					// Update progress after processing.
					$this->update_translation_progress( $target_locale, $batch_number, $total_batches, $total_saved, $language_name );

					// Small delay between batches to prevent server overload.
					if ( $batch_number < $total_batches ) {
						usleep( 500000 ); // 0.5 second delay
					}
				} catch ( Exception $batch_exception ) {
					++$failed_batches;
				}
			}

			// Calculate final results.
			$success_rate = ( $total_batches > 0 ) ? ( $total_saved / $total_batches ) * 100 : 0;

			// Determine overall success based on completion rate.
			if ( $total_saved === $total_batches ) {
				$this->clear_translation_data( true );
				$this->notify_user_directly( 'completed', $target_locale, '', $language_name );
				return true;
			} else {
				$this->clear_translation_data( false );
				$failure_message = ( $total_saved > 0 )
					? sprintf( 'Translation partially failed. Only %d%% of content was translated.', round( $success_rate ) )
					: 'Translation failed completely.';

				$this->notify_user_directly( 'failed', $target_locale, $failure_message, $language_name );
				return false;
			}
		} catch ( Exception $e ) {
			// Clear translation active status and progress on exception.
			$this->clear_translation_data( false );

			$this->notify_user_directly( 'failed', $target_locale, $e->getMessage(), $language_name );
			return false;
		}
	}

	/**
	 * Notify user about translation completion or failure with automatic cleanup.
	 *
	 * @param string $status         The job status.
	 * @param string $target_locale  The target locale.
	 * @param string $message        Optional message.
	 * @param string $language_name  Optional language display name.
	 *
	 * @return void
	 */
	private function notify_user_directly( $status, $target_locale, $message = '', $language_name = '' ) {
		// Ensure the admin notice class is available (should be loaded by core plugin).
		if ( ! class_exists( 'WPConsent_Notice' ) ) {
			return;
		}

		// Use provided language name or fallback to locale.
		if ( empty( $language_name ) ) {
			$language_name = $target_locale;
		}

		if ( 'completed' === $status ) {
			$notification_message = sprintf(
				/* translators: %1$s is the target language name, %2$s is the languages admin URL, %3$s is the link closing tag */
				__( '<strong>Translation Complete!</strong> Your WPConsent content has been successfully translated to <strong>%1$s</strong>. Please %2$sreview the translation%3$s for accuracy.', 'wpconsent-premium' ),
				esc_html( $language_name ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wpconsent-cookies&view=languages' ) ) . '">',
				'</a>'
			);

			WPConsent_Notice::register_translation_notice(
				$notification_message,
				'success',
				strtolower( $target_locale )
			);
		} else {
			$notification_message = sprintf(
				/* translators: %1$s is the target language name, %2$s is the languages admin URL, %3$s is the link closing tag */
				__( '<strong>Translation Failed</strong> - The translation to <strong>%1$s</strong> could not be completed. You can manually %2$smanage your languages%3$s to add content.', 'wpconsent-premium' ),
				esc_html( $language_name ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=wpconsent-cookies&view=languages' ) ) . '">',
				'</a>'
			);

			if ( ! empty( $message ) ) {
				$notification_message .= '<br><em>' . esc_html( $message ) . '</em>';
			}

			WPConsent_Notice::register_translation_notice(
				$notification_message,
				'error',
				strtolower( $target_locale )
			);
		}
	}

	/**
	 * Handle Action Scheduler completion events for additional processing.
	 *
	 * @param int    $action_id The Action Scheduler action ID.
	 * @param string $hook      The action hook name.
	 * @param array  $args      The action arguments.
	 *
	 * @return void
	 */
	public function handle_translation_completion( $action_id, $hook, $args ) {
		// Only handle our translation actions.
		if ( 'wpconsent_process_translation' !== $hook ) {
			return;
		}

		// Prevent unused parameter warnings.
		unset( $action_id, $args );

		// Additional completion handling can be added here if needed.
		// For example, updating Action Scheduler specific metrics or logs.
	}

	/**
	 * Build translation payload batches for the worker by gathering data directly from database.
	 *
	 * @param string $target_locale The target locale.
	 *
	 * @return array Array of batch payloads
	 */
	private function build_translation_batches( $target_locale ) {
		// Determine if we're translating to default language.
		$source_locale       = wpconsent()->multilanguage->get_plugin_locale();
		$is_default_language = ( $target_locale === $source_locale );

		// Try to get cached strings array first.
		// Make cache key locale-specific to prevent collisions between different language translations.
		$cache_key      = 'wpconsent_translation_strings_' . $target_locale;
		$cached_strings = false;

		// Skip cache for default language translations to always check translation flags.
		if ( ! $is_default_language ) {
			$cached_strings = get_transient( $cache_key );
		}

		if ( false !== $cached_strings ) {
			$strings = $cached_strings;
		} else {
			$strings = array();

			// Check if fields are already translated for default language.
			$is_fields_translated = wpconsent()->settings->get_option( 'wpconsent_fields_translated', false );
			if ( ! ( $is_default_language && $is_fields_translated ) ) {
				// Get banner settings directly.
				$translatable_fields = wpconsent()->multilanguage->get_translatable_options();

				// Add banner texts to strings.
				foreach ( $translatable_fields as $field ) {
					$text = wpconsent()->settings->get_option( $field );
					$text = trim( $text );
					if ( ! empty( $text ) ) {
						$strings[] = array(
							'id'   => 'banner.' . $field,
							'text' => $text,
						);
					}
				}
			}

			// Get categories and process directly.
			$categories = wpconsent()->cookies->get_categories();
			foreach ( $categories as $category_slug => $category ) {
				$category_id = $category['id'];

				// Check if category is already translated for default language.
				$is_category_translated = get_term_meta( $category_id, 'wpconsent_translated', true );

				// Add category strings only if not already translated.
				if ( ! ( $is_default_language && $is_category_translated ) ) {
					if ( ! empty( $category['name'] ) ) {
						$strings[] = array(
							'id'   => 'category.' . $category_id . '.name',
							'text' => trim( $category['name'] ),
						);
					}
					if ( ! empty( $category['description'] ) ) {
						$strings[] = array(
							'id'   => 'category.' . $category_id . '.description',
							'text' => trim( $category['description'] ),
						);
					}
				}

				// Get cookies and services for this category.
				$cookies  = wpconsent()->cookies->get_cookies_by_category( $category_id );
				$services = wpconsent()->cookies->get_services_by_category( $category_id );

				// Process services and their cookies.
				foreach ( $services as $service ) {
					// Check if service is already translated for default language.
					$is_service_translated = get_term_meta( $service['id'], 'wpconsent_translated', true );

					// Add service strings only if not already translated.
					if ( ! ( $is_default_language && $is_service_translated ) ) {
						if ( ! empty( $service['name'] ) ) {
							$strings[] = array(
								'id'   => 'service.' . $service['id'] . '.name',
								'text' => trim( $service['name'] ),
							);
						}
						if ( ! empty( $service['description'] ) ) {
							$strings[] = array(
								'id'   => 'service.' . $service['id'] . '.description',
								'text' => trim( $service['description'] ),
							);
						}
					}

					// Add cookies that belong to this service.
					foreach ( $cookies as $cookie ) {
						if ( in_array( $service['id'], $cookie['categories'], true ) ) {
							// Check if cookie is already translated for default language.
							$is_cookie_translated = get_post_meta( $cookie['id'], 'wpconsent_translated', true );

							// Add cookie strings only if not already translated.
							if ( ! ( $is_default_language && $is_cookie_translated ) ) {
								if ( ! empty( $cookie['name'] ) ) {
									$strings[] = array(
										'id'   => 'cookie.' . $cookie['id'] . '.name',
										'text' => trim( $cookie['name'] ),
									);
								}
								if ( ! empty( $cookie['description'] ) ) {
									$strings[] = array(
										'id'   => 'cookie.' . $cookie['id'] . '.description',
										'text' => trim( $cookie['description'] ),
									);
								}
								if ( ! empty( $cookie['duration'] ) ) {
									$strings[] = array(
										'id'   => 'cookie.' . $cookie['id'] . '.duration',
										'text' => trim( $cookie['duration'] ),
									);
								}
							}
						}
					}
				}

				// Add direct cookies (cookies that belong only to this category, not to any service).
				foreach ( $cookies as $cookie ) {
					if ( count( $cookie['categories'] ) === 1 && $cookie['categories'][0] === $category_id ) {
						// Check if cookie is already translated for default language.
						$is_cookie_translated = get_post_meta( $cookie['id'], 'wpconsent_translated', true );

						// Add cookie strings only if not already translated.
						if ( ! ( $is_default_language && $is_cookie_translated ) ) {
							if ( ! empty( $cookie['name'] ) ) {
								$strings[] = array(
									'id'   => 'cookie.' . $cookie['id'] . '.name',
									'text' => trim( $cookie['name'] ),
								);
							}
							if ( ! empty( $cookie['description'] ) ) {
								$strings[] = array(
									'id'   => 'cookie.' . $cookie['id'] . '.description',
									'text' => trim( $cookie['description'] ),
								);
							}
							if ( ! empty( $cookie['duration'] ) ) {
								$strings[] = array(
									'id'   => 'cookie.' . $cookie['id'] . '.duration',
									'text' => trim( $cookie['duration'] ),
								);
							}
						}
					}
				}
			}

			// Cache the strings array for 24 hours (skip caching for empty arrays or default language).
			if ( ! empty( $strings ) && ! $is_default_language ) {
				set_transient( $cache_key, $strings, DAY_IN_SECONDS );
			}
		}

		$source_locale = wpconsent()->multilanguage->get_plugin_locale();

		// Split strings into batches for processing.
		$batches       = array();
		$total_strings = count( $strings );
		$batch_size    = $this->get_batch_size();

		// Create batches from strings in their natural order (banner first, then categories, services, cookies).
		$current_batch = array();
		$batch_count   = 0;

		foreach ( $strings as $string ) {
			$current_batch[] = $string;

			if ( count( $current_batch ) >= $batch_size ) {
				$batches[] = array(
					'version'       => 1,
					'source_locale' => $source_locale,
					'target_locale' => $target_locale,
					'strings'       => $current_batch,
					'batch_info'    => array(
						'batch_number' => ++$batch_count,
						'batch_size'   => count( $current_batch ),
					),
				);

				$current_batch = array();
			}
		}

		// Add remaining strings as final batch.
		if ( ! empty( $current_batch ) ) {
			$batches[] = array(
				'version'       => 1,
				'source_locale' => $source_locale,
				'target_locale' => $target_locale,
				'strings'       => $current_batch,
				'batch_info'    => array(
					'batch_number' => ++$batch_count,
					'batch_size'   => count( $current_batch ),
				),
			);
		}

		return $batches;
	}

	/**
	 * Send payload to translation worker.
	 *
	 * @param array $payload The translation payload.
	 *
	 * @return array|false
	 * @throws Exception If the translation worker request fails.
	 */
	private function send_to_translation_worker( $payload ) {
		$worker_url   = $this->baseurl . 'translate';
		$request_body = wp_json_encode( $payload );

		// Get license key for authentication.
		$license_key = '';
		if ( class_exists( 'WPConsent_License' ) ) {
			$license     = new WPConsent_License();
			$license_key = $license->get();
		}

		// Build required headers for the worker.
		$headers = array(
			'Content-Type' => 'application/json',
		);

		// Add required authentication headers.
		if ( ! empty( $license_key ) ) {
			$headers['x-wpconsent-licensekey'] = $license_key;
		}

		// Add plugin version header.
		if ( defined( 'WPCONSENT_VERSION' ) ) {
			$headers['wpconsent-version'] = WPCONSENT_VERSION;
		}

		// Add website URL header.
		$headers['wpconsent-referer'] = get_home_url();

		// Add PHP version header.
		$headers['wpconsent-php-version'] = PHP_VERSION;

		// Add WordPress version header.
		$headers['wpconsent-wp-version'] = get_bloginfo( 'version' );

		$response = wp_remote_post(
			$worker_url,
			array(
				'headers' => $headers,
				'body'    => $request_body,
				'timeout' => 300, // 5 minutes timeout for translation processing.
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Translation worker request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		$data = json_decode( $body, true );

		if ( null === $data ) {
			throw new Exception( 'Translation worker returned invalid JSON response.' );
		}

		// Handle error responses from the worker.
		if ( 200 !== $response_code ) {
			$error_message = 'Translation worker returned error';

			if ( isset( $data['error_message'] ) ) {
				$error_message = $data['error_message'];
			} elseif ( isset( $data['error'] ) ) {
				$error_message = $data['error'];
			}

			throw new Exception( esc_html( $error_message ) );
		}

		// For successful translation requests, the worker returns the translated data directly.
		if ( empty( $data ) ) {
			throw new Exception( 'Translation worker returned empty response.' );
		}

		return $data;
	}

	/**
	 * Save translated content to the database.
	 *
	 * @param array  $translated_content The translated content.
	 * @param string $target_locale      The target locale.
	 *
	 * @return bool
	 */
	private function save_translated_content( $translated_content, $target_locale ) {
		if ( empty( $translated_content ) || ! is_array( $translated_content ) ) {
			return false;
		}

		// Get the first translation result (API returns array of translation objects).
		$translation_result = $translated_content[0];
		if ( ! isset( $translation_result['translations'] ) || ! is_array( $translation_result['translations'] ) ) {
			return false;
		}

		$translations = $translation_result['translations'];
		$saved_count  = 0;

		// Check if we're translating into the default language.
		$default_locale      = wpconsent()->multilanguage->get_plugin_locale();
		$is_default_language = ( $target_locale === $default_locale );

		// Group translations by type for efficient processing.
		$banner_options   = array();
		$category_updates = array();
		$service_updates  = array();
		$cookie_updates   = array();

		foreach ( $translations as $translation ) {
			if ( ! isset( $translation['id'] ) || ! isset( $translation['text'] ) ) {
				continue;
			}

			$id   = $translation['id'];
			$text = wp_kses_post( $translation['text'] );

			// Parse the ID to determine what type of content this is.
			if ( strpos( $id, 'banner.' ) === 0 ) {
				// Banner content: banner.accept_button_text -> accept_button_text.
				$option_name                    = str_replace( 'banner.', '', $id );
				$banner_options[ $option_name ] = $text;

			} elseif ( strpos( $id, 'category.' ) === 0 ) {
				// Category content: category.123.name -> 123 + name.
				$parts = explode( '.', $id );
				if ( count( $parts ) === 3 ) {
					$category_id = $parts[1];
					$field_type  = $parts[2]; // Name or description.

					if ( ! isset( $category_updates[ $category_id ] ) ) {
						$category_updates[ $category_id ] = array();
					}
					$category_updates[ $category_id ][ $field_type ] = $text;
				}
			} elseif ( strpos( $id, 'service.' ) === 0 ) {
				// Service content: service.123.name -> 123 + name.
				$parts = explode( '.', $id );
				if ( count( $parts ) === 3 ) {
					$service_id = $parts[1];
					$field_type = $parts[2]; // Name or description.

					if ( ! isset( $service_updates[ $service_id ] ) ) {
						$service_updates[ $service_id ] = array();
					}
					$service_updates[ $service_id ][ $field_type ] = $text;
				}
			} elseif ( strpos( $id, 'cookie.' ) === 0 ) {
				// Cookie content: cookie.123.name -> 123 + name.
				$parts = explode( '.', $id );
				if ( count( $parts ) === 3 ) {
					$post_id    = $parts[1]; // Post ID.
					$field_type = $parts[2]; // Name or description.

					if ( ! isset( $cookie_updates[ $post_id ] ) ) {
						$cookie_updates[ $post_id ] = array();
					}
					$cookie_updates[ $post_id ][ $field_type ] = $text;
				}
			}
		}

		// Save banner options.
		if ( ! empty( $banner_options ) ) {
			if ( $is_default_language ) {
				// Save directly to options for default language.
				// Mark fields as translated for default language.
				$banner_options['wpconsent_fields_translated'] = true;
				wpconsent()->settings->bulk_update_options( $banner_options );
			} else {
				// Prepare the nested structure for non-default locale.
				$locale_options = array(
					$target_locale => $banner_options,
				);
				wpconsent()->settings->bulk_update_options( $locale_options );
			}
			$saved_count += count( $banner_options );
		}

		// Save category translations.
		foreach ( $category_updates as $category_id => $fields ) {
			if ( $is_default_language ) {
				// Save directly to main tables for default language.
				foreach ( $fields as $field_type => $text ) {
					wp_update_term( $category_id, wpconsent()->cookies->taxonomy, array( $field_type => sanitize_text_field( $text ) ) );
					++$saved_count;
				}
				// Mark category as translated for default language.
				update_term_meta( $category_id, 'wpconsent_translated', true );
			} else {
				// Save to meta for non-default language using the same structure as multilanguage class.
				foreach ( $fields as $field_type => $text ) {
					$meta_key = 'wpconsent_category_' . $field_type . '_' . $target_locale;
					update_term_meta( $category_id, $meta_key, sanitize_text_field( $text ) );
					++$saved_count;
				}
			}
		}

		// Save service translations.
		foreach ( $service_updates as $service_id => $fields ) {
			if ( $is_default_language ) {
				// Save directly to main tables for default language.
				foreach ( $fields as $field_type => $text ) {
					wp_update_term( $service_id, wpconsent()->cookies->taxonomy, array( $field_type => sanitize_text_field( $text ) ) );
					++$saved_count;
				}
				// Mark service as translated for default language.
				update_term_meta( $service_id, 'wpconsent_translated', true );
			} else {
				// Save to meta for non-default language using the same structure as multilanguage class.
				foreach ( $fields as $field_type => $text ) {
					$meta_key = 'wpconsent_service_' . $field_type . '_' . $target_locale;
					update_post_meta( $service_id, $meta_key, sanitize_text_field( $text ) );
					++$saved_count;
				}
			}
		}

		// Save cookie translations.
		foreach ( $cookie_updates as $post_id => $fields ) {
			if ( $is_default_language ) {
				// Save directly to main tables for default language.
				foreach ( $fields as $field_type => $text ) {
					if ( 'name' === $field_type ) {
						wp_update_post(
							array(
								'ID'         => $post_id,
								'post_title' => sanitize_text_field( $text ),
							)
						);
					} elseif ( 'description' === $field_type ) {
						wp_update_post(
							array(
								'ID'           => $post_id,
								'post_content' => sanitize_text_field( $text ),
							)
						);
					} elseif ( 'duration' === $field_type ) {
						update_post_meta( $post_id, 'wpconsent_cookie_duration', sanitize_text_field( $text ) );
					}
					++$saved_count;
				}
				// Mark cookie as translated for default language.
				update_post_meta( $post_id, 'wpconsent_translated', true );
			} else {
				// Save to meta for non-default language using the same structure as multilanguage class.
				foreach ( $fields as $field_type => $text ) {
					$meta_key = 'wpconsent_cookie_' . $field_type . '_' . $target_locale;
					update_post_meta( $post_id, $meta_key, sanitize_text_field( $text ) );
					++$saved_count;
				}
			}
		}

		return $saved_count > 0;
	}

	/**
	 * Clear translation cache for a specific locale or all locales.
	 *
	 * @param string $target_locale Optional. The target locale to clear cache for. If empty, clears all locale caches.
	 *
	 * @return void
	 */
	private function clear_translation_cache( $target_locale = '' ) {
		if ( ! empty( $target_locale ) ) {
			// Clear cache for specific locale.
			$cache_key = 'wpconsent_translation_strings_' . $target_locale;
			delete_transient( $cache_key );
		} else {
			// Clear cache for all possible locales.
			// Get all enabled languages.
			$enabled_languages = (array) wpconsent()->settings->get_option( 'enabled_languages', array() );
			$default_locale    = wpconsent()->multilanguage->get_plugin_locale();

			// Add default locale to the list.
			$all_locales   = $enabled_languages;
			$all_locales[] = $default_locale;

			// Clear cache for each locale.
			foreach ( $all_locales as $locale ) {
				$cache_key = 'wpconsent_translation_strings_' . $locale;
				delete_transient( $cache_key );
			}
		}
	}

	/**
	 * Reset translation status to allow starting a new translation.
	 * This is useful when a translation gets stuck.
	 *
	 * @return bool True if reset successfully.
	 */
	public function reset_translation_status() {
		// Clear the translation status.
		delete_option( 'wpconsent_translation_status' );

		// Clear all translation caches.
		$this->clear_translation_cache();

		return true;
	}

	/**
	 * Reset all translation flags for categories, services, cookies, and banner fields.
	 * This allows content to be re-translated if needed.
	 *
	 * @return void
	 */
	public function reset_translation_flags() {
		// Reset banner fields translation flag.
		if ( wpconsent()->settings->get_option( 'wpconsent_fields_translated', false ) ) {
			wpconsent()->settings->update_option( 'wpconsent_fields_translated', false );
		}

		// Reset category translation flags.
		$categories = get_terms(
			array(
				'taxonomy'   => wpconsent()->cookies->taxonomy,
				'hide_empty' => false,
				'number'     => 0,
				'parent'     => 0,
				'meta_query' => array(
					array(
						'key'     => 'wpconsent_translated',
						'value'   => true,
						'compare' => '=',
					),
				),
			)
		);

		foreach ( $categories as $category ) {
			delete_term_meta( $category->term_id, 'wpconsent_translated' );
		}

		// Reset service translation flags (services are child terms).
		$services = get_terms(
			array(
				'taxonomy'       => wpconsent()->cookies->taxonomy,
				'hide_empty'     => false,
				'number'         => 0,
				'parent__not_in' => array( 0 ),
				'meta_query'     => array(
					array(
						'key'     => 'wpconsent_translated',
						'value'   => true,
						'compare' => '=',
					),
				),
			)
		);

		foreach ( $services as $service ) {
			delete_term_meta( $service->term_id, 'wpconsent_translated' );
		}

		// Reset cookie translation flags.
		$cookies = get_posts(
			array(
				'post_type'      => wpconsent()->cookies->post_type,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'     => 'wpconsent_translated',
						'value'   => true,
						'compare' => '=',
					),
				),
			)
		);

		foreach ( $cookies as $cookie ) {
			delete_post_meta( $cookie->ID, 'wpconsent_translated' );
		}

		// Clear the translation strings cache for all locales to force rebuild.
		$this->clear_translation_cache();
	}
}
