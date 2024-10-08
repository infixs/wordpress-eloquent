<?php

namespace Infixs\WordpressEloquent;

use Infixs\WordpressEloquent\Database;
use Infixs\WordpressEloquent\QueryBuilder;
use Infixs\WordpressEloquent\SoftDeletes;
use Infixs\WordpressEloquent\Relations\BelongsTo;
use Infixs\WordpressEloquent\Relations\HasMany;
use Infixs\WordpressEloquent\Relations\HasOne;

defined( 'ABSPATH' ) || exit;

abstract class Model {
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
	protected $table_name;

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
		$this->table_name = $this->db->getTableName( self::modelToTable( get_called_class() ), $this->getPrefix() );
		$this->foregin_key = $this->modelToForeign( get_called_class() );
		$this->data = $data;
	}

	public static function modelToTable( $model ) {
		$reflect = new \ReflectionClass( $model );
		$table_name_underscored = preg_replace( '/(?<!^)([A-Z])/', '_$1', $reflect->getShortName() );
		return strtolower( $table_name_underscored ) . 's';
	}


	public function __get( $name ) {
		if ( array_key_exists( $name, $this->data ) ) {
			return $this->data[ $name ];
		}

		return $this->$name;
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

	public static function getPrefix() {
		$class = get_called_class();
		$reflect = new \ReflectionClass( $class );
		$prefix = $reflect->getProperty( 'prefix' );
		return $prefix->getDefaultValue();
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
		return $instance->db->update( $instance->table_name, $columns_values, $where_values );
	}

	/**
	 * Save the model to the database.
	 *
	 * @return false|int
	 */
	public function save() {
		return $this->db->insert( $this->table_name, $this->data );
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
		return $this->table_name;
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
		return $instance->table_name;
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

}