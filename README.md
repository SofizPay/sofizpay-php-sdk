<div align="center">
  <img src="https://github.com/kenandarabeh/sofizpay-sdk/blob/main/assets/sofizpay-logo.png?raw=true" alt="SofizPay Logo" width="200" />

  <h2>SofizPay PHP SDK</h2>
  <p><strong>The official PHP library for secure digital payments on the SofizPay platform.</strong></p>

  [![Packagist Version](https://img.shields.io/packagist/v/sofizpay/sofizpay-sdk-php.svg)](https://packagist.org/packages/sofizpay/sofizpay-sdk-php)
  [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
  [![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)
</div>

---

## 📋 Table of Contents

- [Overview](#overview)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Core Methods](#core-methods)
- [Digital Services (Missions)](#digital-services-missions)
- [Bank Integration (CIB)](#bank-integration-cib)
- [Real-time Transaction Streaming](#real-time-transaction-streaming)
- [Response Format](#response-format)
- [Security Best Practices](#security-best-practices)
- [Support](#support)

---

## 🌟 Overview

The SofizPay PHP SDK provides a comprehensive interface for integrating **DZT digital payments** into your PHP applications. It supports on-chain Stellar payments, CIB/Dahabia bank deposits, and digital service recharges (Missions).

**Key Benefits:**
- ⚡ Modern async-compatible wrapper (using Guzzle)
- 🌍 Compatible with Laravel, Symfony, and plain PHP
- 📊 Exhaustive transaction history (Path Payments, Trustlines)
- 🏦 CIB/Dahabia bank deposit link generation
- 📱 Phone, Internet & Game recharges (Mission APIs)

---

## 📦 Installation

### Composer

```bash
composer require sofizpay/sofizpay-sdk-php
```

**Requirements:** 
- PHP `>= 7.4`
- Extensions: `ext-openssl`, `ext-json`
- Dependencies: `guzzlehttp/guzzle`, `soneso/stellar-php-sdk`

---

## 🚀 Quick Start

```php
<?php
require_once 'vendor/autoload.php';
use SofizPay\SofizPaySDK;

// Initialize the SDK
$sdk = new SofizPaySDK();

// 1. Check DZT balance
$balance = $sdk->getBalance('YOUR_PUBLIC_KEY');
if ($balance['success']) {
    echo "💰 Balance: " . $balance['balance'] . " DZT\n";
}

// 2. Send a DZT payment
$result = $sdk->submit([
    'secretkey'            => 'YOUR_SECRET_KEY',      // 56-char Stellar seed starting with 'S'
    'destinationPublicKey' => 'RECIPIENT_PUBLIC_KEY', // Recipient's public key
    'amount'               => '100.0',                // Amount in DZT
    'memo'                 => 'Invoice #1234'         // Optional memo (max 28 chars)
]);

if ($result['success']) {
    echo "✅ Payment sent! Hash: " . $result['transactionHash'] . "\n";
} else {
    echo "❌ Error: " . $result['error'] . "\n";
}
```

---

## 🔧 Core Methods

### `getBalance(string $publicKey)`

Returns the current **DZT** balance for a given Stellar account.

```php
// Fetch balance for a specific public key
$result = $sdk.getBalance('GCAZI...YOUR_PUBLIC_KEY');

// Response
[
  'success'      => true,
  'balance'      => '1500.0000000',
  'publicKey'    => 'GCAZI...',
  'asset_code'   => 'DZT',
  'asset_issuer' => 'GCAZI7YBLIDJWIVEL7ETNAZGPP3LC24NO6KAOBWZHUERXQ7M5BC52DLV',
  'timestamp'    => '2025-07-28T10:30:00+00:00'
]
```

---

### `submit(array $data)`

Submits a DZT payment to the Stellar network.

```php
$result = $sdk.submit([
    'secretkey'            => 'SXXX...YOUR_SECRET',      // 56-char Stellar seed starting with 'S'
    'destinationPublicKey' => 'GXXX...RECIPIENT',         // Recipient's public key
    'amount'               => '250.50',                  // Amount in DZT
    'memo'                 => 'Order #5567'              // Optional memo (max 28 chars)
]);

// Success Response
[
  'success'            => true,
  'transactionId'      => 'abc123...hash',
  'transactionHash'    => 'abc123...hash',
  'amount'             => '250.50',
  'memo'               => 'Order #5567',
  'destinationPublicKey' => 'GXXX...',
  'timestamp'          => '2025-07-28T10:30:00+00:00'
]
```

> ⚠️ **Memo Truncation:** Memos longer than 28 characters are automatically truncated.

---

### `getTransactions(string $publicKey, ?int $limit, ?string $cursor)`

Fetches **exhaustive transaction history** via Stellar. Includes payments, trustlines, and account creation.

```php
// Fetch up to 100 recent transactions
$history = $sdk.getTransactions('YOUR_PUBLIC_KEY', 100);

if ($history['success']) {
    foreach ($history['transactions'] as $tx) {
        // Log each transaction details
        echo "[{$tx['timestamp']}] {$tx['type']} — {$tx['amount']} {$tx['asset_code']}\n";
    }
}
```

---

### `searchTransactionsByMemo(string $publicKey, string $memo, int $limit)`

Performs a case-insensitive search over recent transactions.

```php
// Search for transactions containing the specific memo
$results = $sdk.searchTransactionsByMemo('YOUR_PUBLIC_KEY', 'Order #1234', 10);
if ($results['success']) {
    echo "Found " . count($results['transactions']) . " matches";
}
```

---

## 📱 Digital Services (Missions)

Mission APIs allow users to spend DZT on real-world digital services. All calls require `encrypted_sk`.

### Phone Recharge

```php
$result = $sdk.rechargePhone([
    'encrypted_sk' => 'USER_ENCRYPTED_SECRET_KEY', // User's encrypted secret key
    'phone'        => '0661000000',               // Recipient's phone number
    'operator'     => 'mobilis',                  // 'mobilis' | 'djezzy' | 'ooredoo'
    'amount'       => '100',                      // Recharge amount
    'offer'        => 'Top'                       // Offer type (e.g., 'Top', 'Pix')
]);

if ($result['success']) {
    // Process successful recharge
    print_r($result['data']);
}
```

### Internet Recharge (Idoom 4G)

```php
$result = $sdk.rechargeInternet([
    'encrypted_sk' => 'USER_ENCRYPTED_SECRET_KEY', // User's encrypted secret key
    'phone'        => '0661000000',               // Account phone number
    'operator'     => 'idoom',                    // Network operator
    'amount'       => '2000',                     // Recharge amount
    'offer'        => 'adsl'                      // Offer type (e.g., 'adsl', '4g')
]);
```

### Game Top-up (FreeFire, PUBG)

```php
$result = $sdk.rechargeGame([
    'encrypted_sk' => 'USER_ENCRYPTED_SECRET_KEY',
    'operator'     => 'freefire',   // e.g., 'freefire', 'pubg'
    'playerId'     => '123456789',
    'amount'       => '500',        // 'amount' from getProducts()
    'offer'        => 'diamonds'    // 'name' from getProducts()
]);
```

### Bill Payment

```php
$result = $sdk.payBill([
    'encrypted_sk' => 'USER_ENCRYPTED_SECRET_KEY',
    'operator'     => 'sonelgaz', // e.g., 'sonelgaz', 'ade'
    'bill_id'      => 'BILL_999',
    'amount'       => '1500'
]);
```

### Operation History & Details

```php
// Recent operations (paginated)
$history = $sdk.getOperationHistory('USER_ENCRYPTED_SK', 10, 0);
if ($history['success']) {
    print_r($history['data']);
}

// Details of a specific operation
$details = $sdk.getOperationDetails('OPERATION_ID', 'USER_ENCRYPTED_SK');
```

### Get Available Products

```php
// Fetch list of available recharging products
$products = $sdk.getProducts('USER_ENCRYPTED_SK');
if ($products['success']) {
    // List all available services and offers
    print_r($products['data']);
}
```

> [!TIP]
> Use the product `name` for the `offer` field and the product `amount` for the `amount` field.

---

## 🏦 Bank Integration (CIB & EDAHABIA)

Generate secure Dahabia/CIB bank payment links with optional real-time webhook callbacks.

```php
$result = $sdk->makeCIBTransaction([
    'account'     => 'YOUR_STELLAR_PUBLIC_KEY',       // Your merchant Stellar public key
    'amount'      => 2500,                            // Amount in DZD (Algerian Dinars)
    'full_name'   => 'Ahmed Benali',                  // Customer's full name
    'phone'       => '0661234567',                    // Customer's phone number
    'email'       => 'ahmed@example.com',              // Customer's email
    'memo'        => 'Order #789',                    // Internal order reference
    'return_url'  => 'https://yoursite.com/callback', // Redirect after payment
    'webhook_url' => 'https://yoursite.com/api/cib-webhook', // Asynchronous Webhook callback
    'language'    => 'ar',                            // 'ar', 'fr', or 'en'
    'redirect'    => 'no'                             // 'yes' for auto-redirect
]);

if ($result['success']) {
    // Top-level direct properties
    $paymentUrl = $result['payment_url'];
    $orderId = $result['cib_transaction_id'];
    
    // Redirect customer to the SATIM checkout page
    header('Location: ' . $paymentUrl);
    exit;
}
```

---

## 🧪 Sandbox Environment & Mock Cards

The SofizPay Sandbox simulates the SATIM CIB/EDAHABIA payment system without connecting to real bank accounts.

```php
// Initialize SDK in Sandbox mode (or pass 'is_sandbox' => true)
$sandboxSdk = new SofizPaySDK('https://horizon.stellar.org', true);

$result = $sandboxSdk->makeSandboxCIBTransaction([
    'account'     => 'YOUR_PUBLIC_KEY',
    'amount'      => 150.0,
    'full_name'   => 'Sandbox Tester',
    'phone'       => '0555000000',
    'email'       => 'sandbox@example.com',
    'webhook_url' => 'https://yoursite.com/api/cib-webhook',
    'return_url'  => 'https://yoursite.com/callback'
]);

if ($result['success']) {
    echo "Sandbox Payment URL: " . $result['payment_url'] . "\n";
    $cibId = $result['cib_transaction_id'];

    // Check sandbox order status
    $status = $sandboxSdk->checkSandboxCIBStatus($cibId);
    echo "Order Status: " . ($status['data']['status'] ?? 'pending') . "\n";
}
```

### Sandbox Test Cards

Use the following test credentials on the sandbox payment page:

| Card Number | Expiry | CVV | Cardholder Name | Result | Note |
|-------------|--------|-----|-----------------|--------|------|
| `6280581001234567` | `12/28` | `123` | `TEST SUCCESS` | ✅ Approved | Use OTP `123456` |
| `6280581009876543` | `12/29` | `456` | `TEST SUCCESS TWO` | ✅ Approved | Use OTP `123456` |
| `6280581005555555` | `06/28` | `999` | `TEST DECLINED` | ❌ Declined by issuer | Tests card refusal |
| `6280581004444444` | `09/28` | `444` | `TEST INSUFFICIENT` | ❌ Insufficient funds | Tests insufficient balance |
| `6280581003333333` | `03/21` | `333` | `TEST EXPIRED` | ❌ Expired card | Tests expired card |
| `6280581002222222` | `11/28` | `222` | `TEST TIMEOUT` | ❌ Timeout | Tests transaction timeout |

> [!TIP]
> **OTP Code:** Always enter `123456` on the OTP page for approved tests. Entering any other OTP triggers an `Invalid OTP` error.

---

## 🔔 Webhooks & Signature Verification

When a payment succeeds or fails, SofizPay delivers a server-to-server asynchronous HTTP `POST` event notification (`cib.payment.updated`) to your registered `webhook_url`.

### Webhook Event Payload

```json
{
  "event": "cib.payment.updated",
  "payment_status": "success",
  "transaction_id": "bb979126-4e55-49dd-8ecc-a1d59867d2b0",
  "cib_transaction_id": "5965287001",
  "order_number": 5965287001,
  "order_status": 2,
  "amount": 150000,
  "currency": "012",
  "resp_code": "00",
  "resp_code_desc": "Approved",
  "message": "https://yoursite.com/api/cib-webhook/success1500005965287001",
  "signature": "MEQCIFz8v982hfkjdsf983274hfkjsdf...=="
}
```

### Verifying Signatures (`verifyWebhookSignature`)

Always verify the RSA SHA-256 signature before processing payments in your database:

```php
<?php
// webhook.php
require_once 'vendor/autoload.php';
use SofizPay\SofizPaySDK;

$sdk = new SofizPaySDK();

// 1. Read input payload
$payload = json_decode(file_get_contents('php://input'), true);

// 2. Validate official digital signature
if (!$sdk->verifyWebhookSignature($payload)) {
    http_response_code(400);
    echo "Invalid digital signature";
    exit;
}

// 3. Process order upon confirmation
if ($payload['payment_status'] === 'success') {
    $orderNumber = $payload['order_number'] ?? $payload['cib_transaction_id'];
    $amountInDZD = ($payload['amount'] ?? 0) / 100;
    
    // ✅ Mark order as PAID in your database:
    // $db->updateOrder($orderNumber, ['status' => 'PAID', 'amount' => $amountInDZD]);
}

http_response_code(200);
echo "OK";
```

---

## 🔄 Return URL vs. Webhook Notifications

| Feature | Return URL (`return_url`) | Webhook (`webhook_url`) |
| :--- | :--- | :--- |
| **Trigger mechanism** | Client browser redirect | Server-to-server direct HTTP POST |
| **Reliability** | Can fail if user closes browser / network drops | Guaranteed server delivery |
| **Security** | Susceptible to tampering | Cryptographically signed (RSA-2048) |
| **Recommended use** | Display "Thank you" screen | Update database, activate subscriptions |

---

## 🔴 Real-time Transaction Streaming

The PHP SDK allows you to start/stop transaction polling (simulated streaming).

```php
// Monitor account activity (simulated via polling)
$status = $sdk.getStreamStatus('YOUR_PUBLIC_KEY');
```

> Note: Real-time streaming is best handled via the JavaScript SDK in the browser or via Node.js for production environments.

---

## 📤 Response Format

All methods return an associative array:

```php
// ✅ Success
[
  'success'   => true,
  'data'      => [...], // method-specific data fields
  'timestamp' => '2025-07-28T10:30:00+00:00'
]

// ❌ Failure
[
  'success'   => false,
  'error'     => 'Error message describing the failure',
  'timestamp' => '2025-07-28T10:30:00+00:00'
]
```


---

## 🛡️ Security Best Practices

- ❌ Never expose secret keys in client-side code.
- ✅ Use environment variables (`$_ENV` or `getenv()`).
- ✅ Keep `encrypted_sk` secure in your backend database.
- ✅ Always use HTTPS for API communications.

---

## 📞 Support

- 🌐 **Website**: [SofizPay.com](https://sofizpay.com)
- 📚 **Full Docs**: [docs.sofizpay.com](https://docs.sofizpay.com)
- 🐛 **Bug Reports**: [GitHub Issues](https://github.com/kenandarabeh/sofizpay-sdk-php/issues)

---

## License

MIT © [SofizPay Team](https://github.com/kenandarabeh)

**Built with ❤️ for PHP developers | Version `1.0.2`**

