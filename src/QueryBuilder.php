<?php

namespace Infixs\WordpressEloquent;

use Infixs\WordpressEloquent\Relations\HasOne;
use Infixs\WordpressEloquent\Model;

defined( 'ABSPATH' ) || exit;
class QueryBuilder {

	/**
	 * Table name
	 * 
	 * @var string
	 */
	protected $table_name;

	/**
	 * Database instance
	 * 
	 * @var Database
	 */
	protected $db;
	protected $whereArray = [];

	protected $joinArray = [];

	private $select = '*';

	protected $model;

	public function __construct( Model $model ) {
		$this->db = $model->getDatabase();
		$this->table_name = $model->getTableName();
		$this->model = $model;
	}

	public function select( $columns ) {
		$this->select = $columns;
		return $this;
	}

	public function where( $column, $value ) {
		$this->whereArray[] = [ 'column' => $column, 'value' => $value ];
		return $this;
	}

	public function with( string $relation ) {
		$reflection = new \ReflectionClass( $this->model );
		$methods = $reflection->getMethods( \ReflectionMethod::IS_PUBLIC );

		foreach ( $methods as $method ) {
			$returnType = $method->getReturnType();
			if ( $returnType && $returnType->getName() === HasOne::class && $relation === $method->getName() ) {
				$this->joinArray[] = [ 
					'table' => $this->db->get_table_name( $relation . 's' ),
					'foreign_key' => $this->model->getForeignKey() . '_id',
					'local_key' => 'id',
				];
			}
		}
		return $this;
	}

	/**
	 * Count all records from the table
	 *
	 * @since 1.0.0
	 * @return int Database query results.
	 */
	public function count() {
		return (int) $this->db->get_var( $this->generateQuery( true ) );
	}

	/**
	 * Check if a record exists in the table
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function exists() {
		return $this->count() > 0;
	}

	/**
	 * Delete a record from the table
	 *
	 * @return void
	 */
	public function delete( $where_format = null ) {
		$where = [];
		foreach ( $this->whereArray as $item ) {
			$where[ $item['column'] ] = $item['value'];
		}
		$this->db->delete( $this->table_name, $where, $where_format );
	}

	/**
	 * Generate the query
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function generateQuery( $count = false ) {
		$sql = $count ? "SELECT count(*) FROM {$this->table_name}" : "SELECT {$this->select} FROM {$this->table_name}";


		foreach ( $this->joinArray as $join ) {
			$sql .= " INNER JOIN {$join['table']} ON {$this->table_name}.{$join['local_key']} = {$join['table']}.{$join['foreign_key']}";
		}

		if ( ! empty( $this->whereArray ) ) {
			$sql .= ' WHERE ';
			$placeholders = array();
			$values = array();
			foreach ( $this->whereArray as $where ) {
				$placeholders[] = $where['column'] . ' = %s';
				$values[] = $where['value'];
			}

			$sql .= implode( ' AND ', $placeholders );
			$sql = $this->db->prepare( $sql, ...$values );
		}

		return $sql;
	}

	/**
	 * Get all records from the table
	 *
	 * @since 1.0.0
	 * @return array|object|null Database query results.
	 */
	public function get() {
		return $this->db->get_results( $this->generateQuery() );
	}

	/**
	 * Get the first record from the table
	 *
	 * @since 1.0.0
	 * @return object|null Database query result.
	 */
	public function first() {
		$results = $this->get();
		return $results[0] ?? null;
	}

	/**
	 * Get the first record from the table or throw an exception
	 *
	 * @since 1.0.0
	 * @return object Database query result.
	 */
	public function firstOrFail() {
		$result = $this->first();
		if ( ! $result ) {
			throw new \Exception( 'No record found' );
		}
		return $result;
	}
}