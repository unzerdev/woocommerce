<?php
$slug = isset($slug)?$slug:'';
?>
<div id="unzer-webhook-container<?php echo $slug; ?>" class="unzer-webhook-container">
    <h2>Webhooks</h2>
    <div id="unzer-webhooks-status<?php echo $slug; ?>" style="margin-bottom:20px;"></div>
    <div id="unzer-webhook-actions<?php echo $slug; ?>" style="margin-bottom:20px;"></div>
    <table id="unzer-webhooks<?php echo $slug; ?>" style="width: 100%; max-width:800px;" cellspacing="0" class="unzer-webhooks">
        <thead style="text-align: left;">
        <th style="width:15%;">
            ID
        </th>
        <th style="width:5%;">
            Event
        </th>
        <th>
            URL
        </th>
        <th>

        </th>
        </thead>
        <tbody id="unzer-webhooks-body<?php echo $slug; ?>">

        </tbody>
    </table>
    <div id="unzer-spinner-container<?php echo $slug; ?>" style="width: 100%; max-width:800px; padding:10px 0; display:none;">
        <div class="unzer-spinner"></div>
    </div>
</div>
<?php

use UnzerPayments\Controllers\AdminController;

$ajaxUrl = WC()->api_request_url(AdminController::WEBHOOK_MANAGEMENT_ROUTE_SLUG);
?>
<script>
    window.unzerWebhookAjaxUrl = '<?php echo esc_url($ajaxUrl); ?>';
    document.addEventListener('DOMContentLoaded', function () {
        unzerWebhookRefreshData('<?php echo $slug; ?>');
    });
</script>
<?php
wp_enqueue_script('unzer_admin_webhook_management_js', UNZER_PLUGIN_URL . '/assets/js/admin_webhook_management.js');