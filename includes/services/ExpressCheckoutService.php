<?php

namespace UnzerPayments\Services;

use UnzerSDK\Resources\Customer;
use UnzerSDK\Resources\Payment;

class ExpressCheckoutService
{

    public const ADDRESS_PART_PLACEHOLDER = '-----';
    public const SESSION_PAYPAL_PAYMENT_ID = 'paypal-express-checkout-payment-id';
    public const SESSION_PAYPAL_PAYMENT_TYPE_ID = 'paypal-express-checkout-payment-type-id';
    public const SESSION_GOOGLE_PAYMENT_TYPE_ID = 'google-express-checkout-payment-type-id';
    public const SESSION_APPLEPAY_PAYMENT_TYPE_ID = 'applepay-express-checkout-payment-type-id';
    public const SESSION_SELECTED_EXPRESS_METHOD = 'express-checkout-payment-id-selected';

    public function startCheckoutSessionFromUnzerPayment(Payment $payment, ?string $paymentMethodId): void
    {
        $data = $this->getRegisterDataFromUnzerPayment($payment);
        $this->startCheckoutDataWithNormalizedCustomerData($data, $paymentMethodId);
    }

    public function startCheckoutSessionFromGooglePayData(array $paymentData, ?string $paymentMethodId): void
    {
        $data = $this->getRegisterDataFromGooglePayData($paymentData);
        $this->startCheckoutDataWithNormalizedCustomerData($data, $paymentMethodId);
    }

    public function startCheckoutSessionFromApplePayData(array $paymentData, array $shippingContact, array $billingContact, ?string $paymentMethodId): void
    {
        $data = $this->getRegisterDataFromApplePayData($shippingContact, $billingContact);
        $this->startCheckoutDataWithNormalizedCustomerData($data, $paymentMethodId);
    }

    private function setPaymentMethod(string $paymentMethodId): void
    {
        WC()->session->set('chosen_payment_method', $paymentMethodId);
    }

    private function createOrGetGuestCustomer(array $data): int
    {
        $email = sanitize_email($data['email'] ?? '');

        if (!$email) {
            return 0;
        }

        $existingUser = get_user_by('email', $email);

        if ($existingUser) {
            return (int) $existingUser->ID;
        }

        if (!empty($data['guest'])) {
            return 0;
        }

        $password = $data['password'] ?? wp_generate_password();

        $userId = wc_create_new_customer(
            $email,
            $email,
            $password,
            [
                'first_name' => sanitize_text_field($data['firstName'] ?? ''),
                'last_name'  => sanitize_text_field($data['lastName'] ?? ''),
            ]
        );

        if (is_wp_error($userId)) {
            throw new \Exception($userId->get_error_message());
        }

        return (int) $userId;
    }

    private function setGuestCheckoutData(array $data): void
    {
        $billing  = $data['billingAddress'] ?? [];
        $shipping = $data['shippingAddress'] ?? $billing;

        WC()->customer->set_billing_first_name($data['firstName'] ?? '');
        WC()->customer->set_billing_last_name($data['lastName'] ?? '');
        WC()->customer->set_billing_email($data['email'] ?? '');

        $this->applyAddressToCustomer($billing, 'billing');
        $this->applyAddressToCustomer($shipping, 'shipping');

        WC()->customer->save();
    }

    private function updateCustomerAddresses(array $data, int $customerId): void
    {
        $customer = new \WC_Customer($customerId);

        $billing  = $data['billingAddress'] ?? [];
        $shipping = $data['shippingAddress'] ?? $billing;

        $this->applyAddressToCustomer($billing, 'billing', $customer);
        $this->applyAddressToCustomer($shipping, 'shipping', $customer);

        $customer->set_billing_first_name($data['firstName'] ?? '');
        $customer->set_billing_last_name($data['lastName'] ?? '');
        $customer->set_billing_email($data['email'] ?? '');

        $customer->save();
    }

    private function applyAddressToCustomer(array $address, string $type, ?\WC_Customer $customer = null): void
    {
        $customer = $customer ?: WC()->customer;

        $prefix = $type . '_';

        $customer->{"set_{$prefix}first_name"}($address['firstName'] ?? '');
        $customer->{"set_{$prefix}last_name"}($address['lastName'] ?? '');
        $customer->{"set_{$prefix}company"}($address['company'] ?? '');
        $customer->{"set_{$prefix}address_1"}($address['street'] ?? '');
        $customer->{"set_{$prefix}address_2"}($address['additionalAddressLine1'] ?? '');
        $customer->{"set_{$prefix}postcode"}($address['zipcode'] ?? '');
        $customer->{"set_{$prefix}city"}($address['city'] ?? '');
        $customer->{"set_{$prefix}country"}($address['country'] ?? '');
        $customer->{"set_{$prefix}phone"}($address['phoneNumber'] ?? '');
    }

