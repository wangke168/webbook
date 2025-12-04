<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FliggySignatureService
{
    protected string $privateKey;
    protected ?string $publicKey; // Can be null if verification isn't needed/configured

    public function __construct(?string $privateKey = null)
    {
        // 如果传入了privateKey则使用，否则从配置文件读取
        $this->privateKey = $privateKey ?? config('fliggy.private_key') ?? config('fliggy.app_secret');
        $this->publicKey = config('fliggy.public_key'); // Optional for verification

        // 检查privateKey是否为空
        if (empty($this->privateKey)) {
            Log::warning("Fliggy Private Key is not configured. Only custom API calls will be available.");
        }
    }

    /**
     * Generates an RSA-SHA256 signature for Fliggy API calls.
     * Assumes sign_method=hmac (RSA-SHA256) is used.
     *
     * @param string $dataToSign The string to be signed.
     * @return string Base64 encoded signature.
     * @throws \Exception If signing fails.
     */
    public function generateSignature(string $dataToSign): string
    {
        // 飞猪提供的privateKey是Base64编码的，需要先解码
        $privateKeyDecoded = base64_decode($this->privateKey);
        if ($privateKeyDecoded === false) {
            // 如果解码失败，可能是PEM格式的私钥，直接使用
            $privateKeyDecoded = $this->privateKey;
        }

        // 构建 PKCS8 格式的私钥
        $privateKeyPem = $this->buildPemKey($privateKeyDecoded, 'PRIVATE');
        $privateKeyResource = openssl_pkey_get_private($privateKeyPem);

        if (!$privateKeyResource) {
            Log::error("Unable to load private key for signing.", [
                'key_length' => strlen($this->privateKey),
                'openssl_error' => openssl_error_string()
            ]);
            throw new \Exception("Unable to load private key for signing. Check configuration and key format.");
        }

        $signature = '';
        // Using OPENSSL_ALGO_SHA256 for RSA-SHA256 (SHA256withRSA)
        $result = openssl_sign($dataToSign, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);

        openssl_free_key($privateKeyResource);

        if (!$result) {
            Log::error("OpenSSL signing operation failed.", ['openssl_error' => openssl_error_string()]);
            throw new \Exception("OpenSSL signing failed: " . openssl_error_string());
        }

        $encodedSignature = base64_encode($signature);
        Log::debug("Generated Fliggy Signature", [
            'data_to_sign_length' => strlen($dataToSign),
            'data_to_sign' => $dataToSign,
            'signature_base64' => $encodedSignature
        ]);

        return $encodedSignature;
    }

    /**
     * 构建 PEM 格式的密钥
     *
     * @param string $keyData 密钥数据
     * @param string $type 'PRIVATE' or 'PUBLIC'
     * @return string
     */
    private function buildPemKey(string $keyData, string $type = 'PRIVATE'): string
    {
        // 如果已经是PEM格式，直接返回
        if (strpos($keyData, '-----BEGIN') !== false) {
            return $keyData;
        }

        // 否则构建 PEM 格式
        $keyBase64 = base64_encode($keyData);
        $keyFormatted = chunk_split($keyBase64, 64, "\n");
        
        if ($type === 'PRIVATE') {
            return "-----BEGIN PRIVATE KEY-----\n" . $keyFormatted . "-----END PRIVATE KEY-----\n";
        } else {
            return "-----BEGIN PUBLIC KEY-----\n" . $keyFormatted . "-----END PUBLIC KEY-----\n";
        }
    }

    /**
     * Verifies an RSA-SHA256 signature from Fliggy notifications.
     * Assumes sign_method=hmac (RSA-SHA256) was used by Fliggy.
     *
     * @param string $dataToSign The original string that was signed.
     * @param string $signature The base64 encoded signature received.
     * @return bool True if verified, false otherwise.
     */
    public function verifySignature(string $dataToSign, string $signature): bool
    {
        if (empty($this->publicKey)) {
            Log::warning("Fliggy Public Key not configured. Signature verification skipped.");
            return false; // Or true based on security policy
        }

        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            Log::warning("Invalid base64 encoded signature received for verification.");
            return false;
        }

        $publicKeyResource = openssl_pkey_get_public($this->publicKey);

        if (!$publicKeyResource) {
            $keySnippet = substr($this->publicKey, 0, 50) . (strlen($this->publicKey) > 50 ? '...' : '');
            Log::error("Unable to load public key for verification.", ['key_snippet' => $keySnippet]);
            return false;
        }

        $result = openssl_verify($dataToSign, $decodedSignature, $publicKeyResource, OPENSSL_ALGO_SHA256);
        openssl_free_key($publicKeyResource);

        if ($result === 1) {
            Log::info("Fliggy signature verified successfully.");
            return true;
        } elseif ($result === 0) {
            Log::notice("Fliggy signature verification failed: Signature invalid.", [
                'data_to_sign_snippet' => substr($dataToSign, 0, 100),
                'received_signature' => $signature
            ]);
        } else {
            Log::error("Fliggy signature verification error: OpenSSL error occurred.", [
                'openssl_error' => openssl_error_string()
            ]);
        }

        return false;
    }
}

?>
