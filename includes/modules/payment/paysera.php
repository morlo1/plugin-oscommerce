<?php

	class paysera {
		var $code, $title, $description, $enabled;


		// class constructor
		function paysera() {
			global $order;

			$this->code			= 'paysera';
			$this->title		= MODULE_PAYMENT_PAYSERA_TEXT_TITLE;
			$this->description	= MODULE_PAYMENT_PAYSERA_TEXT_DESCRIPTION;
			$this->sort_order	= MODULE_PAYMENT_PAYSERA_SORT_ORDER;
			$this->enabled		= ((MODULE_PAYMENT_PAYSERA_STATUS == 'True') ? true : false);

            $libwebtopay_dir = substr(__FILE__, 0, -4) .'/WebToPay.php';
            if ( !is_file($libwebtopay_dir) ) {
                throw new Exception('LibWebToPay library not found in '. $libwebtopay_dir .'. '. "\n" .'
                    You may download it from here: http://bitbucket.org/webtopay/libwebtopay/raw/default/WebToPay.php');
            }
            require_once $libwebtopay_dir;

            $this->form_action_url = 'https://www.mokejimai.lt/pay/';
		}


        function update_status() {
          global $order;

          if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_PAYPAL_STANDARD_ZONE > 0) ) {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYPAL_STANDARD_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
            while ($check = tep_db_fetch_array($check_query)) {
              if ($check['zone_id'] < 1) {
                $check_flag = true;
                break;
              } elseif ($check['zone_id'] == $order->billing['zone_id']) {
                $check_flag = true;
                break;
              }
            }

            if ($check_flag == false) {
              $this->enabled = false;
            }
          }
        }

		function javascript_validation() {
			return false;
		}

        function pre_confirmation_check() {
          global $cartID, $cart;

          if (empty($cart->cartID)) {
            $cartID = $cart->cartID = $cart->generate_cart_id();
          }

          if (!tep_session_is_registered('cartID')) {
            tep_session_register('cartID');
          }
        }

        function selection() {
          global $cart_Paysera_ID;

          if (tep_session_is_registered('cart_Paysera_ID')) {
            $order_id = substr($cart_Paysera_ID, strpos($cart_Paysera_ID, '-')+1);

            $check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

            if (tep_db_num_rows($check_query) < 1) {
              tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
              tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
              tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
              tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
              tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
              tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');

              tep_session_unregister('cart_Paysera_ID');
            }
          }

          return array('id' => $this->code,
                       'module' => $this->title);
        }

        function confirmation() {
          global $cartID, $cart_Paysera_ID, $customer_id, $languages_id, $order, $order_total_modules;

          if (tep_session_is_registered('cartID')) {
            $insert_order = false;

            if (tep_session_is_registered('cart_Paysera_ID')) {
              $order_id = substr($cart_Paysera_ID, strpos($cart_Paysera_ID, '-')+1);

              $curr_check = tep_db_query("select currency from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
              $curr = tep_db_fetch_array($curr_check);

              if ( ($curr['currency'] != $order->info['currency']) || ($cartID != substr($cart_Paysera_ID, 0, strlen($cartID))) ) {
                $check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

                if (tep_db_num_rows($check_query) < 1) {
                  tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
                  tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
                  tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
                  tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
                  tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
                  tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');
                }

                $insert_order = true;
              }
            } else {
              $insert_order = true;
            }

            if ($insert_order == true) {
              $order_totals = array();
              if (is_array($order_total_modules->modules)) {
                reset($order_total_modules->modules);
                while (list(, $value) = each($order_total_modules->modules)) {
                  $class = substr($value, 0, strrpos($value, '.'));
                  if ($GLOBALS[$class]->enabled) {
                    for ($i=0, $n=sizeof($GLOBALS[$class]->output); $i<$n; $i++) {
                      if (tep_not_null($GLOBALS[$class]->output[$i]['title']) && tep_not_null($GLOBALS[$class]->output[$i]['text'])) {
                        $order_totals[] = array('code' => $GLOBALS[$class]->code,
                                                'title' => $GLOBALS[$class]->output[$i]['title'],
                                                'text' => $GLOBALS[$class]->output[$i]['text'],
                                                'value' => $GLOBALS[$class]->output[$i]['value'],
                                                'sort_order' => $GLOBALS[$class]->sort_order);
                      }
                    }
                  }
                }
              }

              $sql_data_array = array('customers_id' => $customer_id,
                                      'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
                                      'customers_company' => $order->customer['company'],
                                      'customers_street_address' => $order->customer['street_address'],
                                      'customers_suburb' => $order->customer['suburb'],
                                      'customers_city' => $order->customer['city'],
                                      'customers_postcode' => $order->customer['postcode'],
                                      'customers_state' => $order->customer['state'],
                                      'customers_country' => $order->customer['country']['title'],
                                      'customers_telephone' => $order->customer['telephone'],
                                      'customers_email_address' => $order->customer['email_address'],
                                      'customers_address_format_id' => $order->customer['format_id'],
                                      'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
                                      'delivery_company' => $order->delivery['company'],
                                      'delivery_street_address' => $order->delivery['street_address'],
                                      'delivery_suburb' => $order->delivery['suburb'],
                                      'delivery_city' => $order->delivery['city'],
                                      'delivery_postcode' => $order->delivery['postcode'],
                                      'delivery_state' => $order->delivery['state'],
                                      'delivery_country' => $order->delivery['country']['title'],
                                      'delivery_address_format_id' => $order->delivery['format_id'],
                                      'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
                                      'billing_company' => $order->billing['company'],
                                      'billing_street_address' => $order->billing['street_address'],
                                      'billing_suburb' => $order->billing['suburb'],
                                      'billing_city' => $order->billing['city'],
                                      'billing_postcode' => $order->billing['postcode'],
                                      'billing_state' => $order->billing['state'],
                                      'billing_country' => $order->billing['country']['title'],
                                      'billing_address_format_id' => $order->billing['format_id'],
                                      'payment_method' => $order->info['payment_method'],
                                      'cc_type' => $order->info['cc_type'],
                                      'cc_owner' => $order->info['cc_owner'],
                                      'cc_number' => $order->info['cc_number'],
                                      'cc_expires' => $order->info['cc_expires'],
                                      'date_purchased' => 'now()',
                                      'orders_status' => $order->info['order_status'],
                                      'currency' => $order->info['currency'],
                                      'currency_value' => $order->info['currency_value']);

              tep_db_perform(TABLE_ORDERS, $sql_data_array);

              $insert_id = tep_db_insert_id();

              for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
                $sql_data_array = array('orders_id' => $insert_id,
                                        'title' => $order_totals[$i]['title'],
                                        'text' => $order_totals[$i]['text'],
                                        'value' => $order_totals[$i]['value']*$order->info['currency_value'],
                                        'class' => $order_totals[$i]['code'],
                                        'sort_order' => $order_totals[$i]['sort_order']);

                tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
              }

              for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
                $sql_data_array = array('orders_id' => $insert_id,
                                        'products_id' => tep_get_prid($order->products[$i]['id']),
                                        'products_model' => $order->products[$i]['model'],
                                        'products_name' => $order->products[$i]['name'],
                                        'products_price' => $order->products[$i]['price'],
                                        'final_price' => $order->products[$i]['final_price'],
                                        'products_tax' => $order->products[$i]['tax'],
                                        'products_quantity' => $order->products[$i]['qty']);

                tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);

                $order_products_id = tep_db_insert_id();

                $attributes_exist = '0';
                if (isset($order->products[$i]['attributes'])) {
                  $attributes_exist = '1';
                  for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
                    if (DOWNLOAD_ENABLED == 'true') {
                      $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
                                           from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                           left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                           on pa.products_attributes_id=pad.products_attributes_id
                                           where pa.products_id = '" . $order->products[$i]['id'] . "'
                                           and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
                                           and pa.options_id = popt.products_options_id
                                           and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
                                           and pa.options_values_id = poval.products_options_values_id
                                           and popt.language_id = '" . $languages_id . "'
                                           and poval.language_id = '" . $languages_id . "'";
                      $attributes = tep_db_query($attributes_query);
                    } else {
                      $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $languages_id . "' and poval.language_id = '" . $languages_id . "'");
                    }
                    $attributes_values = tep_db_fetch_array($attributes);

                    $sql_data_array = array('orders_id' => $insert_id,
                                            'orders_products_id' => $order_products_id,
                                            'products_options' => $attributes_values['products_options_name'],
                                            'products_options_values' => $attributes_values['products_options_values_name'],
                                            'options_values_price' => $attributes_values['options_values_price'],
                                            'price_prefix' => $attributes_values['price_prefix']);

                    tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

                    if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {
                      $sql_data_array = array('orders_id' => $insert_id,
                                              'orders_products_id' => $order_products_id,
                                              'orders_products_filename' => $attributes_values['products_attributes_filename'],
                                              'download_maxdays' => $attributes_values['products_attributes_maxdays'],
                                              'download_count' => $attributes_values['products_attributes_maxcount']);

                      tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
                    }
                  }
                }
              }

              $cart_Paysera_ID = $cartID . '-' . $insert_id;
              tep_session_register('cart_Paysera_ID');
            }
          }

          return false;
        }


		function process_button() {
			global $order, $currencies, $currency, $customer_id, $cart_Paysera_ID;

            $orderid = substr($cart_Paysera_ID, strpos($cart_Paysera_ID, '-')+1);

			$customer = $GLOBALS['order']->customer;
			$paytext = 'Apmokejimas uz prekes ir paslaugas (uz nr. [order_nr]) ([site_name]). Uzsako: ' . $customer['firstname'] . ' ' . $customer['lastname'] . ', ' . $customer['street_address'] . '';

			$arrLTReplace = array(

				'?' => 'A',
				'?' => 'C',
				'?' => 'E',
				'?' => 'E',
				'?' => 'I',
				'?' => 'S',
				'?' => 'U',
				'?' => 'U',
				'?' => 'Z',
				'?' => 'a',
				'?' => 'c',
				'?' => 'e',
				'?' => 'e',
				'?' => 'i',
				'?' => 's',
				'?' => 'u',
				'?' => 'u',
				'?' => 'z'

			);

			$process_button_string = '';

			if(strcmp($currency,substr(MODULE_PAYMENT_PAYSERA_CURRENCY,-3)) != 0 && MODULE_PAYMENT_PAYSERA_CURRENCY !== 'Selected Currency') {

				echo '<b style="color:red; text-align:center;" >Chosen currency is disabled for this payment module. </b>';

				$process_button_string .= '<script type="text/javascript">
			    			$(function () {
			    				$("#tdb5").hide();
							});
						</script>';

			} else {

	            try {
	                $request = WebToPay::buildRequest(array(
	                        'projectid'     => MODULE_PAYMENT_PAYSERA_PROJECT,
	                        'sign_password' => MODULE_PAYMENT_PAYSERA_SIGNATURE,
	                        'orderid'       => $orderid,

	                        'amount'        => number_format($order->info['total'] * $order->info['currency_value'], 2, '.', '') * 100,
	                        'currency'      => $currency,

	                        'lang'          => substr($_SESSION['language'],0, 3),

	                        'p_firstname'   => strtr($order->customer['firstname'], $arrLTReplace),
	                        'p_lastname'    => strtr($order->customer['lastname'], $arrLTReplace),
	                        'p_email'       => strtr($order->customer['email_address'], $arrLTReplace),
	                        'p_street'      => strtr($order->customer['street_address'], $arrLTReplace),
	                        'p_city'        => strtr($order->customer['city'], $arrLTReplace),
	                        'p_state'       => strtr($order->customer['state'], $arrLTReplace),
	                        'p_zip'         => strtr($order->customer['postcode'], $arrLTReplace),
	                        'p_countrycode' => strtr($order->customer['country']['iso_code_2'], $arrLTReplace),

	                        'accepturl'     => tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL'),
	                        'cancelurl'     => tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'),
	                        'callbackurl'   => tep_href_link('ext/modules/payment/paysera/callback.php', '' , 'SSL', true, true, true),

	                        'test'          => (MODULE_PAYMENT_PAYSERA_TESTING == 'Yes') ? '1' : '0',
	                    ));
	            }
	            catch (WebToPayException $e) {
	                echo $e->getMessage();
	            }
			}

            if($request) {
	            foreach( $request as $field=>$value) {
	                $process_button_string .= tep_draw_hidden_field($field, $value);
	            }
            } else {
            	$process_button_string .= tep_draw_hidden_field('cancelurl', tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));;
            }

			return $process_button_string;
		}


        function before_process() {
            global $customer_id, $order, $order_totals, $sendto, $billto, $languages_id, $payment, $currencies, $cart, $cart_Paysera_ID;
            global $$payment;

            $GLOBALS['order']->customer['email_address'] .= "\nDont\nSend\nEmail\nPlease\n:)";

            $order_id = substr($cart_Paysera_ID, strpos($cart_Paysera_ID, '-')+1);

            $check_query = tep_db_query("select orders_status from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");

            $sql_data_array = array('orders_id' => $order_id,
                                  'date_added' => 'now()',
                                  'customer_notified' => (SEND_EMAILS == 'true') ? '1' : '0',
                                  'comments' => $order->info['comments']);

            tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

            $products_ordered = '';
            $subtotal = 0;
            $total_tax = 0;

            for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
                // Stock Update - Joao Correia
                if (STOCK_LIMITED == 'true') {
                    if (DOWNLOAD_ENABLED == 'true') {
                        $stock_query_raw = "SELECT products_quantity, pad.products_attributes_filename
                            FROM " . TABLE_PRODUCTS . " p
                            LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                            ON p.products_id=pa.products_id
                            LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                            ON pa.products_attributes_id=pad.products_attributes_id
                            WHERE p.products_id = '" . tep_get_prid($order->products[$i]['id']) . "'";
                        // Will work with only one option for downloadable products
                        // otherwise, we have to build the query dynamically with a loop
                        $products_attributes = $order->products[$i]['attributes'];
                        if (is_array($products_attributes)) {
                            $stock_query_raw .= " AND pa.options_id = '" . $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . $products_attributes[0]['value_id'] . "'";
                        }
                        $stock_query = tep_db_query($stock_query_raw);
                    }
                    else{
                        $stock_query = tep_db_query("select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                    }
                    if (tep_db_num_rows($stock_query) > 0) {
                        $stock_values = tep_db_fetch_array($stock_query);
                        // do not decrement quantities if products_attributes_filename exists
                        if ((DOWNLOAD_ENABLED != 'true') || (!$stock_values['products_attributes_filename'])) {
                            $stock_left = $stock_values['products_quantity'] - $order->products[$i]['qty'];
                        }
                        else {
                            $stock_left = $stock_values['products_quantity'];
                        }
                        tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                        if ( ($stock_left < 1) && (STOCK_ALLOW_CHECKOUT == 'false') ) {
                            tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '0' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                        }
                    }
                }

                // Update products_ordered (for bestsellers list)
                tep_db_query("update " . TABLE_PRODUCTS . " set products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");

                //------insert customer choosen option to order--------
                $attributes_exist = '0';
                $products_ordered_attributes = '';
                if (isset($order->products[$i]['attributes'])) {
                    $attributes_exist = '1';
                    for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
                        if (DOWNLOAD_ENABLED == 'true') {
                            $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
                                from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                on pa.products_attributes_id=pad.products_attributes_id
                                where pa.products_id = '" . $order->products[$i]['id'] . "'
                                and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
                                and pa.options_id = popt.products_options_id
                                and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
                                and pa.options_values_id = poval.products_options_values_id
                                and popt.language_id = '" . $languages_id . "'
                                and poval.language_id = '" . $languages_id . "'";
                            $attributes = tep_db_query($attributes_query);
                        }
                        else{
                            $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $languages_id . "' and poval.language_id = '" . $languages_id . "'");
                        }
                        $attributes_values = tep_db_fetch_array($attributes);

                        $products_ordered_attributes .= "\n\t" . $attributes_values['products_options_name'] . ' ' . $attributes_values['products_options_values_name'];
                    }
                }
                //------insert customer choosen option eof ----
                $total_weight += ($order->products[$i]['qty'] * $order->products[$i]['weight']);
                $total_tax += tep_calculate_tax($total_products_price, $products_tax) * $order->products[$i]['qty'];
                $total_cost += $total_products_price;

                $products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . $products_ordered_attributes . "\n";
            }

            // load the after_process function from the payment modules
            $this->after_process();

            $cart->reset(true);

            // unregister session variables used during checkout
            tep_session_unregister('sendto');
            tep_session_unregister('billto');
            tep_session_unregister('shipping');
            tep_session_unregister('payment');
            tep_session_unregister('comments');

            tep_session_unregister('cart_Paysera_ID');

            tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
        }

        function after_process() {
          return false;
        }

        function output_error() {
          return false;
        }


		function check() {
			if (!isset($this->_check)) {
				$check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYSERA_STATUS'");
				$this->_check = tep_db_num_rows($check_query);
			}
			return $this->_check;
		}


		function install() {
			$this->remove();

			/* Hack */
			global $language, $module_type;
			include_once(DIR_FS_CATALOG_LANGUAGES.$language.'/modules/'.$module_type.'/paysera.php');
			/*/Hack */

            $field = tep_db_fetch_array(tep_db_query("SELECT `currencies_id` FROM " . TABLE_CURRENCIES . " WHERE  `code` = 'LTL' LIMIT 1"));
            if( !$field['currencies_id'] ){
                tep_db_query("insert into " . TABLE_CURRENCIES . "(`title`, `code`, `symbol_left`, `symbol_right`, `decimal_point`, `thousands_point`, `decimal_places`, `value`, `last_updated`) values ('Lithuania litas', 'LTL', 'Lt', '', '.', ',', '2', '2.38189947', NOW())");
            }

			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('".MODULE_PAYMENT_PAYSERA_STATUS_TITLE."', 'MODULE_PAYMENT_PAYSERA_STATUS', 'True', '".MODULE_PAYMENT_PAYSERA_STATUS_DESCRIPTION."', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('".MODULE_PAYMENT_PAYSERA_PROJECT_TITLE."', 'MODULE_PAYMENT_PAYSERA_PROJECT', '', '".MODULE_PAYMENT_PAYSERA_PROJECT_DESCRIPTION."', '6', '4', now())");
			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('".MODULE_PAYMENT_PAYSERA_SIGNATURE_TITLE."', 'MODULE_PAYMENT_PAYSERA_SIGNATURE', '', '".MODULE_PAYMENT_PAYSERA_SIGNATURE_DESCRIPTION."', '6', '4', now())");
			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('".MODULE_PAYMENT_PAYSERA_CURRENCY_TITLE."', 'MODULE_PAYMENT_PAYSERA_CURRENCY', 'Selected Currency', '".MODULE_PAYMENT_PAYSERA_CURRENCY_DESCRIPTION."', '6', '6', 'tep_cfg_select_option(array(\'Selected Currency\',\'Only USD\',\'Only LTL\',\'Only CAD\',\'Only EUR\',\'Only GBP\',\'Only JPY\'), ', now())");
			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('".MODULE_PAYMENT_PAYSERA_ZONE_TITLE."', 'MODULE_PAYMENT_PAYSERA_ZONE', '0', '".MODULE_PAYMENT_PAYSERA_ZONE_DESCRIPTION."', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('".MODULE_PAYMENT_PAYSERA_SORT_ORDER_TITLE."', 'MODULE_PAYMENT_PAYSERA_SORT_ORDER', '0', '".MODULE_PAYMENT_PAYSERA_SORT_ORDER_DESCRIPTION."', '6', '0', now())");
			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, 	configuration_group_id, sort_order, set_function, date_added) values ('".MODULE_PAYMENT_PAYSERA_TESTING_TITLE."', 'MODULE_PAYMENT_PAYSERA_TESTING', 	'No', '".MODULE_PAYMENT_PAYSERA_TESTING_DESCRIPTION."', '6', '0', 'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_PAYSERA_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");

			$d = dir(DIR_FS_CATALOG_LANGUAGES);
			while ( false !== ($entry = $d->read()) ) {
				if( $entry != "." && $entry != ".." && is_dir($d->path.$entry) && is_file($d->path.$entry.'/modules/'.$module_type.'/paysera.php') ){
					$langFile = implode('', file($d->path.$entry.'/modules/'.$module_type.'/paysera.php'));
					preg_match("/MODULE_PAYMENT_PAYSERA_ORDERS_STATUS_20.*['\"]\s*,\s*['\"]([^\"']+)/", $langFile, $constant);

					$language_query = tep_db_query("SELECT languages_id from " . TABLE_LANGUAGES . " WHERE directory = '" . $entry . "'");
					$languageData = tep_db_fetch_array($language_query);

					if( $languageData ){
						tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values (20, '".$languageData['languages_id']."', '".trim($constant[1])."')");
					}
				}
			}
			$d->close();

			tep_db_query("ALTER TABLE ".TABLE_ORDERS." ADD `SSID` VARCHAR( 40 ) NOT NULL");
		}


		function remove() {
			tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE `configuration_key` IN ('" . implode("', '", $this->keys()) . "')");
			tep_db_query("DELETE FROM " . TABLE_ORDERS_STATUS . " WHERE `orders_status_id` = '20'");

			$res = tep_db_query("SHOW FIELDS FROM " . TABLE_ORDERS);
			while ($field = tep_db_fetch_array($res)) {
				if( $field['Field'] == 'SSID' ){
					tep_db_query("ALTER TABLE " . TABLE_ORDERS . " DROP `SSID` ");
					break;
				}
			}
		}


		function keys() {
			return array('MODULE_PAYMENT_PAYSERA_STATUS', 'MODULE_PAYMENT_PAYSERA_TESTING', 'MODULE_PAYMENT_PAYSERA_PROJECT', 'MODULE_PAYMENT_PAYSERA_SIGNATURE', 'MODULE_PAYMENT_PAYSERA_CURRENCY', 'MODULE_PAYMENT_PAYSERA_ZONE', 'MODULE_PAYMENT_PAYSERA_SORT_ORDER', 'MODULE_PAYMENT_PAYSERA_ORDER_STATUS_ID');
		}


		function tep_remove_order($order_id, $restock = false) {
			if ($restock) {
				$order_query = tep_db_query("SELECT products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int)$order_id . "'");
				while ($order = tep_db_fetch_array($order_query)) {
					tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET products_quantity = products_quantity + " . $order['products_quantity'] . ", products_ordered = products_ordered - " . $order['products_quantity'] . " where products_id = '" . (int)$order['products_id'] . "'");
				}
			}

			tep_db_query("DELETE FROM " . TABLE_ORDERS . " WHERE orders_id = '" . (int)$order_id . "'");
			tep_db_query("DELETE FROM " . TABLE_ORDERS_PRODUCTS . " WHERE orders_id = '" . (int)$order_id . "'");
			tep_db_query("DELETE FROM " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " WHERE orders_id = '" . (int)$order_id . "'");
			tep_db_query("DELETE FROM " . TABLE_ORDERS_STATUS_HISTORY . " WHERE orders_id = '" . (int)$order_id . "'");
			tep_db_query("DELETE FROM " . TABLE_ORDERS_TOTAL . " WHERE orders_id = '" . (int)$order_id . "'");
		}

	}


