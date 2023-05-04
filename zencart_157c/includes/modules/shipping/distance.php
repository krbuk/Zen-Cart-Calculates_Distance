<?php
/**
 * @package shippingMethod
 * @copyright Copyright 2003-2018 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: Nida Verkkopalvelu (www.nida.fi) / krbuk 2021 Jan 4 Modified in v1.5.7
 * Nida Verkkopalvelu Oy (krbuk) <ihsan@nida.fi>  
 */
/**
 * Distance / Will-Call shipping method
 * with multiple location choices as radio-buttons
 */

class distance extends base {
    var $code, $title, $description, $icon, $enabled;
	public $moduleVersion = '1.89';	

  function __construct() {
	global $db, $order;
    $this->code = 'distance';
    $this->title = MODULE_SHIPPING_DISTANCE_TEXT_TITLE;
	$this->description = '<strong>' .MODULE_SHIPPING_DISTANCE_VERSION .' -v' . $this->moduleVersion . '</strong><br><br>' .MODULE_SHIPPING_DISTANCE_TEXT_DESCRIPTION; 
    $this->sort_order = defined('MODULE_SHIPPING_DISTANCE_SORT_ORDER') ? MODULE_SHIPPING_DISTANCE_SORT_ORDER : null;
	  if (null === $this->sort_order) return false;
    $this->icon = ''; // add image filename here; must be uploaded to the /images/ subdirectory
    $this->tax_class = MODULE_SHIPPING_DISTANCE_TAX_CLASS;
    $this->tax_basis = MODULE_SHIPPING_DISTANCE_TAX_BASIS;
	$this->storeaddress    = STORE_NAME_ADDRESS;
	$this->orderaddress = $order->delivery['street_address'];
	$this->enabled   = (MODULE_SHIPPING_DISTANCE_STATUS == 'True');  
	$this->customerID = (int)$_SESSION['customer_id'];
	// Selected address book id	   
    $this->addressbookID = $_SESSION['sendto']; 
	if (IS_ADMIN_FLAG === true && MODULE_SHIPPING_DISTANCE_GOOGLE_API_KEY == 'GOOGLEAPIKEY'){
		$this->title = '<span class="alert">' .MODULE_SHIPPING_DISTANCE_ALERT_TEST .'</span>';
	    $this->enabled = false;		
	} 
    $this->update_status();
  }
  /**
   * Perform various checks to see whether this module should be visible
   */
  function update_status() {
    global $order, $db;
    if (!$this->enabled) return;
    if (IS_ADMIN_FLAG === true) return;
    if (isset($order->delivery) && (int)MODULE_SHIPPING_DISTANCE_ZONE > 0 ) {
      	$check_flag = false;
      	$check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . "
                             where geo_zone_id = '" . MODULE_SHIPPING_DISTANCE_ZONE . "'
                             and zone_country_id = '" . (int)$order->delivery['country']['id'] . "'
                             order by zone_id");
      while (!$check->EOF) {
        if ($check->fields['zone_id'] < 1) {
          $check_flag = true;
          break;
        } elseif ($check->fields['zone_id'] == $order->delivery['zone_id']) {
          $check_flag = true;
          break;
        }
        $check->MoveNext();
      }

      if ($check_flag == false) { $this->enabled = false; }
    }
	// Max distance checking
	// table address book  
  	$addressquery = "select * from " . TABLE_ADDRESS_BOOK . " where address_book_id = '" . $this->addressbookID . "' ";  
	$ad_customeraddress = $db->Execute($addressquery); 
	$distance = $ad_customeraddress->fields['distance'];
	$distance_chk = $distance * 100; // convert km to meters 
	$distance_max = MODULE_SHIPPING_DISTANCE_MAX * 100; // convert km to meters   
	if (MODULE_SHIPPING_DISTANCE_STATUS == 'True' && $distance_chk > $distance_max || $_SESSION['cart']->show_total() < MODULE_SHIPPING_DISTANCE_MIN_ORDER_LIMIT ) {
		$this->enabled = false;		 
	} 	  

  }
  /**
   * Obtain quote from shipping system/calculations
   * @param string $method
   * @return array
   */
  function quote($method = '') {
    global $db, $order;
	  
	// Google calculater distance 
	$privateapiKey = defined('MODULE_SHIPPING_DISTANCE_GOOGLE_API_KEY') ? MODULE_SHIPPING_DISTANCE_GOOGLE_API_KEY : null;
	$google_duration = 0;
	$google_distance = 0;
    $storeaddress  = nl2br(STORE_NAME_ADDRESS);
	$todelivery    = $order->delivery['street_address'] .',' .$order->delivery['postcode'] .' '.$order->delivery['city'];

	// Order check for address 
	$gd_customerorderquery = "select delivery_street_address from " . TABLE_ORDERS . " where customers_id = '" . $this->customerID . "' ";   
	$gd_customerorder = $db->Execute($gd_customerorderquery);
	foreach($gd_customerorder as $addresscheck) {
		if ($addresscheck['delivery_street_address'] == $this->orderaddress)  
		{
			$google_distance_addresscheck =  1;
		} else  
			{
				$google_distance_addresscheck =  0;
			}
	}		

	// table address book  
	$ad_customeraddressquery = "select * from " . TABLE_ADDRESS_BOOK . " where address_book_id = '" . $this->addressbookID . "' ";   
	$ad_customeraddress = $db->Execute($ad_customeraddressquery); 

	foreach($ad_customeraddress as $distanceadd) {
	  if ($distanceadd['customers_id'] == $this->customerID && $distanceadd['address_book_id'] == $this->addressbookID) {
		  	$google_distance = $distanceadd['distance'];
			$google_duration = $distanceadd['duration'];
		  
			if ($distanceadd['distance'] == 0 || $google_distance_addresscheck == 0)
			{
			// Transferin alldata from address book table to distance table
			// populates the distance table  
			$distance_data = file_get_contents('https://maps.googleapis.com/maps/api/distancematrix/json?&origins='.urlencode($storeaddress).'&destinations='.urlencode($todelivery) .'&key=' .$privateapiKey);

				$distance_arr = json_decode($distance_data);
					if ($distance_arr->status=='OK') 
					{
						$destination_addresses = $distance_arr->destination_addresses[0];
						$origin_addresses = $distance_arr->origin_addresses[0];
					} 
					else 
						{
						  echo "<p>" .MODULE_SHIPPING_DISTANCE_ERROR ."</p>";
						  exit();
						}
						if ($origin_addresses == "" or $destination_addresses == "") 
						{
						  //echo "<p>Destination or origin address not found</p>";
						  echo "<p>" .MODULE_SHIPPING_DISTANCE_WRONG_ADDRESS ."</p>";	
							
						  exit();
						}
						// Get the google elements as array
						foreach($distance_arr->rows[0]->elements as $road) 
						{   
							$google_duration = substr($road->duration->text, 0, -4);
							$kmorm  = substr($road->distance->text, -2);
								if ($kmorm == 'km')
								{ 
									$google_distance_arr = substr($road->distance->text, 0, -3);
									$google_distance = $google_distance_arr; 
								}
								else
								{
									$google_distance_arr = substr($road->distance->text, 0, -2);
									$google_distance = $google_distance_arr / 100; 									
								}
							//$google_distance = substr($road->distance->text, 0, -3);					
							//$google_duration = substr($road->duration->text, 0, -4);
							//$google_duration = $road->duration->value;
							//$google_distance = $road->distance->value;			
						}					
				
						$db->Execute("UPDATE " . TABLE_ADDRESS_BOOK . " 
								SET	distance = '" .$google_distance ."', 
									duration = '" .$google_duration ."'
					   where address_book_id = '" .$this->addressbookID ."'
									");	
				} 
		  
			} 

	} // end foreach
	  
 	$distance = $google_distance;
	$duration = $google_duration + intval(MODULE_SHIPPING_DISTANCE_EXTRA_TIME);	
	  
	$distance_chk = $distance * 100; // convert km to meters  
	// order amount 
	$order_amount = zen_round($order->info['subtotal'], 0) * 100;	  
	// Form count setting
	if (!MODULE_SHIPPING_DISTANCE_MIN) { $distance_min = 1; }               else { $distance_min = MODULE_SHIPPING_DISTANCE_MIN * 100;}	  
	if (!MODULE_SHIPPING_DISTANCE_PERKM_COST) { $distance_perkm_cost = 1; } else { $distance_perkm_cost = MODULE_SHIPPING_DISTANCE_PERKM_COST;}
	if (!MODULE_SHIPPING_DISTANCE_EXTRA_COST) { $extra_cost = 0; }          else { $extra_cost = MODULE_SHIPPING_DISTANCE_EXTRA_COST;}	
	if (!MODULE_SHIPPING_DISTANCE_SHIPING_COST) { $shiping_cost  = 0;}      else { $shiping_cost    = MODULE_SHIPPING_DISTANCE_SHIPING_COST;}
	if (!MODULE_SHIPPING_DISTANCE_MIN_ORDER_TOTAL) { $min_order_total = 0;} else { $min_order_total = number_format(MODULE_SHIPPING_DISTANCE_MIN_ORDER_TOTAL, 2, '.', '') * 100;}	
	  
	// this module distance,amount limit,extra cost 
	$distance_amount = $distance * $distance_perkm_cost;
	if ($order_amount >= $min_order_total and $distance_chk <= $distance_min)
		{ 
			$distance_amount = $shiping_cost;
			// if free cost
			//$distance_amount = '0.00';  
		}
	  else if ($order_amount <= $min_order_total and $distance_chk >= $distance_min) 
		{ 
			$newdistance =   ($distance_chk - $distance_min) / 100 ;
			$distance_amount = ($newdistance * $distance_perkm_cost) + $extra_cost;
		}	  
	  else if ($order_amount <= $min_order_total) 
		{ 
			$distance_amount = $extra_cost ;
		}
	  
	  else if ($distance_chk <= $distance_min) 
		{
			$distance_amount = $shiping_cost ;
		}
	  else if ($distance_chk >= $distance_min) 
		{
			$newdistance =   ($distance_chk - $distance_min) / 100 ;
			$distance_amount = ($newdistance * $distance_perkm_cost) + $extra_cost;
		}	  
	// module array	  
	  
    $this->quotes = array('id' => $this->code,
                          'module' => MODULE_SHIPPING_DISTANCE_TEXT_TITLE,
                          'methods' => array(array('id' => $this->code,
                                                   'title' => trim((string)MODULE_SHIPPING_DISTANCE_TEXT_WAY) .' '.$distance .' km - arvio  ' .$duration .MODULE_SHIPPING_DISTANCE_TEXT_MIN,
                                                   'cost' =>  $distance_amount)));
    if ($this->tax_class > 0) {
      $this->quotes['tax'] = zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
    }

    if (zen_not_null($this->icon)) $this->quotes['icon'] = zen_image($this->icon, $this->title);
    return $this->quotes;
    }
  /**
   * Check to see whether module is installed
   *
   * @return boolean
   */
  function check() {
    global $db;
    if (!isset($this->_check)) 
	{
      	$check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_DISTANCE_STATUS'");
      	$this->_check = $check_query->RecordCount();
    }
    return $this->_check;
  }
  /**
   * Install the shipping module and its configuration settings
   */
  function install() {
    global $db;
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable store to shipping address ', 'MODULE_SHIPPING_DISTANCE_STATUS', 'True', 'Do you want to offer In Store rate shipping?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
 
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Google Api Key', 'MODULE_SHIPPING_DISTANCE_GOOGLE_API_KEY', 'GOOGLEAPIKEY', 'This key google devorlop api key', '6', '2', now())");	 
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Per kilometers shipping Cost', 'MODULE_SHIPPING_DISTANCE_PERKM_COST', '0.8065', 'Per kilometers the shipping cost for all orders using this shipping method.<br>Example tax 24% : 0.8065 -> 1€ ', '6', '3', now())");	  
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Shipping Cost', 'MODULE_SHIPPING_DISTANCE_SHIPING_COST', '2.4194', 'Shipping cost for all orders using this shipping method.<br>Example tax 24% : 2.4194 ->3€ ', '6', '4', now())");
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Extra Shipping Cost', 'MODULE_SHIPPING_DISTANCE_EXTRA_COST', '2.8226', 'Fxtra shipping cost.<br> Example tax 24% : 2.8226 -> 3.50€', '6', '5', now())");	  
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Free shiping order total limit', 'MODULE_SHIPPING_DISTANCE_MIN_ORDER_TOTAL', '15.50', 'Free shipping order total. Example : 15.50 -> 15.50€', '6', '6', now())");

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Maximum distance ', 'MODULE_SHIPPING_DISTANCE_MAX', '12', 'Maximum km distance <br>Example: 12 or 12.5 -> 12km or 12.5km ', '6', '7', now())");	  
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Until free distancing', 'MODULE_SHIPPING_DISTANCE_MIN', '5', 'Free cost until this distance km. Example: 5 or 5.5', '6', '8', now())");		  

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tax Class', 'MODULE_SHIPPING_DISTANCE_TAX_CLASS', '0', 'Use the following tax class on the shipping fee.', '6', '9', 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now())");
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Tax Basis', 'MODULE_SHIPPING_DISTANCE_TAX_BASIS', 'Shipping', 'On what basis is Shipping Tax calculated. Options are<br />Shipping - Based on customers Shipping Address<br />Billing Based on customers Billing address<br />Store - Based on Store address if Billing/Shipping Zone equals Store zone', '6', '10', 'zen_cfg_select_option(array(\'Shipping\', \'Billing\'), ', now())");
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Shipping Zone', 'MODULE_SHIPPING_DISTANCE_ZONE', '0', 'If a zone is selected, only enable this shipping method for that zone.', '6', '11', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_SHIPPING_DISTANCE_SORT_ORDER', '0', 'Sort order of display.', '6', '12', now())");
	  
	$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Minimum order limit', 'MODULE_SHIPPING_DISTANCE_MIN_ORDER_LIMIT', '7.50', 'Enable store order limit €. Example : 7.50', '6', '13', now())");
	  
	$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Extra duration minute', 'MODULE_SHIPPING_DISTANCE_EXTRA_TIME', '30', 'For delivery extra duration. Example : 30', '6', '14', now())");
	  
	$db->Execute("ALTER TABLE " . TABLE_ADDRESS_BOOK . " ADD distance varchar(10) NOT NULL DEFAULT 0");
	$db->Execute("ALTER TABLE " . TABLE_ADDRESS_BOOK . " ADD duration varchar(10) NOT NULL DEFAULT 0");	  
  } // end function install
	
/**
 * Remove the module and all its settings
 *
 */	
  function remove() {
	global $db;
	$db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	$db->Execute("ALTER TABLE " . TABLE_ADDRESS_BOOK . " DROP COLUMN distance");
	$db->Execute("ALTER TABLE " . TABLE_ADDRESS_BOOK . " DROP COLUMN duration");  
  } // end function remove	
	
  function keys() {
    return array('MODULE_SHIPPING_DISTANCE_STATUS', 
				 'MODULE_SHIPPING_DISTANCE_GOOGLE_API_KEY', 
				 'MODULE_SHIPPING_DISTANCE_PERKM_COST', 
				 'MODULE_SHIPPING_DISTANCE_SHIPING_COST', 
				 'MODULE_SHIPPING_DISTANCE_EXTRA_COST', 
				 'MODULE_SHIPPING_DISTANCE_MIN_ORDER_TOTAL',
				 'MODULE_SHIPPING_DISTANCE_MAX', 
				 'MODULE_SHIPPING_DISTANCE_MIN', 
				 'MODULE_SHIPPING_DISTANCE_TAX_CLASS', 
				 'MODULE_SHIPPING_DISTANCE_TAX_BASIS', 
				 'MODULE_SHIPPING_DISTANCE_ZONE', 
				 'MODULE_SHIPPING_DISTANCE_SORT_ORDER', 
				 'MODULE_SHIPPING_DISTANCE_MIN_ORDER_LIMIT',
				 'MODULE_SHIPPING_DISTANCE_EXTRA_TIME');
  } // end function keys
}