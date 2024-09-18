<?php

namespace Infixs\WordpressEloquent;

use Infixs\WordpressEloquent\Relations\BelongsTo;
use Infixs\WordpressEloquent\Relations\HasOne;
use Infixs\WordpressEloquent\Model;

defined( 'ABSPATH' ) || exit;

/**
 * QueryBuilder class.
 * 
 * This class is responsible for building database queries.
 * 
 * @since 1.0.0
 */
class QueryBuilder {

	/**
	 * Table name
	 * 
	 * @since 1.0.0
	 * @var string
	 */
	protected $table_name;

	/**
	 * Database instance
	 * 
	 * @since 1.0.0
	 * @var Database
	 */
	protected $db;

	/**
	 * Where array
	 * 
	 * @param array {
	 * 		@type string $column
	 * 		@type mixed $value
	 * 		@type string $operator
	 * 		@type string $method
	 * }
	 * 
	 * @var array
	 */
	protected $whereArray = [];

	protected $joinArray = [];

	protected $orderBy = [];

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

	/**
	 * Add a basic where clause to the query.
	 * 
	 * @since 1.0.0
	 * 
	 * @param mixed $column
	 * @param mixed $operator
	 * @param mixed $value
	 * 
	 * @return static
	 */
	public function where( $column, $operator, $value = null ) {
		$this->whereArray[] = [ 'column' => $column, 'value' => $value ?? $operator, 'operator' => isset( $value ) ? $operator : '=' ];
		return $this;
	}

	/**
	 * Add an "or where" with relation clause to the query.
	 * 
	 * @since 1.0.0
	 * 
	 * @param mixed $column
	 * @param mixed $operator
	 * @param mixed $value
	 * 
	 * @return static
	 */
	public function orWhereRelation( $relation, $column, $value_or_operator, $field_value = null ) {
		$reflection = new \ReflectionClass( $this->model );

		$method = $reflection->getMethod( $relation );
		$returnType = $method->getReturnType();

		if ( $returnType && $relation === $method->getName() ) {
			if ( $returnType->getName() === HasOne::class) {
				/**
				 * @var HasOne
				 */
				$hasOne = $method->invoke( $this->model );
				$relatedClass = $hasOne->getRelatedClass();
				$relatedReflection = new \ReflectionClass( $relatedClass );
				$hasPrefixProperty = $relatedReflection->getProperty( "prefix" );
				$prefix = $hasPrefixProperty->getDefaultValue();

				$table_name = Database::getTableName( "{$relation}s" );

				$this->joinArray[] = [ 
					'table' => $table_name,
					'foreign_key' => $this->model->getForeignKey() . '_id',
					'local_key' => 'id',
				];
			}

			if ( $returnType->getName() === BelongsTo::class) {
				/**
				 * @var BelongsTo
				 */
				$belongsTo = $method->invoke( $this->model );
				$relatedClass = $belongsTo->getRelatedClass();
				$relatedReflection = new \ReflectionClass( $relatedClass );
				$hasPrefixProperty = $relatedReflection->getProperty( "prefix" );
				$prefix = $hasPrefixProperty->getDefaultValue();

				//$belongsTo->ge
				$table_name = Database::getTableName( "{$relation}s" );

				$this->joinArray[] = [ 
					'table' => $table_name,
					'foreign_key' => $belongsTo->getLocalKey(),
					'local_key' => $belongsTo->getForeignKey()
				];
			}
		}
		$this->whereArray[] = [ 
			'method' => 'OR',
			'column' => "{$table_name}.{$column}",
			'value' => isset( $field_value ) ? $field_value : $value_or_operator,
			'operator' => isset( $field_value ) ? $value_or_operator : '='
		];

		return $this;
	}


	/**
	 * TODO: Verify
	 */
	public function with( string $relation ) {
		$reflection = new \ReflectionClass( $this->model );
		$methods = $reflection->getMethods( \ReflectionMethod::IS_PUBLIC );

		foreach ( $methods as $method ) {
			$returnType = $method->getReturnType();
			if ( $returnType && $returnType->getName() === HasOne::class && $relation === $method->getName() ) {
				$this->joinArray[] = [ 
					'table' => $this->db->getTableName( $relation . 's' ),
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
	 * @return int|false The number of rows updated, or false on error.
	 */
	public function delete( $where_format = null ) {
		$where = [];
		foreach ( $this->whereArray as $item ) {
			$where[ $item['column'] ] = $item['value'];
		}
		return $this->db->delete( $this->table_name, $where, $where_format );
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
			$placeholders = [];
			$values = [];
			foreach ( $this->whereArray as $where ) {
				$operator = $where['operator'] ?? '=';
				$placeholder = "{$where['column']} {$operator} %s";
				$method = $where['method'] ?? 'AND';
				$placeholders[] = empty( $placeholders ) ? $placeholder : "{$method} {$placeholder}";
				$values[] = $where['value'];
			}
			$sql .= implode( ' ', $placeholders );
			$sql = $this->db->prepare( $sql, ...$values );
		}

		if ( ! empty( $this->orderBy ) ) {
			$sql .= ' ORDER BY ';
			$orderBy = [];
			foreach ( $this->orderBy as $order ) {
				$orderBy[] = "{$order['column']} " . strtoupper( $order['order'] );
			}
			$sql .= implode( ', ', $orderBy );
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

	public function orderBy( $column, $order = 'asc' ) {
		$this->orderBy[] = [ 'column' => $column, 'order' => $order ];
		return $this;
	}
}