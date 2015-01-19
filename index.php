<?php
include_once("autoload.php");
$application = \Dit\Application::getInstance();
$application->initialize();
var_dump($application->getDb());