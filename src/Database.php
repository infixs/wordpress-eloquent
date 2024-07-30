<?php

namespace Infixs\WordpressEloquent;

defined( 'ABSPATH' ) || exit;

class Database {

	/**
	 * Wordpress database object
	 *
	 * @var \wpdb
	 */
	protected $wpdb;

	private $prefix = '';

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	public function get_table_name( $table_name ) {
		return sprintf( '%s%s%s', $this->wpdb->prefix, $this->prefix, $table_name );
	}

	/**
	 * Create a table in the database
	 *
	 * @param string $table_name
	 * @param array $columns
	 * @return void
	 */
	public function create_table( $table_name, $columns ) {
		$charset_collate = $this->wpdb->get_charset_collate();
		$full_table_name = $this->get_table_name( $table_name );

		$generated_columns = array_map( function ($column_name, $column_type) {
			return "$column_name $column_type";
		}, array_keys( $columns ), $columns );

		$sql = "CREATE TABLE $full_table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			" . implode( ",\n", $generated_columns ) . ",
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once ( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Prepare a query
	 *
	 * @param string $query
	 * @param mixed $args
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		return $this->wpdb->prepare( $query, $args );
	}

	public function query( $query ) {
		return $this->wpdb->query( $query );
	}

	public function get_results( $query ) {
		return $this->wpdb->get_results( $query );
	}

	/**
	 * Get a single row from the database
	 *
	 * @param string $query
	 * @return string|null
	 */
	public function get_var( $query ) {
		return $this->wpdb->get_var( $query );
	}

	/**
	 * Delete row(s) from the database
	 *
	 * @param string $table
	 * @param array $where_values
	 * @param array $where_format
	 * @return bool|int
	 */
	public function delete( $table, $where_values, $where_format = null ) {
		return $this->wpdb->delete( $table, $where_values, $where_format );
	}

	/**
	 * Insert data into a table
	 *
	 * @param string $table
	 * @param array $data
	 * @return int|bool
	 */
	public function insert( $table, $data ) {
		$inserted = $this->wpdb->insert( $table, $data );
		if ( ! $inserted ) {
			return false;
		}
		return $this->wpdb->insert_id;
	}

	/**
	 * Update data into a table
	 *
	 * @param string $table
	 * @param array $data
	 * @param array $where_values
	 * @return int|bool
	 */
	public function update( $table, $data, $where_values ) {
		$updated = $this->wpdb->update( $table, $data, $where_values );
		if ( ! $updated ) {
			return false;
		}
		return $updated;
	}

	/**
	 * Insert multiple rows into a table
	 *
	 * @param string $table_name
	 * @param array $data
	 * @return bool|int Boolean true for CREATE, ALTER, TRUNCATE and DROP queries. Number of rows
	 *                  affected/selected for all other queries. Boolean false on error.
	 */
	function insert_multiple( $table_name, $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		$first_item = $data[0];
		$columns = array_keys( $first_item );
		$columns_sql = implode( ', ', $columns );

		$values = [];
		$placeholders = [];

		foreach ( $data as $item ) {
			foreach ( $item as $value ) {
				$values[] = $value;
			}
			$placeholders[] = '(' . implode( ', ', array_fill( 0, count( $item ), '%s' ) ) . ')';
		}

		$values_sql = implode( ', ', $placeholders );

		$sql = "INSERT INTO $table_name ($columns_sql) VALUES $values_sql";

		return $this->wpdb->query( $this->wpdb->prepare( $sql, $values ) );
	}

}