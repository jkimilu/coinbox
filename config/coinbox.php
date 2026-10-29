<?php

return [
    // Team mailboxes. Contact-form messages go to sales (or support); tickets go to support.
    'contact' => [
        'support' => env('COINBOX_SUPPORT_EMAIL', 'support@coinbox.co.ke'),
        'sales' => env('COINBOX_SALES_EMAIL', 'sales@coinbox.co.ke'),
        // true: send replies From support@/sales@ (your SMTP must allow it).
        // false: send From MAIL_FROM_ADDRESS with Reply-To set to the team mailbox.
        'send_as_mailbox' => (bool) env('COINBOX_SEND_AS_MAILBOX', false),
    ],

    // Integrations an admin can attach to a provider. Add each new bank/card adapter here.
    'adapters' => [
        App\Payments\Mpesa\MpesaAdapter::class => 'M-Pesa C2B (Safaricom Daraja)',
        App\Payments\Unintegrated\UnintegratedAdapter::class => 'Not integrated yet',
    ],

    'mpesa' => [
        // Public base URL Safaricom calls (C2B confirmation/validation). Falls back to APP_URL.
        'callback_base_url' => env('MPESA_CALLBACK_BASE_URL') ?: env('APP_URL', 'http://localhost'),

        // Reject C2B callbacks that don't come from Safaricom's published IPs.
        'enforce_ip_allowlist' => env('MPESA_ENFORCE_IP_ALLOWLIST', false),
        'allowed_ips' => array_filter(explode(',', env('MPESA_ALLOWED_IPS',
            '196.201.214.200,196.201.214.206,196.201.213.114,196.201.214.207,196.201.214.208,196.201.213.44,196.201.212.127,196.201.212.138,196.201.212.129,196.201.212.136,196.201.212.74,196.201.212.69'
        ))),
    ],

    'webhooks' => [
        'timeout' => 15,
        'max_attempts' => 10,
        // Seconds between retries; the last value repeats.
        'backoff' => [10, 60, 300, 900, 1800, 3600, 7200, 14400, 28800, 43200],
    ],
];
