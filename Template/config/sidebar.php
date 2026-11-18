<li <?= $this->app->checkMenuSelection('ResendSettingsController', 'show', 'Resend') ?>>
    <?= $this->url->link('Resend', 'ResendSettingsController', 'show', ['plugin' => 'Resend']) ?>
</li>
