<?php

namespace Kanboard\Plugin\Resend\Model;

use Kanboard\Core\Base;

/**
 * Resend settings and API-key resolution.
 *
 * Values live in Kanboard's settings table. The key is resolved stored-first,
 * then the RESEND_API_KEY env var, and is only ever displayed masked.
 */
class ResendConfig extends Base
{
    public const KEY = 'resend_api_key';
    public const FROM_ADDRESS = 'resend_from_address';
    public const FROM_NAME = 'resend_from_name';
    public const DEFAULT_URL = 'https://api.resend.com';

    public function apiKey(): string
    {
        $stored = $this->storedKey();
        if ($stored !== '') {
            return $stored;
        }

        return trim((string) getenv('RESEND_API_KEY'));
    }

    public function keySource(): string
    {
        if ($this->storedKey() !== '') {
            return 'stored';
        }

        return trim((string) getenv('RESEND_API_KEY')) !== '' ? 'env' : 'none';
    }

    public function maskedKey(): string
    {
        $key = $this->apiKey();

        return $key === '' ? '' : 're_…' . substr($key, -4);
    }

    public function fromAddress(): string
    {
        return trim((string) $this->configModel->get(self::FROM_ADDRESS, ''));
    }

    public function fromName(): string
    {
        return trim((string) $this->configModel->get(self::FROM_NAME, ''));
    }

    public function apiUrl(): string
    {
        return rtrim(getenv('RESEND_API_URL') ?: self::DEFAULT_URL, '/');
    }

    public function save(?string $newKey, bool $removeKey, string $fromAddress, string $fromName): bool
    {
        $values = [
            self::FROM_ADDRESS => trim($fromAddress),
            self::FROM_NAME    => trim($fromName),
        ];

        $newKey = trim((string) $newKey);
        if ($newKey !== '') {
            $values[self::KEY] = $newKey;
        } elseif ($removeKey) {
            $values[self::KEY] = '';
        }

        $saved = $this->configModel->save($values);
        $this->memoryCache->flush();

        return $saved;
    }

    private function storedKey(): string
    {
        return trim((string) $this->configModel->get(self::KEY, ''));
    }
}
