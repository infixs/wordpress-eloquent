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