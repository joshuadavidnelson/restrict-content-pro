<?php
/**
 * Payment Gateway Base Class
 *
 * @package     Restrict Content Pro
 * @subpackage  Classes/Roles
 * @copyright   Copyright (c) 2012, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.2.3
*/

class RCP_Payment_Gateway_2Checkout extends RCP_Payment_Gateway {

	private $secret_key;
	private $publishable_key;
	private $seller_id;
	private $username;
	private $password;
	private $environment;
	private $sandbox;

	/**
	* get things going
	*
	* @since      2.2.3
	*/
	public function init() {
		global $rcp_options;

		$this->supports[]  = 'one-time';
		$this->supports[]  = 'recurring';
		$this->supports[]  = 'fees';

		$this->test_mode   = isset( $rcp_options['sandbox'] );

		if( $this->test_mode ) {

			$this->secret_key      = isset( $rcp_options['twocheckout_test_private'] )     ? trim( $rcp_options['twocheckout_test_private'] )     : '';
			$this->publishable_key = isset( $rcp_options['twocheckout_test_publishable'] ) ? trim( $rcp_options['twocheckout_test_publishable'] ) : '';
			$this->seller_id       = isset( $rcp_options['twocheckout_test_seller_id'] )   ? trim( $rcp_options['twocheckout_test_seller_id'] )   : '';
			$this->username        = isset( $rcp_options['twocheckout_test_username'] )    ? trim( $rcp_options['twocheckout_test_username'] )    : '';
			$this->password        = isset( $rcp_options['twocheckout_test_password'] )    ? trim( $rcp_options['twocheckout_test_password'] )    : '';
			$this->environment     = 'sandbox';
			$this->sandbox         = true;
			
		} else {

			$this->secret_key      = isset( $rcp_options['twocheckout_live_private'] )     ? trim( $rcp_options['twocheckout_live_private'] )     : '';
			$this->publishable_key = isset( $rcp_options['twocheckout_live_publishable'] ) ? trim( $rcp_options['twocheckout_live_publishable'] ) : '';
			$this->seller_id       = isset( $rcp_options['twocheckout_live_seller_id'] )   ? trim( $rcp_options['twocheckout_live_seller_id'] )   : '';
			$this->username        = isset( $rcp_options['twocheckout_test_username'] )    ? trim( $rcp_options['twocheckout_test_username'] )    : '';
			$this->password        = isset( $rcp_options['twocheckout_test_password'] )    ? trim( $rcp_options['twocheckout_test_password'] )    : '';
			$this->environment     = 'production';
			$this->sandbox         = false;

		}

		if( ! class_exists( 'Twocheckout' ) ) {
			require_once RCP_PLUGIN_DIR . 'includes/libraries/twocheckout/Twocheckout.php';
		} 
	} // end init

	/**
	 * Process registration
	 *
	 * @since 2.2.3
	 */
	public function process_signup() {
		require_once RCP_PLUGIN_DIR . 'includes/libraries/twocheckout/Twocheckout.php';
		Twocheckout::privateKey( $this->secret_key );
		Twocheckout::sellerId( $this->seller_id );
		Twocheckout::username( $this->username );
		Twocheckout::password( $this->password );
		Twocheckout::sandbox( $this->sandbox );

		$paid   = false;
		$member = new RCP_Member( $this->user_id );
		$customer_exists = false;

		$subscription = rcp_get_subscription_details( $_POST['rcp_level'] );
		$sub_id = $this->subscription_id;

		if( empty( $_POST['twoCheckoutToken'] ) ) {
			wp_die( __( 'Missing 2Checkout token, please try again or contact support if the issue persists.', 'rcp' ), __( 'Error', 'rcp' ), array( 'response' => 400 ) );
		}

		// Haven't included other stuff yet untill I get this working
			try {

				$recurrence = $subscription->duration . ' ' . ucfirst( $subscription->duration_unit );
				$charge = Twocheckout_Charge::auth(array(
					'merchantOrderId' => $this->subscription_id,
					'token'           => $_POST['twoCheckoutToken'],
					'currency'        => strtolower( $this->currency ),
					'billingAddr'     => array(
						'name'      => $_POST['rcp_card_name'],
						'addrLine1' => $_POST['rcp_card_address'],
						'city'      => $_POST['rcp_card_city'],
						'state'     => $_POST['rcp_card_state'],
						'zipCode'   => $_POST['rcp_card_zip'],
						'country'   => $_POST['rcp_card_country'],
						'email'     => $this->email,
			        ),
			        "lineItems"     => array(
			        	array(
			        		"recurrence"  => $recurrence,
							"type"        => 'product',
	                        "price"       => $this->amount,
	                        "productId"   => $subscription->id,
	                        "name"        => $subscription->name,
	                        "quantity"    => '1',
	                        "tangible"    => 'N',
	                        "startupFee"  => $subscription->fee . '.00',
	                        "description" => $subscription->description
						)
			        ),
			    ));

			    if( $charge['response']['responseCode'] == 'APPROVED' ) {
			    	$response = $charge['response'];
			        $payment_data = array(
						'date'              => date( 'Y-m-d g:i:s', time() ),
						'subscription'      => $this->subscription_name,
						'payment_type' 		=> 'Credit Card One Time',
						'subscription_key' 	=> $this->subscription_key,
						'amount' 			=> $this->amount,
						'user_id' 			=> $this->user_id,
						'transaction_id'    => $response['transactionId']
					);
					$rcp_payments = new RCP_Payments();
					$rcp_payments->insert( $payment_data );
					$paid = true;

					echo "Thanks for your Order!";
			        echo "<h3>Return Parameters:</h3>";
			        echo "<pre>"; print_r($subscription); echo "</pre>";
			        echo "<pre>"; print_r($charge); echo "</pre>";
			        
			    }
			} catch (Twocheckout_Error $e) {
					print_r($e->getMessage());
			}
	}

