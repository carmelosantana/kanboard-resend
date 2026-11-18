# Resend for Kanboard

Sends all Kanboard email through the [Resend](https://resend.com) HTTP API. No SMTP server, no Composer dependencies — plain PHP with ext-curl.

Requires Kanboard ≥ 1.2.47 and PHP ≥ 8.4.

## Install

Unzip the release into `plugins/Resend` (or install it from ModMenu), so the folder holds `plugin.json`.

## Configure

1. In Resend, verify the domain you will send from and create an API key (sending access is enough).
2. In Kanboard, open **Settings → Resend** (administrators only):
   - **New API key** — paste the key and save. A stored key is never shown again, only its last four characters. Leave the field blank to keep it; tick **Remove stored key** to delete it.
   - **From address** (optional) — must be on your verified domain. Blank uses the sender address from **Settings → Email** (or `MAIL_FROM`).
   - **From name** (optional) — blank keeps Kanboard's per-email name, e.g. "Alice via Kanboard".
3. Make Resend the active transport: in **Settings → Email**, choose `resend` as the email transport. If the email settings are locked (`MAIL_CONFIGURATION` is false), set `MAIL_TRANSPORT=resend` in `config.php` or the environment instead. The Resend page shows whether it is active.

Replies go to the user who triggered the email, and every message carries `Auto-Submitted: auto-generated`. `MAIL_BCC` is honoured.

## Environment variables

| Variable | Purpose |
|----------|---------|
| `RESEND_API_KEY` | Used when no key is stored in Settings. |
| `RESEND_API_URL` | API base URL, default `https://api.resend.com`. For testing against a mock. |

The official Kanboard Docker image runs php-fpm with a whitelist of environment variables, so these only reach PHP if you add them to `/etc/php84/php-fpm.d/env.conf`:

```
env[RESEND_API_KEY] = $RESEND_API_KEY
```

Storing the key in **Settings → Resend** avoids this.

## Troubleshooting

Failed sends are logged through Kanboard's logger (the API key is never logged), for example:

```
Resend: HTTP 403: The example.com domain is not verified.
Resend: no API key configured; email to bob@example.com not sent
```

A failed send drops that email; Kanboard does not retry it.

## License

MIT
