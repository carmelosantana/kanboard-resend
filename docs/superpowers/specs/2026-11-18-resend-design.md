# Resend — design

## Intent

Carmelo's Kanboard instances have no working email. Resend (resend.com) gives
reliable delivery from an HTTP API and one key, with no SMTP server to run. This
plugin adds Resend as a Kanboard mail transport, packaged as its own suite plugin
(`carmelosantana/kanboard-resend`).

## Goals

- Every email Kanboard sends (notifications, password reset, etc.) can go through Resend.
- An admin configures it from a Settings page: API key, optional from-address and
  from-name.
- The API key never leaves the server once saved: never echoed into a page, a log
  line or an error.

## Non-goals

- A "send test email" button. A sibling admin-tools plugin adds one that works with
  any transport.
- Resend features beyond plain sending: batching, attachments, scheduling, webhooks,
  domain management, delivery tracking.
- Changing how Kanboard decides what to email.

## Anti-goals

- A Composer dependency or a build step (suite is buildless; plain PHP `curl`).
- A key that leaks: echoed in the form, written to the log, or shown in a flash.
- A plugin that breaks email when it is installed but not configured: until an admin
  selects `resend` as the transport, Kanboard behaves exactly as before.

## Constraints

- Kanboard v1.2.47, PHP ≥ 8.4, buildless, one repo per plugin, tag == `plugin.json`
  version == `getPluginVersion()`.
- Transport contract: `Kanboard\Core\Mail\ClientInterface::sendEmail($recipientEmail,
  $recipientName, $subject, $html, $authorName, $authorEmail = '')`, registered with
  `$this->emailClient->setTransport('resend', Transport\Resend::class)`. Called from
  `EmailJob` (sync or queued), so failures must not throw — log and return, like the
  core `Mail`/`Smtp` transports.
- Active transport is resolved by `MailHelper::getMailTransport()`: the
  `mail_transport` setting when `MAIL_CONFIGURATION` is true, else the
  `MAIL_TRANSPORT` constant/env var.

## Approaches considered

1. **Own settings page (chosen).** A "Resend" entry in the Settings sidebar
   (`template:config:sidebar`) with its own controller and save route. Full control
   of the key field (password input, blank = keep), no interaction with the core
   Email form's save-everything behaviour.
2. **Fields on the core Email page** via the `template:config:email` hook. One place
   for all email config, but the core `ConfigController::save` stores every posted
   field verbatim and re-renders `$values` into the form, so the key would be echoed
   back. Rejected for that reason.
3. **Env/config-only, no UI.** Smallest, but every instance needs a redeploy to
   change a key; Carmelo wants admins to configure it in the app.

Carmelo picked 1.

## Design

### Settings page (`Resend/Controller/SettingsController`)

- Routes `resend/settings` (show) and `resend/save` (POST). ACL: both actions
  registered as `Role::APP_ADMIN` via `$this->applicationAccessMap->add(...)`.
- Rendered inside the core config layout (`config/layout` sidebar) so it sits next to
  Email settings.
- Fields:
  - **API key** — `<input type="password" autocomplete="new-password" value="">`.
    When a key is stored, the page says so and shows only its last 4 characters
    (`re_…abcd`). Blank on save keeps the stored key. A "Remove stored key" checkbox
    clears it.
  - **From address** (optional) — overrides the sender. Blank → Kanboard's own sender
    (`MailHelper::getMailSenderAddress()`, i.e. `mail_sender_address` or `MAIL_FROM`).
    Validated as an email when set.
  - **From name** (optional) — blank → Kanboard's per-email author name
    ("<User> via Kanboard" / "Kanboard"). Set → that name on every email.
- Status panel (read-only):
  - Key source: stored / `RESEND_API_KEY` env / none.
  - Whether `resend` is the active transport (`$this->helper->mail->getMailTransport()`).
    If not: a link to Settings → Email to select it, or — when `MAIL_CONFIGURATION`
    is false — a note to set `MAIL_TRANSPORT=resend` in config/env.
  - Reminder that Resend only sends from a verified domain.
- POST is CSRF-checked (`$this->checkCSRFForm()` / `form->csrf()`), flashes
  success/failure, redirects back.

### Storage

Kanboard `settings` table via `configModel` (no migration), keys
`resend_api_key`, `resend_from_address`, `resend_from_name`. Saved with
`configModel->save([...])` limited to these keys. The key is plaintext at rest, like
AiConnector's provider keys and core's SMTP password; the database is the trust
boundary.

Key resolution: stored setting, else `getenv('RESEND_API_KEY')`, else none.

### Transport (`Resend/Transport/Resend`)

`sendEmail()` builds and POSTs JSON to `https://api.resend.com/emails`:

| Field      | Value |
|------------|-------|
| `from`     | `"<name> <address>"` — name = from-name override or `$authorName`; address = from-address override or Kanboard sender |
| `to`       | `["<recipientName> <recipientEmail>"]` (bare email when name empty) |
| `subject`  | `$subject` |
| `html`     | `$html` |
| `reply_to` | `$authorEmail` when non-empty |
| `bcc`      | `MAIL_BCC` when non-empty |
| `headers`  | `{"Auto-Submitted": "auto-generated"}` (RFC 3834, matches core) |

Display names are quoted/escaped so commas or quotes in a user's name can't break the
address.

Headers: `Authorization: Bearer <key>`, `Content-Type: application/json`,
`User-Agent: kanboard-resend/<version>`. 10 s timeout.

The HTTP call sits behind a small `HttpClient` seam (one method:
`postJson(url, headers, body): [status, body]`, default implementation is `curl`) so
tests inject a fake and never hit the network.

Outcomes:
- No key → `logger->error('Resend: no API key configured; email to <recipient> not sent')`, return.
- 2xx → `logger->debug` with Resend's message id.
- Non-2xx or curl error → `logger->error('Resend: HTTP <status>: <Resend error message>')`.
  Never logs the key or the Authorization header; the response body is trimmed to the
  `message`/`name` fields.

### Endpoint override

`RESEND_API_URL` env var (default `https://api.resend.com`) so a live check can point at
a local mock. Not shown in the UI.

### Plugin wiring (`Plugin.php`)

- `setTransport('resend', ...)` — this alone makes "resend" appear in the core
  Settings → Email transport dropdown.
- Sidebar hook, two routes, ACL entries, `getClasses()` for the controller.
- No JS/CSS assets.

## Testing

TDD with PHPUnit under `Test/`, run with `testing/run-plugin-tests.sh Resend`:
- Transport: payload shape for each override combination, name escaping, BCC,
  reply-to, no-key path, non-2xx and curl-error logging (asserting the key is absent
  from every log line).
- Settings: admin-only ACL, CSRF rejection, blank keeps key, remove clears key,
  masked display never contains the full key, from-address validation.
- Registration: `getAvailableTransports()` contains `resend`.

Live check on the :8081 dev stack: configure via the page with a curl cookie jar,
select `resend` in Settings → Email, trigger a send (e.g. password reset or task
notification) against Resend test mode if a key is available, else against a
`RESEND_API_URL` mock that records the request.

## Left to the builder

File layout inside the skeleton, exact copy text, how the fake HTTP client is
injected (constructor default vs container entry).
