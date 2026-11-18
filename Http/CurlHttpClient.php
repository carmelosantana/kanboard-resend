<?php

namespace Kanboard\Plugin\Resend\Http;

class CurlHttpClient implements HttpClient
{
    public function __construct(private int $timeout = 10)
    {
    }

    public function postJson(string $url, array $headers, string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [0, $error];
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [$status, (string) $response];
    }
}
