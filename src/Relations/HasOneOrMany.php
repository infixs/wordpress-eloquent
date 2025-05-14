<?php
namespace Infixs\WordpressEloquent\Relations;

use Infixs\WordpressEloquent\Model;
use Infixs\WordpressEloquent\Relation;

defined( 'ABSPATH' ) || exit;

abstract class HasOneOrMany extends Relation {

	/**
	 * Attach a model instance to the parent model.
	 *
	 * @param  Model  $model
	 * @return Model|false
	 */
	public function save( Model $model ) {

		$this->setForeignAttributesForCreate( $model );

		return $model->save() ? $model : false;
	}

	/**
	 * Create a record
	 *
	 * @param array $columns_values
	 * @return Model|false
	 */
	public function create( $attributes ) {
		$attributes[ $this->getForeignKey()] = $this->parent->{$this->getLocalKey()};
		return $this->relatedClass::create(
			$attributes
		);
	}

	/**
	 * Set the foreign ID for creating a related model.
	 *
	 * @param  Model  $model
	 * @return void
	 */
	protected function setForeignAttributesForCreate( Model $model ) {
		$localKey = $this->getLocalKey();
		$model->setAttribute( $this->getForeignKey(), $this->parent->$localKey );
	}

}