    private function startCheckoutDataWithNormalizedCustomerData(?array $data, ?string $paymentMethodId): void
    {
        if ($data === null) {
            throw new \Exception('no customer data');
        }

        if (!WC()->session) {
            throw new \Exception('WooCommerce session not available');
        }

        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods') ?: [];

        if (!WC()->customer) {
            wc_load_cart();
        }

        if (is_user_logged_in()) {
            $customerId = get_current_user_id();

            $this->updateCustomerAddresses($data, $customerId);

            if ($paymentMethodId) {
                $this->setPaymentMethod($paymentMethodId);
            }

            $this->setShippingMethod($chosen_shipping_methods);
            $this->setFluidCheckoutSession($data);

            return;
        }

        $customerId = $this->createOrGetGuestCustomer($data);

        if ($customerId > 0) {
            $this->updateCustomerAddresses($data, $customerId);
            wp_set_current_user($customerId);
            wp_set_auth_cookie($customerId);

            WC()->customer = new \WC_Customer($customerId, true);
            $existingUser = get_user_by('ID', $customerId);
            do_action('wp_login', $existingUser->user_login, $existingUser);
            if (WC()->session) {
                WC()->session->set_customer_session_cookie(true);
                WC()->session->set('customer_id', $existingUser->ID);
            }
        } else {
            $this->setGuestCheckoutData($data);
        }

        if ($paymentMethodId) {
            $this->setPaymentMethod($paymentMethodId);
        }

        $this->setShippingMethod($chosen_shipping_methods);
        $this->setFluidCheckoutSession($data);
    }

    private function setFluidCheckoutSession($data): void
    {
        $fields = [
            'billing_first_name'  => $data['firstName'],
            'billing_last_name'   => $data['lastName'],
            'billing_company'     => $data['company'],
            'billing_email'       => $data['email'],
            'billing_country'     => $data['country'],
            'billing_address_1'   => $data['street'],
            'billing_address_2'   => $data['additionalAddressLine1'],
            'billing_postcode'    => $data['zipcode'],
            'billing_city'        => $data['city'],

            'shipping_first_name' => $data['firstName'],
            'shipping_last_name'  => $data['lastName'],
            'shipping_company'    => $data['company'],
            'shipping_email'      => $data['email'],
            'shipping_country'    => $data['country'],
            'shipping_address_1'  => $data['street'],
            'shipping_address_2'  => $data['additionalAddressLine1'],
            'shipping_postcode'   => $data['zipcode'],
            'shipping_city'       => $data['city'],
        ];

        foreach ($fields as $key => $value) {
            WC()->session->set('fc_' . $key, $value);
        }
    }

    private function setShippingMethod($chosen_shipping_methods): void
    {
        $packages = WC()->shipping()->get_packages();
        $valid_chosen_methods = [];
        foreach ($packages as $package_index => $package) {
            $old_method = $chosen_shipping_methods[$package_index] ?? null;
            if ($old_method && isset($package['rates'][$old_method])) {
                $valid_chosen_methods[$package_index] = $old_method;
            }
        }

        if (!empty($valid_chosen_methods)) {
            WC()->session->set('chosen_shipping_methods', $valid_chosen_methods);
        }
    }

    private function getRegisterDataFromGooglePayData(array $paymentData): ?array
    {
        $data = [];
        $password = bin2hex(random_bytes(16));
        $name = $paymentData['paymentMethodData']['info']['billingAddress']['name'] ?? self::ADDRESS_PART_PLACEHOLDER . ' ' . self::ADDRESS_PART_PLACEHOLDER;
        $names = $this->separateName($name);

        $shippingAddress = $this->getAddressFromGoogleData($paymentData['shippingAddress'] ?? []);
        $billingAddress = $this->getAddressFromGoogleData($paymentData['paymentMethodData']['info']['billingAddress'] ?? []);
        if (!$this->isAddressDataBagComplete($billingAddress)) {
            $billingAddress = $shippingAddress;
        }

        $data = array_merge($data, [
            'firstName' => $names['firstName'],
            'lastName' => $names['lastName'],
            'guest' => true,
            'email' => $paymentData['email'] ?? '',
            'acceptedDataProtection' => true,
            'password' => $password,
            'passwordConfirmation' => $password,
            'billingAddress' => $billingAddress,
            'shippingAddress' => $shippingAddress,
        ]);

        return $data;
    }

    private function getRegisterDataFromApplePayData(array $shippingContact, array $billingContact): ?array
    {
        $data = [];
        $password = bin2hex(random_bytes(16));
        $firstName = $shippingContact['givenName'] === '' ? '----' : $shippingContact['givenName'];
        $lastName = $shippingContact['familyName'] === '' ? '----' : $shippingContact['familyName'];

        $shippingAddress = $this->getAddressFromApplePayData($shippingContact ?? []);
        $billingAddress = $this->getAddressFromApplePayData($billingContact ?? []);
        if (!$this->isAddressDataBagComplete($billingAddress)) {
            $billingAddress = $shippingAddress;
        }

        $data = array_merge($data, [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'guest' => true,
            'email' => $shippingContact['emailAddress'] ?? '',
            'acceptedDataProtection' => true,
            'password' => $password,
            'passwordConfirmation' => $password,
            'billingAddress' => $billingAddress,
            'shippingAddress' => $shippingAddress,
        ]);

        return $data;
    }

