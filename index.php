<?php
include_once("autoload.php");
$application = \Dit\Application::getInstance();
$application->initialize();

\Dit\Geographic::getSuggestedRoutes();
// Example
// Origin location: Hilton Chicago, 720 South Michigan Avenue, Chicago, IL 60605, United States [41.8721534,-87.6256818]
// $origin = ['41.8721534', '-87.6256818'];
// Desitnation location: Umai, 730 South Clark Street, Chicago, IL 60605, United States [41.872937,-87.6310037]
// $destination = ['41.872937', '-87.6310037'];
// $routes = \Dit\Google\Geographic::retrieveDirections($origin, $destination);


// Get all "steps" along in the first path ($routes[0]) from $origin to $destination
// $steps = $routes[0]['steps'];
// var_dump($steps);

// Result is
/*
array (size=10)
  0 => 
    array (size=2)
      'lat' => float 41.8721498
      'lng' => float -87.6259375
  1 => 
    array (size=2)
      'lat' => float 41.8718259
      'lng' => float -87.6259293
  2 => 
    array (size=2)
      'lat' => float 41.8718259
      'lng' => float -87.6259293
  3 => 
    array (size=2)
      'lat' => float 41.8717916
      'lng' => float -87.6274517
  4 => 
    array (size=2)
      'lat' => float 41.8717916
      'lng' => float -87.6274517
  5 => 
    array (size=2)
      'lat' => float 41.8723158
      'lng' => float -87.6274733
  6 => 
    array (size=2)
      'lat' => float 41.8723158
      'lng' => float -87.6274733
  7 => 
    array (size=2)
      'lat' => float 41.8722398
      'lng' => float -87.6306061
  8 => 
    array (size=2)
      'lat' => float 41.8722398
      'lng' => float -87.6306061
  9 => 
    array (size=2)
      'lat' => float 41.8729437
      'lng' => float -87.6306199
 */
