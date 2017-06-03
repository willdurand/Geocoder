<?php

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider\Geoip\Tests;

use Geocoder\IntegrationTest\ProviderIntegrationTest;
use Geocoder\Provider\Geoip\Geoip;
use Http\Client\HttpClient;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class IntegrationTest extends ProviderIntegrationTest
{
    protected $skippedTests = [
        'testGeocodeQuery' => 'No street addresses supported',
        'testGeocodeQueryWithNoResults' => 'No street addresses supported',
        'testReverseQuery' => 'No reverse supported',
        'testEmptyReverseQuery' => 'No reverse supported',
        'testServer500Error' => 'The provider does not support street addresses.',
        'testServer500ErrorReverse' => 'The provider does not support street addresses.',
        'testServer400Error' => 'The provider does not support street addresses.',
        'testServer400ErrorReverse' => 'The provider does not support street addresses.',
        'testServerEmptyResponse' => 'The provider does not support street addresses.',
        'testServerEmptyResponseReverse' => 'The provider does not support street addresses.',
        'testQuotaExceededResponse' => 'The provider does not support street addresses.',
        'testQuotaExceededResponseReverse' => 'The provider does not support street addresses.',
        'testInvalidCredentialsResponse' => 'The provider does not support street addresses.',
        'testInvalidCredentialsResponseReverse' => 'The provider does not support street addresses.',
    ];

    protected function setUp()
    {
        if (!function_exists('geoip_record_by_name')) {
            $this->markTestSkipped('You have to install GeoIP.');
        }
    }

    protected function createProvider(HttpClient $httpClient)
    {
        return new Geoip();
    }

    protected function getCacheDir()
    {
        return __DIR__.'/.cached_responses';
    }

    protected function getApiKey()
    {
        return null;
    }
}