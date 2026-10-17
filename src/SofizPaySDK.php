<?php

namespace SofizPay;

use Soneso\StellarSDK\StellarSDK;
use Soneso\StellarSDK\Asset;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\TransactionBuilder;
use Soneso\StellarSDK\Memo;
use Soneso\StellarSDK\Xdr\XdrTransactionBuilder;
use Soneso\StellarSDK\Requests\Order;
use Soneso\StellarSDK\Responses\Operations\PaymentOperationResponse;
use Soneso\StellarSDK\Responses\Operations\PathPaymentStrictReceiveOperationResponse;
use Soneso\StellarSDK\Responses\Operations\PathPaymentStrictSendOperationResponse;
use Soneso\StellarSDK\Responses\Operations\CreateAccountOperationResponse;
use Soneso\StellarSDK\Responses\Operations\ChangeTrustOperationResponse;

class SofizPaySDK {
    private StellarSDK $sdk;
    private bool $isSandbox;
    public const VERSION = '1.1.11';
    public const DZT_ASSET_CODE = 'DZT';
    public const DZT_ASSET_ISSUER = 'GCAZI7YBLIDJWIVEL7ETNAZGPP3LC24NO6KAOBWZHUERXQ7M5BC52DLV';

    public function __construct(string|array $horizonUrl = 'https://horizon.stellar.org', bool $isSandbox = false) {
        if (is_array($horizonUrl)) {
            $network = strtoupper($horizonUrl['network'] ?? '');
            $isSandbox = ($network === 'TESTNET') || !empty($horizonUrl['isSandbox']) || !empty($horizonUrl['sandbox']);
            $horizonUrl = $horizonUrl['horizonUrl'] ?? ($isSandbox ? 'https://horizon-testnet.stellar.org' : 'https://horizon.stellar.org');
        }
        $this->sdk = new StellarSDK($horizonUrl);
        $this->isSandbox = $isSandbox;
    }

    /**
     * Get SDK Version
     */
    public function getVersion(): string {
        return self::VERSION;
    }

