<?php

namespace UnzerPayments\Services;

use UnzerPayments\Controllers\AdminController;

class DashboardService
{
    const OPTION_KEY_NOTIFICATIONS = 'unzer_notifications';

    public function getNotifications(): array
    {
        return get_option(self::OPTION_KEY_NOTIFICATIONS, []);
    }

    public function addError($messageType, $parameters = [])
    {
        $errors = $this->getNotifications();
        $errors[uniqid()] = [
            'type' => 'error',
            'messageType'=>$messageType,
            'parameters' => $parameters,
        ];
        update_option(self::OPTION_KEY_NOTIFICATIONS, $errors);
    }

    public function showNotifications()
    {
        $notifications = $this->getNotifications();
        foreach ($notifications as $notificationId => $notification) {
            $message = isset($notification['message']) ? $notification['message'] :'';
            switch($notification['messageType']){
                case 'chargeback':
                    $message = sprintf(__('A chargeback has been received. Please check the payment details for order %s', 'unzer-payments'), $notification['parameters'][0]);
                    break;
            }
            echo '<div class="notice notice-' . $notification['type'] . '">
                    <p>' . $message . '</p>
                    <p><button href="#" class="button button-small button-primary dismiss-unzer-notification" data-id="' . $notificationId . '" data-url="' . WC()->api_request_url(AdminController::NOTIFICATION_SLUG) . '">' . __('Dismiss this notification', 'unzer-payments') . '</button></p>
                  </div>';
        }
    }

    public function removeNotification($key)
    {
        $notifications = $this->getNotifications();
        if (isset($notifications[$key])) {
            unset($notifications[$key]);
            update_option(self::OPTION_KEY_NOTIFICATIONS, $notifications);
        }
    }
}
