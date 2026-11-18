<?php

namespace Kanboard\Plugin\Resend\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\Resend\Model\ResendConfig;

/**
 * Admin-only Resend settings. The stored key is never rendered — only its
 * last four characters.
 */
class ResendSettingsController extends BaseController
{
    public function show(): void
    {
        $this->assertAdmin();

        $config = new ResendConfig($this->container);

        $this->response->html($this->helper->layout->config('Resend:config/settings', [
            'title'          => t('Settings') . ' &gt; Resend',
            'masked_key'     => $config->maskedKey(),
            'key_source'     => $config->keySource(),
            'from_address'   => $config->fromAddress(),
            'from_name'      => $config->fromName(),
            'is_active'      => $this->helper->mail->getMailTransport() === 'resend',
            'mail_config_ui' => (bool) MAIL_CONFIGURATION,
        ]));
    }

    public function save(): void
    {
        $this->assertAdmin();

        // getValues() validates and consumes the single-use CSRF token: call it once.
        $values = $this->request->getValues();
        if ($values === []) {
            throw new AccessForbiddenException();
        }

        $fromAddress = trim((string) ($values['from_address'] ?? ''));
        if ($fromAddress !== '' && filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            $this->flash->failure(t('The from address is not a valid email address.'));
            $this->response->redirect($this->helper->url->to('ResendSettingsController', 'show', ['plugin' => 'Resend']));
            return;
        }

        $saved = (new ResendConfig($this->container))->save(
            (string) ($values['api_key'] ?? ''),
            ! empty($values['remove_key']),
            $fromAddress,
            (string) ($values['from_name'] ?? '')
        );

        if ($saved) {
            $this->flash->success(t('Resend settings saved.'));
        } else {
            $this->flash->failure(t('Unable to save the Resend settings.'));
        }

        $this->response->redirect($this->helper->url->to('ResendSettingsController', 'show', ['plugin' => 'Resend']));
    }

    private function assertAdmin(): void
    {
        if (! $this->userSession->isAdmin()) {
            throw new AccessForbiddenException();
        }
    }
}
