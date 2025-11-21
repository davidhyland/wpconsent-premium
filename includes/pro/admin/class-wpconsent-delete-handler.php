<?php
/**
 * Handles large log deletions with proper resource management
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_Delete_Handler
 */
class WPConsent_Delete_Handler {
	/**
	 * Batch size for processing records
	 */
	const BATCH_SIZE = 1000;

	/**
	 * Delete status constants
	 */
	const STATUS_PENDING    = 'pending';
	const STATUS_PROCESSING = 'processing';
	const STATUS_COMPLETED  = 'completed';
	const STATUS_FAILED     = 'failed';

	/**
	 * Database table name
	 *
	 * @var string
	 */
	protected $table_name;

	/**
	 * Date column name
	 *
	 * @var string
	 */
	protected $date_column;

	/**
	 * ID column name
	 *
	 * @var string
	 */
	protected $id_column;

	/**
	 * Progress tracking prefix
	 *
	 * @var string
	 */
	protected $option_prefix = 'wpconsent_delete_';

	/**
	 * Initialize the delete handler
	 * Defaults to RoC table but extensible for other tables
	 *
	 * @param string|null $table_name Database table name (with prefix).
	 * @param string      $date_column Date column name for filtering.
	 * @param string      $id_column ID column name for primary key.
	 */
	public function __construct( $table_name = null, $date_column = 'created_at', $id_column = 'consent_id' ) {
		global $wpdb;
		$this->table_name  = esc_sql( $table_name ?? $wpdb->prefix . 'wpconsent_consent_logs' );
		$this->date_column = $this->validate_identifier( $date_column );
		$this->id_column   = $this->validate_identifier( $id_column );
	}

	/**
	 * Validate database identifier (table/column name)
	 * Only allows alphanumeric characters and underscores
	 *
	 * @param string $identifier The identifier to validate.
	 *
	 * @return string Validated identifier.
	 *
	 * @throws Exception If identifier contains invalid characters.
	 */
	protected function validate_identifier( $identifier ) {
		// Only allow alphanumeric characters and underscores.
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $identifier ) ) {
			throw new Exception( 'Invalid database identifier: ' . esc_html( $identifier ) );
		}
		return $identifier;
	}

	/**
	 * Convert period string to date threshold
	 *
	 * @param string $period Period ('3_months', '6_months', '1_year', '2_years', 'all').
	 *
	 * @return string Date threshold in SQL format.
	 *
	 * @throws Exception If invalid period specified.
	 */
	public function get_date_threshold( $period ) {
		$valid_periods = array( '3_months', '6_months', '1_year', '2_years', 'all' );
		if ( ! in_array( $period, $valid_periods, true ) ) {
			throw new Exception( 'Invalid period specified' );
		}

		if ( 'all' === $period ) {
			return '9999-12-31 23:59:59'; // Future date to delete all.
		}

		$interval_map = array(
			'3_months' => '3 months',
			'6_months' => '6 months',
			'1_year'   => '1 year',
			'2_years'  => '2 years',
		);

		return gmdate( 'Y-m-d H:i:s', strtotime( '-' . $interval_map[ $period ] ) );
	}

	/**
	 * Count records to delete
	 *
	 * @param string $date_threshold SQL date threshold.
	 *
	 * @return int Record count.
	 */
	public function count_records_to_delete( $date_threshold ) {
		global $wpdb;

		return (int) $wpdb->get_var(  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE {$this->date_column} < %s",  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$date_threshold
			)
		);
	}

	/**
	 * Delete a batch of records
	 *
	 * @param string $date_threshold SQL date threshold.
	 * @param int    $batch_number Current batch number.
	 *
	 * @return int Number of records deleted.
	 *
	 * @throws Exception If deletion fails.
	 */
	public function delete_batch( $date_threshold, $batch_number ) {
		global $wpdb;

		try {
			$deleted = $wpdb->query(  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"DELETE FROM {$this->table_name}
					WHERE {$this->date_column} < %s
					LIMIT %d",
					$date_threshold,
					self::BATCH_SIZE
				)
			);

			if ( false === $deleted ) {
				throw new Exception( 'Database delete operation failed: ' . $wpdb->last_error );
			}

			return $deleted;

		} catch ( Exception $e ) {
			error_log( 'Delete batch failed: ' . $e->getMessage() . ' Batch: ' . $batch_number );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			throw $e;
		}
	}

	/**
	 * Update delete progress
	 *
	 * @param string $request_id The ID for a specific request.
	 * @param string $status The status of the deletion.
	 * @param array  $data Additional data to store.
	 *
	 * @return bool
	 */
	public function update_progress( $request_id, $status, $data = array() ) {
		$option_name = $this->option_prefix . $request_id;
		$existing    = get_option( $option_name );

		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

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
	 * Get delete progress
	 *
	 * @param string $request_id The ID for a specific request.
	 *
	 * @return array|false
	 */
	public function get_progress( $request_id ) {
		return get_option( $this->option_prefix . $request_id );
	}

	/**
	 * Delete progress and cleanup
	 *
	 * @param string $request_id The ID for a specific request.
	 *
	 * @return void
	 */
	public function cleanup_progress( $request_id ) {
		delete_option( $this->option_prefix . $request_id );
	}
}
