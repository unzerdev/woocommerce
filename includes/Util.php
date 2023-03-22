<?php

namespace UnzerPayments;

class Util
{
    public static function safeCompareAmount($amount1, $amount2): bool
    {
        return number_format($amount1, 2) === number_format($amount2, 2);
    }
}