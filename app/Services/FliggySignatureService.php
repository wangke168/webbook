<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FliggySignatureService
{
    protected string $privateKey;
    protected ?string $publicKey; // Can be null if verification isn't needed/configured

    public function __construct()
    {
        $this->privateKey = config('fliggy.private_key');
        $this->publicKey = config('fliggy.public_key'); // Optional for verification

        if (empty($this->privateKey)) {
            Log::critical("Fliggy Private Key is missing or empty in configuration.");
            throw new \InvalidArgumentException("Fliggy Private Key not configured correctly.");
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
        $privateKeyResource = openssl_pkey_get_private($this->privateKey);

        if (!$privateKeyResource) {
            $keySnippet = substr($this->privateKey, 0, 50) . (strlen($this->privateKey) > 50 ? '...' : '');
            Log::error("Unable to load private key for signing.", ['key_snippet' => $keySnippet]);
            throw new \Exception("Unable to load private key for signing. Check configuration and key format.");
        }

        $signature = '';
        // Using OPENSSL_ALGO_SHA256 for RSA-SHA256
        $result = openssl_sign($dataToSign, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);

        openssl_free_key($privateKeyResource);

        if (!$result) {
            Log::error("OpenSSL signing operation failed.", ['openssl_error' => openssl_error_string()]);
            throw new \Exception("OpenSSL signing failed: " . openssl_error_string());
        }

        $encodedSignature = base64_encode($signature);
        Log::debug("Generated Fliggy Signature", [
            'data_to_sign_length' => strlen($dataToSign),
            'data_to_sign_snippet' => substr($dataToSign, 0, 100) . (strlen($dataToSign) > 100 ? '...' : ''),
            'signature_base64' => $encodedSignature
        ]);

        return $encodedSignature;
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
