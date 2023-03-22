<?php
wp_enqueue_style('unzer_admin_css', UNZER_PLUGIN_URL.'/assets/css/admin.css');
?>
<img src="<?php echo esc_url(UNZER_PLUGIN_URL); ?>/assets/img/logo.svg" width="150" alt="Unzer" style="margin-bottom: 10px;"/>
<div style="background: #fff; padding:20px;">
    <h2><?php echo esc_html(__('General settings', 'unzer-payments')); ?></h2>
    <div id="unzer-key-status" style="margin-bottom:20px;">
        <span class="unzer-status-circle" style="background:#999;"></span>
    </div>
    <?php

    use UnzerPayments\Controllers\AdminController;

    $ajaxUrl = WC()->api_request_url(AdminController::KEY_VALIDATION_ROUTE_SLUG);
    ?>
    <script>
        function unzerGetKeyStatus() {
            fetch('<?php echo esc_url($ajaxUrl);?>')
                .then(response => response.json())
                .then(data => {

                    let statusText = '';
                    if (data.isValid === 0) {
                        statusText = '<div style="color:#dc1b1b;"><span class="unzer-status-circle" style="background:#cc0000;"></span> <?php echo esc_html(__('Keys are not valid', 'unzer-payments')); ?></div>'
                    } else if (data.isValid === 1) {
                        statusText = '<div><span class="unzer-status-circle" style="background:#00a800;"></span> <?php echo esc_html(__('Keys are valid', 'unzer-payments')); ?></div>'
                    }
                    document.getElementById('unzer-key-status').innerHTML = statusText;
                });
        }

        unzerGetKeyStatus()
    </script>
