<?php

namespace Kanboard\Plugin\Resend\Transport;

use Kanboard\Core\Base;
use Kanboard\Core\Mail\ClientInterface;
use Kanboard\Plugin\Resend\Http\CurlHttpClient;
use Kanboard\Plugin\Resend\Http\HttpClient;
use Kanboard\Plugin\Resend\Model\ResendConfig;
use Kanboard\Plugin\Resend\Plugin;

/**
 * Mail transport that sends through the Resend HTTP API.
 *
 * Never throws: failures are logged (without the API key) and the email is
 * dropped, like the core Mail/Smtp transports.
 */
class Resend extends Base implements ClientInterface
{
    private ?HttpClient $http = null;

    public function setHttpClient(HttpClient $http): void
    {
        $this->http = $http;
    }

    public static function formatAddress(string $email, string $name): string
    {
        $name = trim(str_replace(["\r", "\n"], '', $name));
        if ($name === '') {
            return $email;
        }

        return '"' . addcslashes($name, '"\\') . '" <' . $email . '>';
    }

    public function sendEmail($recipientEmail, $recipientName, $subject, $html, $authorName, $authorEmail = '')
    {
        $config = new ResendConfig($this->container);
        $key = $config->apiKey();

        try {
            $this->send($config, $key, $recipientEmail, $recipientName, $subject, $html, $authorName, $authorEmail);
        } catch (\Throwable $e) {
            $message = 'Resend: ' . $e->getMessage();
            $this->logger->error($key === '' ? $message : str_replace($key, '[redacted]', $message));
        }
    }

    private function send(ResendConfig $config, string $key, $recipientEmail, $recipientName, $subject, $html, $authorName, $authorEmail): void
    {
        if ($key === '') {
            $this->logger->error('Resend: no API key configured; email to ' . $recipientEmail . ' not sent');
            return;
        }

        $payload = [
            'from'    => self::formatAddress(
                $config->fromAddress() ?: $this->helper->mail->getMailSenderAddress(),
                $config->fromName() ?: (string) $authorName
            ),
            'to'      => [self::formatAddress((string) $recipientEmail, (string) $recipientName)],
            'subject' => (string) $subject,
            'html'    => (string) $html,
            'headers' => ['Auto-Submitted' => 'auto-generated'],
        ];

        if (! empty($authorEmail)) {
            $payload['reply_to'] = (string) $authorEmail;
        }

        if (! empty(MAIL_BCC)) {
            $payload['bcc'] = [MAIL_BCC];
        }

        [$status, $body] = ($this->http ?? new CurlHttpClient())->postJson(
            $config->apiUrl() . '/emails',
            [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
                'User-Agent: kanboard-resend/' . Plugin::VERSION,
            ],
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
        );

        if ($status >= 200 && $status < 300) {
            $id = json_decode($body, true)['id'] ?? '?';
            $this->logger->debug('Resend: sent to ' . $recipientEmail . ', id ' . $id);
            return;
        }

        $this->logger->error(str_replace($key, '[redacted]', $this->describeFailure($status, $body)));
    }

    private function describeFailure(int $status, string $body): string
    {
        if ($status === 0) {
            return 'Resend: request failed: ' . mb_substr($body, 0, 200);
        }

        $decoded = json_decode($body, true);
        $message = is_array($decoded) && isset($decoded['message'])
            ? (string) $decoded['message']
            : trim(preg_replace('/\s+/', ' ', strip_tags($body)));

        return 'Resend: HTTP ' . $status . ': ' . mb_substr($message, 0, 200);
    }
}
