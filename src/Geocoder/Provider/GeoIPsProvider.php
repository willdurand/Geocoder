<?php

/**
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider;

use Geocoder\Exception\InvalidCredentialsException;
use Geocoder\Exception\NoResultException;
use Geocoder\Exception\UnsupportedException;
use Geocoder\HttpAdapter\HttpAdapterInterface;

/**
 * @author Andrea Cristaudo <andrea.cristaudo@gmail.com>
 */
class GeoIPsProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * @var string
     */
    const GEOCODE_ENDPOINT_URL = 'http://api.geoips.com/ip/%s/key/%s/output/json/timezone/true/';

    /**
     * @var string
     */
    private $apiKey = null;

    /**
     * @param \Geocoder\HttpAdapter\HttpAdapterInterface $adapter
     * @param string                                     $apiKey
     */
    public function __construct(HttpAdapterInterface $adapter, $apiKey)
    {
        parent::__construct($adapter, null);

        $this->apiKey = $apiKey;
    }

    /**
     * {@inheritDoc}
     */
    public function getGeocodedData($address)
    {
        if (null === $this->apiKey) {
            throw new InvalidCredentialsException('No API Key provided.');
        }

        if (!filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedException('The GeoIPsProvider does not support street addresses.');
        }

        // This API does not support IPv6
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new UnsupportedException('The GeoIPsProvider does not support IPv6 addresses.');
        }

        if ($address === '127.0.0.1') {
            return $this->getLocalhostDefaults();
        }

        $query = sprintf(self::GEOCODE_ENDPOINT_URL, $address, $this->apiKey);

        return $this->executeQuery($query);
    }

    /**
     * {@inheritDoc}
     */
    public function getReversedData(array $coordinates)
    {
        throw new UnsupportedException('The GeoIPsProvider is not able to do reverse geocoding.');
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'geoips';
    }

    /**
     * @param  string $query
     * @return array
     */
    protected function executeQuery($query)
    {
        $content = $this->getAdapter()->getContent($query);

        if (null === $content) {
            throw new NoResultException(sprintf('Could not execute query %s', $query));
        }

        if ('' === $content) {
            throw new NoResultException(sprintf('Could not execute query %s', $query));
        }

        $json = json_decode($content, true);
        $response = $json['response'];

        if (!is_array($response) or !count($response)) {
            throw new NoResultException(sprintf('Could not execute query %s', $query));
        }

        if (!array_key_exists('status', $response)) {
            throw new NoResultException(sprintf('Could not execute query %s', $query));
        } elseif('Bad Request' == $response['status']) {
            throw new NoResultException(sprintf('Could not execute query %s', $query));
        } elseif('Forbidden' == $response['status']) {
            if ('Limit Exceeded' == $response['message']) {
                throw new NoResultException(sprintf('Could not execute query %s', $query));
            }

            throw new InvalidCredentialsException('API Key provided is not valid.');
        }

        return array_merge($this->getDefaults(), array(
            'country'     => '' === $response['country_name'] ? null : $response['country_name'],
            'countryCode' => '' === $response['country_code'] ? null : $response['country_code'],
            'region'      => '' === $response['region_name']  ? null : $response['region_name'],
            'regionCode'  => '' === $response['region_code']  ? null : $response['region_code'],
            'county'      => '' === $response['county_name']  ? null : $response['county_name'],
            'city'        => '' === $response['city_name']    ? null : $response['city_name'],
            'latitude'    => '' === $response['latitude']     ? null : $response['latitude'],
            'longitude'   => '' === $response['longitude']    ? null : $response['longitude'],
            'timezone'    => '' === $response['timezone']     ? null : $response['timezone'],
        ));
    }
}