<?php

$version['name'] = 'SolusVM';
$version['api_version'] = '1.0';
$version['date'] = '2016-05-21'; // Last modification date
$version['wefact_version'] = '3.4.3'; // Version released for WeFact

// Information for customer (will be showed at registrar-show-page)
$version['dev_logo'] = ''; // URL to your logo
$version['dev_author'] = 'Cas de Reuver'; // Your companyname
$version['dev_website'] = 'http://casdr.me'; // URL website
$version['dev_email'] = 'reuverc@gmail.com'; // Your e-mailaddress for support questions

// when you need additional settings when creating a VPS node, set this to true. See function showSettingsHTML()
$version['hasAdditionalSettings'] = true;

// if the VPS platform does not support creating a VPS server with custom properties (eg diskspace, cpu cores), set this to false
// the result is that you cannot adjust package properties when creating/editing a VPS server in WeFact
$version['supports_custom_vps_properties'] = false;
