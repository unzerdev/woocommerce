<?php
/**
 * This file contains the different web hook events which can be subscribed.
 *
 * Copyright (C) 2020 - today Unzer E-Com GmbH
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @link  https://docs.unzer.com/
 *
 * @package  UnzerSDK\Constants
 */

namespace UnzerSDK\Constants;

class WebhookEvents
{
    // all events
    public const ALL = 'all';

    // authorize events
    public const AUTHORIZE = 'authorize';
    public const AUTHORIZE_CANCELED = 'authorize.canceled';
    public const AUTHORIZE_EXPIRED = 'authorize.expired';
    public const AUTHORIZE_FAILED = 'authorize.failed';
    public const AUTHORIZE_PENDING = 'authorize.pending';
    public const AUTHORIZE_RESUMED = 'authorize.resumed';
    public const AUTHORIZE_SUCCEEDED = 'authorize.succeeded';

    // charge events
    public const CHARGE = 'charge';
    public const CHARGE_CANCELED = 'charge.canceled';
    public const CHARGE_EXPIRED = 'charge.expired';
    public const CHARGE_FAILED = 'charge.failed';
    public const CHARGE_PENDING = 'charge.pending';
    public const CHARGE_RESUMED = 'charge.resumed';
    public const CHARGE_SUCCEEDED = 'charge.succeeded';

    // chargeback events
    public const CHARGEBACK = 'chargeback';

    // payout events
    public const PAYOUT = 'payout';
    public const PAYOUT_SUCCEEDED = 'payout.succeeded';
    public const PAYOUT_FAILED = 'payout.failed';

    // types events
    public const TYPES = 'types';

    // customer events
    public const CUSTOMER = 'customer';
    public const CUSTOMER_CREATED = 'customer.created';
    public const CUSTOMER_DELETED = 'customer.deleted';
    public const CUSTOMER_UPDATED = 'customer.updated';

    // payment events
    public const PAYMENT = 'payment';
    public const PAYMENT_PENDING = 'payment.pending';
    public const PAYMENT_COMPLETED = 'payment.completed';
    public const PAYMENT_CANCELED = 'payment.canceled';
    public const PAYMENT_PARTLY = 'payment.partly';
    public const PAYMENT_PAYMENT_REVIEW = 'payment.payment_review';
    public const PAYMENT_CHARGEBACK = 'payment.chargeback';

    // shipment events
    public const SHIPMENT = 'shipment';

    public const ALLOWED_WEBHOOKS = [
        self::ALL,
        self::AUTHORIZE,
        self::AUTHORIZE_CANCELED,
        self::AUTHORIZE_EXPIRED,
        self::AUTHORIZE_FAILED,
        self::AUTHORIZE_PENDING,
        self::AUTHORIZE_RESUMED,
        self::AUTHORIZE_SUCCEEDED,
        self::CHARGE,
        self::CHARGE_CANCELED,
        self::CHARGE_EXPIRED,
        self::CHARGE_FAILED,
        self::CHARGE_PENDING,
        self::CHARGE_RESUMED,
        self::CHARGE_SUCCEEDED,
        self::CHARGEBACK,
        self::PAYOUT,
        self::PAYOUT_SUCCEEDED,
        self::PAYOUT_FAILED,
        self::TYPES,
        self::CUSTOMER,
        self::CUSTOMER_CREATED,
        self::CUSTOMER_DELETED,
        self::CUSTOMER_UPDATED,
        self::PAYMENT,
        self::PAYMENT_PENDING,
        self::PAYMENT_COMPLETED,
        self::PAYMENT_CANCELED,
        self::PAYMENT_PARTLY,
        self::PAYMENT_PAYMENT_REVIEW,
        self::PAYMENT_CHARGEBACK,
        self::SHIPMENT
    ];
}
