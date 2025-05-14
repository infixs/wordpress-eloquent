<?php

namespace Infixs\WordpressEloquent;

use Infixs\WordpressEloquent\Relations\BelongsTo;
use Infixs\WordpressEloquent\Relations\HasMany;
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
	 * @since 1.0.0
	 * 
	 * @updated 1.0.3
	 * 
	 * @var array{
	 * 		column: string,
	 * 		value: mixed,
	 * 		operator: string,
	 * 		method: string,
	 * 		table: string
	 * }
	 */
	protected $whereArray = [];

	/**
	 * Exists array
	 * 
	 * @since 1.0.0
	 * 
	 * @var array{
	 * 		sql: string,
	 * 		method: string
	 * }
	 */
	protected $existsArray = [];

	/**
	 * Where column array
	 * 
	 * @since 1.0.3
	 * 
	 * @var array{
	 * 		column_one: string,
	 * 		operator: string,
	 * 		column_two: string,
	 * 		method: string
	 * }
	 */
	protected $whereColumnArray = [];

	protected $joinArray = [];

	protected $groupBy = [];

	/**
	 * With array
	 * 
	 * @since 1.0.2
	 * 
	 * @var array{
	 * 		@type string $relation,
	 * 		@type string $table,
	 * 		@type string $foreign_key,
	 * 		@type string $local_key,
	 * 		@type \Infixs\WordpressEloquent\Model $model,
	 * 		@type Relation $relation_type
	 * }
	 */
	protected $withArray = [];

	protected $orderBy = [];

	private $select = '*';

	/**
	 * Limit
	 * 
	 * @since 1.0.0
	 * @var int
	 */
	private $limit;

	/**
	 * Offset
	 * 
	 * @since 1.0.0
	 * @var int
	 */
	private $offset;

	/**
	 * Model instance
	 * 
	 * @since 1.0.0
	 * @var Model
	 */
	protected $model;

	public function __construct( Model $model ) {
		$this->db = $model->getDatabase();
		$this->table_name = $model->getTableName();
		$this->model = $model;
		if ( $model->trashed() ) {
			$this->whereArray[] = [ 'column' => 'deleted_at', 'value' => '!#####NULL#####!', 'operator' => 'IS' ];
			add_filter( 'query', [ $this, 'nulled_query_replace' ] );
		}
	}

	public function nulled_query_replace( $query ) {
		return str_replace( [ "IS '!#####NULL#####!'", "IS NOT '!#####NULL#####!'" ], [ 'IS NULL', 'IS NOT NULL' ], $query );
	}

	/**
	 * Select columns
	 * 
	 * @since 1.0.0
	 * 
	 * @param string|array $columns
	 * 
	 * @return QueryBuilder
	 */
	public function select( $columns ) {
		$this->select = is_array( $columns ) ? implode( ', ', $columns ) : $columns;
		return $this;
	}

	/**
	 * Add a basic where clause to the query.
	 * 
	 * @since 1.0.0
	 * 
	 * @param mixed $column Column name
	 * @param mixed $operator Value or Operator
	 * @param mixed $value Valor or null
	 * 
	 * @return QueryBuilder
	 */
	public function where( $column, $operator = null, $value = null, $method = null, $table = null ) {
		if ( is_array( $column ) ) {
			foreach ( $column as $col => $val ) {
				$this->where( $col, $val );
			}
			return $this;
		}

		$where = [ 
			'column' => $column,
			'value' => $value ?? $operator,
			'operator' => isset( $value ) ? $operator : '='
		];

		if ( $method ) {
			$where['method'] = $method;
		}

		if ( $table ) {
			$where['table'] = $table;
		}

		$this->whereArray[] = $where;

		return $this;
	}

	/**
	 * Where has relation
	 * 
	 * @param string $relation
	 * @param callable(QueryBuilder $query) $callback
	 * 
	 * @return QueryBuilder
	 */
	public function whereHas( $relation, $callback ) {
		$reflection = new \ReflectionClass( $this->model );

		$method = $reflection->getMethod( $relation );

		/** @var Relation $relation */
		$relation = $method->invoke( $this->model );
		$related_class = $relation->getRelatedClass();

		$query = $related_class::query();

		if ( $relation instanceof HasMany ) {
			$query->whereColumn( $relation->getForeignKey(), $this->model->getTableName() . '.' . $relation->getLocalKey() );
		} elseif ( $relation instanceof BelongsTo ) {
			$query->whereColumn( $relation->getLocalKey(), $this->model->getTableName() . '.' . $relation->getForeignKey() );
		}

		if ( is_callable( $callback ) ) {
			call_user_func( $callback, $query );
		}

		$sql = $query->generateQuery();

		$this->existsArray[] = [ 
			'sql' => $sql,
			'method' => 'AND'
		];

		return $this;
	}


	/**
	 * Group by columns
	 * 
	 * @since 1.0.0
	 * 
	 * @param string ...$column
	 * 
	 * @return QueryBuilder
	 */
	public function groupBy( ...$columns ) {
		foreach ( $columns as $column ) {
			$this->groupBy[] = $column;
		}
		return $this;
	}

	/**
	 * Where In
	 *
	 * @since 1.0.2
	 * 
	 * @param string $column Name of the column.
	 * @param array $value Array values of the column.
	 * 
	 * @return QueryBuilder
	 */
	public function whereIn( $column, $values = [] ) {
		$this->where( $column, 'IN', $values );
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
		return $this->whereRelation( $relation, $column, $value_or_operator, $field_value, 'OR' );
	}

	/**
	 * Add an "where" with relation clause to the query.
	 * 
	 * @since 1.0.0
	 * 
	 * @param mixed $column
	 * @param mixed $operator
	 * @param mixed $value
	 * 
	 * @return static
	 */
	public function whereRelation( $relation, $column, $value_or_operator, $field_value = null, $query_method = 'AND' ) {
		//TODO: Verify and refactor this
		$reflection = new \ReflectionClass( $this->model );

		$method = $reflection->getMethod( $relation );
		$returnType = $method->getReturnType();

		if ( $returnType && $relation === $method->getName() ) {
			if ( $returnType->getName() === HasOne::class) {
				/**
				 * @var HasOne
				 * TODO: Verify not implemented
				 */
				$hasOne = $method->invoke( $this->model );
				$related_class = $hasOne->getRelatedClass();
				$table_name = $related_class::generateTableName();

				$this->joinArray[] = [ 
					'table' => $table_name,
					'foreign_key' => $this->model->getForeignKey() . '_id',
					'local_key' => 'id',
				];

				//TODO: Replace by $this->where
				$this->whereArray[] = [ 
					'method' => $query_method,
					'column' => "{$column}",
					'table' => $table_name,
					'value' => isset( $field_value ) ? $field_value : $value_or_operator,
					'operator' => isset( $field_value ) ? $value_or_operator : '='
				];

				if ( $related_class::isTrashed() ) {
					$this->whereArray[] = [ 
						'column' => 'deleted_at',
						'table' => $table_name,
						'value' => '!#####NULL#####!',
						'operator' => 'IS'
					];
				}
			}

			if ( $returnType->getName() === BelongsTo::class) {
				/**
				 * @var BelongsTo
				 */
				$belongsTo = $method->invoke( $this->model );
				$related_class = $belongsTo->getRelatedClass();
				$table_name = $related_class::generateTableName();

				$this->joinArray[] = [ 
					'table' => $table_name,
					'foreign_key' => $belongsTo->getLocalKey(),
					'local_key' => $belongsTo->getForeignKey()
				];

				//TODO: Replace by $this->where
				$this->whereArray[] = [ 
					'method' => $query_method,
					'column' => "{$column}",
					'table' => $table_name,
					'value' => isset( $field_value ) ? $field_value : $value_or_operator,
					'operator' => isset( $field_value ) ? $value_or_operator : '='
				];

				if ( $related_class::isTrashed() ) {
					$this->whereArray[] = [ 
						'column' => 'deleted_at',
						'table' => $table_name,
						'value' => '!#####NULL#####!',
						'operator' => 'IS'
					];
				}
			}
		}



		return $this;
	}

	/**
	 * Add an "where" with columns compare clause to the query.
	 * 
	 * @since 1.0.3
	 * 
	 * @param string $column_one Column name
	 * @param string $operator Operator or Column name
	 * @param string $column_two Column name 
	 * 
	 * @return static
	 */
	public function whereColumn( $column_one, $operator = null, $column_two = null, $method = 'AND' ) {
		$this->whereColumnArray[] = [ 
			'column_one' => $column_one,
			'operator' => $column_two ? $operator : '=',
			'column_two' => $column_two ?? $operator,
			'method' => $method
		];

		return $this;
	}

	/**
	 * TODO: Verify
	 */
	public function with( string $relation ) {
		$reflection = new \ReflectionClass( $this->model );
		$method = $reflection->getMethod( $relation );

		if ( $relation !== $method->getName() ) {
			return $this;
		}

		$returnType = $method->getReturnType();

		$returnClassType = $method->invoke( $this->model );
		$relatedClass = $returnClassType->getRelatedClass();

		if ( $returnType && $returnType->getName() === HasOne::class) {
			$this->withArray[] = [ 
				'model' => $relatedClass,
				'relation' => $relation,
				'table' => $relatedClass::getTable(),
				'foreign_key' => $this->model->getForeignKey() . '_id',
				'local_key' => 'id',
			];
		}

		if ( $returnType && $returnType->getName() === BelongsTo::class) {
			$this->withArray[] = [ 
				'model' => $relatedClass,
				'relation' => $relation,
				'table' => $relatedClass::getTable(),
				'foreign_key' => 'id',
				'local_key' => $relatedClass::getForeignKeyStatic(),
				'relation_type' => BelongsTo::class
			];
		}

		if ( $returnType && $returnType->getName() === HasMany::class) {
			$this->withArray[] = [ 
				'model' => $relatedClass,
				'relation' => $relation,
				'table' => $relatedClass::getTable(),
				'foreign_key' => $this->model->getForeignKey() . '_id',
				'local_key' => 'id',
				'relation_type' => HasMany::class
			];
		}

		return $this;
	}

	/**
	 * Count all records from the table
	 *
	 * @since 1.0.0
	 * 
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
		if ( $this->model->trashed() ) {
			return $this->update( [ 'deleted_at' => current_time( 'mysql' ) ] );
		} else {
			$where = [];
			foreach ( $this->whereArray as $item ) {
				$where[ $item['column'] ] = $item['value'];
			}
			return $this->db->delete( $this->table_name, $where, $where_format );
		}
	}

	public function update( $columns_values ) {
		$where = $this->resolveWhere();
		$set_clauses = [];
		$values = [];

		foreach ( $columns_values as $column => $value ) {
			$set_clauses[] = "{$column} = %s";
			$values[] = $value;
		}

		$set_clause = implode( ', ', $set_clauses );
		$where_clause = implode( ' ', $where['placeholders'] );
		$values = array_merge( $values, $where['values'] );

		$sql = "UPDATE {$this->table_name} SET {$set_clause} WHERE {$where_clause}";

		$prepared_query = $this->db->prepare( $sql, ...$values );
		return $this->db->query( $prepared_query );
	}

	/**
	 * Generate the query
	 *
	 * @since 1.0.0
	 * @since 1.0.2 Add IN operator
	 * 
	 * @return string
	 */
	protected function generateQuery( $count = false ) {
		$sql = $count ? "SELECT count(*) FROM {$this->table_name}" : "SELECT {$this->select} FROM {$this->table_name}";

		foreach ( $this->joinArray as $join ) {
			$sql .= " INNER JOIN {$join['table']} ON {$this->table_name}.{$join['local_key']} = {$join['table']}.{$join['foreign_key']}";
		}

		if ( ! empty( $this->whereArray ) ) {
			$sql .= ' WHERE ';
			$whereExistsSql = $this->resolveWhereExists();
			$whereColumnSql = $this->resolveWhereColumn();
			$where = $this->resolveWhere();
			$placeholders = $where['placeholders'];
			$values = $where['values'];

			$whereSql = implode( ' ', $placeholders );

			$conditions = array_filter( [ $whereExistsSql, $whereColumnSql, $whereSql ] );
			$sql .= implode( ' AND ', $conditions );

			$sql = $this->db->prepare( $sql, ...$values );
		}

		if ( ! empty( $this->groupBy ) ) {
			$sql .= ' GROUP BY ';
			$sql .= implode( ', ', $this->groupBy );
		}

		if ( ! empty( $this->orderBy ) ) {
			$sql .= ' ORDER BY ';
			$orderBy = [];
			foreach ( $this->orderBy as $order ) {
				$orderBy[] = "{$order['column']} " . strtoupper( $order['order'] );
			}
			$sql .= implode( ', ', $orderBy );
		}

		if ( $this->limit ) {
			$sql .= " LIMIT {$this->limit}";
		}

		if ( $this->offset ) {
			$sql .= " OFFSET {$this->offset}";
		}

		return $sql;
	}

	public function resolveWhere() {
		$placeholders = [];
		$values = [];
		foreach ( $this->whereArray as $where ) {
			$operator = $where['operator'] ?? '=';
			$where['value'] = is_array( $where['value'] ) && empty( $where['value'] ) ? [ null ] : $where['value'];
			$value = $where['operator'] === 'IN' ? '(' . implode( ', ', array_fill( 0, count( $where['value'] ), '%s' ) ) . ')' : '%s';
			$table_name = $where['table'] ?? $this->table_name;
			$placeholder = "{$table_name}.{$where['column']} {$operator} {$value}";
			$method = $where['method'] ?? 'AND';
			$placeholders[] = empty( $placeholders ) ? $placeholder : "{$method} {$placeholder}";

			if ( is_array( $where['value'] ) ) {
				$values = array_merge( $values, $where['value'] );
			} else {
				$values[] = $where['value'];
			}

		}

		return [ 'placeholders' => $placeholders, 'values' => $values ];
	}

	public function resolveWhereColumn() {
		$placeholders = [];

		foreach ( $this->whereColumnArray as $where ) {
			$operator = $where['operator'] ?? '=';
			$column_two = $where['column_two'];
			$table_name = $this->table_name;
			$placeholder = "{$table_name}.{$where['column_one']} {$operator} {$column_two}";
			$method = $where['method'] ?? 'AND';
			$placeholders[] = empty( $placeholders ) ? $placeholder : "{$method} {$placeholder}";
		}

		$sql = implode( ' ', $placeholders );

		return $sql;
	}

	public function resolveWhereExists() {
		$placeholders = [];
		foreach ( $this->existsArray as $where ) {
			$placeholder = $where['sql'];
			$method = $where['method'] ?? 'AND';
			$placeholders[] = empty( $placeholders ) ? "EXISTS ({$placeholder})" : "{$method} EXISTS ({$placeholder})";
		}

		$sql = implode( ' ', $placeholders );

		return $sql;
	}

	/**
	 * Generate the query
	 *
	 * @since 1.0.0
	 * 
	 * @return array
	 */
	public function getWithRelations( $results ) {
		if ( empty( $this->withArray ) ) {
			return [];
		}

		$relations = [];

		$ids = wp_list_pluck( $results, $this->model->getPrimaryKey() );

		//TODO: optimize this
		foreach ( $this->withArray as $with ) {
			if ( $with['relation_type'] !== BelongsTo::class) {
				foreach ( $ids as $id ) {
					$relations[ $id ][ $with['relation'] ] = new Collection( [] );
				}
			}

			$foreginIds = wp_list_pluck( $results, $with['local_key'] );

			/** @var Model $foreginModel */
			$foreginModel = new $with['model'];
			$relationResult = $foreginModel::whereIn( $with['foreign_key'], $foreginIds )->get();

			if ( $with['relation_type'] === BelongsTo::class) {
				foreach ( $results as $item ) {
					$data = $relationResult->firstWhere( $with['foreign_key'], $item->{$with['local_key']} );
					$foreginKey = $with['foreign_key'];
					$relations[ $item->$foreginKey ][ $with['relation'] ] = $data;
				}
			} else {
				foreach ( $relationResult as $item ) {
					$foreginKey = $with['foreign_key'];
					if ( isset( $relations[ $item->$foreginKey ], $relations[ $item->$foreginKey ][ $with['relation'] ] ) ) {
						$relations[ $item->$foreginKey ][ $with['relation'] ]->push( $item );
					}
				}
			}
		}

		return $relations;
	}

	/**
	 * Get all records from the table
	 *
	 * @since 1.0.0
	 * @return Collection Database query results.
	 */
	public function get() {
		$results = $this->db->get_results( $this->generateQuery() );
		$relations = $this->getWithRelations( $results );
		$items = [];
		foreach ( $results as $result ) {
			$primaryKey = $this->model->getPrimaryKey();
			if ( isset( $result->$primaryKey ) ) {
				$relation = $relations[ $result->$primaryKey ] ?? [];
				$result = array_merge( (array) $result, $relation );
			}
			$itemModel = new $this->model( (array) $result );
			$itemModel->setWasRetrieved( true );
			$items[] = $itemModel;
		}
		return new Collection( $items );
	}

	/**
	 * Get the first record from the table
	 *
	 * @since 1.0.0
	 * @return Model|null Database query result.
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

	/**
	 * Order by
	 *
	 * @since 1.0.0
	 * 
	 * @param array $attributes
	 * @param string $order Values: asc, desc
	 * 
	 * @return QueryBuilder
	 */
	public function orderBy( $column, $order = 'asc' ) {
		$this->orderBy[] = [ 'column' => $column, 'order' => $order ];
		return $this;
	}

	/**
	 * Limit
	 *
	 * @since 1.0.0
	 * 
	 * @param int $limit
	 * 
	 * @return QueryBuilder
	 */
	public function limit( $limit ) {
		$this->limit = $limit;
		return $this;
	}

	/**
	 * Offset
	 *
	 * @since 1.0.0
	 * 
	 * @param int $offset
	 * 
	 * @return QueryBuilder
	 */
	public function offset( $offset ) {
		$this->offset = $offset;
		return $this;
	}

	/**
	 * Get the model instance
	 *
	 * @since 1.0.3
	 * 
	 * @return Model
	 */
	public function getModel() {
		return $this->model;
	}
}