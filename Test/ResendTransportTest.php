<?php

require_once 'tests/units/Base.php';

use Kanboard\Plugin\Resend\Http\HttpClient;
use Kanboard\Plugin\Resend\Model\ResendConfig;
use Kanboard\Plugin\Resend\Transport\Resend;
use KanboardTests\units\Base;

class FakeHttpClient implements HttpClient
{
    public array $calls = [];

    public function __construct(private array $response = [200, '{"id":"msg_1"}'])
    {
    }

    public function postJson(string $url, array $headers, string $body): array
    {
        $this->calls[] = ['url' => $url, 'headers' => $headers, 'body' => json_decode($body, true)];
        return $this->response;
    }
}

class ThrowingHttpClient implements HttpClient
{
    public function postJson(string $url, array $headers, string $body): array
    {
        throw new \Error('Call to undefined function curl_init() with re_test');
    }
}

class ResendTransportTest extends Base
{
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $logs = &$this->logs;
        $this->container['logger'] = new class($logs) extends \Psr\Log\AbstractLogger {
            private $logs;
            public function __construct(array &$logs) { $this->logs = &$logs; }
            public function log($level, $message, array $context = array()) { $this->logs[] = [$level, (string) $message]; }
        };
        $this->container['configModel']->save([
            ResendConfig::KEY   => 're_test',
            'mail_sender_address' => 'kb@example.com',
        ]);
        $this->container['memoryCache']->flush();
    }

    private function send(FakeHttpClient $http, string $toName = 'Bob', string $authorEmail = 'alice@example.com', string $authorName = 'Alice via Kanboard'): void
    {
        $t = new Resend($this->container);
        $t->setHttpClient($http);
        $t->sendEmail('bob@example.com', $toName, 'Hello', '<p>Hi</p>', $authorName, $authorEmail);
    }

    private function errors(): array
    {
        return array_values(array_map(fn ($l) => $l[1], array_filter($this->logs, fn ($l) => $l[0] === 'error')));
    }

    private function assertKeyNeverLogged(): void
    {
        foreach ($this->logs as [, $message]) {
            $this->assertStringNotContainsString('re_test', $message);
        }
    }

    public function testPostsToEmailsEndpointWithBearer(): void
    {
        $http = new FakeHttpClient();
        $this->send($http);
        $this->assertCount(1, $http->calls);
        $this->assertSame('https://api.resend.com/emails', $http->calls[0]['url']);
        $this->assertContains('Authorization: Bearer re_test', $http->calls[0]['headers']);
        $this->assertContains('Content-Type: application/json', $http->calls[0]['headers']);
        $this->assertKeyNeverLogged();
    }

    public function testPayloadDefaults(): void
    {
        $http = new FakeHttpClient();
        $this->send($http);
        $body = $http->calls[0]['body'];
        $this->assertSame('"Alice via Kanboard" <kb@example.com>', $body['from']);
        $this->assertSame(['"Bob" <bob@example.com>'], $body['to']);
        $this->assertSame('Hello', $body['subject']);
        $this->assertSame('<p>Hi</p>', $body['html']);
        $this->assertSame('alice@example.com', $body['reply_to']);
        $this->assertSame(['Auto-Submitted' => 'auto-generated'], $body['headers']);
    }

    public function testOverridesWin(): void
    {
        $this->container['configModel']->save([
            ResendConfig::FROM_ADDRESS => 'noreply@mail.example.com',
            ResendConfig::FROM_NAME    => 'Projects',
        ]);
        $this->container['memoryCache']->flush();
        $http = new FakeHttpClient();
        $this->send($http);
        $this->assertSame('"Projects" <noreply@mail.example.com>', $http->calls[0]['body']['from']);
    }

    public function testNoReplyToWhenAuthorEmailEmpty(): void
    {
        $http = new FakeHttpClient();
        $this->send($http, 'Bob', '');
        $this->assertArrayNotHasKey('reply_to', $http->calls[0]['body']);
    }

    public function testEmptyRecipientNameIsBareEmail(): void
    {
        $http = new FakeHttpClient();
        $this->send($http, '');
        $this->assertSame(['bob@example.com'], $http->calls[0]['body']['to']);
    }

    public function testNameEscaping(): void
    {
        $this->assertSame('"Doe, \"JJ\"" <x@y.z>', Resend::formatAddress('x@y.z', "Doe, \"JJ\"\r\n"));
        $this->assertSame('"a\\\\b" <x@y.z>', Resend::formatAddress('x@y.z', 'a\\b'));
    }

    public function testNoKeyLogsAndSkips(): void
    {
        $this->container['configModel']->save([ResendConfig::KEY => '']);
        $this->container['memoryCache']->flush();
        $http = new FakeHttpClient();
        $this->send($http);
        $this->assertCount(0, $http->calls);
        $this->assertCount(1, $this->errors());
        $this->assertStringContainsString('no API key', $this->errors()[0]);
    }

    public function testNon2xxLogsStatusAndMessageWithoutKey(): void
    {
        $this->send(new FakeHttpClient([422, '{"name":"validation_error","message":"Invalid from"}']));
        $this->assertCount(1, $this->errors());
        $this->assertStringContainsString('HTTP 422', $this->errors()[0]);
        $this->assertStringContainsString('Invalid from', $this->errors()[0]);
        $this->assertKeyNeverLogged();
    }

    public function testNonJsonBodyIsTrimmed(): void
    {
        $this->send(new FakeHttpClient([502, str_repeat('<html>Bad gateway', 200)]));
        $this->assertCount(1, $this->errors());
        $this->assertStringContainsString('HTTP 502', $this->errors()[0]);
        $this->assertLessThanOrEqual(300, strlen($this->errors()[0]));
    }

    public function testCurlFailureLogged(): void
    {
        $this->send(new FakeHttpClient([0, 'Could not resolve host: api.resend.com']));
        $this->assertCount(1, $this->errors());
        $this->assertStringContainsString('Could not resolve host', $this->errors()[0]);
        $this->assertKeyNeverLogged();
    }

    public function testThrowingHttpClientIsLoggedNotThrown(): void
    {
        $t = new Resend($this->container);
        $t->setHttpClient(new ThrowingHttpClient());
        $t->sendEmail('bob@example.com', 'Bob', 'Hello', '<p>Hi</p>', 'Alice', '');
        $this->assertCount(1, $this->errors());
        $this->assertStringContainsString('curl_init', $this->errors()[0]);
        $this->assertKeyNeverLogged();
    }

    public function testInvalidUtf8IsSubstitutedNotDropped(): void
    {
        $http = new FakeHttpClient();
        $t = new Resend($this->container);
        $t->setHttpClient($http);
        $t->sendEmail('bob@example.com', 'Bob', "Caf\xE9", '<p>Hi</p>', 'Alice', '');
        $this->assertSame("Caf\u{FFFD}", $http->calls[0]['body']['subject']);
    }
}
