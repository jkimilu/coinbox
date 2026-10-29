# Connecting your M-Pesa PayBill or Till to CoinBox

Customers keep paying you exactly as they do today. The money goes straight into **your own** PayBill or Till. CoinBox never receives or holds the funds. It only receives Safaricom's payment notification, records the payment, and forwards it to your systems.

```
Customer pays  →  Safaricom  →  your PayBill/Till (funds)
                     │
                     └─ payment notification → CoinBox → your webhook / API
```

To set this up, you give CoinBox a Daraja app for your shortcode. CoinBox then tells Safaricom to send payment notifications for that shortcode to CoinBox.

---

## What you need before you start

| Item | Where to get it |
|---|---|
| An active **PayBill** or **Till (Buy Goods)** number | Safaricom M-Pesa for Business |
| Access to the **M-Pesa Business (Org) portal** for that shortcode, with a user who has the **Business Administrator / Business Manager** role | Your business admin, or Safaricom if you don't have one |
| The phone number linked to that portal user (Safaricom sends an OTP to it during go-live) | — |
| For a Till: your **Store number (Head Office / HO number)**. This is *not* the till number customers pay to | M-Pesa Business portal → Organisation details, or your Safaricom account manager |

> **Till numbers:** Safaricom registers notification URLs against the **store (HO) number**. If you register the till number itself, notifications will not arrive.

---

## Step 1: Create a Daraja account

1. Go to **https://developer.safaricom.co.ke** and sign up.
2. Verify your email and log in.

## Step 2: Create an app and test in the sandbox (optional but recommended)

1. Open **My Apps → Create new App**.
2. Give it a name (for example `CoinBox`) and tick **M-Pesa Sandbox** / **C2B**.
3. Open the app and copy its **Consumer Key** and **Consumer Secret**.
4. Note the sandbox test shortcode Daraja shows you (usually `600xxx`).
5. In CoinBox, connect a **Sandbox** account using that shortcode and the sandbox keys (see Step 4). Then use **Send test payment (sandbox)** to check that notifications reach CoinBox.

## Step 3: Take the app live for your real shortcode

1. In the Daraja portal, click **Go Live**.
2. Choose verification by **Shortcode** and enter your **PayBill number**, or your **store (HO) number** for a Till.
3. Enter your organisation name and the **M-Pesa Business portal username** of your Business Administrator/Manager.
4. Enter the **OTP** Safaricom sends to that user's phone.
5. Select the **C2B** (Customer to Business / Register URL) API and finish the wizard.
6. A new **production app** appears under **My Apps**. Open it and copy its **Consumer Key** and **Consumer Secret**.

> Keep these keys private. Anyone who has them can change where your payment notifications go.

## Step 4: Connect the account in CoinBox

1. Sign in to the CoinBox merchant portal → **M-Pesa accounts → Connect M-Pesa account**.
2. **Account**: choose PayBill or Till, give it a name, and enter the PayBill number or store (HO) number. For a Till, also enter the till number customers pay to.
3. **Daraja credentials**: choose **Production**, then paste the production Consumer Key and Consumer Secret. CoinBox encrypts them and never shows them again.
4. **Notifications**: pick which webhook endpoint should receive these payments, or leave it on *all endpoints*.
5. Leave **Register callback URLs with Safaricom now** switched on, then click **Create**.

CoinBox calls Safaricom's *C2B Register URL* API for your shortcode. When this succeeds, **URLs registered** shows a time and the account becomes **active**.

To see the exact URLs registered for you, open **Setup details** on the account.

### If registration fails

| Message from Safaricom | What to do |
|---|---|
| `Invalid Access Token` / authentication failed | Check that you pasted the **production** keys and chose **Production**. Use **Update Daraja credentials**, then **Register URLs with Safaricom**. |
| URLs already registered / duplicate notification info | Your shortcode already sends notifications to another system. In production, Safaricom may only allow URLs to be changed on request: email **M-PESABusiness@safaricom.co.ke** (copy **apisupport@safaricom.co.ke**) asking them to delete the existing C2B URLs for your shortcode, then click **Register URLs with Safaricom** again. |
| `Invalid ShortCode` | For a Till, use the **store (HO) number**, not the till number. Make sure the app went live for this exact shortcode. |

> **Important:** registering CoinBox *replaces* any confirmation URL your shortcode used before. If another system (an ERP or POS, for example) was receiving M-Pesa notifications directly, point it at CoinBox's webhooks or API instead.

## Step 4b: Optional: several account numbers on one PayBill

If different businesses, branches or systems share one PayBill, give each its own **account number**. Customers pay with Account **`<account number>#<reference>`**, for example **PayBill 4072129, Account `100#INV001`**.

1. **M-Pesa accounts** → **Account numbers** on the PayBill.
2. Add each account number (e.g. `100` *Sokofiti*, `200` *Branch 2*) and choose the webhook it should notify. You can create a new webhook right there.
3. Save.

What happens to a payment to `100#INV001`:
- It is tagged with account number **100**, and **INV001** is saved as the reference.
- The notification goes **only** to account 100's webhook. In the webhook and API, `account_code` is `"100"`, `merchant_reference` is `"INV001"` and `account_number` is `"100#INV001"`.
- Customers can also pay just `100`, with no reference.

Payments to an account number you haven't set up go to the PayBill's default webhook. They are never sent to another account number's webhook. Pull one account number's payments with `GET /api/v1/transactions?account_code=100`. If Safaricom has enabled validation (Step 5), you can also reject unknown account numbers.

