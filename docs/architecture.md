The merchant receives money directly through their own M-Pesa PayBill or Till. Your platform receives payment notifications, records them in PostgreSQL, provides transaction APIs, and forwards notifications to the merchant's systems.
1. Final payment flow
Customer pays via M-Pesa

PayBill 899399 · Account 254#INV-001

Safaricom

Processes the payment and sends the confirmation

Your Gateway

Validate · Identify account · Deduplicate · Store transaction

Merchant callback

Push payment notification


Merchant API

Pull transactions and totals



Funds move directly between the customer and merchant through Safaricom. Your gateway does not receive or hold the funds.

2. What “balance” means in this system
Since you do not hold funds, we should not maintain a wallet balance or describe a transaction total as withdrawable money.

Instead, expose transaction summaries for each merchant account:
- Total successful payments received.
- Total reversals and refunds recorded.
- Net recorded collections.
- Pending or unconfirmed transactions, if the payment flow supports them.
- Today's collections and historical totals.
- Transaction counts by status.
- Last payment received and last successful reconciliation.
For example:

GET /api/v1/accounts/{account_id}/balance

This endpoint can return a clearly labelled collection_summary, calculated from recorded transactions. It will not claim to represent the actual Safaricom account balance.
3. Final merchant API
Endpoint	Function
GET /api/v1/me	Identify the authenticated merchant
GET /api/v1/accounts	List connected PayBills and tills
GET /api/v1/accounts/{id}/balance	Get collection totals for one account
GET /api/v1/transactions	Pull transactions with filters and cursor pagination
GET /api/v1/transactions/{id}	Retrieve a specific transaction
GET /api/v1/transactions/reference/{reference}	Find a transaction by invoice/reference
GET /api/v1/transactions/receipt/{receipt}	Find a transaction by provider receipt
GET /api/v1/reports/collections	Retrieve collection summaries for a date range

All endpoints will enforce merchant ownership, so one merchant cannot retrieve another merchant's payments.
4. What we should remove from the original design
- No merchant wallet or stored monetary balance.
- No payout or withdrawal functionality.
- No settlement processing as a core requirement.
- No funds-transfer ledger for money held by the gateway.
We still need an immutable transaction history, reversal records, audit logs, provider reconciliation, callback retries, API authentication and duplicate-payment protection.
5. Technology and implementation
- Backend: Laravel 13.
- Admin panel: Filament 5.
- Merchant panel: Filament 5.
- Database: One PostgreSQL database.
- Payment ingestion: Safaricom Daraja callbacks.
- Merchant integrations: Versioned REST API and signed webhooks.
- Reliability: Durable event processing, idempotency, redundant application servers and PostgreSQL high availability.
One technical point remains important: merchants can configure their callback URLs in your portal, but Safaricom must send the original payment confirmation to an endpoint registered and authorized for the relevant shortcode. Your gateway then forwards the notification to the merchant's chosen URL.

1. Future-ready payment architecture
Merchant Panel + REST API

Accounts · Transactions · Reports · Webhooks

Unified Payment Gateway

Merchant resolution · Transaction processing · Idempotency · Reconciliation

M-Pesa
Phase 1
PayBill · Till


Banks
Future
Transfers · Collections · Statements


Cards
Future
Processor integrations



All providers normalize confirmed payments into the same transaction model.

2. Database design for multiple payment providers
Use a generic payment-account model rather than creating separate tables for M-Pesa accounts, bank accounts and card accounts.
Table	Purpose
merchants	Merchant businesses
payment_providers	Provider registry: Safaricom, banks and card processors
payment_accounts	Merchant's connected accounts for each provider
provider_credentials	Encrypted credentials and connection configuration
transactions	Unified payment records
provider_events	Original provider notifications and event history
callback_endpoints	Merchant notification URLs
callback_deliveries	Webhook delivery attempts
reconciliation_runs	Matching provider records against gateway transactions
api_keys	Merchant API access and permissions

Each payment_accounts record should include fields such as:
id
merchant_id
provider_id
account_type
display_name
external_account_reference
currency
credentials_id
callback_endpoint_id
configuration
status
created_at
updated_at


The configuration field can hold provider-specific settings, but credentials should be stored separately, encrypted, and never exposed through the merchant API.
3. A common transaction format
Regardless of which provider reports the payment, your gateway should normalize it into a consistent structure.
{
  "id": "txn_01JXYZ",
  "merchant_id": "mer_01JABC",
  "payment_account_id": "acct_01JDEF",
  "provider": "mpesa",
  "payment_method": "paybill",
  "provider_transaction_id": "QHX123ABC",
  "merchant_reference": "INV-2026-001",
  "amount": "2500.00",
  "currency": "KES",
  "status": "succeeded",
  "paid_at": "2026-10-28T10:15:30+03:00"
}


For a bank payment, the same structure would use provider: "bank" and a bank-specific transaction identifier. A card payment could use provider: "card_processor" and the processor's payment reference.

Keep the original provider payload in a separate event record so you don't lose information needed for reconciliation, investigations or future integrations.
4. Use provider adapters
In Laravel, define a common interface that every provider integration implements.
interface PaymentProviderAdapter
{
    public function validateWebhook(
        array $payload,
        array $headers
    ): bool;

    public function normalizeTransaction(
        array $payload
    ): NormalizedPayment;

    public function verifyTransaction(
        string $providerTransactionId
    ): ProviderTransactionResult;

    public function capabilities(): array;
}


The M-Pesa adapter implements the functionality supported by Daraja. Later, each bank or card processor gets its own adapter without requiring a redesign of the transaction tables, merchant API or Filament panels.

The interface represents the gateway's internal contract. Individual providers may not support every operation, so the gateway must check capabilities rather than assuming that all providers offer balance enquiries, transaction verification or identical webhook mechanisms.
5. Future bank integrations
Design the system to accommodate three common bank integration methods:
- Bank API or direct integration: payment notifications and transaction verification through an authorized bank API.
- Virtual accounts or collection references: incoming transfers mapped to a particular merchant or invoice.
- Statement import or reconciliation: matching bank statement entries against recorded payments where real-time callbacks are unavailable.
Card payments will generally require a card processor or acquiring partner. The gateway should receive payment status events and references from that partner rather than storing raw card numbers or CVV data.

For all three, the gateway records and reports payments; it does not need to hold merchant funds.
6. Recommended implementation sequence
1. Foundation: Laravel 13, Filament 5, single PostgreSQL database, admin and merchant panels, merchant isolation and API authentication.
2. M-Pesa Phase 1: PayBill and supported Till flows, callback ingestion, account-reference routing, transaction queries and collection summaries.
3. Reliability: Durable webhook delivery, duplicate-event protection, reconciliation, monitoring and high availability.
4. Bank integrations: Implement one bank at a time using the provider adapter architecture and agreed bank API capabilities.
5. Card integrations: Add a processor adapter, payment-status notifications, refunds or reversals where supported, and required security controls.
My recommendation: establish the generic payment_providers, payment_accounts, transactions and provider_events design before implementing M-Pesa. That small architectural investment will make adding banks and card processors substantially easier without complicating Phase 1.