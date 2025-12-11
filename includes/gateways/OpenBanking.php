<?php

namespace UnzerPayments\Gateways;

use UnzerPayments\Gateways\Blocks\OpenBankingBlock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OpenBanking extends AbstractGateway {

	const GATEWAY_ID            = 'unzer_open_banking';
	const BLOCK_CLASS           = OpenBankingBlock::class;
	public $allowedCountries    = array( 'DE' );
	public $allowedCurrencies   = array( 'EUR' );
	public $paymentTypeResource = \UnzerSDK\Resources\PaymentTypes\OpenbankingPis::class;
	public $method_title        = 'Unzer Direct Bank Transfer';
	public $method_description;
	public $title       = 'Direct Bank Transfer';
	public $description = '';
	public $id          = self::GATEWAY_ID;
	public $plugin_id;
	public $supports = array(
		'products',
		'refunds',
	);

	public function has_fields() {
		return true;
	}

	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}

		$html = '
            
            <div class="unzer-ui-container"></div>
            <template class="unzer-ui-template">
                    <unzer-payment
                        publicKey="' . esc_attr( $this->get_public_key() ) . '"
                        locale="' . esc_attr( get_locale() ) . '"
                    >
                        <unzer-open-banking></unzer-open-banking>
                    </unzer-payment>
            </template>';
		echo wp_kses( $html, $this->get_allowed_html_tags() );
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return $this->process_refund_on_payment( $order_id, $amount, $reason );
	}

	public function get_form_fields() {
		return apply_filters(
			'wc_unzer_settings',
			array(

				'enabled'     => array(
					'title'       => __( 'Enable/Disable', 'unzer-payments' ),
					'label'       => __( 'Enable Unzer Direct Bank Transfer', 'unzer-payments' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no',
				),
				'title'       => array(
					'title'       => __( 'Title', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'unzer-payments' ),
					'default'     => __( 'Direct Bank Transfer', 'unzer-payments' ),
				),
				'description' => array(
					'title'       => __( 'Description', 'unzer-payments' ),
					'type'        => 'text',
					'description' => __( 'This controls the description which the user sees during checkout.', 'unzer-payments' ),
					'default'     => '',
				),
			)
		);
	}
}
