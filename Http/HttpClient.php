<?php

namespace Kanboard\Plugin\Resend\Http;

/**
 * Minimal HTTP seam so the transport can be tested without the network.
 */
interface HttpClient
{
    /**
     * @param  string[] $headers  "Name: value" lines
     * @return array{0: int, 1: string}  [status, body]; status 0 = transport failure, body = error text
     */
    public function postJson(string $url, array $headers, string $body): array;
}
