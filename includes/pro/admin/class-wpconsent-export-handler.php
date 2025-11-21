<?php
/**
 * Handles large CSV exports with proper resource management
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Export_Handler
 */
class WPConsent_Export_Handler {
	/**
	 * Batch size for processing records
	 */
	const BATCH_SIZE = 1000;

	/**
	 * File chunk size for reading/writing
	 */
	const CHUNK_SIZE = 1048576; // 1MB

	/**
	 * Export status constants
	 */
	const STATUS_PENDING    = 'pending';
	const STATUS_PROCESSING = 'processing';
	const STATUS_READY      = 'ready';
	const STATUS_FAILED     = 'failed';
	const STATUS_MERGING    = 'merging';

	/**
	 * Export directory paths
	 *
	 * @var string
	 */
	private $export_dir;

	/**
	 * Temporary directory paths
	 *
	 * @var string
	 */
	private $temp_dir;

	/**
	 * Progress tracking prefix
	 *
	 * @var string
	 */
	private $option_prefix = 'wpconsent_export_';

	/**
	 * Initialize the export handler
	 */
	public function __construct() {
		$upload_dir       = wp_upload_dir();
		$this->export_dir = trailingslashit( $upload_dir['basedir'] ) . 'wpconsent/exports';
		$this->temp_dir   = $this->export_dir . '/temp';
		$this->init_directories();
	}

	/**
	 * Initialize export directories
	 *
	 * @return void
	 *
	 * @throws Exception If directories cannot be created.
	 */
	private function init_directories() {
		if ( ! wp_mkdir_p( $this->export_dir ) ) {
			throw new Exception( 'Failed to create export directory' );
		}
		if ( ! wp_mkdir_p( $this->temp_dir ) ) {
			throw new Exception( 'Failed to create temp directory' );
		}
	}

	/**
	 * Get CSV headers
	 *
	 * @return array
	 */
	private function get_csv_headers() {
		return array(
			__( 'Date', 'wpconsent-premium' ),
			__( 'User ID', 'wpconsent-premium' ),
			__( 'IP Address', 'wpconsent-premium' ),
			__( 'Essential Cookies', 'wpconsent-premium' ),
			__( 'Statistics Cookies', 'wpconsent-premium' ),
			__( 'Marketing Cookies', 'wpconsent-premium' ),
			__( 'Services', 'wpconsent-premium' ),
		);
	}

	/**
	 * Format a single record for CSV
	 *
	 * @param array $record A single record from the database.
	 *
	 * @return array
	 */
	private function format_record( $record ) {
		$consent_data = $this->parse_consent_data( $record['consent_data'] );
		$services     = array();

		foreach ( $consent_data as $category => $status ) {
			if ( ! in_array( $category, array( 'essential', 'statistics', 'marketing' ), true ) ) {
				$services[] = $category . ': ' . $this->format_consent( $status );
			}
		}

		return array(
			wp_date( 'Y-m-d H:i:s', strtotime( $record['created_at'] ) ),
			empty( $record['user_id'] ) ? __( 'Guest', 'wpconsent-premium' ) : $record['user_id'],
			empty( $record['ip_address'] ) ? __( 'Unknown', 'wpconsent-premium' ) : $record['ip_address'],
			$this->format_consent( $consent_data['essential'] ?? null ),
			$this->format_consent( $consent_data['statistics'] ?? null ),
			$this->format_consent( $consent_data['marketing'] ?? null ),
			! empty( $services ) ? implode( ', ', $services ) : '',
		);
	}

	/**
	 * Parse consent data from JSON or serialized format
	 *
	 * @param string $data Consent data string.
	 *
	 * @return array
	 */
	private function parse_consent_data( $data ) {
		if ( empty( $data ) ) {
			return array();
		}

		$consent_data = json_decode( $data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$consent_data = maybe_unserialize( $data );
		}

		return is_array( $consent_data ) ? $consent_data : array();
	}

	/**
	 * Format consent value
	 *
	 * @param mixed $value Value in consent data column.
	 *
	 * @return string
	 */
	private function format_consent( $value ) {
		if ( empty( $value ) ) {
			return __( 'Declined', 'wpconsent-premium' );
		}

		$is_accepted = '1' === $value || strtolower( $value ) === 'true' || true === $value;
		return $is_accepted ? __( 'Accepted', 'wpconsent-premium' ) : __( 'Declined', 'wpconsent-premium' );
	}

	/**
	 * Merge batch files into final CSV
	 *
	 * @param string $request_id The ID for a specific request.
	 * @param int    $total_batches The total number of batches.
	 *
	 * @return bool
	 *
	 * @throws Exception If merging fails.
	 */
	public function merge_batch_files( $request_id, $total_batches ) {
		try {
			$this->update_progress( $request_id, self::STATUS_MERGING );

			$final_file = "{$this->export_dir}/{$request_id}.csv";
			$out        = fopen( $final_file, 'w' );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

			if ( ! $out ) {
				throw new Exception( 'Cannot create final export file' );
			}

			$wrote_headers = false;

			for ( $i = 1; $i <= $total_batches; $i++ ) {
				$batch_file = "{$this->temp_dir}/{$request_id}_{$i}.csv";

				if ( ! file_exists( $batch_file ) ) {
					continue;
				}

				$in = fopen( $batch_file, 'r' );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
				if ( $in ) {
					while ( ! feof( $in ) ) {
						$buffer = fread( $in, self::CHUNK_SIZE );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
						if ( false !== $buffer ) {
							fwrite( $out, $buffer );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
						}
					}

					fclose( $in );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
					wp_delete_file( $batch_file );
				}
			}

			fclose( $out );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			$this->update_progress(
				$request_id,
				self::STATUS_READY,
				array(
					'file_path'    => $final_file,
					'file_size'    => filesize( $final_file ),
					'completed_at' => time(),
				)
			);

			return true;

		} catch ( Exception $e ) {
			error_log( 'Merge failed: ' . $e->getMessage() . ' Request ID: ' . $request_id );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			throw $e;
		}
	}

