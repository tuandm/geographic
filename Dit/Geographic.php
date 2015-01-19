<?php
/**
 * Geographic utilities class
 * @author  Tuan Duong <duongthaso@gmail.com>
 */

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
                SELECT dit_store_location.p_geo_lat, dit_store_location.p_geo_long, dit_order.d_geo_lat, dit_order.d_geo_long
                FROM dit_ship_group, dit_store_location, dit_order 
                WHERE dit_ship_group.order_id = dit_order.order_id and 
                dit_ship_group.store_loc_id = dit_store_location.store_loc_id and 
                dit_ship_group.ship_group_id='%s'",
                mysql_real_escape_string($bookingId));
            
            $result = mysql_query($dropOffQuery);
            if (!$result) {
                die("Invalid query: " . mysql_error());
            }

            $numrow = mysql_num_rows($result);

            if ($numrow>0) {
                $loan_rowclient=mysql_fetch_array($result);
                $dropOffLat = $loan_rowclient['d_geo_lat'];
                $dropOffLong = $loan_rowclient['d_geo_long'];
                
                // Find deliveries within a specific radius based on coordinates
                // of drop off location. 
                // Currently this only checks the dropoff location.  We will eventually
                // want to improve this logic to check full route using 
                // center of minimum distance method
                $query =sprintf("
                    SELECT dit_ship_group.ship_group_id, dit_ship_group.order_id, dit_ship_group.store_loc_id, dit_store_location.*, dit_order.*,
                (3959 * acos(cos(radians('%s')) * cos(radians(dit_order.d_geo_lat)) * cos(radians(dit_order.d_geo_long) - radians('%s')) + sin(radians('%s')) * sin(radians(dit_order.d_geo_lat)))) as distance
                from dit_ship_group, dit_order, dit_store_location where dit_ship_group.order_id = dit_order.order_id and
                dit_ship_group.store_loc_id = dit_store_location.store_loc_id and
                (dit_ship_group.state = '0' or dit_ship_group.state is null)
                having distance < '%s'
                order by distance limit 0, 20",
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
                            'bookingId' => $loan_rowclient['ship_group_id'],                    
                            'companyName' => $loan_rowclient['company_name'],
                            'pAddr1'      => $loan_rowclient['store_address1'],
                            'pAddr2' => $loan_rowclient['store_address2'],
                            'pCity' => $loan_rowclient['store_city'],
                            'pState' => $loan_rowclient['store_state'],
                            'pZip' => $loan_rowclient['store_zip'],
                            'pLat' => $loan_rowclient['p_geo_lat'],
                            'pLng' => $loan_rowclient['p_geo_long'],
                            'customerName' => $loan_rowclient['cust_name'],
                            'cAddr1' => $loan_rowclient['dest_address_1'],
                            'cAddr2' => $loan_rowclient['dest_address_2'],
                            'cCity' => $loan_rowclient['dest_city'],
                            'cState' => $loan_rowclient['dest_state'],
                            'cZip' => $loan_rowclient['dest_zip'],
                            'cLat' => $loan_rowclient['d_geo_lat'],
                            'cLng' => $loan_rowclient['d_geo_long'],
                            'bookingTime' => $loan_rowclient['submit_time'],
                            'distance' => $loan_rowclient['distance'],
                            'deFlag' => $loan_rowclient['dit_ship_group.state'],
                            'driverId' => $loan_rowclient['assigned_driver_id']                        
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
    public static function getSugestedRoutes()
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
            $dropOffLong    = $row['d_loc_long'];
            $pickupLat      = $row['p_geo_lat'];
            $pickupLng      = $row['p_geo_long'];

            $driverLocation = self::getDriverLocation($driverId);
            if ($driverLocation === false) {
                throw new \Dit\Exception("Driver not found");
            }
            $driverLat = $driverLocation['loc_lat'];
            $driverLng = $driverLocation['loc_lng'];

            // Find routes
            // Find all pickup locations within 5 miles radius from current driver location to original pickup location
            $sql = "
                SELECT group.ship_group_id, group.order_id, store.*, order.*,
                (3959 * acos(cos(radians('%s')) * cos(radians(store.p_geo_lat)) * cos(radians(store.p_geo_long) - radians('%s')) + sin(radians('%s')) * sin(radians(store.p_geo_lat)))) as distance_pickup,
                FROM dit_ship_group group
                INNER JOIN dit_store_location store ON group.store_loc_id = store.store_loc_id
                INNER JOIN dit_order order ON group.order_id = order.order_id
                HAVING distance_pickup < ?
                ORDER BY distance_pickup ASC
                LIMIT 0, 20
            ";            
            $result1 = $db->query($sql, $driverLat, $driverLng, $driverLat, $maxDistance)->result_array*();

            // Find all pickup locations within 5 miles radius from original pickup location to original dropoff location
            $sql = "
                SELECT group.ship_group_id, group.order_id, store.*, order.*,
                (3959 * acos(cos(radians('%s')) * cos(radians(store.p_geo_lat)) * cos(radians(store.p_geo_long) - radians('%s')) + sin(radians('%s')) * sin(radians(store.p_geo_lat)))) as distance_pickup,
                FROM dit_ship_group group
                INNER JOIN dit_store_location store ON group.store_loc_id = store.store_loc_id
                INNER JOIN dit_order order ON group.order_id = order.order_id
                HAVING distance_pickup < ?
                ORDER BY distance_pickup ASC
                LIMIT 0, 20
            ";            
            $result2 = $db->query($sql, $driverLat, $driverLng, $driverLat, $maxDistance)->result_array();
            $result = array_merge($result1, $result2);
        }
    }

    /**
     * Calculate distance in miles between 2 locations
     * http://stackoverflow.com/questions/17125608/php-distance-between-two-locations-approach
     * @param  [type] $fromLat [description]
     * @param  [type] $fromLng [description]
     * @param  [type] $toLat   [description]
     * @param  [type] $toLng   [description]
     * @return [type]          [description]
     */
    private static function getDistance($fromLat, $fromLng, $toLat, $toLng)
    {
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
        $sql = 'SELECT cur_loc_lat, cur_loc_lng FROM dit_driver WHERE driver_id = ?';
        $result = \Dit\Application::getInstance()->getDb()->query($sql, $driverId)->result_array();
        if (count($result) > 0) {
            return array(
                'loc_lat'   => $result[0]['cur_loc_lat'],
                'loc_lng'   => $result[0]['cur_loc_lng']
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
            SELECT dit_store_location.p_geo_lat, dit_store_location.p_geo_long, dit_order.d_geo_lat, dit_order.d_geo_long
            FROM dit_ship_group, dit_store_location, dit_order 
            WHERE dit_ship_group.order_id = dit_order.order_id and 
            dit_ship_group.store_loc_id = dit_store_location.store_loc_id and 
            dit_ship_group.ship_group_id='?'";
        $result = \Dit\Application->getInstance()->getDb->query($dropOffQuery, $bookingId)->result_array();
        if (count($result) > 0) {
            return array(
                'd_loc_lat' => $row['d_geo_lat'],
                'd_loc_lng' => $row['d_geo_long'],
                'p_loc_lat' => $row['p_geo_lat'],
                'p_loc_lng' => $row['p_geo_long']
            );
        } else {
            return false;
        }
    }
}