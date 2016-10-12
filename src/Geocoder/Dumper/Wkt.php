<?php

/**
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Dumper;

use Geocoder\Model\Address;
use Geocoder\Position;

/**
 * @author Jan Sorgalla <jsorgalla@googlemail.com>
 */
class Wkt implements Dumper
{
    /**
     * {@inheritDoc}
     */
    public function dump(Position $position)
    {
        return sprintf('POINT(%F %F)', $position->getLongitude(), $position->getLatitude());
    }
}
