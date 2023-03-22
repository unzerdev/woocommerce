<?php

use UnzerPayments\Controllers\AdminController;

$paymentInstructions = get_post_meta($_GET['post'], \UnzerPayments\Main::ORDER_META_KEY_PAYMENT_INSTRUCTIONS, true);

if ($paymentInstructions) {
    ?>
    <h3><?php echo esc_html(__('Payment Instructions', 'unzer-payments')); ?></h3>
    <div><?php echo $paymentInstructions; ?></div>
    <?php
}
?>
<h3><?php echo esc_html(__('Totals', 'unzer-payments')); ?></h3>
<table id="unzer-sums">
    <tbody id="unzer-sums-body">

    </tbody>
</table>
<h3><?php echo esc_html(__('Detailed Transactions', 'unzer-payments')); ?></h3>
<table id="unzer-transactions" style="width: 100%;">
    <thead style="text-align: left;">
    <tr>
        <th><?php echo esc_html(__('Time', 'unzer-payments')); ?></th>
        <th><?php echo esc_html(__('Type', 'unzer-payments')); ?></th>
        <th>ID</th>
        <th><?php echo esc_html(__('Amount', 'unzer-payments')); ?></th>
        <th><?php echo esc_html(__('Status', 'unzer-payments')); ?></th>
    </tr>
    </thead>
    <tbody id="unzer-transactions-body">

    </tbody>
</table>
<div style="margin-top:20px;">
    <a href="#" onclick="document.getElementById('unzer-debug').style.display = 'block'; return false;" class="button">
        <?php echo esc_html(__('Show Debug Information', 'unzer-payments')); ?>
    </a>
    <pre id="unzer-debug" style="display: none; font-size:10px;">

    </pre>
</div>
<?php
$ajaxUrl = WC()->api_request_url(AdminController::GET_ORDER_TRANSACTIONS_ROUTE_SLUG);
$ajaxUrl .= (strpos($ajaxUrl, '?') === false ? '?' : '&') . 'order_id=' . $_GET['post'];

$chargeUrl = WC()->api_request_url(AdminController::CHARGE_ROUTE_SLUG);
?>
<script>
    const unzerOrderId = <?php echo (int)$_GET['post']; ?>;

    function unzerRefreshData() {
        fetch('<?php echo esc_url($ajaxUrl);?>')
            .then(response => response.json())
            .then(data => {

                if (data.transactions) {
                    let tHtml = '';
                    for (const transaction of data.transactions) {
                        let color = transaction.status === 'error' ? '#cc0000' : '#000000';
                        color = transaction.status === 'pending' ? '#bbb' : color;
                        tHtml += `
                    <tr style="color:${color};">
                        <td>${transaction.time}</td>
                        <td>${transaction.type}</td>
                        <td>${transaction.id}</td>
                        <td>${transaction.amount}</td>
                        <td>${transaction.status}</td>
                    </tr>
                `;
                    }
                    document.getElementById('unzer-transactions-body').innerHTML = tHtml;
                }

                let captureAction = '';
                if (data.remainingPlain) {
                    captureAction = '<div><input type="number" step="0.01" min="0.01"  max="'+data.remainingPlain+'" value="'+data.remainingPlain+'" id="unzer-capture-amount-input" /></div> ' +
                        '<a href="#" onclick="unzerCaptureOrder(unzerOrderId, document.getElementById(\'unzer-capture-amount-input\').value); return false;" class="button button-small" style="width:100%; text-align: center;"><?php echo esc_html(__('Capture Amount', 'unzer-payments')); ?></a>'

                }

                let amountHtml = `
                <tr><th style="text-align: left;"><?php echo esc_html(__('Total amount', 'unzer-payments')); ?>: </th><td style="text-align: right;">${data.amount}</td></tr>
                <tr><th style="text-align: left;"><?php echo esc_html(__('Charged amount', 'unzer-payments')); ?>: </th><td style="text-align: right;">${data.charged}</td></tr>
                <tr><th style="text-align: left;"><?php echo esc_html(__('Cancelled amount', 'unzer-payments')); ?>: </th><td style="text-align: right;">${data.cancelled}</td></tr>
                <tr><th style="text-align: left;"><?php echo esc_html(__('Remaining amount', 'unzer-payments')); ?>: </th><td style="text-align: right;">${data.remaining}</td></tr>
                <tr><td colspan="2">${captureAction}</td></tr>
            `;

                document.getElementById('unzer-sums-body').innerHTML = amountHtml;
                if (data.raw) {
                    document.getElementById('unzer-debug').innerHTML = data.raw;
                }
                if (data.error) {
                    document.getElementById('unzer-transactions').parentNode.innerHTML = '<b>ERROR:</b> ' + data.error;
                }
                console.log(data);
            });
    }

    unzerRefreshData();

    function unzerCaptureOrder(orderId, amount) {
        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('amount', amount);
        fetch('<?php echo esc_url($chargeUrl);?>', {
            method: 'POST',
            body: formData
        }).then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                }
                unzerRefreshData();
            })

    }
</script>