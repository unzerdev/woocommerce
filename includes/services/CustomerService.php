<?php /** @noinspection PhpPossiblePolymorphicInvocationInspection */

namespace UnzerPayments\Services;

use Exception;
use UnzerPayments\Gateways\AbstractGateway;
use UnzerPayments\Util;
use UnzerSDK\Constants\ShippingTypes;
use UnzerSDK\Resources\Customer;
use UnzerSDK\Resources\EmbeddedResources\Address;
use UnzerSDK\Resources\EmbeddedResources\CompanyInfo;
use WC_Abstract_Order;
use WC_Order;

class CustomerService {


	public const SESSION_KEY_USER_ID = 'unzer_temp_user_id';

	/**
	 * @var LogService
	 */
	protected $logger;

	public function __construct() {
		$this->logger = new LogService();
	}

	public function calculateCustomerNumber( ?int $customerId = null ) {
		$prefix = 'wp-' . substr( md5( site_url() ), 0, 10 ) . '-';
		if ( $customerId !== null ) {
			return $prefix . $customerId;
		} elseif ( is_user_logged_in() ) {
			return $prefix . get_current_user_id();
		} else {
			static $sessionUserId;
			if ( empty( $sessionUserId ) ) {
				$sessionUserId = isset( $_COOKIE[ self::SESSION_KEY_USER_ID ] ) ? sanitize_text_field( $_COOKIE[ self::SESSION_KEY_USER_ID ] ) : null;
				if ( empty( $sessionUserId ) ) {
					$sessionUserId = uniqid();
					setcookie( self::SESSION_KEY_USER_ID, $sessionUserId, time() + 3600, COOKIEPATH, COOKIE_DOMAIN );
					WC()->session->set( self::SESSION_KEY_USER_ID, $sessionUserId );
				}
			}
			return 'temp-' . $prefix . $sessionUserId;
		}
	}

	public function getCustomerFromData( AbstractGateway $paymentGateway, array $data ) {
		$customer       = null;
		$paymentService = new PaymentService();
		$unzer          = $paymentService->getUnzerManager( $paymentGateway, ! empty( $data['billing_company'] ), get_woocommerce_currency() );
		$customerNumber = $this->calculateCustomerNumber();
		try {
			$customer = $unzer->fetchCustomerByExtCustomerId( $customerNumber );
		} catch ( Exception $e ) {
			// no worries, we cover this by creating a new customer
		}

		if ( empty( $customer ) ) {
			$customer = new Customer();
			$customer->setCustomerId( $customerNumber );
		}

		$billingAddress = $customer->getBillingAddress();

		if ( ! empty( $data['billing_first_name'] ) && ! empty( $data['billing_last_name'] ) ) {
			$customer->setFirstName( $data['billing_first_name'] );
			$customer->setLastName( $data['billing_last_name'] );
			$billingAddress->setName( $customer->getFirstname() . ' ' . $customer->getLastname() );
		}

		if ( isset( $data['billing_email'] ) ) {
			$customer->setEmail( $data['billing_email'] );
		}

		$postBillingCompany = $data['billing_company'] ?? null;

		if ( $postBillingCompany !== null ) {
			$updatedCompany = false;
			if ( $customer->getCompany() !== $postBillingCompany ) {
				$customer->setCompany( $postBillingCompany );
				$updatedCompany = true;
			}

			if ( empty( $customer->getCompany() ) ) {
				$customer->setCompanyInfo( null );
			} else {
				$companyInfo = $customer->getCompanyInfo();
				if ( empty( $companyInfo ) ) {
					$companyInfo = new CompanyInfo();
				}
				if ( empty( $companyInfo->getCompanyType() ) || $updatedCompany ) {
					$companyInfo->setCompanyType( 'Company Type' );
				}
				$customer->setCompanyInfo( $companyInfo );
			}
		}

		if ( isset( $data['billing_address_1'] ) ) {
			$billingAddress->setStreet( $data['billing_address_1'] );
		}

		if ( isset( $data['billing_city'] ) ) {
			$billingAddress->setCity( $data['billing_city'] );
		}

		if ( isset( $data['billing_postcode'] ) ) {
			$billingAddress->setZip( $data['billing_postcode'] );
		}

		if ( isset( $data['billing_country'] ) ) {
			$billingAddress->setCountry( $data['billing_country'] );
		}

		if ( ! empty( $customer->getFirstname() ) && ! empty( $customer->getLastname() ) ) {
			if ( empty( $customer->getId() ) ) {
				try {
					$customer = $unzer->createCustomer( $customer );
				} catch ( Exception $e ) {
					$this->logger->error( 'create customer failed: ' . $e->getMessage() );
				}
			} else {
				try {
					$customer = $unzer->updateCustomer( $customer );
				} catch ( Exception $e ) {
					$this->logger->error( 'update customer failed: ' . $e->getMessage() );
				}
			}
		}

		return $customer->getId() ? $customer : null;
	}

