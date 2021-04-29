<?php
/**
 * Container for dispatchers.
 *
 * @package query-monitor
 */

class QM_Dispatchers implements IteratorAggregate {

	/** @var QM_Dispatcher[] */
	private $items = array();

	public function getIterator() {
		return new ArrayIterator( $this->items );
	}

	public static function add( QM_Dispatcher $dispatcher ) {
		$dispatchers                           = self::init();
		$dispatchers->items[ $dispatcher->id ] = $dispatcher;
	}

	public static function get( $id ) {
		$dispatchers = self::init();
		if ( isset( $dispatchers->items[ $id ] ) ) {
			return $dispatchers->items[ $id ];
		}
		return false;
	}

	public static function cease() {
		$dispatchers = self::init();

		/** @var QM_Dispatcher $dispatcher */
		foreach ( $dispatchers as $dispatcher ) {
			$dispatcher->cease();
		}
	}

	public static function init() {
		static $instance;

		if ( ! $instance ) {
			$instance = new QM_Dispatchers();
		}

		return $instance;

	}

}
