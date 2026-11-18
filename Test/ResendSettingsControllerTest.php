<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\Resend\Controller\ResendSettingsController;
use Kanboard\Plugin\Resend\Model\ResendConfig;
use KanboardTests\units\Base;

/** Renders only the page body: the full config layout needs a real logged-in session. */
class ResendBodyOnlyLayoutHelper extends \Kanboard\Helper\LayoutHelper
{
    public function config($template, array $params)
    {
        return $this->template->render($template, $params);
    }
}

class ResendSettingsControllerTest extends Base
{
    private string $html = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->container['helper']->register('layout', ResendBodyOnlyLayoutHelper::class);
    }

    private array $flashes = [];

    private function stub(array $formValues, bool $admin = true): void
    {
        $session = $this->getMockBuilder(\Kanboard\Core\User\UserSession::class)
            ->setConstructorArgs([$this->container])->onlyMethods(['isAdmin', 'getId', 'isLogged', 'getRole'])->getMock();
        $session->method('isAdmin')->willReturn($admin);
        $session->method('getId')->willReturn(1);
        $session->method('isLogged')->willReturn(true);
        $session->method('getRole')->willReturn($admin ? 'app-admin' : 'app-manager');
        unset($this->container['userSession']);
        $this->container['userSession'] = $session;

        $request = $this->getMockBuilder(\Kanboard\Core\Http\Request::class)
            ->setConstructorArgs([$this->container])->onlyMethods(['getValues'])->getMock();
        $request->expects($this->atMost(1))->method('getValues')->willReturn($formValues);
        unset($this->container['request']);
        $this->container['request'] = $request;

        $flash = $this->getMockBuilder(\Kanboard\Core\Session\FlashMessage::class)
            ->setConstructorArgs([$this->container])->onlyMethods(['success', 'failure'])->getMock();
        $flash->method('success')->willReturnCallback(function ($m) { $this->flashes['success'] = $m; });
        $flash->method('failure')->willReturnCallback(function ($m) { $this->flashes['failure'] = $m; });
        unset($this->container['flash']);
        $this->container['flash'] = $flash;

        $response = $this->getMockBuilder(\Kanboard\Core\Http\Response::class)
            ->setConstructorArgs([$this->container])->onlyMethods(['redirect', 'html'])->getMock();
        $response->method('html')->willReturnCallback(function ($h) { $this->html = $h; });
        unset($this->container['response']);
        $this->container['response'] = $response;
    }

    private function seed(array $settings): void
    {
        $this->container['configModel']->save($settings);
        $this->container['memoryCache']->flush();
    }

    private function setting(string $key): string
    {
        $this->container['memoryCache']->flush();
        return (string) $this->container['configModel']->get($key, '');
    }

    private function show(): void
    {
        $this->stub([]);
        (new ResendSettingsController($this->container))->show();
    }

    private function save(array $values, bool $admin = true): void
    {
        $this->stub($values, $admin);
        (new ResendSettingsController($this->container))->save();
    }

    public function testShowRendersMaskedKeyNeverFull(): void
    {
        $this->seed([ResendConfig::KEY => 're_abcdef123456']);
        $this->show();
        $this->assertStringContainsString('re_…3456', $this->html);
        $this->assertStringNotContainsString('abcdef123456', $this->html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="password"[^>]*value=""/', $this->html);
    }

    public function testShowReportsTransportStatus(): void
    {
        $this->seed(['mail_transport' => 'smtp']);
        $this->show();
        $this->assertStringContainsString('not the active email transport', $this->html);

        $this->seed(['mail_transport' => 'resend']);
        $this->show();
        $this->assertStringContainsString('Resend is the active email transport', $this->html);
    }

    public function testSaveStoresTrimmedKeyAndFrom(): void
    {
        $this->save(['api_key' => '  re_new  ', 'from_address' => 'noreply@x.io', 'from_name' => 'Projects']);
        $this->assertSame('re_new', $this->setting(ResendConfig::KEY));
        $this->assertSame('noreply@x.io', $this->setting(ResendConfig::FROM_ADDRESS));
        $this->assertSame('Projects', $this->setting(ResendConfig::FROM_NAME));
        $this->assertArrayHasKey('success', $this->flashes);
        $this->assertStringNotContainsString('re_new', $this->flashes['success']);
    }

    public function testSaveBlankKeyKeepsStored(): void
    {
        $this->seed([ResendConfig::KEY => 're_keep']);
        $this->save(['api_key' => '', 'from_address' => '', 'from_name' => '']);
        $this->assertSame('re_keep', $this->setting(ResendConfig::KEY));
    }

    public function testSaveRemoveClearsKey(): void
    {
        $this->seed([ResendConfig::KEY => 're_gone']);
        $this->save(['api_key' => '', 'remove_key' => '1', 'from_address' => '', 'from_name' => '']);
        $this->assertSame('', $this->setting(ResendConfig::KEY));
    }

    public function testInvalidFromAddressRejected(): void
    {
        $this->seed([ResendConfig::KEY => 're_keep', ResendConfig::FROM_ADDRESS => 'old@x.io']);
        $this->save(['api_key' => 're_other', 'from_address' => 'not-an-email', 'from_name' => '']);
        $this->assertArrayHasKey('failure', $this->flashes);
        $this->assertSame('re_keep', $this->setting(ResendConfig::KEY));
        $this->assertSame('old@x.io', $this->setting(ResendConfig::FROM_ADDRESS));
    }

    public function testCsrfFailureForbidden(): void
    {
        $this->seed([ResendConfig::KEY => 're_keep']);
        try {
            $this->save([]);
            $this->fail('expected AccessForbiddenException');
        } catch (AccessForbiddenException $e) {
        }
        $this->assertSame('re_keep', $this->setting(ResendConfig::KEY));
    }

    public function testNonAdminForbidden(): void
    {
        foreach (['show', 'save'] as $action) {
            $this->stub(['api_key' => 're_evil', 'from_address' => '', 'from_name' => ''], false);
            try {
                (new ResendSettingsController($this->container))->$action();
                $this->fail("expected AccessForbiddenException on $action");
            } catch (AccessForbiddenException $e) {
            }
        }
        $this->assertSame('', $this->setting(ResendConfig::KEY));
    }
}