    /**
     * Extract Stellar public key from secret key
     */
    public function getPublicKey(string $secretKey): array {
        try {
            $keyPair = KeyPair::fromSeed($secretKey);
            return [
                'success' => true,
                'publicKey' => $keyPair->getAccountId(),
                'secretKey' => $secretKey,
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'publicKey' => null,
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Submit a payment (Alias for sendPayment for JS parity)
     */
    public function submit(array $params): array {
        return $this->sendPayment(
            $params['secretkey'] ?? '',
            $params['destinationPublicKey'] ?? '',
            $params['amount'] ?? '0',
            $params['memo'] ?? null
        );
    }

    /**
     * Send DZT payment
     */
    public function sendPayment(string $secretKey, string $destinationId, string $amount, ?string $memoText = null): array {
        $startTime = microtime(true);
        try {
            try {
                $sourceKeyPair = KeyPair::fromSeed($secretKey);
            } catch (\Exception $e) {
                throw new \Exception("Invalid secret key format (Seed must be 56 characters starting with S)");
            }
            
            $sourceAccountId = $sourceKeyPair->getAccountId();
            $sourceAccount = $this->sdk->requestAccount($sourceAccountId);
            $asset = Asset::createNonNativeAsset(self::DZT_ASSET_CODE, self::DZT_ASSET_ISSUER);

            $paymentOperation = (new \Soneso\StellarSDK\PaymentOperationBuilder($destinationId, $asset, $amount))->build();
            
            $transactionBuilder = new TransactionBuilder($sourceAccount);
            $transactionBuilder->addOperation($paymentOperation);

            if ($memoText) {
                if (strlen($memoText) > 28) {
                    $memoText = substr($memoText, 0, 28);
                }
                $transactionBuilder->addMemo(Memo::text($memoText));
            }

            $transaction = $transactionBuilder->build();
            $transaction->sign($sourceKeyPair, Network::public());

            $response = $this->sdk->submitTransaction($transaction);

            return [
                'success' => true,
                'transactionId' => $response->getHash(),
                'transactionHash' => $response->getHash(),
                'amount' => $amount,
                'memo' => $memoText,
                'destinationPublicKey' => $destinationId,
                'duration' => microtime(true) - $startTime,
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'duration' => microtime(true) - $startTime,
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Get DZT balance
     */
    public function getBalance(string $publicKey): array {
        try {
            $account = $this->sdk->requestAccount($publicKey);
            $balanceValue = '0.0000000';

            foreach ($account->getBalances() as $balance) {
                if ($balance->getAssetCode() === self::DZT_ASSET_CODE && 
                    $balance->getAssetIssuer() === self::DZT_ASSET_ISSUER) {
                    $balanceValue = $balance->getBalance();
                    break;
                }
            }

            return [
                'success' => true,
                'balance' => $balanceValue,
                'publicKey' => $publicKey,
                'asset_code' => self::DZT_ASSET_CODE,
                'asset_issuer' => self::DZT_ASSET_ISSUER,
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'balance' => '0.0000000',
                'publicKey' => $publicKey,
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Get transactions history
     */
    public function getTransactions(string $publicKey, ?int $limit = 50, ?string $cursor = null): array {
        try {
            $builder = $this->sdk->operations()->forAccount($publicKey)
                ->includeTransactions(true)
                ->order('desc')
                ->limit($limit ?? 50);
            
            if ($cursor) {
                $builder->cursor($cursor);
            }

            $response = $builder->execute();
            $transactions = [];

            foreach ($response->getOperations() as $op) {
                $txData = [
                    'id' => $op->getTransactionHash(),
                    'transactionId' => $op->getTransactionHash(),
                    'hash' => $op->getTransactionHash(),
                    'created_at' => $op->getCreatedAt(),
                    'timestamp' => $op->getCreatedAt(),
                    'paging_token' => $op->getPagingToken(),
                    'successful' => $op->isTransactionSuccessful(),
                ];

                $memo = '';
                if ($op->getTransaction() !== null) {
                    $memo = $op->getTransaction()->getMemo()->getValue() ?? '';
                }
                $txData['memo'] = $memo;

                // 1. Regular Payment
                if ($op instanceof PaymentOperationResponse) {
                    $asset = $op->getAsset();
                    if ($asset instanceof \Soneso\StellarSDK\AssetTypeCreditAlphanum && 
                        $asset->getCode() === self::DZT_ASSET_CODE) {
                        
                        $txData['type'] = $op->getSourceAccount() === $publicKey ? 'sent' : 'received';
                        $txData['amount'] = $op->getAmount();
                        $txData['from'] = $op->getSourceAccount();
                        $txData['to'] = $op->getTo();
                        $txData['asset_code'] = $asset->getCode();
                        $txData['asset_issuer'] = $asset->getIssuer();
                        $transactions[] = $txData;
                    }
                }
                // 2. Path Payments (Strict Receive/Send)
                elseif ($op instanceof PathPaymentStrictReceiveOperationResponse || $op instanceof PathPaymentStrictSendOperationResponse) {
                    $asset = $op->getAsset();
                    if ($asset instanceof \Soneso\StellarSDK\AssetTypeCreditAlphanum && 
                        $asset->getCode() === self::DZT_ASSET_CODE &&
                        $asset->getIssuer() === self::DZT_ASSET_ISSUER) {
                        
                        $txData['type'] = $op->getSourceAccount() === $publicKey ? 'sent' : 'received';
                        $txData['amount'] = $op->getAmount();
                        $txData['from'] = $op->getSourceAccount();
                        $txData['to'] = $op->getTo();
                        $txData['asset_code'] = $asset->getCode();
                        $txData['asset_issuer'] = $asset->getIssuer();
                        $transactions[] = $txData;
                    }
                }
                // 3. Trustline (DZT Setup)
                elseif ($op instanceof ChangeTrustOperationResponse) {
                    if ($op->getAssetCode() === self::DZT_ASSET_CODE && 
                        $op->getAssetIssuer() === self::DZT_ASSET_ISSUER) {
                        
                        $txData['type'] = 'trustline';
                        $txData['amount'] = '0';
                        $txData['asset_code'] = $op->getAssetCode();
                        $transactions[] = $txData;
                    }
                }
                // 4. Account Creation
                elseif ($op instanceof CreateAccountOperationResponse) {
                    if ($op->getAccount() === $publicKey) {
                        $txData['type'] = 'account_created';
                        $txData['amount'] = $op->getStartingBalance();
                        $txData['from'] = $op->getFunder();
                        $txData['asset_code'] = 'XLM';
                        $transactions[] = $txData;
                    }
                }
            }

            return [
                'success' => true,
                'transactions' => $transactions,
                'total' => count($transactions),
                'publicKey' => $publicKey,
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'transactions' => [],
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Search transactions by memo
     */
    public function searchTransactionsByMemo(string $publicKey, string $memo, int $limit = 50): array {
        try {
            $transactions = $this->getTransactions($publicKey, 100);
            
            if (!$transactions['success'] || empty($transactions['transactions'])) {
                return [
                    'success' => true,
                    'transactions' => [],
                    'total' => 0,
                    'totalFound' => 0,
                    'searchMemo' => $memo,
                    'publicKey' => $publicKey,
                    'timestamp' => date('c')
                ];
            }
            
            $filtered = array_filter($transactions['transactions'], function($tx) use ($memo) {
                return isset($tx['memo']) && stripos($tx['memo'], (string)$memo) !== false;
            });
            
            $limited = array_slice($filtered, 0, $limit);

            return [
                'success' => true,
                'transactions' => array_values($limited),
                'total' => count($limited),
                'totalFound' => count($filtered),
                'searchMemo' => $memo,
                'publicKey' => $publicKey,
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Get transaction by hash
     */
    public function getTransactionByHash(string $hash): array {
        try {
            $tx = $this->sdk->requestTransaction($hash);
            return [
                'success' => true,
                'found' => true,
                'hash' => $hash,
                'ledger' => $tx->getLedger(),
                'created_at' => $tx->getCreatedAt(),
                'successful' => $tx->isSuccessful(),
                'memo' => $tx->getMemo() ? $tx->getMemo()->getValue() : null,
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            // Check if it's a 404 error
            $is404 = false;
            if (method_exists($e, 'getStatusCode') && $e->getStatusCode() === 404) {
                $is404 = true;
            } elseif (stripos($e->getMessage(), '404') !== false || stripos($e->getMessage(), 'not found') !== false) {
                $is404 = true;
            }

            return [
                'success' => true,
                'found' => false,
                'error' => $is404 ? 'Transaction not found' : $e->getMessage(),
                'hash' => $hash,
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Web API Services
     */
    public function makeCIBTransaction(array $params): array {
        $isSandbox = (bool) (
            isset($params['is_sandbox']) ? $params['is_sandbox'] :
            (isset($params['isSandbox']) ? $params['isSandbox'] : $this->isSandbox)
        );
        return $this->_makeCIBRequest($params, $isSandbox);
    }

    /**
     * Initiate a CIB transaction specifically in Sandbox mode.
     */
    public function makeSandboxCIBTransaction(array $params): array {
        return $this->_makeCIBRequest($params, true);
    }

    private function _makeCIBRequest(array $params, bool $useSandbox): array {
        $baseUrl = $useSandbox
            ? 'https://sofizpay.com/sandbox/make-cib-transaction/'
            : 'https://www.sofizpay.com/make-cib-transaction/';

        $queryParams = [
            'account' => $params['account'] ?? '',
            'amount' => (string)($params['amount'] ?? 0),
            'full_name' => $params['full_name'] ?? '',
            'phone' => $params['phone'] ?? '',
            'email' => $params['email'] ?? '',
        ];

        if (!empty($params['return_url'])) $queryParams['return_url'] = $params['return_url'];
        if (!empty($params['webhook_url'])) $queryParams['webhook_url'] = $params['webhook_url'];
        if (!empty($params['invoice_id'])) $queryParams['invoice_id'] = $params['invoice_id'];
        if (!empty($params['language'])) $queryParams['language'] = $params['language'];
        if (!empty($params['memo'])) $queryParams['memo'] = $params['memo'];

        if (isset($params['redirect'])) {
            $queryParams['redirect'] = is_bool($params['redirect'])
                ? ($params['redirect'] ? 'yes' : 'no')
                : $params['redirect'];
        } else {
            $queryParams['redirect'] = 'no';
        }

        if (isset($params['keep_return_url'])) {
            $queryParams['keep_return_url'] = is_bool($params['keep_return_url'])
                ? ($params['keep_return_url'] ? 'True' : 'False')
                : $params['keep_return_url'];
        }

        $res = $this->_get($baseUrl, $queryParams);

        if (!$res['success']) {
            return [
                'success' => false,
                'error' => $res['error'] ?? 'Transaction failed',
                'account' => $params['account'] ?? '',
                'amount' => $params['amount'] ?? 0,
                'timestamp' => date('c')
            ];
        }

        $data = $res['data'];
        $paymentUrl = $data['payment_url'] ?? $data['cib_response']['formUrl'] ?? null;

        return [
            'success' => ($data['status'] ?? '') !== 'error' && ($data['success'] ?? true) !== false,
            'data' => $data,
            'payment_url' => $paymentUrl,
            'transaction_id' => $data['transaction_id'] ?? null,
            'cib_transaction_id' => $data['cib_transaction_id'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'webhook_url' => $data['webhook_url'] ?? $params['webhook_url'] ?? null,
            'account' => $params['account'] ?? '',
            'amount' => $params['amount'] ?? 0,
            'full_name' => $params['full_name'] ?? '',
            'phone' => $params['phone'] ?? '',
            'email' => $params['email'] ?? '',
            'memo' => $params['memo'] ?? null,
            'is_sandbox' => $useSandbox,
            'timestamp' => date('c')
        ];
    }

    public function getProducts(?string $encSk = null): array {
        return $this->_get('https://sofizpay.com/services/get_products/', [], ['encrypted_sk' => $encSk]);
    }

    public function checkCIBStatus(string $cibTransactionId, ?bool $isSandbox = null): array {
        $useSandbox = $isSandbox !== null ? $isSandbox : $this->isSandbox;
        return $this->_checkCIBStatusRequest($cibTransactionId, $useSandbox);
    }

    /**
     * Check status of a CIB transaction specifically in Sandbox mode.
     */
    public function checkSandboxCIBStatus(string $cibTransactionId): array {
        return $this->_checkCIBStatusRequest($cibTransactionId, true);
    }

    private function _checkCIBStatusRequest(string $cibTransactionId, bool $useSandbox): array {
        $baseUrl = $useSandbox
            ? 'https://sofizpay.com/sandbox/cib-transaction-check/'
            : 'https://www.sofizpay.com/cib-transaction-check/';
        return $this->_get($baseUrl, ['order_number' => $cibTransactionId]);
    }

    public function getOperationHistory(string $encSk, int $limit = 10, int $offset = 0): array {
        return $this->_get('https://sofizpay.com/services/operation-history/', [
            'encrypted_sk' => $encSk, 
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    public function getOperationDetails(string $operationId, string $encSk): array {
        return $this->_get("https://sofizpay.com/services/operation-detail/{$operationId}/", ['encrypted_sk' => $encSk]);
    }

    /**
     * Service operations (Recharge, Bills, etc)
     */
    public function rechargePhone(array $data): array {
        return $this->_performServiceOperation($data);
    }

    public function rechargeInternet(array $data): array {
        return $this->_performServiceOperation($data);
    }

    public function rechargeGame(array $data): array {
        return $this->_performServiceOperation($data);
    }

    public function payBill(array $data): array {
        return $this->_performServiceOperation($data);
    }

    private function _performServiceOperation(array $data): array {
        return $this->_post('https://www.sofizpay.com/services/operation_post', $data);
    }

    private function _get(string $url, array $params = [], array $json = []): array {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 30]);
            $options = [
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'SofizPay-PHP-SDK/' . self::VERSION
                ]
            ];
            if (!empty($params)) {
                $options['query'] = $params;
            }
            if (!empty($json)) {
                $options['json'] = $json;
                $options['headers']['Content-Type'] = 'application/json';
            }
            
            $response = $client->request('GET', $url, $options);
            $content = $response->getBody()->getContents();
            $data = json_decode($content, true);
            
            return [
                'success' => true,
                'data' => is_array($data) ? $data : $content,
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $body = $e->getResponse()->getBody()->getContents();
                $errData = json_decode($body, true);
                if (isset($errData['error'])) $errorMsg .= " - " . $errData['error'];
            }
            return ['success' => false, 'error' => $errorMsg, 'timestamp' => date('c')];
        }
    }

    private function _post(string $url, array $data): array {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 30]);
            $response = $client->request('POST', $url, [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'SofizPay-PHP-SDK/' . self::VERSION
                ]
            ]);
            $content = $response->getBody()->getContents();
            $data = json_decode($content, true);
            
            return [
                'success' => true,
                'data' => is_array($data) ? $data : $content,
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $body = $e->getResponse()->getBody()->getContents();
                $errData = json_decode($body, true);
                if (isset($errData['error'])) $errorMsg .= " - " . $errData['error'];
            }
            return ['success' => false, 'error' => $errorMsg, 'timestamp' => date('c')];
        }
    }

    /**
     * Verify digital RSA signature of a callback / webhook notification
     *
     * @param array|string $verificationData Payload array or message string
     * @param string|null $signature Signature string if verificationData is message
     * @return bool True if signature is valid
     */
    public function verifySignature($verificationData, ?string $signature = null): bool {
        $message = '';
        $sig = '';

        if (is_string($verificationData)) {
            $message = $verificationData;
            $sig = $signature ?? '';
        } elseif (is_array($verificationData)) {
            $message = $verificationData['message'] ?? '';
            $sig = $verificationData['signature_url_safe'] ?? $verificationData['signature'] ?? '';
        }

        if (empty($message) || empty($sig)) {
            return false;
        }

        $publicKey = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA1N+bDPxpqeB9QB0affr/\n02aeRXAAnqHuLrgiUlVNdXtF7t+2w8pnEg+m9RRlc+4YEY6UyKTUjVe6k7v2p8Jj\nUItk/fMNOEg/zY222EbqsKZ2mF4hzqgyJ3QHPXjZEEqABkbcYVv4ZyV2Wq0x0ykI\n+Hy/5YWKeah4RP2uEML1FlXGpuacnMXpW6n36dne3fUN+OzILGefeRpmpnSGO5+i\nJmpF2mRdKL3hs9WgaLSg6uQyrQuJA9xqcCpUmpNbIGYXN9QZxjdyRGnxivTE8awx\nTHV3WRcKrP2krz3ruRGF6yP6PVHEuPc0YDLsYjV5uhfs7JtIksNKhRRAQ16bAsj/\n9wIDAQAB\n-----END PUBLIC KEY-----";

        $base64 = str_replace(['-', '_'], ['+', '/'], $sig);
        while (strlen($base64) % 4 !== 0) {
            $base64 .= '=';
        }
        $decodedSignature = base64_decode($base64, true);

        if ($decodedSignature === false) {
            return false;
        }

        $result = @openssl_verify($message, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    /**
     * Helper method specifically to verify incoming webhook payloads
     *
     * @param array $webhookPayload Webhook payload array containing message and signature
     * @return bool True if signature is valid
     */
    public function verifyWebhookSignature(array $webhookPayload): bool {
        return $this->verifySignature($webhookPayload);
    }

    // ==========================================
    // Backward-Compatibility Aliases
    // ==========================================

    /**
     * Alias for getBalance() to match documentation
     */
    public function getDzdBalance(string $publicKey): array {
        return $this->getBalance($publicKey);
    }

    /**
     * Alias for getTransactions() to match documentation
     */
    public function getPaymentHistory(string $publicKey, int $limit = 50): array {
        return $this->getTransactions($publicKey, $limit);
    }

    /**
     * Alias for searchTransactionsByMemo() to match documentation
     */
    public function getTransactionsByMemo(string $publicKey, string $memo, int $limit = 50): array {
        return $this->searchTransactionsByMemo($publicKey, $memo, $limit);
    }
}

// Global class aliases for backward compatibility
if (!class_exists('Sofiz\SofizPay\SofizPayClient', false)) {
    class_alias(\SofizPay\SofizPaySDK::class, 'Sofiz\SofizPay\SofizPayClient');
}

