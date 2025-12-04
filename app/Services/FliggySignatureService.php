<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FliggySignatureService
{
    protected $privateKey;
    protected $publicKey;

    public function __construct()
    {
        $this->privateKey = config('fliggy.private_key');
        $this->publicKey = config('fliggy.public_key'); // 可选

        if (!$this->privateKey) {
            throw new \Exception("Fliggy Private Key not configured.");
        }
    }

    /**
     * 生成签名
     *
     * @param string $dataToSign 签名原始字符串 (param)
     * @return string base64 编码后的签名
     */
    public function generateSignature(string $dataToSign): string
    {
        $privateKey = openssl_pkey_get_private($this->privateKey);

        if (!$privateKey) {
            Log::error("Unable to load private key for signing.", ['key_snippet' => substr($this->privateKey, 0, 50)]);
            throw new \Exception("Unable to load private key for signing.");
        }

        $signature = '';
        $result = openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        openssl_free_key($privateKey);

        if (!$result) {
            Log::error("OpenSSL signing failed.");
            throw new \Exception("OpenSSL signing failed.");
        }

        return base64_encode($signature);
    }

    /**
     * 验证签名 (处理 Fliggy 推送时使用)
     *
     * @param string $dataToSign 签名原始字符串 (param)
     * @param string $signature base64 编码的签名
     * @return bool
     */
    public function verifySignature(string $dataToSign, string $signature): bool
    {
        if (!$this->publicKey) {
            Log::warning("Fliggy Public Key not configured. Skipping signature verification.");
            // 根据业务需求决定是否允许无签名验证。此处选择拒绝。
            // 如果文档说明无需验证，可以直接返回 true。
            // return true;
            return false; // 更安全的做法是必须配置公钥才能验证
        }

        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            Log::warning("Invalid base64 encoded signature received.");
            return false;
        }

        $publicKeyResource = openssl_pkey_get_public($this->publicKey);

        if (!$publicKeyResource) {
            Log::error("Unable to load public key for verification.", ['key_snippet' => substr($this->publicKey, 0, 50)]);
            return false;
        }

        $result = openssl_verify($dataToSign, $decodedSignature, $publicKeyResource, OPENSSL_ALGO_SHA256);
        openssl_free_key($publicKeyResource);

        if ($result === 1) {
            return true; // 验证成功
        } elseif ($result === 0) {
            Log::notice("Signature verification failed: Signature invalid.", ['data_to_sign' => $dataToSign, 'received_signature' => $signature]);
        } else {
            Log::error("Signature verification error: OpenSSL error occurred.", ['openssl_error' => openssl_error_string()]);
        }

        return false; // 验证失败或出错
    }
}
