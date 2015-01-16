<?php
include_once("autoload.php");
\Dit\Application::getInstance()->initialize();
var_dump(\Dit\Config::get());