## Step 5: Optional: payment validation

By default Safaricom only sends **confirmations** (payment completed). If you want CoinBox to **reject** payments before they complete (for example, a PayBill payment with an empty account number, or below a minimum amount), ask Safaricom to **enable external validation** on your shortcode. Email **M-PESABusiness@safaricom.co.ke**. Then set the rules under **M-Pesa accounts → Edit**.

## Step 6: Receive payments in your own system

You can use either method, or both.

### A. Webhooks (push)

1. **Webhooks → Add endpoint** and enter your HTTPS URL.
2. Click **Signing secret** and store the secret in your system.
3. Click **Send test event** to confirm your endpoint returns HTTP 2xx.

Every payment is sent as a `POST` with JSON like this:

```json
{
  "id": "evt_01JXYZ...",
  "type": "transaction.succeeded",
  "created_at": "2026-10-28T10:15:31+03:00",
  "data": {
    "id": "txn_01JXYZ...",
    "merchant_id": "mer_01JABC...",
    "payment_account_id": "acct_01JDEF...",
    "type": "payment",
    "provider": "mpesa",
    "payment_method": "paybill",
    "provider_transaction_id": "QHX123ABC",
    "merchant_reference": "INV-2026-001",
    "amount": "2500.00",
    "currency": "KES",
    "status": "succeeded",
    "payer": { "identifier": "2547****123", "name": "JOHN DOE" },
    "paid_at": "2026-10-28T10:15:30+03:00"
  }
}
```

Events: `transaction.succeeded` and `transaction.reversed`.

**Verify every webhook.** The `X-CoinBox-Signature` header looks like `t=1730103331,v1=<hex>`, where `v1 = HMAC_SHA256(secret, t + "." + raw_body)`.

```php
[$t, $v1] = array_map(fn ($p) => explode('=', $p, 2)[1], explode(',', $_SERVER['HTTP_X_COINBOX_SIGNATURE']));
$expected = hash_hmac('sha256', $t.'.'.file_get_contents('php://input'), $secret);
if (! hash_equals($expected, $v1) || abs(time() - (int) $t) > 300) { http_response_code(400); exit; }
```

```js
const [t, v1] = req.get('X-CoinBox-Signature').split(',').map(p => p.split('=')[1]);
const expected = crypto.createHmac('sha256', secret).update(`${t}.${rawBody}`).digest('hex');
if (!crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(v1))) return res.sendStatus(400);
```

Return HTTP 2xx quickly. If you don't, CoinBox retries with increasing delays (10s, 1m, 5m … up to 10 attempts). Every attempt is listed under **Webhook deliveries**, where you can retry a delivery by hand. Use `data.id` or `provider_transaction_id` to ignore duplicates.

### B. REST API (pull)

Create a key under **API keys**. It is shown only once. Send it as a bearer token:

```bash
curl -H "Authorization: Bearer cbx_xxxxxxxxxxxx_..." https://YOUR-COINBOX-DOMAIN/api/v1/transactions?limit=50
```

| Endpoint | Purpose |
|---|---|
| `GET /api/v1/me` | Identify the authenticated merchant |
| `GET /api/v1/accounts` | List connected PayBills and Tills |
| `GET /api/v1/accounts/{id}/balance` | Collection totals for one account (see note) |
| `GET /api/v1/transactions` | Filters: `account_id`, `account_code`, `status`, `type`, `reference`, `from`, `to`, `min_amount`, `max_amount`, `limit`, `cursor` |
| `GET /api/v1/transactions/{id}` | One transaction |
| `GET /api/v1/transactions/reference/{reference}` | Find by invoice / account number |
| `GET /api/v1/transactions/receipt/{receipt}` | Find by M-Pesa receipt (e.g. `QHX123ABC`) |
| `GET /api/v1/reports/collections?from=2026-10-01&to=2026-10-31` | Totals for a date range, grouped by `day` or `month` |

For pagination, pass the `next_cursor` value back as `?cursor=` to get the next page.

> **About "balance":** the balance endpoint returns a `collection_summary` of payments CoinBox has *recorded*. It is **not** your M-Pesa account balance and is not money you can withdraw from CoinBox. Your funds are always in your own PayBill or Till.

---

## Billing: your CoinBox float

CoinBox charges a small processing fee on the payments it records for you (see **Billing** in the portal for the current rate and free allowance). Your customers' money is never involved: it always stays in your own PayBill, Till or bank.

- **Keep a float.** Prepay CoinBox by M-Pesa to **PayBill 4072129**, using your **merchant number** as the Account (shown on the Billing page, e.g. `234`). Monthly invoices are paid from the float automatically.
- **Pay an invoice directly.** Use Account **merchant number#invoice number**, e.g. `234#INV001`. Anything paid over the amount owed stays in your float.
- **Top-up advice.** The Billing page and dashboard tell you how much to top up, based on last month's invoice and this month's projected fee. We also email you if your float runs low.
- Invoices are issued on the 1st of each month for the previous month, and only once you pass the free allowance.

---

## Quick checklist

- [ ] Daraja production app live for my PayBill / store (HO) number
- [ ] Account connected in CoinBox and **URLs registered** shows a time
- [ ] Webhook endpoint added, signature verification in place, test event delivered
- [ ] API key created (if pulling transactions)
- [ ] Made a real KES 1–10 test payment and saw it under **Transactions**
- [ ] Topped up my CoinBox float (PayBill 4072129, Account = my merchant number)
