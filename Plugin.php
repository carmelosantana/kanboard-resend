<?php

namespace Kanboard\Plugin\Resend;

use Kanboard\Core\Plugin\Base;
use Kanboard\Plugin\Resend\Transport\Resend;

/**
 * Resend — sends Kanboard email through the Resend HTTP API.
 *
 * Registers the "resend" mail transport (it then appears in Settings → Email)
 * and an admin-only settings page. See docs/superpowers/specs/2026-11-18-resend-design.md.
 */
class Plugin extends Base
{
    public const VERSION = '1.0.0';

    public function initialize(): void
    {
        $this->emailClient->setTransport('resend', Resend::class);

        $this->hook->on('template:config:sidebar', ['template' => 'Resend:config/sidebar']);

        $this->route->addRoute('resend/settings', 'SettingsController', 'show', 'Resend');
        $this->route->addRoute('resend/save', 'SettingsController', 'save', 'Resend');
    }

    public function getPluginName(): string
    {
        return 'Resend';
    }

    public function getPluginDescription(): string
    {
        return t('Send all Kanboard email through the Resend HTTP API.');
    }

    public function getPluginAuthor(): string
    {
        return 'Carmelo Santana';
    }

    public function getPluginVersion(): string
    {
        return self::VERSION;
    }

    public function getPluginHomepage(): string
    {
        return 'https://github.com/carmelosantana/kanboard-resend';
    }

    public function getCompatibleVersion(): string
    {
        return '>=1.2.47';
    }
}
