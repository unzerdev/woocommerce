=== Unzer Payments ===
Contributors: Unzer
Tags: payments, woocommerce
Requires at least: 4.5
Tested up to: 6.3
Stable tag: 1.5.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Author URI: https://unzer.com
Attributions: unzer

Use Unzer plugin for WooCommerce to provide an easy-to-install and use payment gateway integration for your payments

== Description ==

• Unzer Payments for WooCommerce payments by credit card, SOFORT, PayPal, Unzer Invoice (Paylater), iDEAL, EPS, and Giropay and many more
• Easily accept digital payments in your online store with Unzer Plugins.

== Features ==

• Customer-friendly dashboard with up-to-date payment details, real-time billing and refunds made easy (Unzer Insights)
• Payment processing via the Unzer Payment API
• 3D-Secure authentication
• PCI-DSS Level 1 certified
• Seamless integration into the WooCommerce

== Available payment methods ==

• Credit Cards 
• EPS
• Giropay
• iDEAL
• PayPal
• SOFORT
• Unzer Invoice (Paylater)
• Unzer Installment
• Apple Pay
• Alipay
• Bancontact
• Direct Debit
• Direct Debit Secured
• PostFinance Card
• PostFinance E-Finance
• Prepayment
• Przelewy24
• Wechat Pay

== Support ==

Personal support via e-mail to support@unzer.com or +49 (6221) 43101-00

== About Unzer ==

Unzer is one of the leading payment companies in Europe. Over 70,000 retailers trust in the end-to-end solutions for more growth - online, mobile or at the point of sale. Whether international payment processing, risk management or analysis of customer behavior: merchants can put together the data-driven services in a modular way. This means that merchants only need one partner to make their payment future-proof, flexible and innovative.

== Changelog ==

= 1.5.1 =
- Bugfix PHP8.1 compatibility
- Added languages de_AT and de_DE_formal

= 1.5.0 =
- Direct Debit Secured as a new payment method
- SEPA mandate is now available for Direct Debit
- Missing Payment ID with redirect payment methods is now fixed

= 1.4.2 =
- Sending metadata for the plugin with shop and plugin version
- Prepayment text is now avaiable in German as well.
- Order status is now fixed: https://github.com/unzerdev/woocommerce/issues/16

= 1.4.1 =
- Adjustments on Recurring Payments ( CC, PayPal, Direct Debit).
- Save Birth date for Unzer paylater invoice and installment.
- Clean and enhance the code of Unzer invoice paylater.

= 1.4.0 =
- Added Unzer Installment payment method
- New order status: `Waiting for payment` for Unzer Prepayment payment method

= 1.3.0 =
- Added Apple Pay payment method
- Added saving multiple key pairs for the Unzer Invoice payment method
- Added LICENSE and NOTICE files for the plugin in GitHub
- Removed the Capture Amount button for Prepayment payment method
- Fixed bug for negative amount in checkout

= 1.2.1 =
- Bugfix tax excluded calculation

= 1.2.0 =
- New payment method PostFinance E-Finance
- New payment method PostFinance Card
- Chargeback transaction display
- Digital products without shipping address
- Order ID Fix
- Update Unzer SDK 3.1.0
- JS Bugfix in Frontend

= 1.1.0 =
- Save card details for subsequent transactions for registered customers
- Save PayPal details for subsequent transactions for registered customers
- The following payment methods are now available in the plugin:
• Alipay
• Bancontact
• Direct Debit
• Prepayment
• Przelewy24
• Wechat Pay

= 1.0.2 =
Bugfix Logging method / serialization

= 1.0.1 =
Unzer Invoice (Paylater) payment available
Bugfixing

= 1.0.0 =
Release
