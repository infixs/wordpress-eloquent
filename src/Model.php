<?php

namespace Infixs\WordpressEloquent;

use Infixs\WordpressEloquent\Database;
use Infixs\WordpressEloquent\QueryBuilder;
use Infixs\WordpressEloquent\Relations\BelongsTo;
use Infixs\WordpressEloquent\Relations\HasOne;

defined( 'ABSPATH' ) || exit;

abstract class Model {
	private static $instances = [];

	/**
	 * Prefix
	 * 
	 * @var string
	 */
	protected $prefix = '';

	/**
	 * Table name
	 * 
	 * @var string
	 */
	protected $table_name;

	protected $foregin_key;

	/**
	 * Database instance
	 * 
	 * @var Database
	 */
	protected $db;
	/**
	 * Database instance
	 * 
	 * @return Model
	 */
	protected static function getInstance() {
		$class = get_called_class();
		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new $class( new Database() );
		}
		return self::$instances[ $class ];
	}

	public function __construct( Database $db ) {
		$this->db = $db;
		$this->table_name = $this->db->getTableName( $this->modelToTable( get_called_class() ) );
		$this->foregin_key = $this->modelToForeign( get_called_class() );
	}

	private function modelToTable( $model ) {
		$reflect = new \ReflectionClass( $model );
		$table_name_underscored = preg_replace( '/(?<!^)([A-Z])/', '_$1', $reflect->getShortName() );
		return strtolower( $table_name_underscored ) . 's';
	}


	private function modelToForeign( $model ) {
		$reflect = new \ReflectionClass( $model );
		$table_name_underscored = preg_replace( '/(?<!^)([A-Z])/', '_$1', $reflect->getShortName() );
		return strtolower( $table_name_underscored );
	}

	public function getForeignKey() {
		return $this->foregin_key;
	}

	/**
	 * Query
	 *
	 * @since 1.0.0
	 * 
	 * @return QueryBuilder
	 */
	public static function query() {
		$instance = self::getInstance();
		$queryBuilder = new QueryBuilder( $instance );
		return $queryBuilder;
	}

	/**
	 * Add relations to a QueryBuilder
	 *
	 * @param string $relation_name
	 * @return QueryBuilder
	 */
	public static function with( string $relation_name ) {
		$instance = self::getInstance();
		$queryBuilder = new QueryBuilder( $instance );
		$queryBuilder->with( $relation_name );
		return $queryBuilder;
	}


	/**
	 * Find a record by id
	 * 
	 * @param int $id
	 * 
	 * @return object|array
	 */
	public static function find( $id ) {
		throw new \Exception( 'Method not implemented' );
	}

	/**
	 * Get all records from the database
	 * 
	 * @since 1.0.0
	 * 
	 * @return object|array
	 */
	public static function all() {
		$instance = self::getInstance();
		$builder = new QueryBuilder( $instance );
		$result = $builder->get();
		return $result;
	}

	/**
	 * Where
	 *
	 * @since 1.0.0
	 * 
	 * @param string|array $column Name of the column or array of columns.
	 * @param string|null $value Value of the column.
	 * 
	 * @return QueryBuilder
	 */
	public static function where( $column, $value = null ) {
		$instance = self::getInstance();
		$builder = new QueryBuilder( $instance );

		if ( is_array( $column ) ) {
			foreach ( $column as $col => $val ) {
				$builder->where( $col, $val );
			}
		} else {
			$builder->where( $column, $value );
		}

		return $builder;
	}

	/**
	 * Create a record
	 *
	 * @param array $columns_values
	 * @return int|bool	
	 */
	public static function create( $columns_values ) {
		$instance = self::getInstance();
		return $instance->db->insert( $instance->table_name, $columns_values );
	}


	/**
	 * Update
	 *
	 * @since 1.0.0
	 * 
	 * @param array $columns_values
	 * @param array $where_values
	 * 
	 * @return int|bool	
	 */
	public static function update( array $columns_values, array $where_values ) {
		$instance = self::getInstance();
		return $instance->db->update( $instance->table_name, $columns_values, $where_values );
	}

	/**
	 * Create Many
	 *
	 * @since 1.0.0
	 * @param array $columns_values
	 * @return int|bool	
	 */
	public static function createMany( $columns_values ) {
		$instance = self::getInstance();
		return $instance->db->insert_multiple( $instance->table_name, $columns_values );
	}

	/**
	 * One to one relationship
	 *
	 * @since 1.0.0
	 * 
	 * @param string $related_class
	 * 
	 * @return HasOne
	 */
	public function hasOne( string $related_class ): HasOne {
		$foreignKey = $this->modelToForeign( $related_class );
		return new HasOne( $related_class, "{$foreignKey}_id", "id" );
	}

	/**
	 * Belongs to relationship
	 *
	 * @since 1.0.0
	 * @param string $related_class
	 * 
	 * @return BelongsTo
	 */
	public function belongsTo( $related_class ): BelongsTo {
		$foreignKey = $this->modelToForeign( $related_class );
		return new BelongsTo( $related_class, "{$foreignKey}_id", "id" );
	}

	/**
	 * Get table name
	 *
	 * @param int $id
	 * @return string
	 */
	public function getTableName() {
		return $this->table_name;
	}


	/**
	 * Get database
	 *
	 * @param int $id
	 * @return Database
	 */
	public function getDatabase() {
		return $this->db;
	}

}