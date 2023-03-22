<div id="unzer-webhook-container">
    <h2>Webhooks</h2>
    <div id="unzer-webhooks-status" style="margin-bottom:20px;"></div>
    <div id="unzer-webhook-actions" style="margin-bottom:20px;"></div>
    <table id="unzer-webhooks" style="width: 100%; max-width:800px;" cellspacing="0">
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
        <tbody id="unzer-webhooks-body">

        </tbody>
    </table>
    <div id="unzer-spinner-container" style="width: 100%; max-width:800px; padding:10px 0; display:none;">
        <div class="unzer-spinner"></div>
    </div>
</div>
<?php

use UnzerPayments\Controllers\AdminController;

$ajaxUrl = WC()->api_request_url(AdminController::WEBHOOK_MANAGEMENT_ROUTE_SLUG);
?>
<script>
    function unzerWebhookRefreshData() {
        unzerStartLoading();
        fetch('<?php echo esc_url($ajaxUrl);?>')
            .then(response => response.json())
            .then(data => {

                if (data.webhooks) {
                    let tHtml = '';
                    for (const webhook of data.webhooks) {

                        tHtml += `
                    <tr>
                        <td>${webhook.id}</td>
                        <td>${webhook.event}</td>
                        <td>${webhook.url}</td>
                        <td><a href="#" onclick="unzerDeleteWebhook('${webhook.id}'); return false;" class="button button-small"><?php echo esc_html(__('Delete', 'unzer-payments')); ?></a></td>
                    </tr>
                `;
                    }
                    document.getElementById('unzer-webhooks-body').innerHTML = tHtml;
                }


                let addWebhook = '';
                let statusText = '';
                if (!data.isRegistered) {
                    addWebhook = '<a href="#" onclick="unzerAddCurrentWebhook(); return false;" class="button button-small button-primary">Add Webhook</a>';
                    statusText = '<div style="color:#dc1b1b;"><span class="unzer-status-circle" style="background:#cc0000;"></span> Webhook is not active</div>';
                }else{
                    statusText = '<div><span class="unzer-status-circle" style="background:#00a800;"></span><?php echo esc_html(__('Webhook is active', 'unzer-payments')); ?></div>';
                }

                document.getElementById('unzer-webhook-actions').innerHTML = addWebhook;
                document.getElementById('unzer-webhooks-status').innerHTML = statusText;
                console.log(data);
                unzerStopLoading();
            });
    }

    unzerWebhookRefreshData();

    function unzerAddCurrentWebhook() {
        unzerClearData();
        unzerStartLoading();
        const formData = new FormData();
        formData.append('action', 'add');
        fetch('<?php echo esc_url($ajaxUrl);?>', {
            method: 'POST',
            body: formData
        }).then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                }
                unzerWebhookRefreshData();
            })
    }

    function unzerDeleteWebhook(id) {
        unzerClearData();
        unzerStartLoading();
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        fetch('<?php echo esc_url($ajaxUrl);?>', {
            method: 'POST',
            body: formData
        }).then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                }
                unzerWebhookRefreshData();
            })
    }

    function unzerStartLoading(){
        document.getElementById('unzer-spinner-container').style.display = 'block';
    }

    function unzerStopLoading(){
        document.getElementById('unzer-spinner-container').style.display = 'none';
    }

    function unzerClearData(){
        document.getElementById('unzer-webhooks-body').innerHTML = '';
        document.getElementById('unzer-webhook-actions').innerHTML = '';
        document.getElementById('unzer-webhooks-status').innerHTML = '';
    }
</script>