<?php

declare(strict_types=1);

namespace NakoPay\Magento2\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use NakoPay\Magento2\Logger\Logger;

/**
 * HTTP client for NakoPay API. Handles dual base URL strategy,
 * authentication, and HMAC-SHA256 webhook signature verification.
 */
class ApiClient
{
    private const PRIMARY_BASE = 'https://daslrxpkbkqrbnjwouiq.supabase.co/functions/v1';
    private const FALLBACK_BASE = 'https://api.nakopay.com/v1';
    private const API_VERSION = '2025-04-20';
    private const PLUGIN_VERSION = '1.0.0';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Logger $logger,
    ) {}

    /**
     * Resolve the active API base URL.
     * Priority: admin setting > PHP constant > PRIMARY.
     */
    public function getBaseUrl(): string
    {
        $adminUrl = trim((string) $this->getConfig('api_base_url'));
        if ($adminUrl !== '') {
            return rtrim($adminUrl, '/');
        }
        if (defined('NAKOPAY_API_BASE')) {
            return rtrim((string) constant('NAKOPAY_API_BASE'), '/');
        }
        return self::PRIMARY_BASE;
    }

    public function getApiKey(): string
    {
        return (string) $this->getConfig('api_key');
    }

    public function getWebhookSecret(): string
    {
        return (string) $this->getConfig('webhook_secret');
    }

    /**
     * Create a NakoPay invoice via the API.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws \RuntimeException on API error
     */
    public function createInvoice(array $params): array
    {
        return $this->post('/invoices-create', $params);
    }

    /**
     * Retrieve an invoice by ID.
     *
     * @return array<string, mixed>
     */
    public function getInvoice(string $id): array
    {
        return $this->get('/invoices-get', ['id' => $id]);
    }

    /**
     * Verify a webhook signature (HMAC-SHA256).
     *
     * @param string $payload Raw request body
     * @param string $signatureHeader X-NakoPay-Signature header value
     * @param int $tolerance Max age in seconds (default 300)
     * @return array<string, mixed> Parsed event payload
     * @throws \RuntimeException on verification failure
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader, int $tolerance = 300): array
    {
        $secret = $this->getWebhookSecret();
        if ($secret === '') {
            throw new \RuntimeException('NakoPay webhook secret is not configured');
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $kv) {
            $i = strpos($kv, '=');
            if ($i === false) continue;
            $parts[trim(substr($kv, 0, $i))] = trim(substr($kv, $i + 1));
        }

        if (!isset($parts['t'], $parts['v1']) || !ctype_digit($parts['t'])) {
            throw new \RuntimeException('Malformed NakoPay signature header');
        }

        $t = (int) $parts['t'];
        if (abs(time() - $t) > $tolerance) {
            throw new \RuntimeException("NakoPay webhook timestamp {$t} outside tolerance of {$tolerance}s");
        }

        $expected = hash_hmac('sha256', "{$t}.{$payload}", $secret);
        if (!hash_equals($expected, $parts['v1'])) {
            throw new \RuntimeException('NakoPay webhook signature mismatch');
        }

        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('NakoPay webhook payload is not valid JSON');
        }
        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        $url = $this->getBaseUrl() . $path;
        $json = json_encode($body, JSON_THROW_ON_ERROR);
        $idempotencyKey = 'idem_' . bin2hex(random_bytes(16));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->getApiKey(),
                'Content-Type: application/json',
                'Accept: application/json',
                'X-NakoPay-Version: ' . self::API_VERSION,
                'Idempotency-Key: ' . $idempotencyKey,
                'User-Agent: nakopay-magento2/' . self::PLUGIN_VERSION,
            ],
        ]);

        return $this->execute($ch, $url);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $url = $this->getBaseUrl() . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query(array_filter($query, fn($v) => $v !== null));
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->getApiKey(),
                'Accept: application/json',
                'X-NakoPay-Version: ' . self::API_VERSION,
                'User-Agent: nakopay-magento2/' . self::PLUGIN_VERSION,
            ],
        ]);

        return $this->execute($ch, $url);
    }

    /**
     * @return array<string, mixed>
     */
    private function execute(\CurlHandle $ch, string $url): array
    {
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->logger->error("NakoPay API connection error: {$err}", ['url' => $url]);
            throw new \RuntimeException("NakoPay API connection failed: {$err}");
        }

        $data = json_decode((string) $response, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $data['error']['message'] ?? "HTTP {$httpCode}";
            $code = $data['error']['code'] ?? 'api_error';
            $this->logger->error("NakoPay API error: [{$code}] {$msg}", [
                'url' => $url,
                'http_status' => $httpCode,
            ]);
            throw new \RuntimeException("NakoPay API error: [{$code}] {$msg}");
        }

        return $data ?? [];
    }

    private function getConfig(string $field): mixed
    {
        return $this->scopeConfig->getValue(
            "payment/nakopay/{$field}",
            ScopeInterface::SCOPE_STORE,
        );
    }
}
