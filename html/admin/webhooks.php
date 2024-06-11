<?php

use UnzerPayments\Controllers\AdminController;
use UnzerPayments\Util;

$slug    = isset( $slug ) ? $slug : '';
$ajaxUrl = WC()->api_request_url( AdminController::WEBHOOK_MANAGEMENT_ROUTE_SLUG );
?>
	<div id="unzer-webhook-container<?php echo esc_attr( $slug ); ?>" class="unzer-webhook-container" data-slug="<?php echo esc_attr( $slug ); ?>" data-url="<?php echo esc_url( $ajaxUrl ); ?>" data-nonce="<?php echo esc_attr( Util::getNonce() ); ?>">
		<h2>Webhooks</h2>
		<div id="unzer-webhooks-status<?php echo esc_attr( $slug ); ?>" style="margin-bottom:20px;"></div>
		<div id="unzer-webhook-actions<?php echo esc_attr( $slug ); ?>" style="margin-bottom:20px;"></div>
		<table id="unzer-webhooks<?php echo esc_attr( $slug ); ?>" style="width: 100%; max-width:800px;" cellspacing="0" class="unzer-webhooks">
			<thead style="text-align: left;">
			<th style="width:15%;">
				ID
			</th>
			<th style="width:5%;">
				Event
			</th>
			<th style="width:75%;">
				URL
			</th>
			<th style="width:5%;">

			</th>
			</thead>
			<tbody id="unzer-webhooks-body<?php echo esc_attr( $slug ); ?>">

			</tbody>
		</table>
		<div id="unzer-spinner-container<?php echo esc_attr( $slug ); ?>" style="width: 100%; max-width:800px; padding:10px 0; display:none;">
			<div class="unzer-spinner"></div>
		</div>
	</div>
<?php
wp_enqueue_script( 'unzer_admin_webhook_management_js', UNZER_PLUGIN_URL . '/assets/js/admin_webhook_management.js', array(), UNZER_VERSION, array( 'in_footer' => false ) );