	/**
	 * Proccess webhooks
	 *
	 * @since 2.2.3
	 */
	public function process_webhooks() {
	}

	/**
	 * Process registration
	 *
	 * @since 2.2.3
	 */
	public function fields() {
		ob_start();
		?>
		<script type="text/javascript">
			// Called when token created successfully.
		    var successCallback = function(data) {
		        // re-enable the submit button
				jQuery('#rcp_registration_form #rcp_submit').attr("disabled", false);
				// Remove loding overlay
				jQuery('#rcp_ajax_loading').hide();
				// show the errors on the form
				
		        var myForm = document.getElementById('rcp_registration_form');

		        // Set the token as the value for the token input
		        //myForm.token.value = data.response.token.token;

		        var form$ = jQuery('#rcp_registration_form');
				// token contains id, last4, and card type
				var token = data.response.token.token;
				// insert the token into the form so it gets submitted to the server
				form$.append("<input type='hidden' name='twoCheckoutToken' value='" + token + "' />");

		        // IMPORTANT: Here we call `submit()` on the form element directly instead of using jQuery to prevent and infinite token request loop.
		        myForm.submit();
		    };
		    // Called when token creation fails.
		    var errorCallback = function(data) {
		        if (data.errorCode === 200) {
		            tokenRequest();
		        } else {
		            alert(data.errorMsg);
		        }
		        jQuery('#rcp_registration_form').unblock();
				jQuery('#rcp_submit').before( '<div class="rcp_message error"><p class="rcp_error"><span>' + data.reponerrorCode + '</span></p></div>' );
				jQuery('#rcp_submit').val( rcp_script_options.register );
		    };
		    var tokenRequest = function() {
		        // Setup token request arguments
		        var args = {
		            sellerId: '<?php echo $this->seller_id; ?>',
            		publishableKey: '<?php echo $this->publishable_key; ?>',
		            ccNo: jQuery('.rcp_card_number').val(),
		            cvv: jQuery('.rcp_card_cvc').val(),
		            expMonth: jQuery('.rcp_card_exp_month').val(),
		            expYear: jQuery('.rcp_card_exp_year').val()
		        };
		        // Make the token request
		        TCO.requestToken(successCallback, errorCallback, args);
		    };
		    jQuery(document).ready(function($) {
		        // Pull in the public encryption key for our environment
		        TCO.loadPubKey('<?php echo $this->environment; ?>');
		        jQuery("#rcp_registration_form").submit(function(e) {
		            // Call our token request function
		            tokenRequest();

		            // Prevent form from submitting
		            return false;
		        });
		    });
		</script>
		<?php
		require_once( RCP_PLUGIN_DIR . 'templates/twocheckout-form.php' );
		return ob_get_clean();
	}

	/**
	 * Validate additional fields during registration submission
	 *
	 * @since 2.2.3
	 */
	public function validate_fields() {

		if( empty( $_POST['rcp_card_number'] ) ) {
			rcp_errors()->add( 'missing_card_number', __( 'The card number you have entered is invalid', 'rcp' ), 'register' );
		}

		if( empty( $_POST['rcp_card_cvc'] ) ) {
			rcp_errors()->add( 'missing_card_code', __( 'The security code you have entered is invalid', 'rcp' ), 'register' );
		}

		if( empty( $_POST['rcp_card_address'] ) ) {
			rcp_errors()->add( 'missing_card_address', __( 'The address you have entered is invalid', 'rcp' ), 'register' );
		}

		if( empty( $_POST['rcp_card_city'] ) ) {
			rcp_errors()->add( 'missing_card_city', __( 'The city you have entered is invalid', 'rcp' ), 'register' );
		}

		if( empty( $_POST['rcp_card_state'] ) ) {
			rcp_errors()->add( 'missing_card_state', __( 'The state you have entered is invalid', 'rcp' ), 'register' );
		}

		if( empty( $_POST['rcp_card_country'] ) ) {
			rcp_errors()->add( 'missing_card_country', __( 'The country you have entered is invalid', 'rcp' ), 'register' );
		}

		if( empty( $_POST['rcp_card_zip'] ) ) {
			rcp_errors()->add( 'missing_card_zip', __( 'The zip / postal code you have entered is invalid', 'rcp' ), 'register' );
		}

		if( empty( $_POST['rcp_card_name'] ) ) {
			rcp_errors()->add( 'missing_card_name', __( 'The card holder name you have entered is invalid', 'rcp' ), 'register' );
		}

		if( empty( $_POST['rcp_card_exp_month'] ) ) {
			rcp_errors()->add( 'missing_card_exp_month', __( 'The card expiration month you have entered is invalid', 'rcp' ), 'register' );
		}

		if( empty( $_POST['rcp_card_exp_year'] ) ) {
			rcp_errors()->add( 'missing_card_exp_year', __( 'The card expiration year you have entered is invalid', 'rcp' ), 'register' );
		}

		

	}

	/**
	 * Load 2Checkout JS
	 *
	 * @since 2.2.3
	 */
	public function scripts() {
		wp_enqueue_script( 'twocheckout', 'https://www.2checkout.com/checkout/api/2co.min.js', array( 'jquery' ) );
	}
}