<?php
/**
 * @package shippingMethod
 * @copyright Copyright 2003-2018 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: Nida Verkkopalvelu (www.nida.fi) / krbuk 2021 Jan 4 Module V1.1 Modified in v1.5.7 $ 
 */
/**
 * Google api cheking distance
*/	
if(!defined('IS_ADMIN_FLAG'))
	die('Illegal Access');

  function getDistance($storeaddress, $customeraddress, $unit = ''){	  
	global $db; 
	  
	$privateapiKey   = defined('MODULE_SHIPPING_DISTANCE_GOOGLE_API_KEY') ? MODULE_SHIPPING_DISTANCE_GOOGLE_API_KEY : null;

    // Change address format
    $formattedAddrFrom    = str_replace(' ', '+', $storeaddress);
    $formattedAddrTo     = str_replace(' ', '+', $customeraddress);
    
    // Geocoding API request with start address
    $geocodeFrom = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address='.$formattedAddrFrom.'&sensor=false&key='.$privateapiKey);
    $outputFrom = json_decode($geocodeFrom);
    if(!empty($outputFrom->error_message)){
        return $outputFrom->error_message;
    }
    
    // Geocoding API request with end address
    $geocodeTo = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address='.$formattedAddrTo.'&sensor=false&key='.$privateapiKey);
    $outputTo = json_decode($geocodeTo);
    if(!empty($outputTo->error_message)){
        return $outputTo->error_message;
    }
    
    // Get latitude and longitude from the geodata
    $latitudeFrom  = $outputFrom->results[0]->geometry->location->lat;
    $longitudeFrom = $outputFrom->results[0]->geometry->location->lng;
    $latitudeTo    = $outputTo->results[0]->geometry->location->lat;
    $longitudeTo   = $outputTo->results[0]->geometry->location->lng;
    
    // Calculate distance between latitude and longitude
    $theta = $longitudeFrom - $longitudeTo;
    $dist  = sin(deg2rad($latitudeFrom)) * sin(deg2rad($latitudeTo)) +  cos(deg2rad($latitudeFrom)) * cos(deg2rad($latitudeTo)) * cos(deg2rad($theta));
    $dist  = acos($dist);
    $dist  = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    
    // Convert unit and return distance
    $unit = strtoupper($unit);
    if($unit == "K"){
        return round($miles * 1.609344, 2).' km';
     }
	  elseif($unit == "M"){
        return round($miles * 1609.344, 0).' meters';
    } else{
        return round($miles, 2).' miles';
    }
}	
?>