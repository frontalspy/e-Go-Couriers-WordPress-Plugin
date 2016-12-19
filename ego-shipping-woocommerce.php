<?php
/**
*   Plugin Name: e-Go Shipping Plugin for Woocommerce
*   Description: e-Go Shipping Plugin for Woocommerce
*   Version: 1.0
*   Author: Brendon Ngan
*   Author URI: http://www.rotapix.com
*   Requires at least Woocommerce 2.5
*   Tested with Wordpress 4.4.2
*/


// Deny Direct access
defined( 'ABSPATH' ) or die( 'No looking' );

add_action( 'wp_enqueue_scripts', 'wptuts_scripts_register' );
function wptuts_scripts_register()
{
  // Add the main.js to the head of the site
    wp_register_script( 'ego-custom-js', plugins_url( '/ego-custom.js', __FILE__ ), array('jquery') );
    wp_enqueue_script( 'ego-custom-js' );
}
add_action('init', 'ego_start_session', 1);
add_action('wp_logout', 'ego_end_session');
add_action('wp_login', 'ego_end_session');
function ego_start_session() {
    if(!session_id()) {
        session_start();
    }
}
function ego_end_session() {
    session_destroy ();
}
add_action( 'plugins_loaded', 'ego_shipping_init' );
  if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

      function ego_shipping_init() {
          if ( ! class_exists( 'WC_Ego_Shipping_Method' ) ) {
              class WC_Ego_Shipping_Method extends WC_Shipping_Method {

                  public function __construct() {
                      $this->id                 = 'ego_shipping';
                      $this->method_title       = __( 'e-Go Shipping' );
                      $this->method_description = __( 'e-Go Shipping Couriers' );
                      $this->enabled            = $this->get_option('enabled');
                      $this->pickup_postcode    = $this->get_option('pickup_postcode');
                      $this->pickup_suburb      = $this->get_option('pickup_suburb');
                      $this->delivery           = $this->get_option('delivery_type');
                      $this->title              = "e-Go Shipping";
                      $this->init();
                    
                  }

                  function init() {
                      $this->form_fields = array(
                          'enabled' => array(
                            'title' 		=> __( 'Enable/Disable', 'woocommerce' ),
                            'type' 			=> 'checkbox',
                            'default' 		=> 'yes'
                           ),
                        'pickup_suburb' => array(
                            'title' 		=> __( 'Suburb to pickup product', 'woocommerce' ),
                            'type' 			=> 'text',
                            'default' 		=> 'Ultimo'
                           ),  
                        'pickup_postcode' => array(
                            'title' 		=> __( 'Postcode to pickup product', 'woocommerce' ),
                            'type' 			=> 'text',
                            'default' 		=> '2007'
                           ),  
                        'delivery_type' => array(
                            'title' 		=> __( 'Freight Options (may save up to 30%)', 'woocommerce' ),
                            'type' 			=> 'select',
                            'default' 		=> 'default',
                            'description'   => 'If choosing any Depot 2 X methods, please setup the pickup location to match http://www.e-go.com.au/depot_locations_inframe.do',
                            'options' => array(
                                              'default' => 'Default',
                                              'depot2depot' => 'Depot 2 Depot',
                                              'depot2door' => 'Depot 2 Door',
                                              'door2depot' => 'Door 2 Depot'
                                              )
                           ),  
                      );
                      $this->init_settings();

                      add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                  }              
                
                public function eGoShipping($postcode, $suburb, $page) {
                  // E-Go Calc URL
                  $calcUrl          = "http://www.e-go.com.au/calculatorAPI2";
                  // Home Location for E Go to pickup
                  $pickupCode       = $this->pickup_postcode;
                  $pickupSuburb     = str_replace(" ", "+", $this->pickup_suburb);
                  $pickupUrl        = "?pickuppostcode=". $pickupCode . "&pickupsuburb=" . $pickupSuburb;
                  // Customer location
                  $deliverCode      = $postcode;
                  $deliverSuburb    = str_replace(" ", "+", $suburb);
                  $deliverUrl       = "&deliverypostcode=" . $deliverCode ."&deliverysuburb=" . $deliverSuburb;
                  // Other var
                  $productsUrl      = "";
                  if ($this->delivery == "default") {
                    $deliver == "";
                  }
                  else {
                    $deliver = "&bookingtype=" . $this->delivery;
                  }
                  if ("product" != $page) {
                    $cartProducts = WC()->cart->cart_contents;
                    $i = 0;
                    foreach($cartProducts as $cartProduct => $values){
                      $product = $values['data'];
                      $width = "&width[". $i . "]=" . $product->get_width();
                      $height = "&height[". $i . "]=" . $product->get_length();
                      $type = "&type[" . $i . "]=" . $product->get_shipping_class();
                      $depth = "&depth[". $i . "]=" . $product->get_height();
                      $weight = "&weight[". $i . "]=" . $product->get_weight();
                      $items = "&items[". $i . "]=" . $values['quantity'];
                      $productsUrl .= $type . $width . $height . $depth . $weight . $items;
                      $i++;
                    }
                  }
                  // This is for implmentation for the product page. 
                  // Could turn into an option later on
                  else {
                      $curProduct = $product;
                      $width = "&width=" . $curProduct->get_width();
                      $height = "&height=" . $curProduct->get_length();
                      $type = "&type=" . $curProduct->get_shipping_class();
                      $depth = "&depth=" . $curProduct->get_height();
                      $weight = "&weight=" . $curProduct->get_weight();
                      $productsUrl .= $type . $width . $height . $depth . $weight . $items;
                  }

                  $sendURL = $calcUrl . $pickupUrl . $deliverUrl . $productsUrl . $deliver;
                  $quote = file($sendURL);
                  if (count($quote) > 1 ) {  
                    $quote[0] .= "=";
                    $quote[1] .= "=";
                    $quote[2] .= "=";
                    $quote = implode("", $quote);
                    $quote_field = explode("=", $quote);
                    $_SESSION['eta'] = $quote_field[3];
                    $price = $quote_field[5];
                  } else {
                      $error = "<div class='button alert small-12'>Note: <br> We do not ship to " . $suburb . ", " . $deliverCode . "</div>";
                      wc_add_notice( __( $error), 'error' );
                  }
                    return $price;
              }
                
              public function calculate_shipping( $package ) {
                $cur_stored_post = WC()->customer->get_shipping_postcode();
                $cur_stored_city = WC()->customer->get_shipping_city();
                  if(isset($_POST["calc_shipping_postcode"]) && !empty($_POST["calc_shipping_postcode"]) && isset($_POST["calc_shipping_city"]) && !empty($_POST["calc_shipping_city"])) {
                    $price = $this->eGoShipping($_POST["calc_shipping_postcode"], $_POST["calc_shipping_city"], "cart");    
                  }
                  elseif(isset($_POST["s_postcode"]) && !empty($_POST["s_postcode"]) && isset($_POST["s_city"]) && !empty($_POST["s_city"])) {
                    $price = $this->eGoShipping($_POST["s_postcode"], $_POST["s_city"], "checkout");    
                  }
                  elseif(is_cart() && !empty($cur_stored_post) && !empty($cur_stored_city)) {
                    $cust_postcode = WC()->customer->get_shipping_postcode();
                    $cust_suburb = WC()->customer->get_shipping_city();
                    $price = $this->eGoShipping($cust_postcode, $cust_suburb, 'cart');
                  }
                    $rate = array(
                        'id' => $this->id,
                        'label' => $this->title,
                        'cost' => $price,
                        'calc_tax' => 'per_order'
                    );
                    $this->add_rate( $rate );
                }
                
            function ego_error_check() {
              $shipping = $_POST['shipping_method'];
              $cust_postcode = WC()->customer->get_shipping_postcode();
              $cust_suburb = WC()->customer->get_shipping_city();
              if ("ego_shipping" == $shipping[0] && (empty($cust_postcode) || empty($cust_suburb))) {
                  $error = "<div class='button alert small-12'>Please Enter Suburb/Postcode</div>";
                  wc_print_notice( __( $error), 'error' );
                }
            }
          }
        }
      }
    
    add_action( 'woocommerce_after_shipping_rate', 'check_depot');
  
  function check_depot() {
    $shipping = $_POST['shipping_method'];
    if ("ego_depot2depot" == $shipping[0]) {
      ?>
      <form class="woocommerce-shipping-calculator" action="<?php home_url(); ?>" method="post">
        <select name="depot_location" id="depot_location">
        <option>-- Select Location --</option>
         <optgroup label="ACT">
            <option value="MITCHELL/2911">MITCHELL, 2911</option>
          </optgroup>
          <optgroup label="NSW">
            <option value="ALBURY/2640">ALBURY, 2640</option>
            <option value="BELLA+VISTA/2153">BELLA VISTA, 2153</option>
            <option value="BERKELEY/2506">BERKELEY, 2506</option>
            <option value="BLACKTOWN/2148">BLACKTOWN, 2148</option>
            <option value="CHATHAM/2430">CHATHAM, 2430</option>
            <option value="COFFS+HARBOUR/2450">COFFS HARBOUR, 2450</option>
            <option value="DEE+WHY/2099">DEE WHY, 2099</option>
            <option value="DUBBO/2830">DUBBO, 2830</option>
            <option value="GLEN+INNES/2370">GLEN INNES, 2370</option>
            <option value="GOSFORD/2250">GOSFORD, 2250</option>
            <option value="GOULBURN/2580">GOULBURN, 2580</option>
            <option value="GUNNEDAH/2380">GUNNEDAH, 2380</option>
            <option value="INVERELL/2360">INVERELL, 2360</option>
            <option value="SOUTH+LISMORE/2480">SOUTH LISMORE, 2480</option>
            <option value="MOREE/2400">MOREE, 2400</option>
            <option value="MORUYA+DEPOT/2537">MORUYA DEPOT, 2537</option>
            <option value="NARRABRI/2390">NARRABRI, 2390</option>
            <option value="NEWCASTLE/2300">NEWCASTLE, 2300</option>
            <option value="NORTH+SYDNEY/2060">NORTH SYDNEY, 2060</option>
            <option value="NYNGAN/2825">NYNGAN, 2825</option>
            <option value="ORANGE/2800">ORANGE, 2800</option>
            <option value="PARRAMATTA/2150">PARRAMATTA, 2150</option>
            <option value="PORT+MACQUARIE/2444">PORT MACQUARIE, 2444</option>
            <option value="SOUTH+GRAFTON/2460">SOUTH GRAFTON, 2460</option>
            <option value="TAREN+POINT/2229">TAREN POINT, 2229</option>
            <option value="ULTIMO/2007">ULTIMO, 2007</option>
            <option value="VILLAWOOD/2163">VILLAWOOD, 2163</option>
            <option value="TAMWORTH/2340">TAMWORTH, 2340</option>
            <option value="WAGGA+WAGGA/2650">WAGGA WAGGA, 2650</option>
          </optgroup>
          <optgroup label="NT">
            <option value="DARWIN/0800">DARWIN, 0800</option>
            <option value="ALICE+SPRINGS/0870">ALICE SPRINGS, 0870</option>
          </optgroup>
          <optgroup label="QLD">
            <option value="ARUNDEL/4214">ARUNDEL, 4214</option>
            <option value="BRISBANE/4000">BRISBANE, 4000</option>
            <option value="BUNDABERG/4670">BUNDABERG, 4670</option>
            <option value="CABOOLTURE/4510">CABOOLTURE, 4510</option>
            <option value="CAIRNS/4870">CAIRNS, 4870</option>
            <option value="CALOUNDRA/4551">CALOUNDRA, 4551</option>
            <option value="CAPALABA/4157">CAPALABA, 4157</option>
            <option value="GARBUTT/4814">GARBUTT, 4814</option>
            <option value="IPSWICH/4305">IPSWICH, 4305</option>
            <option value="MARYBOROUGH/4650">MARYBOROUGH, 4650</option>
            <option value="MOUNT+ISA/4825">MOUNT ISA, 4825</option>
            <option value="PAGET/4740">PAGET, 4740</option>
            <option value="PINKENBA/4008">PINKENBA, 4008</option>
            <option value="SOUTH+GLADSTONE/4680">SOUTH GLADSTONE, 4680</option>
            <option value="TOOWOOMBA/4350">TOOWOOMBA, 4350TOOWOOMBA, 4350</option>
          </optgroup>
          <optgroup label="SA">
            <option value="BERRI/5343">BERRI, 5343</option>
            <option value="CLARE/5453">CLARE, 5453</option>
            <option value="KADINA/5554">KADINA, 5554</option>
            <option value="KIDMAN PARK/5025">KIDMAN PARK, 5025</option>
            <option value="MOUNT+GAMBIER/5290">MOUNT GAMBIER, 5290</option>
            <option value="PORT+AUGUSTA/5700">PORT AUGUSTA, 5700</option>
            <option value="PORT+LINCOLN/5606">PORT LINCOLN, 5606</option>
            <option value="PORT+PIRIE/5540">PORT PIRIE, 5540</option>
            <option value="WHYALLA/5600">WHYALLA, 5600</option>
          </optgroup>
          <optgroup label="TAS">
            <option value="EAST+DEVONPORT/7310">EAST DEVONPORT, 7310</option>
            <option value="GLENORCHY/7010">GLENORCHY, 7010</option>
            <option value="LAUNCESTON/7250">LAUNCESTON, 7250</option>
          </optgroup>
          <optgroup label="VIC">
            <option value="BOX HILL/3128">BOX HILL, 3128</option>
            <option value="CAMPBELLFIELD/3061">CAMPBELLFIELD, 3061</option>
            <option value="DANDENONG/3175">DANDENONG, 3175</option>
            <option value="EAST+BENDIGO/3550">EAST BENDIGO, 3550</option>
            <option value="DANDENONG+SOUTH/3175">DANDENONG SOUTH, 3175</option>
            <option value="HAWTHORN/3122">HAWTHORN, 3122</option>
            <option value="HOPPERS+CROSSING/3029">HOPPERS CROSSING, 3029</option>
            <option value="HORSHAM/3400">HORSHAM, 3400</option>
            <option value="MELBOURNE/3000">MELBOURNE, 3000</option>
            <option value="MILDURA/3500">MILDURA, 3500</option>
            <option value="MITCHELL+PARK/3355">MITCHELL PARK, 3355</option>
            <option value="MOOLAP/3221">MOOLAP, 3221</option>
            <option value="MOONEE+PONDS/3039">MOONEE PONDS, 3039</option>
            <option value="SHEPPARTON+EAST/3631">SHEPPARTON EAST, 3631</option>
            <option value="SOUTH+MELBOURNE/3205">SOUTH MELBOURNE, 3205</option>
            <option value="TRARALGON/3844">TRARALGON, 3844</option>
          </optgroup>
          <optgroup label="WA">
            <option value="ALBANY/6330">ALBANY, 6330</option>
            <option value="BUNBURY/6230">BUNBURY, 6230</option>
            <option value="CARNARVON/6701">CARNARVON, 6701</option>
            <option value="COCKBURN+CENTRAL/6164">COCKBURN CENTRAL, 6164</option>
            <option value="GERALDTON/6530">GERALDTON, 6530</option>
            <option value="KARRATHA/6714">KARRATHA, 6714</option>
            <option value="KUNUNURRA/6743">KUNUNURRA, 6743</option>
            <option value="MINYIRR/6725">MINYIRR, 6725</option>
            <option value="NEWMAN/6753">NEWMAN, 6753</option>
            <option value="OSBORNE+PARK/6017">OSBORNE PARK, 6017</option>
            <option value="PORT+HEDLAND/6721">PORT HEDLAND, 6721</option>
            <option value="ROCKINGHAM/6168">ROCKINGHAM, 6168</option>
            <option value="TOM+PRICE/6751">TOM PRICE, 6751</option>
            <option value="WELSHPOOL/6106">WELSHPOOL, 6106</option>
            <option value="WEST+KALGOORLIE/6430">WEST KALGOORLIE, 6430</option>
          </optgroup>
        </select>
        <p><button type="submit" class="button"><?php _e( 'Update Totals', 'woocommerce' ); ?></button></p>
	</form>
      <?php      
    }
  }

      add_action( 'woocommerce_shipping_init', 'ego_shipping_init' );

      function ego_shipping_method( $methods ) {
          $methods['eGo Shipping'] = 'WC_Ego_Shipping_Method';
          return $methods;
      }

      add_filter( 'woocommerce_shipping_methods', 'ego_shipping_method' );
  }

    function add_ego_city( $false ) {
         $false = true;
         return $false;
    }

  add_filter( 'woocommerce_shipping_calculator_enable_city' , 'add_ego_city', 10, 1 );
  add_action( 'woocommerce_after_shipping_calculator', array('WC_Ego_Shipping_Method', 'ego_error_check') );
  add_filter( 'woocommerce_cart_shipping_method_full_label', 'WC_Ego_remove_local_pickup_free_label', 10, 2 );


  function WC_Ego_remove_local_pickup_free_label($full_label, $method){
      if ("ego_shipping" == $method->id) {        
        if(strpos($full_label, '(Free)') !== false) {
          $full_label = str_replace("(Free)"," - Please Set Valid Address",$full_label);
        }
        else {
          $eta = $_SESSION['eta'];
          $full_label .= "". " - Delivers " . $eta;
        }
      }
      return $full_label;
  }
