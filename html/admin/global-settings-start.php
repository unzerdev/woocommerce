<link rel="stylesheet" href="<?php echo UNZER_PLUGIN_URL; ?>/assets/css/admin.css" />
<img src="<?php echo UNZER_PLUGIN_URL; ?>/assets/img/logo.svg" width="150" alt="Unzer" style="margin-bottom: 10px;"/>
<div style="background: #fff; padding:20px;">
    <h2><?php echo __('General settings', UNZER_PLUGIN_NAME); ?></h2>
    <div id="unzer-key-status" style="margin-bottom:20px;">
        <span class="unzer-status-circle" style="background:#999;"></span>
    </div>
    <?php

    use UnzerPayments\Controllers\AdminController;

    $ajaxUrl = WC()->api_request_url(AdminController::KEY_VALIDATION_ROUTE_SLUG);
    ?>
    <script>
        function unzerGetKeyStatus() {
            fetch('<?php echo $ajaxUrl;?>')
                .then(response => response.json())
                .then(data => {

                    let statusText = '';
                    if (data.isValid === 0) {
                        statusText = '<div style="color:#dc1b1b;"><span class="unzer-status-circle" style="background:#cc0000;"></span> <?php echo __('Keys are not valid', UNZER_PLUGIN_NAME); ?></div>'
                    } else if (data.isValid === 1) {
                        statusText = '<div><span class="unzer-status-circle" style="background:#00a800;"></span> <?php echo __('Keys are valid', UNZER_PLUGIN_NAME); ?></div>'
                    }
                    document.getElementById('unzer-key-status').innerHTML = statusText;
                });
        }

        unzerGetKeyStatus()
    </script>
