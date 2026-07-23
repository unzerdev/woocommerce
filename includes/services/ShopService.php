<?php

namespace UnzerPayments\Services;

use UnzerSDK\Resources\Metadata;

class ShopService {


	public function getMetadata(): Metadata {
        $unzerMetadata = new Metadata();
        $unzerMetadata->addMetadata( 'pluginType', 'unzerdev/woocommerce' )
            ->addMetadata( 'pluginVersion', UNZER_VERSION )
            ->setShopType( 'WooCommerce' )
            ->setShopVersion( WC()->version );
        if ((string)WC()->session->get(ExpressCheckoutService::SESSION_SELECTED_EXPRESS_METHOD) !== '') {
            $unzerMetadata->addMetadata('isExpress', '1');
        } else {
            $unzerMetadata->addMetadata('isExpress', '0');
        }
		return $unzerMetadata;
	}

    public function setIsExpress(Metadata $unzerMetadata, bool $isExpress): void
    {
        $unzerMetadata->addMetadata('isExpress', $isExpress ? '1' : '0');
    }
}
