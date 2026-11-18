<div class="page-header">
    <h2>Resend</h2>
</div>

<fieldset>
    <legend><?= t('Status') ?></legend>
    <?php if ($is_active): ?>
        <p class="alert alert-success"><?= t('Resend is the active email transport.') ?></p>
    <?php elseif ($mail_config_ui): ?>
        <p class="alert">
            <?= t('Resend is not the active email transport.') ?>
            <?= $this->url->link(t('Select "resend" in Email settings'), 'ConfigController', 'email') ?>
        </p>
    <?php else: ?>
        <p class="alert">
            <?= t('Resend is not the active email transport. Email settings are locked by the administrator: set MAIL_TRANSPORT=resend in config.php or the environment.') ?>
        </p>
    <?php endif ?>
    <p class="form-help"><?= t('Resend only sends from a domain you have verified in your Resend account.') ?></p>
</fieldset>

<form method="post" action="<?= $this->url->href('ResendSettingsController', 'save', ['plugin' => 'Resend']) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <fieldset>
        <legend><?= t('API key') ?></legend>
        <?php if ($key_source === 'stored'): ?>
            <p><?= t('A key is stored:') ?> <code><?= $this->text->e($masked_key) ?></code></p>
        <?php elseif ($key_source === 'env'): ?>
            <p><?= t('Using the RESEND_API_KEY environment variable:') ?> <code><?= $this->text->e($masked_key) ?></code></p>
        <?php else: ?>
            <p class="alert alert-error"><?= t('No API key is configured. Email sent through Resend will be dropped.') ?></p>
        <?php endif ?>

        <label for="form-api_key"><?= t('New API key') ?></label>
        <input type="password" name="api_key" id="form-api_key" class="form-text" value="" autocomplete="new-password" placeholder="re_…">
        <p class="form-help"><?= t('Stored keys are never shown. Leave blank to keep the current key. Falls back to RESEND_API_KEY.') ?></p>

        <?php if ($key_source === 'stored'): ?>
            <?= $this->form->checkbox('remove_key', t('Remove stored key'), 1, false) ?>
        <?php endif ?>
    </fieldset>

    <fieldset>
        <legend><?= t('Sender') ?></legend>
        <?= $this->form->label(t('From address'), 'from_address') ?>
        <?= $this->form->email('from_address', ['from_address' => $from_address], [], ['placeholder="' . $this->text->e($this->app->config('mail_sender_address') ?: MAIL_FROM) . '"']) ?>
        <p class="form-help"><?= t('Optional. Blank uses the sender address from Email settings.') ?></p>

        <?= $this->form->label(t('From name'), 'from_name') ?>
        <?= $this->form->text('from_name', ['from_name' => $from_name], [], ['placeholder="' . t('%s via Kanboard', t('User')) . '"']) ?>
        <p class="form-help"><?= t('Optional. Blank keeps Kanboard\'s per-email name, e.g. "Alice via Kanboard".') ?></p>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-blue"><?= t('Save') ?></button>
    </div>
</form>
