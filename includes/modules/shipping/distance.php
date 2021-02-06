<?php
/**
 * @package shippingMethod
 * @copyright Copyright 2003-2018 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: Nida Verkkopalvelu (www.nida.fi) / krbuk 2021 Jan 4 Module V1.1 Modified in v1.5.7 $ 
 */
/**
 * Distance / Will-Call shipping method
 * with multiple location choices as radio-buttons
 */
class distance extends base {
    var $code, $title, $description, $icon, $enabled;
	public $moduleVersion = '1.00';	

  function __construct() {
	global $db, $order;
	  
    $this->code = 'distance';
    $this->title = MODULE_SHIPPING_DISTANCE_TEXT_TITLE;
	$this->description = '<strong>Calculates the distance version -v' . $this->moduleVersion . '</strong><br><br>' .MODULE_SHIPPING_DISTANCE_TEXT_DESCRIPTION; 
    $this->sort_order = defined('MODULE_SHIPPING_DISTANCE_SORT_ORDER') ? MODULE_SHIPPING_DISTANCE_SORT_ORDER : null;
    	if (null === $this->sort_order) return false;

    $this->icon = ''; // add image filename here; must be uploaded to the /images/ subdirectory
    $this->tax_class = MODULE_SHIPPING_DISTANCE_TAX_CLASS;
    $this->tax_basis = MODULE_SHIPPING_DISTANCE_TAX_BASIS;
	 
	$this->storeaddress    = STORE_NAME_ADDRESS;
	$this->customeraddress = $order->delivery['street_address'] .',' .$order->delivery['city']; 
	$this->enabled   = (MODULE_SHIPPING_DISTANCE_STATUS == 'True');  
    
	// $this->enabled = ((MODULE_SHIPPING_DISTANCE_STATUS == 'True' && $this->distance_km < MODULE_SHIPPING_DISTANCE_MAX  ) ? true : false);
	  
	if (IS_ADMIN_FLAG === true && MODULE_SHIPPING_DISTANCE_GOOGLE_API_KEY == 'GOOGLEAPIKEY'){
		$this->title .= '<span class="alert">' .MODULE_SHIPPING_DISTANCE_ALERT_TEST .'</span>';		
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
	$distance_chk = getDistance($this->storeaddress, $this->customeraddress, "K");
	$distance_chk = $distance_chk * 1000;  // convert km to meters 
	$distance_max = MODULE_SHIPPING_DISTANCE_MAX * 1000; // convert km to meters  
	if (MODULE_SHIPPING_DISTANCE_STATUS == 'True' && $distance_chk > $distance_max) {
		//$this->enabled = (MODULE_SHIPPING_DISTANCE_STATUS == 'False');
		$this->enabled = false;
	} 

  }
	
  /**
   * Obtain quote from shipping system/calculations
   *
   * @param string $method
   * @return array
   */
  function quote($method = '') {
    global $db, $order;
	  
  	// Google calculater distance 
	$distance = getDistance($this->storeaddress, $this->customeraddress, "K");
	$distance_chk = $distance * 1000;  // convert km to meters 

	// From setting
	$distance_cost = MODULE_SHIPPING_DISTANCE_COST;                								//     0.8065
	$free_cost_max = number_format(MODULE_SHIPPING_DISTANCE_MAX_FREE_COST, 2, '.', '') * 100;	//  1850
	$distance_max  = MODULE_SHIPPING_DISTANCE_MAX * 1000; // convert km to meters 				// 12000
	$distance_free = MODULE_SHIPPING_DISTANCE_FREE * 1000; // convert km to meters				//  5000 
	
	// order amount 
    $order_amount = $order->info['total'] = zen_round($order->info['total'], 2) * 100;
	  
	// Olasiliklar buraya gelecek
	$distance_amount = $distance_cost * $distance;   
	if ($distance_chk < $distance_free || $order_amount > $free_cost_max) {
		$distance_amount = '0.00';
	}

	// module array
    $this->quotes = array('id' => $this->code,
                          'module' => MODULE_SHIPPING_DISTANCE_TEXT_TITLE,
                          'methods' => array(array('id' => $this->code,
                                                   'title' => $distance,
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
    if (!isset($this->_check)) {
      $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_DISTANCE_STATUS'");
      $this->_check = $check_query->RecordCount();
    }
    return $this->_check;
  }
  /**
   * Install the shipping module and its configuration settings
   *
   */
  function install() {
    global $db;
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable store to shipping address ', 'MODULE_SHIPPING_DISTANCE_STATUS', 'True', 'Do you want to offer In Store rate shipping?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
 
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Google Api Key', 'MODULE_SHIPPING_DISTANCE_GOOGLE_API_KEY', 'GOOGLEAPIKEY', 'This key google devorlop api key', '6', '0', now())");	  
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Shipping Cost', 'MODULE_SHIPPING_DISTANCE_COST', '0.0000', 'Per meters the shipping cost for all orders using this shipping method. ', '6', '0', now())");
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Free Shipping order total', 'MODULE_SHIPPING_DISTANCE_MAX_FREE_COST', '0.00', 'Per meters the shipping cost for all orders using this shipping method. ', '6', '0', now())");	  
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Maximum distance ', 'MODULE_SHIPPING_DISTANCE_MAX', '10', 'Maximum km distance -> 12 or 12.5 ', '6', '0', now())");	  
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Free distance ', 'MODULE_SHIPPING_DISTANCE_FREE', '5', 'Free cost until this distance km. 5.5km', '6', '0', now())");		  
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tax Class', 'MODULE_SHIPPING_DISTANCE_TAX_CLASS', '0', 'Use the following tax class on the shipping fee.', '6', '0', 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now())");
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Tax Basis', 'MODULE_SHIPPING_DISTANCE_TAX_BASIS', 'Shipping', 'On what basis is Shipping Tax calculated. Options are<br />Shipping - Based on customers Shipping Address<br />Billing Based on customers Billing address<br />Store - Based on Store address if Billing/Shipping Zone equals Store zone', '6', '0', 'zen_cfg_select_option(array(\'Shipping\', \'Billing\'), ', now())");
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Shipping Zone', 'MODULE_SHIPPING_DISTANCE_ZONE', '0', 'If a zone is selected, only enable this shipping method for that zone.', '6', '0', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
	  
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_SHIPPING_DISTANCE_SORT_ORDER', '0', 'Sort order of display.', '6', '0', now())");
  }
  /**
   * Remove the module and all its settings
   *
   */
  function remove() {
    global $db;
    $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_SHIPPING\_DISTANCE\_%'");
  }
  /**
   * Internal list of configuration keys used for configuration of the module
   *
   * @return array
   */
  function keys() {
    return array('MODULE_SHIPPING_DISTANCE_STATUS', 'MODULE_SHIPPING_DISTANCE_GOOGLE_API_KEY', 'MODULE_SHIPPING_DISTANCE_COST', 'MODULE_SHIPPING_DISTANCE_MAX_FREE_COST', 'MODULE_SHIPPING_DISTANCE_MAX', 'MODULE_SHIPPING_DISTANCE_FREE', 'MODULE_SHIPPING_DISTANCE_TAX_CLASS', 'MODULE_SHIPPING_DISTANCE_TAX_BASIS', 'MODULE_SHIPPING_DISTANCE_ZONE', 'MODULE_SHIPPING_DISTANCE_SORT_ORDER');
  }
}
