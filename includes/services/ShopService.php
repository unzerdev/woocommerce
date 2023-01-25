<?php

namespace UnzerPayments\Services;


use UnzerSDK\Resources\Metadata;

class ShopService
{

    public function getMetadata(): Metadata
    {
        return (new Metadata())
            ->addMetadata('pluginType', 'Unzer Payments')
            ->addMetadata('pluginVersion', UNZER_VERSION)
            ->setShopType('WooCommerce')
            ->setShopVersion(WC()->version);
    }

}