	public function getCustomerFromSession( AbstractGateway $paymentGateway, ?int $orderId = null ): ?Customer {
		if ( ! empty( $orderId ) ) {
			return $this->getCustomerFromOrder( $orderId );
		}
		$data = Util::getNonceCheckedBillingData();

		return $this->getCustomerFromData( $paymentGateway, $data );
	}


	/**
	 * @param int|WC_Order $order
	 * @return Customer
	 */
	public function getCustomerFromOrder( $order ): Customer {
		$order               = is_object( $order ) ? $order : wc_get_order( $order );
		$paymentService      = new PaymentService();
		$unzer               = $paymentService->getUnzerManagerForOrder( $order );
		$unzerCustomerNumber = $this->calculateCustomerNumber( $order->get_customer_id() ?: null );
		try {
			$customer = $unzer->fetchCustomerByExtCustomerId( $unzerCustomerNumber );
		} catch ( Exception $e ) {
			// no worries, we cover this by creating a new customer
		}

		if ( empty( $customer ) ) {
			$customer = new Customer();
			$customer->setCustomerId( $unzerCustomerNumber );
		}

		$customer
			->setFirstname( $order->get_billing_first_name() ?: '' )
			->setLastname( $order->get_billing_last_name() ?: '' )
			->setPhone( $order->get_billing_phone() ?: '' )
			->setCompany( $order->get_billing_company() ?: '' )
			->setEmail( $order->get_billing_email() ?: '' );

		$this->setAddresses( $customer, $order );
		$this->logger->debug( 'customer data', array( $customer->expose() ) );

		if ( $customer->getId() ) {
			try {
				$unzer->updateCustomer( $customer );
			} catch ( Exception $e ) {
				$this->logger->warning( 'update customer failed: ' . $e->getMessage(), array( $customer->expose() ) );
			}
		} else {
			try {
				$customer = $unzer->createCustomer( $customer );
			} catch ( Exception $e ) {
				$this->logger->warning( 'update customer failed: ' . $e->getMessage(), array( $customer->expose() ) );
			}
		}

		return $customer;
	}


	protected function setAddresses( Customer $customer, WC_Abstract_Order $order ) {
		$shippingType = ShippingTypes::EQUALS_BILLING;
		if ( $order->has_shipping_address() && $order->get_formatted_shipping_address() !== $order->get_formatted_billing_address() ) {
			$shippingType = ShippingTypes::DIFFERENT_ADDRESS;
		}

		$billingAddress = ( new Address() )
			->setName( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() )
			->setStreet( $order->get_billing_address_1() )
			->setZip( $order->get_billing_postcode() )
			->setCity( $order->get_billing_city() )
			->setState( $order->get_billing_state() )
			->setCountry( $order->get_billing_country() );

		if ( $order->has_shipping_address() ) {
			$shippingAddress = ( new Address() )
				->setName( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() )
				->setStreet( $order->get_shipping_address_1() )
				->setZip( $order->get_shipping_postcode() )
				->setCity( $order->get_shipping_city() )
				->setState( $order->get_shipping_state() )
				->setCountry( $order->get_shipping_country() )
				->setShippingType( $shippingType );
		} else {
			$shippingAddress = $billingAddress;
			$shippingAddress->setShippingType( ShippingTypes::EQUALS_BILLING );
		}

		$customer
			->setShippingAddress( $shippingAddress )
			->setBillingAddress( $billingAddress );
	}
}