	/**
	 * Update export progress
	 *
	 * @param string $request_id The ID for a specific request.
	 * @param string $status The status of the export.
	 * @param array  $data Additional data to store.
	 *
	 * @return bool
	 */
	public function update_progress( $request_id, $status, $data = array() ) {
		$option_name = $this->option_prefix . $request_id;
		// Retrieve any existing progress data.
		$existing = get_option( $option_name );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		// Merge the existing data with the new defaults and new data.
		$progress = array_merge(
			$existing,
			array(
				'status'       => $status,
				'updated_at'   => time(),
				'memory_usage' => memory_get_usage( true ),
			),
			$data
		);
		return update_option( $option_name, $progress, false );
	}

	/**
	 * Get export progress
	 *
	 * @param string $request_id The ID for a specific request.
	 *
	 * @return array
	 */
	public function get_progress( $request_id ) {
		return get_option( $this->option_prefix . $request_id );
	}

	/**
	 * Delete export progress and files
	 *
	 * @param string $request_id The ID for a specific request.
	 *
	 * @return void
	 */
	public function cleanup_export( $request_id ) {
		$progress = $this->get_progress( $request_id );

		if ( $progress ) {
			if ( ! empty( $progress['file_path'] ) && file_exists( $progress['file_path'] ) ) {
				wp_delete_file( $progress['file_path'] );
			}

			// Clean up temp files.
			$temp_pattern = $this->temp_dir . '/' . $request_id . '_*.csv';
			array_map( 'unlink', glob( $temp_pattern ) );

			delete_option( $this->option_prefix . $request_id );
		}
	}

	/**
	 * Process a batch of records
	 *
	 * @param string $request_id The ID for a specific request.
	 * @param int    $batch The current batch number.
	 * @param int    $last_id ID for the previous request.
	 * @param array  $export_data The export data that was processed.
	 *
	 * @return array
	 *
	 * @throws Exception If an error occurs during processing.
	 */
	public function process_batch( $request_id, $batch, $last_id, $export_data ) {
		try {
			if ( empty( $export_data['date_from'] ) || empty( $export_data['date_to'] ) ) {
				throw new Exception( 'Missing date range for export' );
			}

			$this->update_progress(
				$request_id,
				self::STATUS_PROCESSING,
				array(
					'batch'        => $batch,
					'last_id'      => $last_id,
					'memory_usage' => memory_get_usage( true ),
				)
			);

			$records = $this->get_batch_records( $last_id, $export_data );

			if ( empty( $records ) ) {
				return array(
					'success'   => true,
					'last_id'   => $last_id,
					'processed' => 0,
				);
			}

			$batch_file = "{$this->temp_dir}/{$request_id}_{$batch}.csv";

			// Verify directory is writable.
			if ( ! is_writable( dirname( $batch_file ) ) ) {  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
				throw new Exception( 'Export directory is not writable' );
			}

			$success = $this->write_batch_to_file( $batch_file, $records, 1 === $batch );

			if ( ! $success ) {
				throw new Exception( 'Failed to write batch file' );
			}

			// Get last record ID before clearing memory.
			$processed_count = count( $records );
			end( $records );
			$last_record = current( $records );
			$last_id     = $last_record['consent_id'];

			// Clear memory.
			unset( $records );

			return array(
				'success'   => true,
				'last_id'   => $last_id,
				'processed' => $processed_count,
			);

		} catch ( Exception $e ) {
			$this->update_progress(
				$request_id,
				self::STATUS_FAILED,
				array(
					'error' => $e->getMessage(),
					'batch' => $batch,
				)
			);
			throw $e;
		}
	}

	/**
	 * Get batch of records from database
	 *
	 * @param int   $last_id ID for the previous request.
	 * @param array $export_data The export data that was processed.
	 *
	 * @return array
	 */
	private function get_batch_records( $last_id, $export_data ) {
		global $wpdb;

		return $wpdb->get_results(  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT SQL_NO_CACHE consent_id, user_id, ip_address, consent_data, created_at 
					FROM {$wpdb->prefix}wpconsent_consent_logs 
					WHERE consent_id > %d 
					AND created_at BETWEEN %s AND %s
					ORDER BY consent_id ASC 
					LIMIT %d",
				$last_id,
				$export_data['date_from'] . ' 00:00:00',
				$export_data['date_to'] . ' 23:59:59',
				self::BATCH_SIZE
			),
			ARRAY_A
		);
	}

	/**
	 * Write batch records to file
	 *
	 * @param string $filename The file to write to.
	 * @param array  $records The records to write.
	 * @param bool   $include_headers Whether to include headers.
	 *
	 * @return bool
	 *
	 * @throws Exception If file cannot be opened.
	 */
	private function write_batch_to_file( $filename, $records, $include_headers = false ) {
		$handle = fopen( $filename, 'w' );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			// translators: %s is the file name that could not be opened.
			throw new Exception( sprintf( esc_html__( 'Cannot open file: %s', 'wpconsent-premium' ), $filename ) );  // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		try {
			if ( $include_headers ) {
				fputcsv( $handle, $this->get_csv_headers() );
			}

			foreach ( $records as $record ) {
				$row = $this->format_record( $record );
				fputcsv( $handle, $row );
			}

			fclose( $handle );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return true;

		} catch ( Exception $e ) {
			if ( is_resource( $handle ) ) {
				fclose( $handle );  // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
			throw $e;
		}
	}
}
