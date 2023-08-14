<?php /** @noinspection PhpPossiblePolymorphicInvocationInspection */

namespace UnzerPayments\Services;


use Exception;
use UnzerPayments\Main;
use UnzerSDK\Constants\CompanyRegistrationTypes;
use UnzerSDK\Constants\ShippingTypes;
use UnzerSDK\Resources\Customer;
use UnzerSDK\Resources\EmbeddedResources\Address;
use UnzerSDK\Resources\EmbeddedResources\CompanyInfo;
use WC_Abstract_Order;
use WC_Order;

class CustomerService
{

    /**
     * @var LogService
     */
    protected $logger;

    public function __construct()
    {
        $this->logger = new LogService();
    }

    /**
     * @param int|WC_Order $order
     * @return Customer
     */
    public function getCustomerFromOrder($order): Customer
    {
        $order = is_object($order) ? $order : wc_get_order($order);

        if (is_user_logged_in()) {
            $paymentService = new PaymentService();
            $unzer = $paymentService->getUnzerManagerForOrder($order);
            try {
                $customer = $unzer->fetchCustomerByExtCustomerId('wp-' . wp_get_current_user()->ID);
            } catch (Exception $e) {
                //no worries, we cover this by creating a new customer
            }
        }

        if (empty($customer)) {
            $customer = new Customer();
            if (is_user_logged_in()) {
                $customer->setCustomerId('wp-' . wp_get_current_user()->ID);
            }
        }

        $customer
            ->setFirstname($order->get_billing_first_name() ?: '')
            ->setLastname($order->get_billing_last_name() ?: '')
            ->setPhone($order->get_billing_phone() ?: '')
            ->setCompany($order->get_billing_company() ?: '')
            ->setEmail($order->get_billing_email() ?: '');


        $this->setDateOfBirth($customer, $order);
        $this->setCompanyInfo($customer, $order);
        $this->setAddresses($customer, $order);
        $this->logger->debug('customer data', [$customer->expose()]);

        if ($customer->getId()) {
            try {
                /** @noinspection PhpUndefinedVariableInspection */
                $unzer->updateCustomer($customer);
            } catch (Exception $e) {
                $this->logger->warning('update customer failed: ' . $e->getMessage(), [$customer->expose()]);
            }
        }

        return $customer;
    }

    protected function setDateOfBirth(Customer $customer, WC_Abstract_Order $order)
    {
        $dob = $order->get_meta(Main::ORDER_META_KEY_DATE_OF_BIRTH);
        if (empty($dob) && !empty($_POST['unzer-dob'])) {
            $dob = $_POST['unzer-dob'];
        }
        if (!empty($dob)) {
            $customer->setBirthDate(date('Y-m-d', strtotime($dob)));
        }
    }

    protected function setCompanyInfo(Customer $customer, WC_Abstract_Order $order)
    {
        if ($order->get_billing_company()) {
            $companyType = $order->get_meta(Main::ORDER_META_KEY_COMPANY_TYPE);
            if (empty($companyType) && !empty($_POST['unzer-invoice-company-type'])) {
                $companyType = $_POST['unzer-invoice-company-type'];
            }
            if (!empty($companyType)) {
                $companyInfo = (new CompanyInfo())
                    ->setCompanyType($companyType)
                    ->setRegistrationType(CompanyRegistrationTypes::REGISTRATION_TYPE_NOT_REGISTERED)
                    ->setFunction('OWNER');
                $customer->setCompanyInfo($companyInfo);
            }
        }
    }

    protected function setAddresses(Customer $customer, WC_Abstract_Order $order)
    {
        $shippingType = ShippingTypes::EQUALS_BILLING;
        if ($order->has_shipping_address() && $order->get_formatted_shipping_address() !== $order->get_formatted_billing_address()) {
            $shippingType = ShippingTypes::DIFFERENT_ADDRESS;
        }

        $billingAddress = (new Address())
            ->setName($order->get_billing_first_name() . ' ' . $order->get_billing_last_name())
            ->setStreet($order->get_billing_address_1())
            ->setZip($order->get_billing_postcode())
            ->setCity($order->get_billing_city())
            ->setState($order->get_billing_state())
            ->setCountry($order->get_billing_country());

        if ($order->has_shipping_address()) {
            $shippingAddress = (new Address())
                ->setName($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name())
                ->setStreet($order->get_shipping_address_1())
                ->setZip($order->get_shipping_postcode())
                ->setCity($order->get_shipping_city())
                ->setState($order->get_shipping_state())
                ->setCountry($order->get_shipping_country())
                ->setShippingType($shippingType);
        } else {
            $shippingAddress = $billingAddress;
            $shippingAddress->setShippingType(ShippingTypes::EQUALS_BILLING);
        }

        $customer
            ->setShippingAddress($shippingAddress)
            ->setBillingAddress($billingAddress);
    }

}