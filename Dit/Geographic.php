<?php
/**
 * Geographic utilities class
 * @author  Tuan Duong <duongthaso@gmail.com>
 */
namespace Dit;

class Geographic
{
    /**
     * This will return a nearby deliveries assuming starting point is the same
     * for all deliveries. Example, user is delivering for a single restaurant
     * and pickup location will always be restaurant location
     * -- Hasnt been implemented yet on iPhone
     */
    public static function getSugestedDeliveries() {
        if (isset($_GET['userId']) && isset($_GET['bookingId'])) {
            // Get parameters from URL
            $driverId = $_GET['userId'];
            $bookingId = $_GET['bookingId'];

            // This gets the store location ID that driver is tied to
            //$storeLocId = $_GET["storeLocId"];
            // TODO needs to be configurable property
            $maxDistance = 5;  
            
            // Get dropOff coordinates from bookingId
            $dropOffQuery = sprintf("
                SELECT DIT_STORE_LOCATION.P_GEO_LAT, DIT_STORE_LOCATION.P_GEO_LONG, DIT_ORDER.D_GEO_LAT, DIT_ORDER.D_GEO_LONG
                FROM DIT_SHIP_GROUP, DIT_STORE_LOCATION, DIT_ORDER 
                WHERE DIT_SHIP_GROUP.ORDER_ID = DIT_ORDER.ORDER_ID AND 
                DIT_SHIP_GROUP.STORE_LOC_ID = DIT_STORE_LOCATION.STORE_LOC_ID AND 
                DIT_SHIP_GROUP.SHIP_GROUP_ID='%s'",
                mysql_real_escape_string($bookingId));
            
            $result = mysql_query($dropOffQuery);
            if (!$result) {
                die("Invalid query: " . mysql_error());
            }

            $numrow = mysql_num_rows($result);

            if ($numrow>0) {
                $loan_rowclient=mysql_fetch_array($result);
                $dropOffLat = $loan_rowclient['D_GEO_LAT'];
                $dropOffLong = $loan_rowclient['D_GEO_LONG'];
                
                // Find deliveries within a specific radius based on coordinates
                // of drop off location. 
                // Currently this only checks the dropoff location.  We will eventually
                // want to improve this logic to check full route using 
                // center of minimum distance method
                $query =sprintf("
                    SELECT DIT_SHIP_GROUP.SHIP_GROUP_ID, DIT_SHIP_GROUP.ORDER_ID, DIT_SHIP_GROUP.STORE_LOC_ID, DIT_STORE_LOCATION.*, DIT_ORDER.*,
                (3959 * ACOS(COS(RADIANS('%s')) * COS(RADIANS(DIT_ORDER.D_GEO_LAT)) * COS(RADIANS(DIT_ORDER.D_GEO_LONG) - RADIANS('%s')) + SIN(RADIANS('%s')) * SIN(RADIANS(DIT_ORDER.D_GEO_LAT)))) AS DISTANCE
                FROM DIT_SHIP_GROUP, DIT_ORDER, DIT_STORE_LOCATION WHERE DIT_SHIP_GROUP.ORDER_ID = DIT_ORDER.ORDER_ID AND
                DIT_SHIP_GROUP.STORE_LOC_ID = DIT_STORE_LOCATION.STORE_LOC_ID AND
                (DIT_SHIP_GROUP.STATE = '0' OR DIT_SHIP_GROUP.STATE IS NULL)
                HAVING DISTANCE < '%s'
                ORDER BY DISTANCE LIMIT 0, 20",
                mysql_real_escape_string($dropOffLat),
                mysql_real_escape_string($dropOffLong),
                mysql_real_escape_string($dropOffLat),
                mysql_real_escape_string($maxDistance));

                $result = mysql_query($query);
                if (!$result) {
                    die("Invalid query: " . mysql_error());
                }

                $numrow=mysql_num_rows($result);

                if($numrow>0) {
                    while ($loan_rowclient = mysql_fetch_array($result)) {
                        $data[] = array(
                            'bookingId' => $loan_rowclient['SHIP_GROUP_ID'],                    
                            'companyName' => $loan_rowclient['COMPANY_NAME'],
                            'pAddr1'      => $loan_rowclient['STORE_ADDRESS1'],
                            'pAddr2' => $loan_rowclient['STORE_ADDRESS2'],
                            'pCity' => $loan_rowclient['STORE_CITY'],
                            'pState' => $loan_rowclient['STORE_STATE'],
                            'pZip' => $loan_rowclient['STORE_ZIP'],
                            'pLat' => $loan_rowclient['P_GEO_LAT'],
                            'pLng' => $loan_rowclient['P_GEO_LONG'],
                            'customerName' => $loan_rowclient['CUST_NAME'],
                            'cAddr1' => $loan_rowclient['DEST_ADDRESS_1'],
                            'cAddr2' => $loan_rowclient['DEST_ADDRESS_2'],
                            'cCity' => $loan_rowclient['DEST_CITY'],
                            'cState' => $loan_rowclient['DEST_STATE'],
                            'cZip' => $loan_rowclient['DEST_ZIP'],
                            'cLat' => $loan_rowclient['D_GEO_LAT'],
                            'cLng' => $loan_rowclient['D_GEO_LONG'],
                            'bookingTime' => $loan_rowclient['SUBMIT_TIME'],
                            'distance' => $loan_rowclient['DISTANCE'],
                            'deFlag' => $loan_rowclient['DIT_SHIP_GROUP.STATE'],
                            'driverId' => $loan_rowclient['ASSIGNED_DRIVER_ID']                        
                        );
                    }
                }                
            }
            
            
            // this functionality is for orders that are tied to specific
            // drivers. I.E a restarurant with its own delivery service
            // TODO This will be passed in parameter and will need to dertermine 
            // which qury to run based on the driver type.  This will prob be
            // an array since a driver can have multiple exclusive stores
            


            $classes['data'] = $data;
            return $classes;         
        }
    }

