<?php

declare(strict_types=1);

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider\Nominatim;

use Geocoder\Collection;
use Geocoder\Exception\InvalidArgument;
use Geocoder\Exception\InvalidServerResponse;
use Geocoder\Exception\UnsupportedOperation;
use Geocoder\Location;
use Geocoder\Model\AddressBuilder;
use Geocoder\Model\AddressCollection;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\LookupQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\Http\Provider\AbstractHttpProvider;
use Geocoder\Provider\Provider;
use Geocoder\Provider\Nominatim\Model\NominatimAddress;
use Http\Client\HttpClient;

/**
 * @author Niklas Närhinen <niklas@narhinen.net>
 * @author Jonathan Beliën <jbe@geo6.be>
 */
final class Nominatim extends AbstractHttpProvider implements Provider
{
    /**
     * @var string
     */
    private $rootUrl;

    /**
     * @var string
     */
    private $userAgent;

    /**
     * @var string
     */
    private $referer;

    /**
     * @param HttpClient $client    an HTTP client
     * @param string     $userAgent Value of the User-Agent header
     * @param string     $referer   Value of the Referer header
     *
     * @return Nominatim
     */
    public static function withOpenStreetMapServer(HttpClient $client, string $userAgent, string $referer = ''): self
    {
        return new self($client, 'https://nominatim.openstreetmap.org', $userAgent, $referer);
    }

