<?php

require_once 'tests/units/Base.php';

use Kanboard\Plugin\Resend\Plugin;
use Kanboard\Plugin\Resend\Transport\Resend;
use KanboardTests\units\Base;

class PluginTest extends Base
{
    public function testResendTransportRegistered(): void
    {
        // A real mail client: Kanboard 1.2.5x's test Base stubs every emailClient method, so setTransport() is a no-op.
        unset($this->container['emailClient']);
        $this->container['emailClient'] = new \Kanboard\Core\Mail\Client($this->container);
        (new Plugin($this->container))->initialize();
        $this->assertArrayHasKey('resend', $this->container['emailClient']->getAvailableTransports());
        $this->assertInstanceOf(Resend::class, $this->container['emailClient']->getTransport('resend'));
    }

    public function testSettingsPagesAreAdminOnly(): void
    {
        (new Plugin($this->container))->initialize();
        $authz = $this->container['applicationAuthorization'];
        foreach (['show', 'save'] as $action) {
            $this->assertTrue($authz->isAllowed('ResendSettingsController', $action, \Kanboard\Core\Security\Role::APP_ADMIN));
            $this->assertFalse($authz->isAllowed('ResendSettingsController', $action, \Kanboard\Core\Security\Role::APP_MANAGER));
            $this->assertFalse($authz->isAllowed('ResendSettingsController', $action, \Kanboard\Core\Security\Role::APP_USER));
        }
    }

    public function testVersionMatchesPluginJson(): void
    {
        $json = json_decode(file_get_contents(__DIR__ . '/../plugin.json'), true);
        $this->assertSame($json['version'], (new Plugin($this->container))->getPluginVersion());
    }
}
