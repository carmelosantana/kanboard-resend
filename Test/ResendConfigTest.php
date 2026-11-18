<?php

require_once 'tests/units/Base.php';

use Kanboard\Plugin\Resend\Model\ResendConfig;
use KanboardTests\units\Base;

class ResendConfigTest extends Base
{
    protected function tearDown(): void
    {
        putenv('RESEND_API_KEY');
        putenv('RESEND_API_URL');
        parent::tearDown();
    }

    private function config(): ResendConfig
    {
        $this->container['memoryCache']->flush();
        return new ResendConfig($this->container);
    }

    private function store(string $key): void
    {
        $this->container['configModel']->save([ResendConfig::KEY => $key]);
    }

    public function testStoredKeyWinsOverEnv(): void
    {
        putenv('RESEND_API_KEY=re_env');
        $this->store('re_stored');
        $this->assertSame('re_stored', $this->config()->apiKey());
        $this->assertSame('stored', $this->config()->keySource());
    }

    public function testEnvFallbackAndNone(): void
    {
        putenv('RESEND_API_KEY=re_env');
        $this->assertSame('re_env', $this->config()->apiKey());
        $this->assertSame('env', $this->config()->keySource());

        putenv('RESEND_API_KEY');
        $this->assertSame('', $this->config()->apiKey());
        $this->assertSame('none', $this->config()->keySource());
    }

    public function testMaskedKeyShowsOnlyLastFour(): void
    {
        $this->assertSame('', $this->config()->maskedKey());
        $this->store('re_abcdef123456');
        $masked = $this->config()->maskedKey();
        $this->assertSame('re_…3456', $masked);
        $this->assertStringNotContainsString('abcdef', $masked);
    }

    public function testSaveBlankKeepsKey(): void
    {
        $this->store('re_keep');
        $this->assertTrue($this->config()->save('', false, 'a@b.co', 'Name'));
        $c = $this->config();
        $this->assertSame('re_keep', $c->apiKey());
        $this->assertSame('a@b.co', $c->fromAddress());
        $this->assertSame('Name', $c->fromName());
    }

    public function testSaveRemoveClearsKey(): void
    {
        $this->store('re_gone');
        $this->config()->save(null, true, '', '');
        $this->assertSame('none', $this->config()->keySource());
    }

    public function testSaveNewKeyBeatsRemove(): void
    {
        $this->store('re_old');
        $this->config()->save('re_new', true, '', '');
        $this->assertSame('re_new', $this->config()->apiKey());
    }

    public function testSaveTrimsKey(): void
    {
        $this->config()->save("  re_x  \n", false, '', '');
        $this->assertSame('re_x', $this->config()->apiKey());
    }

    public function testApiUrlOverrideStripsTrailingSlash(): void
    {
        $this->assertSame('https://api.resend.com', $this->config()->apiUrl());
        putenv('RESEND_API_URL=http://mock:9/');
        $this->assertSame('http://mock:9', $this->config()->apiUrl());
    }
}
