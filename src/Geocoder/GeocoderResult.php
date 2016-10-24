<?php

namespace Geocoder;

/**
 * This is the interface that is always return from a Geocoder.
 *
 * @author William Durand <william.durand1@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface GeocoderResult extends \IteratorAggregate, \Countable
{
    /**
     * {@inheritDoc}
     */
    public function getIterator();

    /**
     * {@inheritDoc}
     */
    public function count();

    /**
     * @return Location
     */
    public function first();

    /**
     * @return Location[]
     */
    public function slice($offset, $length = null);

    /**
     * @return bool
     */
    public function has($index);

    /**
     * @return Location
     * @throws \OutOfBoundsException
     */
    public function get($index);

    /**
     * @return Location[]
     */
    public function all();
}