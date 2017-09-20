<?php
/**
 * WP ZenDesk HelpCenter API (https://developer.zendesk.com/rest_api/docs/help_center/introduction)
 *
 * @package WP-ZD-HelpCenter-API
 */

/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) { exit; }


if ( ! class_exists( 'WPHeartlandAPI' ) ) {

	/**
	 * Snexy API Class.
	 */
	class WPHeartlandAPI {

		private $public_key;

		private $private_key;

		private $charge_service;
		private $cs;

		public function __construct( $public_key, $secret_key ){
			$this->public_key = $public_key;
			$this->private_key = $secret_key;

			$config = new HpsServicesConfig();
			$config->secretApiKey =  "skapi_cert_MazmAQBXMF8A-0tCmBAKv7j6-5MNP3h6-Czau_eS4A";
			$this->charge_service = new HpsCreditService($config);

			$this->cs = $this->charge_service; // Alias.
		}

		/*
 		 * Credit Card Payments
		 * https://developer.heartlandpaymentsystems.com/Documentation/v2/credit-card-payments/#create-a-customer
		 */

		public function create_customer( $zipcode ){
			$card_holder = new HpsCardHolder();
			$address = new HpsAddress();
			$address->zip = $zipcode;
			$card_holder->address = $address;

			return $card_holder;
		}

		public function charge_single_token( $amount, $token, $card_holder ){
	    return $this->cs->charge(10, "usd", "supt_4KxXnE7JKtQVFcxjQaikxNNT", $card_holder);
		}

		public function verify_credit_card_token( $token, $card_holder ){
			return $this->cs->verify( $token, $card_holder );
		}

		// Authorized a purchase, DOES NOT put it in the batch. Can be committed using the capture method.
		public function authorize_credit_card_token( $amount, $token, $card_holder ){
			return $this->cs->authorize( $amount, 'usd', $token, $card_holder );
		}

		public function request_multiuse_token( $amount, $token ){
			$response = $this->cs->authorize( $amount, 'usd', $token, null, true );
			return $response->tokenData->tokenValue;
		}

		public function charge_multiuse_token( $amount, $mutoken, $card_holder ){
			$mu_token = new HspTokenData();
			$mu_token->tokenValue = $mutoken;
			return $this->cs->charge( $amount, $mutoken, $card_holder );
		}

		public function get_transaction_history( $start_date, $end_date, $filter = '' ){
			$dateFormat = 'Y-m-d\TH:i:s.00\Z';
	    $dateMinus10 = new DateTime();
	    $dateMinus10->sub(new DateInterval('P10D')); // History over 10 days.
	    $dateMinus10Utc = gmdate($dateFormat, $dateMinus10->Format('U')); // Get start date.
	    $nowUtc = gmdate($dateFormat); // Get end date.

	    $items = $this->cs->listTransactions($dateMinus10Utc, $nowUtc, "CreditSale");

			return $items;
		}
	}
}
