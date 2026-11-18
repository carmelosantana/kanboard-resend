<?php

require_once 'tests/units/Base.php';

use Kanboard\Plugin\Resend\Plugin;
use Kanboard\Plugin\Resend\Transport\Resend;
use KanboardTests\units\Base;

class PluginTest extends Base
{
    public function testResendTransportRegistered(): void
    {
        (new Plugin($this->container))->initialize();
        $this->assertArrayHasKey('resend', $this->container['emailClient']->getAvailableTransports());
        $this->assertInstanceOf(Resend::class, $this->container['emailClient']->getTransport('resend'));
    }

    public function testVersionMatchesPluginJson(): void
    {
        $json = json_decode(file_get_contents(__DIR__ . '/../plugin.json'), true);
        $this->assertSame($json['version'], (new Plugin($this->container))->getPluginVersion());
    }
}
