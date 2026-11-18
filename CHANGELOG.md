# Changelog

## 1.0.0 — 2026-11-18

- `resend` mail transport: sends every Kanboard email through the Resend HTTP API.
- Admin-only **Settings → Resend** page: API key (stored, masked, never echoed), optional from address and from name, active-transport status.
- `RESEND_API_KEY` fallback and `RESEND_API_URL` override.
