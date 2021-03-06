<?php

namespace li3_location\core;


use lithium\util\Set;
use lithium\net\http\Service;

/**
 * Location allows retrieval of geo-location data via YAHOO API
 *
 */
class Location extends \lithium\core\StaticObject {

	/**
	 * host of remote endpoint
	 *
	 * @see http://developer.yahoo.com/geo/placefinder/
	 * @var string
	 */
	public static $host = 'where.yahooapis.com';

	/**
	 * app_id of your yahoo developer program
	 *
	 * please signup here:
	 * http://developer.yahoo.com/dashboard/createKey.html
	 *
	 * @var string
	 */
	public static $app_id = '[yourappidhere]';

	/**
	 * Controls how long to wait for remote endpoint
	 *
	 * @var integer
	 */
	public static $timeout = 10;

	/**
	 * Finds lat/lon for locations
	 *
	 * @see li3_location\core\Location::geocode()
	 * @param string|array $name a string or an array of strings with
	 *        location names
	 * @param array $options additional options, currently none
	 * @return array|boolean lat/lon of given $name, an array of these or
	 *         false on failure
	 */
	public static function find($name, array $options = array()) {
		$defaults = array(
			'all' => false,
			'params' => array(
				'locale' => 'de_DE',
				'flags' => 'JXTR',
				'gflags' => 'L',
				'appid' => static::$app_id
			)
		);
		$options = Set::merge($defaults, $options);
		return static::geocode($name, $options);
	}

	/**
	 * Get information about a location, given by lat+lon
	 *
	 * @see li3_location\core\Location::geocode()
	 * @param string $lat latitude coordinates
	 * @param string $lon longtitude coordinates
	 * @param array $options additional options, currently none
	 * @return array|boolean lat/lon of given $name, an array of these or
	 *         false on failure
	 */
	public static function lookup($lat, $lon, array $options = array()) {
		$defaults = array(
			'all' => false,
			'params' => array(
				'locale' => 'de_DE',
				'flags' => 'JXTR',
				'gflags' => 'LR',
				'appid' => static::$app_id
			)
		);
		$options = Set::merge($defaults, $options);
		$location = implode(',', array(
			'Latitude' => $lat,
			'Longitude' => $lon
		));
		return static::geocode($location, $options);
	}

	/**
	 * Finds lat/lon for locations
	 *
	 * @see http://developer.yahoo.com/geo/placefinder/guide/requests.html
	 * @param string|array $location a string or an array of strings with
	 *        location names
	 * @param array $options additional options, currently none
	 * @return array|boolean lat/lon of given $name, an array of these or
	 *         false on failure
	 */
	public static function geocode($location, array $options = array()) {
		$defaults = array(
			'all' => false,
			'raw' => true,
			'params' => array(
				'locale' => 'de_DE',
				'flags' => 'JXTR',
				'gflags' => 'L',
				'appid' => static::$app_id
			)
		);
		$options = Set::merge($defaults, $options);
		$socket = new Service(array('host' => self::$host, 'timeout' => self::$timeout));
		$response = $socket->get('/geocode', Set::merge($options['params'], compact('location')));
		if (empty($response)) {
			return false;
		}
		$response = json_decode($response, true);
		if (!is_array($response)) {
			return false;
		}
		$result = Set::extract($response, '/ResultSet/Results[quality]/.');

		if (!$options['raw']) {
			$map = array(
				'woeid' => 'woeid',
				'city' => 'city',
				'zip' => 'uzip',
				'country' => 'country',
				'countrycode' => 'countrycode'
			);
			$result = self::_map($result, $map);
		}
		$result = (!$options['all'])
			? current($result)
			: $result;
		return $result;
	}

	/**
	 * Manipulates each item of $data using map.
	 *
	 * @param array $data i.e. an array of objects.
	 * @param array $map An array of key method/property/closure pairs.
	 * @return array The mapped data.
	 */
	protected static function _map($data, $map) {
		$results = array();
		foreach ($data as $item) {
			$result = array();
			foreach ($map as $key => $source) {
				if (is_string($source) && array_key_exists($source, $item)) {
					$result[$key] = $item[$source];
				} elseif (is_callable($source)) {
					$result[$key] = $source($item);
				} elseif (is_callable(array($this, $source))) {
					$result[$key] = $this->{$source}($item);
				} elseif (is_object($item)) {
					$result[$key] = $item->{$source};
				} else {
					$result[$key] = $item[$source];
				}
			}
			if ($result) {
				$results[] = $result;
			}
		}
		return $results;
	}
}

?>