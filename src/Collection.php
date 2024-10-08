<?php

namespace Infixs\WordpressEloquent;

defined( 'ABSPATH' ) || exit;

/**
 * Collection class
 * 
 * This class is responsible for handling collections
 * 
 * @since 1.0.0
 */
class Collection implements \ArrayAccess {

	public $items = [];

	/**
	 * Collection constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct( $items = [] ) {
		$this->items = $items;
	}

	/**
	 * Count the number of items in the collection.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->items );
	}

	/**
	 * Push one or more items onto the end of the collection.
	 *
	 * @param  mixed  ...$values
	 * @return $this
	 */
	public function push( ...$values ) {
		foreach ( $values as $value ) {
			$this->items[] = $value;
		}

		return $this;
	}

	/**
	 * Determine if an item exists at an offset.
	 *
	 * @param  mixed  $key
	 * @return bool
	 */
	public function offsetExists( $key ): bool {
		return isset( $this->items[ $key ] );
	}

	/**
	 * Get an item at a given offset.
	 *
	 * @param  mixed  $key
	 * @return mixed
	 */
	public function offsetGet( $key ): mixed {
		return $this->items[ $key ];
	}

	/**
	 * Set the item at a given offset.
	 *
	 * @param  mixed|null  $key
	 * @param  mixed  $value
	 * @return void
	 */
	public function offsetSet( $key, $value ): void {
		if ( is_null( $key ) ) {
			$this->items[] = $value;
		} else {
			$this->items[ $key ] = $value;
		}
	}

	/**
	 * Unset the item at a given offset.
	 *
	 * @param  mixed  $key
	 * @return void
	 */
	public function offsetUnset( $key ): void {
		unset( $this->items[ $key ] );
	}

}