    /**
     * Find all “routes” between originalPickupLocation and originalDropoffLocation
     * Input: originalPickupLocation, originalDropoffLocation, currentDriverLocation
     * Output: list of pair locations (from database) between driverLocation and storeLocation
     * (location[0], location[1])
     * (location[1], location[2])
     * ...
     * (location[N-1], location[N])
     * 
     * With location[0..N] is all coordinates near by "main routes" within the 5 miles radius
     * @return [type] [description]
     * @throws \Dit\Exception
     */
    public static function getSuggestedRoutes()
    {
        if (isset($_GET['userId']) && isset($_GET['bookingId'])) {
            $driverId = intval($_GET['userId']);
            $bookingId = intval($_GET['bookingId']);

            // This gets the store location ID that driver is tied to
            //$storeLocId = $_GET["storeLocId"];
            // TODO needs to be configurable property
            $maxDistance = 5;  
            
            $db = \Dit\Application::getInstance()->getDb();
            // Get dropOff coordinates from bookingId
            $bookingLocation = self::getBookingLocation($bookingId);
            if ($bookingLocation === false) {
                throw new \Dit\Exception("Booking not found");
            }
            $dropOffLat     = $row['d_loc_lat'];
            $dropOffLng    = $row['d_loc_long'];
            $pickupLat      = $row['p_geo_lat'];
            $pickupLng      = $row['p_geo_long'];

            $driverLocation = self::getDriverLocation($driverId);
            if ($driverLocation === false) {
                throw new \Dit\Exception("Driver not found");
            }
            $driverLat = $driverLocation['loc_lat'];
            $driverLng = $driverLocation['loc_lng'];

            // Find all stops along the path from driver to pickup location
            $driverToPickupStops = \Dit\Google\Geographic::retrieveDirections(array($driverLat, $driverLng), array($pickupLat, $pickupLng));
            $driverToPickupStops = $driverToPickupStops[0]['steps'];
            $pickupToDropoffStops = \Dit\Google\Geographic::retrieveDirections(array($pickupLat, $pickupLng), array($dropOffLat, $dropOffLng));
            $pickupToDropoffStops = $pickupToDropoffStops[0]['steps'];
            // We need remove the last stop of $driverToPickupStops, it's pickup Location and in $pickupToDropoffStops already
            array_pop($driverToPickupStops);
            $stops = array_merge($driverToPickupStops, $pickupToDropoffStops);
            // Clean stops - 2 stops need to have distance about 5 miles
            $stops = self::cleanStops($stops);
            // Find routes
            // Find all locations within 5 miles radius of each location in $stops
            $pickupLocations = array();
            $dropoffLocations = array();
            foreach ($stops[0]['steps'] as $stop) {
                // Pikcup
                $sql = "
                    SELECT GROUP.SHIP_GROUP_ID, GROUP.ORDER_ID, STORE.*
                    (3959 * ACOS(COS(RADIANS(?)) * COS(RADIANS(STORE.P_GEO_LAT)) * COS(RADIANS(STORE.P_GEO_LONG) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(STORE.P_GEO_LAT)))) AS DISTANCE,
                    FROM DIT_SHIP_GROUP GROUP
                    INNER JOIN DIT_STORE_LOCATION STORE ON GROUP.STORE_LOC_ID = STORE.STORE_LOC_ID
                    WHERE (GROUP.STATE = '0' OR GROUP.STATE IS NULL) AND DISTANCE < ?
                    ORDER BY DISTANCE ASC
                    LIMIT 0, 20
                ";            
                $result = $db->query($sql, $stop['lat'], $stop['lng'], $stop['lat'], $maxDistance)->result_array();
                foreach ($result as $row) {
                    if (!isset($pickupLocations['SHIP_GROUP_ID'])) {
                        $pickupLocations[$row['SHIP_GROUP_ID']] = $row;
                    }
                }
                // Dropoff
                $sql = "
                    SELECT GROUP.SHIP_GROUP_ID, GROUP.ORDER_ID, ORDER.*,
                    (3959 * ACOS(COS(RADIANS('?')) * COS(RADIANS(ORDER.D_GEO_LAT)) * COS(RADIANS(ORDER.D_GEO_LONG) - RADIANS('?')) + SIN(RADIANS('?')) * SIN(RADIANS(ORDER.D_GEO_LAT)))) AS DISTANCE,
                    FROM DIT_SHIP_GROUP GROUP
                    INNER JOIN DIT_ORDER ORDER ON GROUP.ORDER_ID = ORDER.ORDER_ID
                    WHERE (GROUP.STATE = '0' OR GROUP.STATE IS NULL) AND DISTANCE < ?
                    ORDER BY DISTANCE ASC
                    LIMIT 0, 20
                ";            
                $result = $db->query($sql, $stop['lat'], $stop['lng'], $stop['lat'], $maxDistance)->result_array();
                foreach ($result as $row) {
                    if (!isset($dropoffLocations[$row['SHIP_GROUP_ID']])) {
                        $dropoffLocations[$row['SHIP_GROUP_ID']] = $row;
                    }
                }
            }

            // Now we have all pickable/dropable locations in $pickupLocations and $dropoffLocations
            // We need to remove all dropoff that never pickup before
            foreach ($dropoffLocations as $groupId => $row) {
                if (!isset($pickupLocations[$groupId])) {
                    unset($pickupLocations[$groupId]);
                    continue;
                }
                if ($pickupLocations[$groupId]['DISTANCE_PICKUP'] > $row['DISTANCE_DROPOFF']) {
                    unset($dropoffLocations[$groupId]);
                    continue;
                }
            }

            // We need to choose all ship groups that have pickup and dropoff within 5 miles radius
            foreach ($pickupLocations as $groupId => $value) {
                if (!isset($dropoffLocations[$groupId])) {
                    unset($pickupLocations[$groupId]);
                }
            }

            // Merge pickupLocations and dropoffLocations into one
            $locations = array();
            foreach ($pickupLocations as $location) {
                $locations[] = $location;
            }
            foreach ($dropoffLocations as $location) {
                $locations[] = $location;
            }
            // Then sort by distance
            $totalLocations = count($locations);
            for ($i = 0; $i < count($locations) - 1; $i++) {
                for ($j = 1; $j < count($locations); $j++) {
                    if ($locations[$i]['DISTANCE'] > $locations[$j]['DISTANCE']) {
                        $tmp = $locations[$i];
                        $locations[$i] = $locations[$j];
                        $locations[$j] = $tmp;
                    }
                }
            }
            return $locations;
        }
    }

