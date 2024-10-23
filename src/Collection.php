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
class Collection implements \ArrayAccess, \IteratorAggregate {

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
	public function count() {
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

	public function toArray() {
		return $this->items;
	}

	public function pluck( $value, $key = null ) {
		$results = [];

		foreach ( $this->items as $item ) {
			$itemValue = $this->data_get( $item, $value );

			if ( is_null( $key ) ) {
				$results[] = $itemValue;
			} else {
				$itemKey = $this->data_get( $item, $key );

				$results[ $itemKey ] = $itemValue;
			}
		}

		return new self( $results );
	}


	public function data_get( $target, $key, $default = null ) {
		if ( is_null( $key ) ) {
			return $target;
		}

		foreach ( explode( '.', $key ) as $segment ) {
			if ( is_array( $target ) ) {
				if ( ! array_key_exists( $segment, $target ) ) {
					return $default;
				}

				$target = $target[ $segment ];
			} elseif ( $target instanceof \ArrayAccess ) {
				if ( ! isset( $target[ $segment ] ) ) {
					return $default;
				}

				$target = $target[ $segment ];
			} elseif ( is_object( $target ) ) {
				if ( ! isset( $target->{$segment} ) ) {
					return $default;
				}

				$target = $target->{$segment};
			} else {
				return $default;
			}
		}

		return $target;
	}


	/**
	 * Determine if an item exists at an offset.
	 *
	 * @param  mixed  $key
	 * @return bool
	 */
	public function offsetExists( $key ) {
		return isset( $this->items[ $key ] );
	}

	/**
	 * Get an item at a given offset.
	 *
	 * @param  mixed  $key
	 * @return mixed
	 */
	public function offsetGet( $key ) {
		return $this->items[ $key ];
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
	public function offsetUnset( $key ) {
		unset( $this->items[ $key ] );
	}


	/**
	 * Get an iterator for the items.
	 *
	 * @return \ArrayIterator
	 */
	public function getIterator() {
		return new \ArrayIterator( $this->items );
	}
}