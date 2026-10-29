# CoinBox: payment notification gateway

Merchants are paid directly into their **own** M-Pesa PayBill or Till. CoinBox receives Safaricom's payment notifications, stores them in PostgreSQL, gives merchants a REST API and dashboards, and forwards signed webhooks to their systems. **CoinBox never holds funds.** There are no wallets, payouts or settlements.

- Architecture: [docs/architecture.md](docs/architecture.md)
- Merchant M-Pesa setup guide (also shown in the merchant panel): [docs/mpesa-merchant-setup.md](docs/mpesa-merchant-setup.md)

## Stack

Laravel 13 · Filament 5 (admin panel `/admin`, merchant panel `/merchant`) · PostgreSQL · Daraja C2B v2 · database queue

## Local setup

```bash
composer install
npm install --legacy-peer-deps && npm run build
cp .env.example .env && php artisan key:generate   # then set DB_* and MAIL_*
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder             # optional demo data
php artisan filament:assets
php artisan serve
php artisan queue:work                             # in a second terminal
```

Demo logins (from `DemoSeeder`):

| Panel | URL | Email | Password |
|---|---|---|---|
| Admin | `/admin` | admin@coinbox.test | Admin@12345 |
| Merchant | `/merchant` | merchant@coinbox.test | Merchant@12345 |

Create a real admin with `php artisan coinbox:make-admin you@example.com`.

Tests use the `coinbox_test` PostgreSQL database: `php artisan test`.

## How a payment flows

1. A merchant signs up at `/merchant/register` and connects a PayBill/Till with **their own** Daraja app's consumer key and secret. These are encrypted in `provider_credentials`.
2. CoinBox calls Daraja **C2B Register URL v2** for that shortcode with per-account URLs:
   `https://APP_URL/api/c2b/{route_key}/confirmation` and `/validation`.
   The `route_key` is a random 40-character secret, and the path avoids words Daraja blocks (`mpesa`, `safaricom`, `sql`, `query`, `exec`, `cmd`).
3. Safaricom posts the confirmation. CoinBox stores the raw `provider_events` row **before** acknowledging. Duplicates are prevented by a unique `(provider, event_type, TransID)` key.
4. The `ProcessProviderEvent` job normalises the payload through `MpesaAdapter` into `transactions`. A transaction row can never be changed or deleted afterwards. A reversal is written as a separate row.
5. `WebhookDispatcher` creates `callback_deliveries`. `DeliverCallback` POSTs them with an HMAC signature, retries up to 10 times with backoff, and logs every attempt.
6. Merchants pull data with `/api/v1/*` using `cbx_…` API keys. Only a SHA-256 hash of each key is stored, and every query is limited to the key's own merchant.

## Code map

| Path | What |
|---|---|
| `app/Payments/Contracts/PaymentProviderAdapter.php` | Provider contract (M-Pesa now; banks and cards later) |
| `app/Payments/Mpesa/` | `MpesaAdapter`, `DarajaClient` (OAuth, register URL, sandbox simulate), `MpesaAccountConnector` |
| `app/Payments/PaymentIngestor.php` | Event → transaction, exactly once |
| `app/Payments/CollectionSummary.php` | "Balance" = recorded collections, never a wallet |
| `app/Payments/ReversalService.php`, `Reconciler.php` | Reversal records, statement reconciliation |
| `app/Http/Controllers/Provider/MpesaC2bController.php` | Safaricom callbacks |
| `app/Http/Controllers/Api/V1/` | Merchant REST API |
| `app/Filament/Merchant`, `app/Filament/Admin` | Panels |

## Billing

- Fee and free allowance: **admin → Pricing** (default 1% from KES 10,000/month). It's applied to the month's successful payment volume.
- Each merchant has a numeric **merchant number** (`merchants.account_number`) and a prepaid **float** (`float_entries`, balance = sum).
- `coinbox:invoices` runs on the 1st (06:00 Nairobi) for the previous month. It issues `billing_invoices` (INV001, INV002… per merchant) and pays them from the float where possible. `coinbox:low-float` sends top-up reminders daily, at most one per merchant per week.
- Merchants pay **PayBill 4072129**: Account `234` tops up the float, and Account `234#INV001` pays that invoice. Connect CoinBox's own PayBill as an account and select it under *Pricing* for automatic matching. Otherwise record payments in **admin → Billing → Float / Invoices**. Each receipt is only ever credited once.

## Sign-in security

- **MFA:** users turn it on under **Profile**. Two methods are available: an authenticator app (TOTP, with recovery codes) and a code sent by email. The authenticator secret and recovery codes are stored encrypted.
- **Passkeys** (`spatie/laravel-passkeys`): users add them under **Profile → Passkeys**, then use **Sign in with a passkey** on either login screen. Passkeys are tied to the domain in `APP_URL`, so one created on the ngrok address won't work on another domain. The browser library is self-hosted at `public/js/simplewebauthn-browser.umd.min.js`.

## Production checklist

- **Public HTTPS `APP_URL`** on port 443. Safaricom will not call `localhost`, plain HTTP or tunnel URLs in production. The domain itself must not contain `mpesa` or `safaricom`.
- Run a queue worker under Supervisor (`php artisan queue:work --tries=5`) on at least two app servers.
- Add a cron entry for `* * * * * php artisan schedule:run`. It runs `coinbox:sweep` every 5 minutes, which re-queues stuck events and webhooks.
- Set `MPESA_ENFORCE_IP_ALLOWLIST=true` so only Safaricom's published IPs (`config/coinbox.php`) can post callbacks. Set `TRUSTED_PROXIES` to your load balancer's address.
- PostgreSQL HA (primary + streaming replica), backups, and `APP_KEY` kept safe. **Losing `APP_KEY` makes stored Daraja credentials unreadable.**
- Reconcile against M-Pesa statements: `php artisan coinbox:reconcile 899399 statement.csv`.