    public static function cleanStops(array $stops, $distance = 5)
    {
        $queue = array();
        $queue[] = $stops[0];
        $count = count($stops);
        for ($index = 0; $index < $count; $index++) {
            $from = $stops[$index];
            $next = 1;
            while (self::getDistanceInMiles($from, $stops[$index + $next]) < 5) {
                $next++;
            }
            $queue[] = $stops[$index + $next];
            $index = $index + $next - 1;
        }
        return $queue;
    }

    /**
     * Calculate distance in miles between 2 locations
     * http://stackoverflow.com/questions/17125608/php-distance-between-two-locations-approach
     * @param  array $from  [lat, lng]
     * @param  array $to    [lat, lng]
     * @return [type]          [description]
     */
    public static function getDistanceInMiles(array $from, array $to)
    {
        $fromLat = $from['lat'];
        $fromLng = $from['lng'];
        $toLat = $to['lat'];
        $toLng = $to['lng'];
        $earthRadius = 3960.00; # in miles
        $deltaLat = $toLat - $fromLat ;
        $deltaLon = $toLng - $fromLng ;

        $alpha    = $deltaLat / 2;
        $beta     = $deltaLon / 2;
        $a        = sin(deg2rad($alpha)) * sin(deg2rad($alpha)) + cos(deg2rad($fromLat)) * cos(deg2rad($fromLat)) * sin(deg2rad($beta)) * sin(deg2rad($beta)) ;
        $c        = asin(min(1, sqrt($a)));
        $distance = 2 * $earthRadius * $c;
        $distance = round($distance, 4);
        return $distance;
    }

