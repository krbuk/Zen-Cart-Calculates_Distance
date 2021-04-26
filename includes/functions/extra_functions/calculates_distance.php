<?php
/**
 * @package shippingMethod
 * @copyright Copyright 2003-2018 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: Nida Verkkopalvelu (www.nida.fi) / krbuk 2021 Jan 4 Modified in v1.5.7 $
 */
/**
 * Google api cheking distance
*/	
if(!defined('IS_ADMIN_FLAG'))
	die('Illegal Access');

//  function getDistance($storeaddress, $customeraddress, $unit = ''){	
  function getDistance($distance_count) 
 {	  
   global $db, $order;
	  
   // Google calculater distance 
   $privateapiKey = defined('MODULE_SHIPPING_DISTANCE_GOOGLE_API_KEY') ? MODULE_SHIPPING_DISTANCE_GOOGLE_API_KEY : null;

   // Address  store address to  customer address
   $storeaddress = STORE_NAME_ADDRESS;
   $customeraddress = $order->delivery['street_address'] .',' .$order->delivery['postcode'] .' '.$order->delivery['city'];

   // In metric unit. This is default
   $distance_data = file_get_contents('https://maps.googleapis.com/maps/api/distancematrix/json?&origins='.urlencode($storeaddress).'&destinations='.urlencode($customeraddress) .'&key=' .$privateapiKey);

   $distance_arr = json_decode($distance_data);
	if ($distance_arr->status=='OK') {
		$destination_addresses = $distance_arr->destination_addresses[0];
		$origin_addresses = $distance_arr->origin_addresses[0];
	} else {
	  echo "<p>The request was Invalid</p>";
	  exit();
	}

	if ($origin_addresses=="" or $destination_addresses=="") {
      echo "<p>Destination or origin address not found</p>";
      exit();
	}

   // Get the elements as array
   $elements = $distance_arr->rows[0]->elements;
   $distance = $elements[0]->distance->text;
   $duration = $elements[0]->duration->text;
   return $distance;
}
?>