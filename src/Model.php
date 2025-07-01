<?php

namespace Infixs\WordpressEloquent;

use Infixs\WordpressEloquent\Database;
use Infixs\WordpressEloquent\QueryBuilder;
use Infixs\WordpressEloquent\SoftDeletes;
use Infixs\WordpressEloquent\Relations\BelongsTo;
use Infixs\WordpressEloquent\Relations\HasMany;
use Infixs\WordpressEloquent\Relations\HasOne;

defined( 'ABSPATH' ) || exit;

abstract class Model implements \ArrayAccess {
	private static $instances = [];

	protected $primaryKey = 'id';

	protected $fillable = [];

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
	protected $table;

	protected $foregin_key;

	protected $data = [];

	private $was_retrieved = false;


	private $update_data = [];

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
			self::$instances[ $class ] = new $class( [] );
		}
		return self::$instances[ $class ];
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * 
	 * @param Database $db
	 * @param array $data
	 */
	public function __construct( $data = [] ) {
		$this->db = new Database();
		$this->table = $this->generateTableName();
		$this->foregin_key = $this->modelToForeign( get_called_class() );
		$this->data = $data;
	}

	public static function generateTableName() {
		$class = get_called_class();
		$defaultTableName = Reflection::getDefaultValue( $class, 'table' );

		if ( ! isset( $defaultTableName ) || empty( $defaultTableName ) ) {
			return Database::getTableName( self::modelToTable( get_called_class() ), self::getPrefix() );
		} else {
			return Database::getTableName( $defaultTableName, self::getPrefix() );
		}
	}

	public static function modelToTable( $model ) {
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

	public static function getForeignKeyStatic() {
		$reflect = new \ReflectionClass( get_called_class() );
		$table_name_underscored = preg_replace( '/(?<!^)([A-Z])/', '_$1', $reflect->getShortName() );
		return strtolower( $table_name_underscored ) . '_id';
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
	 * @return Model|null
	 */
	public static function find( $id ) {
		$instance = self::getInstance();
		$builder = new QueryBuilder( $instance );
		return $builder->where( $instance->primaryKey, $id )->first();
	}

	/**
	 * Get all records from the database
	 * 
	 * @since 1.0.0
	 * 
	 * @return Collection
	 */
	public static function all() {
		$instance = self::getInstance();
		$builder = new QueryBuilder( $instance );
		$result = $builder->get();
		return $result;
	}

	/**
	 * Get count for all records from the table
	 * 
	 * @since 1.0.3
	 * 
	 * @return int Database query results.
	 */
	public static function count() {
		$instance = self::getInstance();
		$builder = new QueryBuilder( $instance );
		$result = $builder->count();
		return $result;
	}

	/**
	 * Where
	 *
	 * @since 1.0.0
	 * @since 1.0.2
	 * 
	 * @param mixed $column Column name
	 * @param mixed $operator Value or Operator
	 * @param mixed $value Valor or null
	 * 
	 * @return QueryBuilder
	 */
	public static function where( $column, $operator = null, $value = null ) {
		$instance = self::getInstance();
		$builder = new QueryBuilder( $instance );
		$builder->where( $column, $operator, $value );
		return $builder;
	}

	/**
	 * Where Has
	 *
	 * @since 1.0.0
	 * 
	 * @param string $relation
	 * @param callable(QueryBuilder $query) $callback
	 * 
	 * @return QueryBuilder
	 */
	public static function whereHas( $relation, $callback ) {
		$instance = self::getInstance();
		$builder = new QueryBuilder( $instance );
		$builder->whereHas( $relation, $callback );
		return $builder;
	}

	/**
	 * Select
	 *
	 * @since 1.0.0
	 * 
	 * @param string|array $columns
	 * 
	 * @return QueryBuilder
	 */
	public static function select( $columns ) {
		$instance = self::getInstance();
		$builder = new QueryBuilder( $instance );
		$builder->select( $columns );
		return $builder;
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
	public static function whereIn( $column, $values = [] ) {
		$instance = self::getInstance();
		$builder = new QueryBuilder( $instance );
		$builder->whereIn( $column, $values );
		return $builder;
	}

	/**
	 * Get Prefix
	 * 
	 * Add Compatibility with PHP 7.4
	 * PHP >= 8 use  getDefaultValue
	 *
	 * @since 1.0.0
	 * 
	 * @return string
	 */
	public static function getPrefix() {
		$class = get_called_class();
		return Reflection::getDefaultValue( $class, 'prefix', '' );
	}

	/**
	 * Create a record
	 *
	 * @param array $columns_values
	 * @return Model|false
	 */
	public static function create( $columns_values ) {
		$tableName = self::generateTableName();
		$inserted = Database::insert( $tableName, $columns_values );
		if ( $inserted ) {
			$class = get_called_class();
			$newClass = new $class(
				array_merge( $columns_values, [ 'id' => $inserted ] )
			);

			$newClass->setWasRetrieved( true );

			return $newClass;
		}
		return false;
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
		return $instance->db->update( $instance->table, $columns_values, $where_values );
	}

	/**
	 * Save the model to the database.
	 *
	 * @return false|int
	 */
	public function save() {
		if ( $this->wasRetrieved() ) {
			$data = $this->update_data;
			$id = $this->data[ $this->primaryKey ];
			$queryBuilder = new QueryBuilder( $this );
			$result = $queryBuilder->where( $this->primaryKey, $id )->update( $data );
			if ( $result ) {
				$this->data = array_merge( $this->data, $this->update_data );
			}
			return $result;
		} else {
			$result = $this->db->insert( $this->table, $this->data );
			if ( $result ) {
				$this->data[ $this->primaryKey ] = $result;
			}
			return $result;
		}
	}

	/**
	 * Delete the model from the database.
	 *
	 * @return bool|int
	 */
	public function delete() {
		if ( $this->wasRetrieved() ) {
			$data = $this->data;
			$id = $data[ $this->primaryKey ];
			$queryBuilder = new QueryBuilder( $this );
			return $queryBuilder->where( $this->primaryKey, $id )->delete();
		}
		return false;
	}

	public function relationLoaded( $relation ) {
		return isset( $this->data[ $relation ] );
	}

	public function setAttribute( $key, $value ) {
		$this->data[ $key ] = $value;
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
		return $instance->db->insert_multiple( $instance->table, $columns_values );
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
		//TODO: See has Many fix it
		$foreignKey = $this->modelToForeign( $related_class );
		return new HasOne( $this, $related_class, "{$foreignKey}_id", "id" );
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
		//TODO: See has Many fix it
		$foreignKey = $this->modelToForeign( $related_class );
		return new BelongsTo( $this, $related_class, "{$foreignKey}_id", "id" );
	}

	/**
	 * HasMany to relationship
	 *
	 * @since 1.0.2
	 * @param string $related_class
	 * 
	 * @return HasMany
	 */
	public function hasMany( $related_class ): HasMany {
		//$foreignKey = $this->modelToForeign( $related_class );
		return new HasMany( $this, $related_class, "{$this->foregin_key}_id", "id" );
	}


	/**
	 * Get table name
	 *
	 * @param int $id
	 * @return string
	 */
	public function getTableName() {
		return $this->table;
	}

	/**
	 * Get primary key
	 *
	 * @since 1.0.2
	 * 
	 * @return string
	 */
	public function getPrimaryKey() {
		return $this->primaryKey;
	}


	/**
	 * Get table name
	 *
	 * @param int $id
	 * @return string
	 */
	static public function getTable() {
		$instance = self::getInstance();
		return $instance->table;
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


	public function trashed() {
		return in_array( SoftDeletes::class, class_uses( $this ) );
	}

	public static function isTrashed() {
		return in_array( SoftDeletes::class, class_uses( get_called_class() ) );
	}

	public function toArray() {
		return $this->data;
	}

	public function __get( $name ) {
		if ( array_key_exists( $name, $this->data ) ) {
			return $this->data[ $name ];
		}

		return $this->$name;
	}

	/**
	 * Dynamically set an attribute on the model.
	 *
	 * @param  string  $name
	 * @param  mixed  $value
	 * 
	 * @return void
	 */
	public function __set( $name, $value ) {
		if ( $this->wasRetrieved() ) {
			$this->update_data[ $name ] = $value;
		} else {
			$this->data[ $name ] = $value;
		}
	}

	public function __isset( $name ) {
		return isset( $this->data[ $name ] );
	}

	/**
	 * Determine if an item exists at an offset.
	 *
	 * @param  mixed  $key
	 * @return bool
	 */
	public function offsetExists( $key ): bool {
		return isset( $this->data[ $key ] );
	}

	/**
	 * Get an item at a given offset.
	 *
	 * @param  mixed  $key
	 * @return mixed
	 */
	#[\ReturnTypeWillChange ]
	public function offsetGet( $key ) {
		return $this->data[ $key ];
	}

	/**
	 * Set the item at a given offset.
	 *
	 * @param  mixed|null  $key
	 * @param  mixed  $value
	 * @return void
	 */
	public function offsetSet( $key, $value ): void {
		if ( $this->wasRetrieved() ) {
			$this->update_data[ $key ] = $value;
		} else {
			if ( $key === null ) {
				$this->data[] = $value;
			} else {
				$this->data[ $key ] = $value;
			}
		}
	}

	/**
	 * Unset the item at a given offset.
	 *
	 * @param  mixed  $key
	 * @return void
	 */
	public function offsetUnset( $key ): void {
		unset( $this->data[ $key ] );
	}

	public function wasRetrieved() {
		return $this->was_retrieved;
	}

	public function setWasRetrieved( $was_retrieved ) {
		$this->was_retrieved = $was_retrieved;
	}
}