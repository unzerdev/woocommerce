<?php


namespace UnzerPayments\SdkExtension\Services;

use UnzerPayments\SdkExtension\Resource\ApplePayCertificate;
use UnzerSDK\Adapter\HttpAdapterInterface;
use UnzerSDK\Unzer;

class AppleKeyService {

	private Unzer $unzer;

	public function __construct( Unzer $unzer ) {

		$this->unzer = $unzer;
	}

	public function activateCertificate( string $certificateId ): bool {
		$certificate  = ( new ApplePayCertificate() )
			->setId( $certificateId )
			->setParentResource( $this->unzer );
		$responseJson = $this->unzer->getHttpService()->send(
			'/keypair/applepay/certificates/' . $certificateId . '/activate',
			$certificate,
			HttpAdapterInterface::REQUEST_POST
		);
		$response     = json_decode( $responseJson, true );
		return $response['active'] ?? false;
	}
}
