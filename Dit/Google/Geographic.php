<?php
/**
 * Google Directions PHP-wrapper class
 * @author Tuan Duong <duongthaso@gmail.com>
 * @package  Dit
 */
namespace Dit\Google;

/**
 * Geographic class - request directions from google and return all routes
 * @author Tuan Duong <duongthaso@gmail.com>
 */
class Geographic 
{
    private static $apiKey = null;
    private static $baseUrl = 'https://maps.googleapis.com/maps/api/directions/json';
    public static function getApiKey()
    {
        if (self::$apiKey === null) {
            self::$apiKey = \Dit\Config::get('google.direction.api_key');
        }
        return self::$apiKey;
    }

    /**
     * Retrieve routes from $origin to $destination
     * 
     * @param  array  $origin      [lat, lng]
     * @param  array  $destination [lat, lng]
     * @param  string $travelMode  in [driving, walking, bicycling, transit], default is driving
     * @param  string $unitSystem  metric for metric system (m/km), imperial for Imperial system (mile, feet)
     * @return array of [long, lat]
     */
    public static function retrieveDirections(array $origin,array $destination, $travelMode = 'driving', $unitSystem = 'imperial')
    {
        $originLocation = implode(',', $origin);
        $destinationLocation = implode(',', $destination);
        $mode = $travelMode;
        $url = self::$baseUrl . '?' . 'origin=' . $originLocation . '&destination=' . $destinationLocation
               . '&mode' . $travelMode . '&units=' . $unitSystem . '&key=' . self::getApiKey();
        $result = file_get_contents($url);
        $data = json_decode(utf8_encode($result), true);

        if (!is_array($data) || !isset($data['status']) || $data['status'] != 'OK') {
            throw new \Dit\Google\Exception("Something is wrong with the origin location [{$originLocation}] and destination one [{$destinationLocation}]. Please check again");
        }
        
        $routes = $data['routes'];
        foreach ($routes as $key => $route) {
            $steps = array();
            foreach ($route['legs'][0]['steps'] as $step) {
                $steps[] = $step['start_location'];
                $steps[] = $step['end_location'];
            }
            $routes[$key]['steps'] = $steps;
        }
        return $routes;
    }               
}