    private function getRegisterDataFromUnzerPayment(Payment $payment): ?array
    {
        $data = [];

        $customer = $payment->getCustomer();

        if ($customer === null) {
            return null;
        }

        $shippingAddress = $this->getAddressFromUnzerCustomer($customer, 'shipping');
        $billingAddress = $this->getAddressFromUnzerCustomer($customer);
        if (!$this->isAddressDataBagComplete($billingAddress)) {
            $billingAddress = $shippingAddress;
        }

        $password = bin2hex(random_bytes(16));
        $data = array_merge($data, [
            'firstName' => $customer->getFirstname(),
            'lastName' => $customer->getLastname(),
            'guest' => true,
            'email' => $customer->getEmail(),
            'acceptedDataProtection' => true,
            'password' => $password,
            'passwordConfirmation' => $password,
            'billingAddress' => $billingAddress,
            'shippingAddress' => $shippingAddress,
        ]);

        return $data;
    }

    private function isAddressDataBagComplete(array $dataBag): bool
    {
        foreach ($dataBag as $key => $value) {
            if ($value === self::ADDRESS_PART_PLACEHOLDER) {
                return false;
            }
        }

        return true;
    }

    private function getAddressFromGoogleData(array $address): ?array
    {
        $nameParts = $this->separateName($address['name'] ?? self::ADDRESS_PART_PLACEHOLDER . ' ' . self::ADDRESS_PART_PLACEHOLDER);

        return [
            'firstName' => $nameParts['firstName'],
            'lastName' => $nameParts['lastName'],
            'city' => $address['locality'] ?? self::ADDRESS_PART_PLACEHOLDER,
            'country' => $address['countryCode'],
            'street' => $address['address1'] ?? self::ADDRESS_PART_PLACEHOLDER,
            'additionalAddressLine1' => $address['address2'] ?? null,
            'additionalAddressLine2' => $address['address3'] ?? null,
            'zipcode' => $address['postalCode'] ?? self::ADDRESS_PART_PLACEHOLDER,
            'phoneNumber' => '',
            'company' => '',
        ];
    }

    private function getAddressFromApplePayData(array $address): ?array
    {
        return [
            'firstName' => $address['givenName'] === '' ? '----' : $address['givenName'],
            'lastName' => $address['familyName'] === '' ? '----' : $address['familyName'],
            'city' => $address['locality'] ?? self::ADDRESS_PART_PLACEHOLDER,
            'country' => $address['countryCode'],
            'street' => $address['addressLines'][0] ?? self::ADDRESS_PART_PLACEHOLDER,
            'additionalAddressLine1' => $address['addressLines'][1] ?? null,
            'additionalAddressLine2' => $address['addressLines'][2] ?? null,
            'zipcode' => $address['postalCode'] ?? self::ADDRESS_PART_PLACEHOLDER,
            'phoneNumber' => $address['phoneNumber'] ?? '',
            'company' => '',
        ];
    }

    private function getAddressFromUnzerCustomer(Customer $customer, string $addressType = 'billing'): ?array
    {
        $address = ($addressType === 'billing' ? $customer->getBillingAddress() : $customer->getShippingAddress());

        if (empty($address->getZip())) {
            $address->setZip(self::ADDRESS_PART_PLACEHOLDER);
        }
        if (empty($address->getCity())) {
            $address->setCity(self::ADDRESS_PART_PLACEHOLDER);
        }
        if (empty($address->getStreet())) {
            $address->setStreet(self::ADDRESS_PART_PLACEHOLDER);
        }
        if (empty($address->getName())) {
            $address->setName(self::ADDRESS_PART_PLACEHOLDER . ' ' . self::ADDRESS_PART_PLACEHOLDER);
        }

        $nameParts = $this->separateName($address->getName());

        return [
            'firstName' => $nameParts['firstName'],
            'lastName' => $nameParts['lastName'],
            'city' => $address->getCity(),
            'country' => $address->getCountry(),
            'street' => $address->getStreet(),
            'zipcode' => $address->getZip(),
            'phoneNumber' => '',
            'company' => '',
        ];
    }

    private function separateName(string $name): array
    {
        $exploded = explode(' ', $name);
        if (\count($exploded) > 1) {
            $lastName = array_pop($exploded);

            return [
                'firstName' => implode(' ', $exploded),
                'lastName' => $lastName,
            ];
        }

        return [
            'firstName' => $exploded[0],
            'lastName' => '.',
        ];
    }

}