    /**
     * Get current location of driver
     * @param  int $driverId driver ID
     * @return array|bool [loc_lat, loc_lng] or false
     */
    public static function getDriverLocation($driverId)
    {
        $sql = 'SELECT CUR_LOC_LAT, CUR_LOC_LNG FROM DIT_DRIVER WHERE DRIVER_ID = ?';
        $result = \Dit\Application::getInstance()->getDb()->query($sql, $driverId)->result_array();
        if (count($result) > 0) {
            return array(
                'loc_lat'   => $result[0]['CUR_LOC_LAT'],
                'loc_lng'   => $result[0]['CUR_LOC_LNG']
            );
        } else {
            return false;
        }
    }

    /**
     * Get booking location
     * @param  int $bookingId
     * @return bool|array [d_loc_lat, d_loc_lng, p_loc_lat, p_loc_lng] or false
     */
    public static function getBookingLocation($bookingId)
    {
        $dropOffQuery = "
            SELECT DIT_STORE_LOCATION.P_GEO_LAT, DIT_STORE_LOCATION.P_GEO_LONG, DIT_ORDER.D_GEO_LAT, DIT_ORDER.D_GEO_LONG
            FROM DIT_SHIP_GROUP, DIT_STORE_LOCATION, DIT_ORDER 
            WHERE DIT_SHIP_GROUP.ORDER_ID = DIT_ORDER.ORDER_ID AND 
            DIT_SHIP_GROUP.STORE_LOC_ID = DIT_STORE_LOCATION.STORE_LOC_ID AND 
            DIT_SHIP_GROUP.SHIP_GROUP_ID='?'";
        $result = \Dit\Application::getInstance()->getDb()->query($dropOffQuery, $bookingId)->result_array();

        if (count($result) > 0) {
            return array(
                'd_loc_lat' => $row['D_GEO_LAT'],
                'd_loc_lng' => $row['D_GEO_LONG'],
                'p_loc_lat' => $row['P_GEO_LAT'],
                'p_loc_lng' => $row['P_GEO_LONG']
            );
        } else {
            return false;
        }
    }
}