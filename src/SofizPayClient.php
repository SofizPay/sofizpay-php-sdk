<?php
namespace SofizPay;

require_once __DIR__ . '/SofizPaySDK.php';

if (!class_exists('SofizPay\SofizPayClient', false)) {
    class SofizPayClient extends SofizPaySDK {
    }
}
