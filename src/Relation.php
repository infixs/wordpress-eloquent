<?php

namespace Infixs\WordpressEloquent;

defined( 'ABSPATH' ) || exit;

abstract class Relation {

	/**
	 * Summary of model
	 * @var 
	 */
	protected $relatedClass;
	protected $foreignKey;
	protected $localKey;
	protected $relation;

	protected $parent;

	/**
	 * Relation constructor.
	 * 
	 * @param Model $parent
	 * @param string $relatedClass
	 * @param $foreignKey
	 * @param $localKey
	 */
	public function __construct( Model $parent, $relatedClass, $foreignKey, $localKey ) {
		$this->relatedClass = $relatedClass;
		$this->foreignKey = $foreignKey;
		$this->localKey = $localKey;
		$this->parent = $parent;
		//$this->relation = $relation;
	}

	public function getForeignKey() {
		return $this->foreignKey;
	}

	public function getLocalKey() {
		return $this->localKey;
	}

	public function getRelatedClass() {
		return $this->relatedClass;
	}

}