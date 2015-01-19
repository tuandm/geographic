<?php
include_once("autoload.php");
$application = \Dit\Application::getInstance();
$application->initialize();
// Find all suggested deliveries
\Dit\Geographic::getSugestedDeliveries();

// Find all "routes" between originalPickupLocation and originalDropoffLocation
\Dit\Geographic::getSuggestedRoutes();