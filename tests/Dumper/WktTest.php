<?php

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Tests\Dumper;

use Geocoder\Dumper\Wkt;
use Geocoder\Tests\TestCase;

/**
 * @author Jan Sorgalla <jsorgalla@googlemail.com>
 * @author William Durand <william.durand1@gmail.com>
 */
class WktTest extends TestCase
{
    private $dumper;

    public function setUp()
    {
        $this->dumper = new Wkt();
    }

    public function testDump()
    {
        $address = $this->createEmptyAddress();
        $expected = sprintf('POINT(%F %F)', 0, 0);
        $result = $this->dumper->dump($address);

        $this->assertTrue(is_string($result));
        $this->assertEquals($expected, $result);
    }

    public function testDumpWithData()
    {
        $address = $this->createAddress([
            'latitude' => 48.8631507,
            'longitude' => 2.3889114,
        ]);
        $expected = sprintf('POINT(%F %F)', 2.3889114, 48.8631507);

        $result = $this->dumper->dump($address);

        $this->assertTrue(is_string($result));
        $this->assertEquals($expected, $result);
    }
}
