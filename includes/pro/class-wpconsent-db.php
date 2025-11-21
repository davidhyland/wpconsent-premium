<?php
/**
 * Class for creating and updating custom DB tables.
 *
 * @package WPConsent
 */

/**
 * Class WPConsent_DB
 */
class WPConsent_DB {

	/**
	 * Version of the db structure.
	 *
	 * @var string
	 */
	private $version = '1.0.3';

	/**
	 * The key used to store the version of the db in the options table.
	 *
	 * @var string
	 */
	private $version_key = 'wpconsent_db_version';

	/**
	 * Run the SQL query to update/create the tables needed by WPConsent.
	 *
	 * @return bool
	 */
	private function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'wpconsent_consent_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
				consent_id   BIGINT(20)  NOT NULL AUTO_INCREMENT,
				user_id	  	 BIGINT(20)  NOT NULL,
				ip_address	 VARCHAR(40)  NOT NULL,
				country_code CHAR(2)  NULL,
				consent_data LONGTEXT NOT NULL,
				created_at 	 datetime NOT NULL default '0000-00-00 00:00:00',
				PRIMARY KEY  (consent_id)
			) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		return empty( $wpdb->last_error );
	}

	/**
	 * Add the country_code column to the existing table.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function add_country_code_column() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpconsent_consent_logs';

		// Check if column already exists.
		$column_exists = $wpdb->get_var(  // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(1) FROM information_schema.columns 
					WHERE table_schema = %s
					AND table_name = %s
					AND column_name = %s",
				DB_NAME,
				$table_name,
				'country_code'
			)
		);
		if ( ! empty( $column_exists ) ) {
			return true;  // Column already there.
		}

		// Add column.
		$wpdb->query(  // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"ALTER TABLE $table_name
				ADD COLUMN country_code CHAR(2) NULL AFTER ip_address"
		);
		// Return success if no error.
		return ( ! $wpdb->last_error );
	}

	/**
	 * Check if table exists
	 *
	 * @return bool
	 */
	private function table_exists() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpconsent_consent_logs';
		return (bool) $wpdb->get_var(  // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$table_name
			)
		);
	}


	/**
	 * Check if the version has changed and we need to update the table.
	 *
	 * @return void
	 */
	public function maybe_update_db() {
		$version = get_option( $this->version_key, '0' );

		if ( ! version_compare( $this->version, $version, '>' ) ) {
			return;
		}

		$updated = false;

		if ( ! $this->table_exists() ) {
			$updated = $this->create_table();
		}

		// If coming from a version before 1.0.3, add country_code column.
		if ( version_compare( $version, '1.0.3', '<' ) ) {
			$this->add_country_code_column();
		}

		if ( $updated ) {
			update_option( $this->version_key, $this->version, false );
		}
	}
}