    /**
     * @param HttpClient $client    an HTTP client
     * @param string     $rootUrl   Root URL of the nominatim server
     * @param string     $userAgent Value of the User-Agent header
     * @param string     $referer   Value of the Referer header
     */
    public function __construct(HttpClient $client, $rootUrl, string $userAgent, string $referer = '')
    {
        parent::__construct($client);

        $this->rootUrl = rtrim($rootUrl, '/');
        $this->userAgent = $userAgent;
        $this->referer = $referer;

        if (empty($this->userAgent)) {
            throw new InvalidArgument('The User-Agent must be set to use the Nominatim provider.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        $address = $query->getText();

        // This API doesn't handle IPs
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedOperation('The Nominatim provider does not support IP addresses.');
        }

        $url = $this->rootUrl
            .'/search?'
            .http_build_query([
                'format' => 'jsonv2',
                'q' => $address,
                'addressdetails' => 1,
                'limit' => $query->getLimit(),
            ]);

        $countrycodes = $query->getData('countrycodes');
        if (!is_null($countrycodes)) {
            if (is_array($countrycodes)) {
                $countrycodes = array_map('strtolower', $countrycodes);

                $url .= '&'.http_build_query([
                    'countrycodes' => implode(',', $countrycodes),
                ]);
            } else {
                $url .= '&'.http_build_query([
                    'countrycodes' => strtolower($countrycodes),
                ]);
            }
        }

        $viewbox = $query->getData('viewbox');
        if (!is_null($viewbox) && is_array($viewbox) && 4 === count($viewbox)) {
            $url .= '&'.http_build_query([
                'viewbox' => implode(',', $viewbox),
            ]);

            $bounded = $query->getData('bounded');
            if (!is_null($bounded) && true === $bounded) {
                $url .= '&'.http_build_query([
                    'bounded' => 1,
                ]);
            }
        }

        $content = $this->executeQuery($url, $query->getLocale());

        $json = json_decode($content);
        if (is_null($json) || !is_array($json)) {
            throw InvalidServerResponse::create($url);
        }

        if (empty($json)) {
            return new AddressCollection([]);
        }

        $results = [];
        foreach ($json as $place) {
            $results[] = $this->jsonResultToLocation($place, false);
        }

        return new AddressCollection($results);
    }

    /**
     * {@inheritdoc}
     */
    public function reverseQuery(ReverseQuery $query): Collection
    {
        $coordinates = $query->getCoordinates();
        $longitude = $coordinates->getLongitude();
        $latitude = $coordinates->getLatitude();

        $url = $this->rootUrl
            .'/reverse?'
            .http_build_query([
                'format' => 'jsonv2',
                'lat' => $latitude,
                'lon' => $longitude,
                'addressdetails' => 1,
                'zoom' => $query->getData('zoom', 18),
            ]);

        $content = $this->executeQuery($url, $query->getLocale());

        $json = json_decode($content);
        if (is_null($json) || isset($json->error)) {
            return new AddressCollection([]);
        }

        if (empty($json)) {
            return new AddressCollection([]);
        }

        return new AddressCollection([$this->jsonResultToLocation($json, true)]);
    }

    /**
     * {@inheritdoc}
     */
    public function lookupQuery(LookupQuery $query): Collection
    {
        $url = $this->rootUrl
            .'/lookup?'
            .http_build_query([
                'format' => 'json', // At the moment, open street map does not support jsonv2
                'osm_ids' => $query->getId(),
                'addressdetails' => 1,
            ]);

        $content = $this->executeQuery($url, $query->getLocale());

        $json = json_decode($content);
        if (is_null($json) || !is_array($json)) {
            throw InvalidServerResponse::create($url);
        }

        if (empty($json)) {
            return new AddressCollection([]);
        }

        $results = [];
        foreach ($json as $place) {
            // Lookup responds with some missing attributes, we need to init them
            $place->boundingbox = [null, null, null, null];
            $place->category = $place->class;

            $results[] = $this->jsonResultToLocation($place, false);
        }

        return new AddressCollection($results);
    }

    /**
     * @param \stdClass $place
     * @param bool      $reverse
     *
     * @return Location
     */
    private function jsonResultToLocation(\stdClass $place, bool $reverse): Location
    {
        $builder = new AddressBuilder($this->getName());

        foreach (['state', 'county'] as $i => $tagName) {
            if (isset($place->address->{$tagName})) {
                $builder->addAdminLevel($i + 1, $place->address->{$tagName}, '');
            }
        }

        // get the first postal-code when there are many
        if (isset($place->address->postcode)) {
            $postalCode = $place->address->postcode;
            if (!empty($postalCode)) {
                $postalCode = current(explode(';', $postalCode));
            }
            $builder->setPostalCode($postalCode);
        }

        $localityFields = ['city', 'town', 'village', 'hamlet'];
        foreach ($localityFields as $localityField) {
            if (isset($place->address->{$localityField})) {
                $localityFieldContent = $place->address->{$localityField};

                if (!empty($localityFieldContent)) {
                    $builder->setLocality($localityFieldContent);

                    break;
                }
            }
        }

        $builder->setStreetName($place->address->road ?? $place->address->pedestrian ?? null);
        $builder->setStreetNumber($place->address->house_number ?? null);
        $builder->setSubLocality($place->address->suburb ?? null);
        $builder->setCountry($place->address->country);
        $builder->setCountryCode(strtoupper($place->address->country_code));

        $builder->setCoordinates(floatval($place->lat), floatval($place->lon));

        $builder->setBounds($place->boundingbox[0], $place->boundingbox[2], $place->boundingbox[1], $place->boundingbox[3]);

        $location = $builder->build(NominatimAddress::class);
        $location = $location->withAttribution($place->licence);
        $location = $location->withOSMId(intval($place->osm_id));
        $location = $location->withOSMType($place->osm_type);
        $location = $location->withDisplayName($place->display_name);

        if (false === $reverse) {
            $location = $location->withCategory($place->category);
            $location = $location->withType($place->type);
        }

        return $location;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'nominatim';
    }

    /**
     * @param string      $url
     * @param string|null $locale
     *
     * @return string
     */
    private function executeQuery(string $url, string $locale = null): string
    {
        if (null !== $locale) {
            $url .= '&'.http_build_query([
                'accept-language' => $locale,
            ]);
        }

        $request = $this->getRequest($url);
        $request = $request->withHeader('User-Agent', $this->userAgent);

        if (!empty($this->referer)) {
            $request = $request->withHeader('Referer', $this->referer);
        }

        return $this->getParsedResponse($request);
    }
}
