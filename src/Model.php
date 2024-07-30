<?php

namespace Infixs\WordpressEloquent;

use Infixs\WordpressEloquent\Database;
use Infixs\WordpressEloquent\QueryBuilder;
use Infixs\WordpressEloquent\Relations\HasOne;

defined( 'ABSPATH' ) || exit;

class Model {
	private static $instances = [];

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
		$this->table_name = $this->db->get_table_name( $this->modelToTable( get_called_class() ) );
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


	public static function find( $id ) {
		$instance = self::getInstance();
	}

	/**
	 * Where
	 *
	 * @param string|array $column Name of the column or array of columns.
	 * @param string|null $value Value of the column.
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
	 * Create
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
	 * @param array $columns_values
	 * @param array $where_values
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
	 * @param string $model_class
	 * @return HasOne
	 */
	public function hasOne( string $model_class ): HasOne {
		return new HasOne();
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