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
		if ( ! isset( $this->table ) || empty( $this->table ) ) {
			$this->table = $this->db->getTableName( self::modelToTable( get_called_class() ), $this->getPrefix() );
		} else {
			$this->table = $this->db->getTableName( $this->table, $this->getPrefix() );
		}
		$this->foregin_key = $this->modelToForeign( get_called_class() );
		$this->data = $data;
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
	 * @return int|bool	The ID of the record or false on error.
	 */
	public static function create( $columns_values ) {
		return Database::insert( Database::getTableName( self::modelToTable( get_called_class() ), self::getPrefix() ), $columns_values );
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
		return $this->db->insert( $this->table, $this->data );
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

	public function __get( $name ) {
		if ( array_key_exists( $name, $this->data ) ) {
			return $this->data[ $name ];
		}

		return $this->$name;
	}

	/**
	 * Determine if an item exists at an offset.
	 *
	 * @param  mixed  $key
	 * @return bool
	 */
	public function offsetExists( $key ) {
		return isset( $this->data[ $key ] );
	}

	/**
	 * Get an item at a given offset.
	 *
	 * @param  mixed  $key
	 * @return mixed
	 */
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
	public function offsetSet( $key, $value ) {
		if ( is_null( $key ) ) {
			$this->data[] = $value;
		} else {
			$this->data[ $key ] = $value;
		}
	}

	/**
	 * Unset the item at a given offset.
	 *
	 * @param  mixed  $key
	 * @return void
	 */
	public function offsetUnset( $key ) {
		unset( $this->data[ $key ] );
	}
}