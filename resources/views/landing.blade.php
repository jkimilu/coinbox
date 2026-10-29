@php
    $appUrl = rtrim(config('app.url'), '/');
    $fee = \App\Support\Pricing::feeLabel();            // e.g. "1%"
    $feeRate = \App\Support\Pricing::feePercent() / 100;
    $threshold = \App\Support\Pricing::thresholdLabel(); // e.g. "KES 10,000"
    $thresholdValue = \App\Support\Pricing::freeThreshold();
    $exampleVolume = 250000;
    $exampleFee = 'KES '.number_format(\App\Support\Pricing::feeFor($exampleVolume));
    $title = 'CoinBox: M-Pesa PayBill & Till API, Webhooks and Reconciliation for Kenya';
    $description = 'Get every M-Pesa PayBill and Till payment into your system the second it lands. Signed webhooks, a REST API and automatic reconciliation on Safaricom Daraja. Free until '.$threshold.' a month.';
    $keywords = 'M-Pesa API, M-Pesa integration Kenya, PayBill API, Till number API, Lipa na M-Pesa integration, M-Pesa C2B, Daraja API, M-Pesa webhook, M-Pesa callback URL, M-Pesa payment notification, M-Pesa reconciliation, M-Pesa transaction API, accept M-Pesa payments, M-Pesa for business, Safaricom Daraja integration, M-Pesa ERP integration, M-Pesa accounting integration';

    $faqs = [
        ['Do you hold or touch my money?', 'Never. Customers pay your own PayBill or Till exactly as they do today and the money stays in your own M-Pesa account, or moves to your bank as it always has. CoinBox only receives Safaricom’s payment notification, records it and forwards it to your systems.'],
        ['What do I need to get started?', 'An active PayBill or Till number, access to your M-Pesa Business portal, and a free Safaricom Daraja account. Our setup guide walks you through creating the Daraja app and taking it live. Most businesses are connected in an afternoon.'],
        ['Does it work with Till numbers (Buy Goods)?', 'Yes. For Tills, Safaricom sends notifications for the store (HO) number, so you connect that and tell us the till number your customers pay to.'],
        ['How do I get payments into my ERP, POS or app?', 'Two ways, use either or both. Webhooks push each payment to your URL within seconds, signed so you can verify it came from us. The REST API lets you pull transactions, look one up by M-Pesa receipt or invoice number, and get daily totals.'],
        ['What happens if my server is down?', 'Nothing is lost. Every Safaricom notification is stored before we acknowledge it, and webhooks are retried with backoff for up to ten attempts. You can see and replay every delivery in your dashboard.'],
        ['How is the '.$fee.' fee charged?', 'It’s free while you process under '.$threshold.' in a month. From '.$threshold.' it’s '.$fee.' of the total recorded that month, billed monthly. We can’t deduct it from your payments because we never hold them.'],
        ['Do you support banks and cards?', 'M-Pesa is live today. Bank payments and card processors are on the way, built on the same transaction format, so your integration won’t change when you add them.'],
    ];

    $ld = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $appUrl.'/#org',
                'name' => 'CoinBox',
                'url' => $appUrl,
                'logo' => $appUrl.'/icon-512.png',
                'areaServed' => 'KE',
            ],
            [
                '@type' => 'SoftwareApplication',
                'name' => 'CoinBox',
                'applicationCategory' => 'FinanceApplication',
                'operatingSystem' => 'Web',
                'url' => $appUrl,
                'description' => $description,
                'publisher' => ['@id' => $appUrl.'/#org'],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'KES',
                    'description' => 'Free under '.$threshold.' processed per month; '.$fee.' of monthly processed volume from '.$threshold.'.',
                ],
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($f) => [
                    '@type' => 'Question',
                    'name' => $f[0],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
                ], $faqs),
            ],
        ],
    ];

    $register = url('/merchant/register');
    $login = url('/merchant/login');
@endphp
<!DOCTYPE html>
<html lang="en-KE" class="bg-paper">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="keywords" content="{{ $keywords }}">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="{{ $appUrl }}/">
    <link rel="alternate" hreflang="en-KE" href="{{ $appUrl }}/">
    <meta name="geo.region" content="KE">
    <meta name="theme-color" content="#2CA01C">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="CoinBox">
    <meta property="og:locale" content="en_KE">
    <meta property="og:title" content="Every M-Pesa payment, in your system the second it lands">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $appUrl }}/">
    <meta property="og:image" content="{{ $appUrl }}/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="CoinBox: M-Pesa PayBill & Till webhooks and API">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $appUrl }}/og-image.png">

    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">

    <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    @vite(['resources/css/app.css'])
</head>
<body class="bg-paper font-sans text-ink-800 antialiased">

<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:shadow">Skip to content</a>

{{-- ========== Header ========== --}}
<header class="sticky top-0 z-40 border-b border-ink-100/80 bg-paper/90 backdrop-blur supports-[backdrop-filter]:bg-paper/75">
    <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6" aria-label="Main">
        <a href="/" class="flex items-center gap-2.5" aria-label="CoinBox home">
            <img src="/favicon.svg" alt="" width="32" height="32" class="size-8">
            <span class="font-display text-[1.35rem] font-bold tracking-tight text-ink-900">CoinBox</span>
        </a>

        <ul class="hidden items-center gap-8 text-[15px] font-medium text-ink-600 md:flex">
            <li><a href="#how-it-works" class="hover:text-ink-900">How it works</a></li>
            <li><a href="#developers" class="hover:text-ink-900">Developers</a></li>
            <li><a href="#pricing" class="hover:text-ink-900">Pricing</a></li>
            <li><a href="#faq" class="hover:text-ink-900">FAQ</a></li>
            <li><a href="#contact" class="hover:text-ink-900">Contact</a></li>
        </ul>

        <div class="hidden items-center gap-2 md:flex">
            <a href="{{ $login }}" class="rounded-full px-4 py-2 text-[15px] font-semibold text-ink-700 hover:text-ink-900">Log in</a>
            <a href="{{ $register }}" class="rounded-full bg-brand-600 px-5 py-2.5 text-[15px] font-semibold text-white shadow-sm transition hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">Start for free</a>
        </div>

        <button type="button" class="-mr-2 rounded-md p-2 text-ink-700 md:hidden" aria-controls="mobile-menu" aria-expanded="false" data-menu-toggle>
            <span class="sr-only">Open menu</span>
            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
    </nav>
    <div id="mobile-menu" class="hidden border-t border-ink-100 bg-paper px-4 pb-5 pt-2 md:hidden">
        <ul class="space-y-1 text-base font-medium text-ink-700">
            <li><a href="#how-it-works" class="block rounded-md px-2 py-2.5 hover:bg-ink-50">How it works</a></li>
            <li><a href="#developers" class="block rounded-md px-2 py-2.5 hover:bg-ink-50">Developers</a></li>
            <li><a href="#pricing" class="block rounded-md px-2 py-2.5 hover:bg-ink-50">Pricing</a></li>
            <li><a href="#faq" class="block rounded-md px-2 py-2.5 hover:bg-ink-50">FAQ</a></li>
            <li><a href="#contact" class="block rounded-md px-2 py-2.5 hover:bg-ink-50">Contact</a></li>
        </ul>
        <div class="mt-4 grid grid-cols-2 gap-3">
            <a href="{{ $login }}" class="rounded-full border border-ink-200 px-4 py-2.5 text-center font-semibold text-ink-800">Log in</a>
            <a href="{{ $register }}" class="rounded-full bg-brand-600 px-4 py-2.5 text-center font-semibold text-white">Start for free</a>
        </div>
    </div>
</header>

<main id="main">

{{-- ========== Hero ========== --}}
<section class="relative overflow-hidden">
    {{-- faint ledger lines, a nod to the paper books CoinBox replaces --}}
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 [background-image:repeating-linear-gradient(to_bottom,transparent_0,transparent_39px,rgb(20_27_22/0.045)_39px,rgb(20_27_22/0.045)_40px)] [mask-image:linear-gradient(to_bottom,black,transparent_85%)]"></div>

    <div class="relative mx-auto grid max-w-6xl items-center gap-14 px-4 pb-20 pt-14 sm:px-6 md:pt-20 lg:grid-cols-[1.15fr_1fr] lg:gap-12 lg:pb-28">
        <div class="cb-rise">
            <p class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-sm font-semibold text-brand-800">
                <span class="size-1.5 rounded-full bg-brand-600"></span>
                For Kenyan businesses with a PayBill or Till
            </p>

            <h1 class="mt-6 font-display text-[2.5rem] font-extrabold leading-[1.03] tracking-[-0.03em] text-ink-900 text-balance sm:text-6xl lg:text-[3.6rem] xl:text-[3.9rem]">
                Every M-Pesa payment, in your system <span class="relative whitespace-nowrap text-brand-700">the second<svg aria-hidden="true" viewBox="0 0 300 14" class="absolute -bottom-2 left-0 h-3 w-full text-brand-400" preserveAspectRatio="none"><path d="M3 9c55-6 130-8 294-3" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/></svg></span> it lands.
            </h1>

            <p class="mt-7 max-w-xl text-lg leading-relaxed text-ink-600 sm:text-xl">
                CoinBox connects your M-Pesa PayBill or Till to your accounting, ERP, POS or app. Customers pay the way they always have. The money goes straight to you. We make sure your books know about it immediately.
            </p>

            <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                <a href="{{ $register }}" class="group inline-flex items-center justify-center gap-2 rounded-full bg-brand-600 px-7 py-4 text-base font-semibold text-white shadow-[0_8px_24px_-8px_rgb(44_160_28/0.6)] transition hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
                    Start for free
                    <svg class="size-4 transition group-hover:translate-x-0.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8h10M9 4l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
                <a href="#how-it-works" class="inline-flex items-center justify-center rounded-full px-6 py-4 text-base font-semibold text-ink-800 ring-1 ring-ink-200 transition hover:bg-white hover:ring-ink-300">See how it works</a>
            </div>
            <p class="mt-4 text-sm text-ink-500">Free until you process {{ $threshold }} a month. No card, no setup fee.</p>
        </div>

        {{-- Product moment: SMS → CoinBox → your system --}}
        <div class="cb-rise cb-rise-2 relative mx-auto mb-10 w-full max-w-[30rem] lg:mr-0" aria-label="Example: an M-Pesa payment arriving in CoinBox and being sent to the merchant's system" role="img">
            {{-- M-Pesa confirmation, the thing every Kenyan merchant knows --}}
            <div class="relative z-10 ml-auto w-[88%] -rotate-[1.5deg] rounded-2xl rounded-tr-sm border border-ink-100 bg-white p-4 shadow-[0_12px_40px_-12px_rgb(20_27_22/0.25)]">
                <div class="flex items-center justify-between text-xs text-ink-400">
                    <span class="font-semibold uppercase tracking-wider text-ink-500">M-PESA</span>
                    <span>10:15 AM</span>
                </div>
                <p class="mt-2 font-mono text-[13px] leading-relaxed text-ink-700">
                    <b class="text-ink-900">UJT41BX7QK</b> Confirmed. Ksh2,500.00 received from WANJIKU MUTHONI 2547****218. Account Number <b class="text-ink-900">INV-2041</b> New Utility balance is…
                </p>
            </div>

            <div class="relative -mt-3 ml-6 h-8 w-px border-l-2 border-dashed border-brand-300" aria-hidden="true"></div>

            {{-- CoinBox live feed --}}
            <div class="relative rounded-2xl border border-ink-900/10 bg-ink-900 p-1.5 shadow-[0_30px_60px_-20px_rgb(11_16_13/0.55)]">
                <div class="rounded-xl bg-ink-800/60 px-4 pb-4 pt-3.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <img src="/favicon.svg" alt="" class="size-5">
                            <span class="text-sm font-semibold text-white">Live payments</span>
                        </div>
                        <span class="flex items-center gap-1.5 text-xs font-medium text-brand-300"><span class="cb-ping size-2 rounded-full bg-brand-400"></span>Main PayBill</span>
                    </div>

                    <ul class="mt-3 divide-y divide-white/5 text-sm">
                        <li class="-mx-2 flex items-center justify-between rounded-lg bg-brand-500/15 px-2 py-2.5 ring-1 ring-brand-400/30">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-white">Wanjiku Muthoni <span class="ml-1 rounded bg-brand-400/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-brand-200">new</span></p>
                                <p class="font-mono text-xs text-ink-300">UJT41BX7QK · INV-2041</p>
                            </div>
                            <p class="tabular font-semibold text-white">2,500.00</p>
                        </li>
                        <li class="flex items-center justify-between py-2.5">
                            <div class="min-w-0"><p class="truncate font-medium text-ink-100">Otieno Brian</p><p class="font-mono text-xs text-ink-400">UJT41AQ2LM · INV-2039</p></div>
                            <p class="tabular text-ink-200">14,800.00</p>
                        </li>
                        <li class="flex items-center justify-between py-2.5">
                            <div class="min-w-0"><p class="truncate font-medium text-ink-100">Chebet Faith</p><p class="font-mono text-xs text-ink-400">UJT419ZK8D · INV-2040</p></div>
                            <p class="tabular text-ink-200">650.00</p>
                        </li>
                    </ul>

                    <div class="mt-3 flex items-center justify-between rounded-lg bg-black/30 px-3 py-2 font-mono text-[11.5px]">
                        <span class="truncate text-ink-300">POST erp.yourbusiness.co.ke/coinbox</span>
                        <span class="ml-3 shrink-0 text-brand-300">200 OK · 184ms</span>
                    </div>
                </div>
            </div>

            <div class="absolute -bottom-16 left-2 hidden rotate-[2deg] rounded-xl border border-ink-100 bg-white px-4 py-3 shadow-lg sm:block">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Today, recorded</p>
                <p class="tabular font-display text-xl font-bold text-ink-900">KES 186,420</p>
            </div>
        </div>
    </div>
</section>

{{-- ========== Promise strip ========== --}}
<section aria-label="Why businesses trust CoinBox" class="border-y border-ink-100 bg-white">
    <div class="mx-auto grid max-w-6xl gap-px bg-ink-100 sm:grid-cols-3">
        @foreach ([
            ['Your money never touches us', 'Payments settle in your own PayBill or Till. We only read the receipt.'],
            ['Nothing slips through', 'Every notification is stored before it’s acknowledged, then delivered with retries.'],
            ['Built on Safaricom Daraja', 'The official M-Pesa C2B API, using your own Daraja app and shortcode.'],
        ] as [$h, $p])
            <div class="bg-white px-6 py-7 sm:px-8">
                <p class="font-display text-lg font-bold text-ink-900">{{ $h }}</p>
                <p class="mt-1.5 text-[15px] leading-relaxed text-ink-500">{{ $p }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- ========== Problem → outcome ========== --}}
<section class="mx-auto max-w-6xl px-4 py-24 sm:px-6">
    <div class="max-w-3xl">
        <h2 class="font-display text-3xl font-bold tracking-tight text-ink-900 sm:text-[2.6rem] sm:leading-[1.1]">Stop matching M-Pesa statements to invoices at 11pm.</h2>
        <p class="mt-5 text-lg leading-relaxed text-ink-600">If your team copies receipt numbers from SMS, downloads statements to reconcile, or calls customers to confirm they’ve paid, CoinBox takes that job off your hands.</p>
    </div>

    <div class="mt-14 grid gap-5 md:grid-cols-2">
        <div class="rounded-2xl border border-ink-100 bg-white p-7 sm:p-9">
            <p class="text-sm font-semibold uppercase tracking-wider text-ink-400">Without CoinBox</p>
            <ul class="mt-6 space-y-4 text-[15.5px] text-ink-600">
                @foreach (['Someone reads M-Pesa SMS and types payments into the system', 'Orders wait while you check whether the money actually arrived', 'Month-end means downloading statements and ticking rows by hand', 'Wrong account numbers sit unmatched for weeks'] as $line)
                    <li class="flex gap-3"><svg class="mt-1 size-4 shrink-0 text-ink-300" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4l8 8M12 4l-8 8" stroke-linecap="round"/></svg>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
        <div class="rounded-2xl bg-brand-700 p-7 text-white sm:p-9">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-200">With CoinBox</p>
            <ul class="mt-6 space-y-4 text-[15.5px] text-brand-50">
                @foreach (['Payments post to your system automatically, with receipt and invoice number', 'Release orders the moment Safaricom confirms. No more “nimetuma”, no screenshots', 'Payment reports by day, month or account, ready when you are', 'Statement reconciliation flags anything missing or mismatched'] as $line)
                    <li class="flex gap-3"><svg class="mt-1 size-4 shrink-0 text-brand-300" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 8.5l3.2 3L13 4.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

{{-- ========== How it works ========== --}}
<section id="how-it-works" class="scroll-mt-20 bg-white py-24">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
            <h2 class="max-w-xl font-display text-3xl font-bold tracking-tight text-ink-900 sm:text-[2.6rem] sm:leading-[1.1]">Connected in an afternoon. Then you forget it’s there.</h2>
            <a href="{{ $register }}" class="shrink-0 font-semibold text-brand-700 underline decoration-brand-300 decoration-2 underline-offset-4 hover:decoration-brand-600">Create your account →</a>
        </div>

        <ol class="mt-16 grid gap-10 md:grid-cols-3 md:gap-8">
            @foreach ([
                ['Connect your PayBill or Till', 'Sign up, then paste the Consumer Key and Secret from your Daraja app. They’re encrypted and never shown again. Our guide covers taking your app live with Safaricom.'],
                ['We register with Safaricom', 'One click tells Safaricom to send this shortcode’s payment confirmations to CoinBox. Optional validation can reject payments with a missing account number.'],
                ['Payments flow to your system', 'Each payment is recorded and pushed to your webhook within seconds. Pull anything else from the API: by receipt, by invoice, or as daily totals.'],
            ] as $i => [$h, $p])
                <li class="relative">
                    <div class="flex items-center gap-4">
                        <span class="font-display text-5xl font-extrabold leading-none text-brand-600/90 tabular">0{{ $i + 1 }}</span>
                        @if ($i < 2)<span aria-hidden="true" class="hidden h-px flex-1 bg-gradient-to-r from-brand-300 to-transparent md:block"></span>@endif
                    </div>
                    <h3 class="mt-5 font-display text-xl font-bold text-ink-900">{{ $h }}</h3>
                    <p class="mt-2.5 leading-relaxed text-ink-600">{{ $p }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- ========== Features ========== --}}
<section class="mx-auto max-w-6xl px-4 py-24 sm:px-6" aria-labelledby="features-title">
    <h2 id="features-title" class="max-w-2xl font-display text-3xl font-bold tracking-tight text-ink-900 sm:text-[2.6rem] sm:leading-[1.1]">Everything between the customer’s PIN and your ledger.</h2>

    <div class="mt-14 grid gap-x-10 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['Signed webhooks', 'HMAC-signed so you know it’s us. Retried for up to 10 attempts, with every delivery logged and replayable.', 'M4 12h4l3-8 4 16 3-8h2'],
            ['A clean REST API', 'Transactions with filters and cursor pagination, lookups by M-Pesa receipt or invoice, and payment reports.', 'M8 6l-5 6 5 6M16 6l5 6-5 6'],
            ['No double posting', 'Safaricom sometimes resends the same notification. Each receipt is recorded exactly once, however many times it arrives.', 'M8 7h11M8 12h11M8 17h11M4 7h.01M4 12h.01M4 17h.01'],
            ['Reconciliation', 'Import your M-Pesa statement and see what matched, what’s missing and what doesn’t add up, line by line.', 'M4 19V5m0 14h16M8 15l3-4 3 2 5-6'],
            ['Reversals on record', 'A reversal is recorded beside the original payment, never over it, so your history stays complete for audit.', 'M9 14L4 9l5-5M4 9h10a6 6 0 010 12h-2'],
            ['Many shortcodes, one view', 'Connect every PayBill and Till your business runs, give each its own webhook, and see totals per account.', 'M4 6h16M4 12h16M4 18h10'],
        ] as [$h, $p, $icon])
            <div>
                <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                </span>
                <h3 class="mt-5 font-display text-lg font-bold text-ink-900">{{ $h }}</h3>
                <p class="mt-2 leading-relaxed text-ink-600">{{ $p }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- ========== Developers ========== --}}
<section id="developers" class="scroll-mt-20 bg-ink-950 py-24 text-ink-100">
    <div class="mx-auto grid max-w-6xl gap-14 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-400">For developers</p>
            <h2 class="mt-4 font-display text-3xl font-bold tracking-tight text-white sm:text-[2.6rem] sm:leading-[1.1]">Skip the Daraja plumbing.</h2>
            <p class="mt-5 text-lg leading-relaxed text-ink-300">No callback servers to babysit, no OAuth tokens to refresh, no deduplicating Safaricom retries. You get one normalised payment object, the same for every PayBill, Till and, soon, every bank.</p>
            <dl class="mt-9 grid grid-cols-2 gap-6 text-sm">
                <div><dt class="text-ink-400">Auth</dt><dd class="mt-1 font-semibold text-white">Scoped API keys</dd></div>
                <div><dt class="text-ink-400">Delivery</dt><dd class="mt-1 font-semibold text-white">Webhooks + REST</dd></div>
                <div><dt class="text-ink-400">Signature</dt><dd class="mt-1 font-semibold text-white">HMAC-SHA256</dd></div>
                <div><dt class="text-ink-400">Amounts</dt><dd class="mt-1 font-semibold text-white">Exact decimals, never floats</dd></div>
            </dl>
        </div>

        <figure class="overflow-hidden rounded-2xl bg-ink-900 ring-1 ring-white/10">
            <figcaption class="flex items-center justify-between border-b border-white/10 px-5 py-3 text-xs">
                <span class="font-mono text-ink-300">POST → your webhook</span>
                <span class="font-mono text-brand-400">transaction.succeeded</span>
            </figcaption>
<pre class="overflow-x-auto p-5 font-mono text-[13px] leading-6"><code><span class="text-ink-500">{</span>
  <span class="text-sky-300">"type"</span>: <span class="text-brand-300">"transaction.succeeded"</span>,
  <span class="text-sky-300">"data"</span>: <span class="text-ink-500">{</span>
    <span class="text-sky-300">"id"</span>: <span class="text-brand-300">"txn_01JB8Q4W6R"</span>,
    <span class="text-sky-300">"provider"</span>: <span class="text-brand-300">"mpesa"</span>,
    <span class="text-sky-300">"payment_method"</span>: <span class="text-brand-300">"paybill"</span>,
    <span class="text-sky-300">"short_code"</span>: <span class="text-brand-300">"4072129"</span>,
    <span class="text-sky-300">"provider_transaction_id"</span>: <span class="text-brand-300">"UJT41BX7QK"</span>,
    <span class="text-sky-300">"merchant_reference"</span>: <span class="text-brand-300">"INV-2041"</span>,
    <span class="text-sky-300">"amount"</span>: <span class="text-brand-300">"2500.00"</span>,
    <span class="text-sky-300">"currency"</span>: <span class="text-brand-300">"KES"</span>,
    <span class="text-sky-300">"status"</span>: <span class="text-brand-300">"succeeded"</span>,
    <span class="text-sky-300">"paid_at"</span>: <span class="text-brand-300">"2026-10-29T10:15:30+03:00"</span>
  <span class="text-ink-500">}</span>
<span class="text-ink-500">}</span></code></pre>
            <div class="border-t border-white/10 bg-black/20 px-5 py-3.5 font-mono text-[12.5px] text-ink-300">
                <span class="text-ink-500">$</span> curl {{ parse_url($appUrl, PHP_URL_HOST) }}/api/v1/transactions/receipt/UJT41BX7QK
            </div>
        </figure>
    </div>
</section>

{{-- ========== Pricing ========== --}}
<section id="pricing" class="scroll-mt-20 mx-auto max-w-6xl px-4 py-24 sm:px-6">
    <div class="grid gap-12 lg:grid-cols-2 lg:items-start">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-700">Pricing</p>
            <h2 class="mt-4 font-display text-3xl font-bold tracking-tight text-ink-900 sm:text-[2.6rem] sm:leading-[1.1]">Free to start. Then {{ $fee }} of what you process.</h2>
            <p class="mt-5 text-lg leading-relaxed text-ink-600">One plan, every feature. No setup fee, no monthly subscription, no per-seat charges. You only pay once your business is moving real volume through CoinBox.</p>

            <ul class="mt-8 space-y-3 text-ink-700">
                @foreach (['Unlimited PayBills, Tills and team members', 'Webhooks, REST API and reconciliation included', 'Billed monthly. Your money stays in your own M-Pesa or bank account'] as $line)
                    <li class="flex gap-3"><svg class="mt-1 size-4 shrink-0 text-brand-600" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 8.5l3.2 3L13 4.5" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ $line }}</li>
                @endforeach
            </ul>
        </div>

        <div class="rounded-3xl border border-ink-100 bg-white p-7 shadow-[0_24px_60px_-30px_rgb(20_27_22/0.35)] sm:p-10">
            <div class="flex items-end justify-between gap-4 border-b border-dashed border-ink-200 pb-7">
                <div>
                    <p class="font-display text-6xl font-extrabold tracking-tight text-ink-900">{{ rtrim($fee, '%') }}<span class="text-4xl">%</span></p>
                    <p class="mt-2 text-ink-500">of monthly volume, from {{ $threshold }}</p>
                </div>
                <p class="rounded-full bg-brand-50 px-3 py-1 text-sm font-semibold text-brand-800 ring-1 ring-brand-100">KES 0 below that</p>
            </div>

            <div class="pt-7" data-calc data-rate="{{ $feeRate }}" data-threshold="{{ $thresholdValue }}" data-threshold-label="{{ $threshold }}">
                <label for="volume" class="flex items-baseline justify-between text-sm font-medium text-ink-600">
                    <span>M-Pesa payments processed per month</span>
                    <output class="tabular font-display text-lg font-bold text-ink-900" data-volume-out>KES {{ number_format($exampleVolume) }}</output>
                </label>
                <input id="volume" type="range" min="0" max="10" step="1" value="5" class="cb-range mt-4 w-full" data-volume aria-describedby="fee-out">
                <div class="mt-2 flex justify-between text-xs text-ink-400"><span>5K</span><span>10M</span></div>

                <div class="mt-7 flex items-center justify-between rounded-2xl bg-ink-50 px-5 py-4">
                    <span class="text-ink-600">You’d pay CoinBox</span>
                    <span id="fee-out" class="tabular font-display text-2xl font-bold text-ink-900" data-fee-out aria-live="polite">{{ $exampleFee }}</span>
                </div>
                <p class="mt-3 text-sm text-ink-500" data-fee-note>That’s per month, on KES {{ number_format($exampleVolume) }} processed.</p>
            </div>

            <a href="{{ $register }}" class="mt-8 flex w-full items-center justify-center rounded-full bg-brand-600 px-6 py-4 text-base font-semibold text-white transition hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">Start for free</a>
        </div>
    </div>
</section>

{{-- ========== FAQ ========== --}}
<section id="faq" class="scroll-mt-20 border-t border-ink-100 bg-white py-24">
    <div class="mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.8fr_1.2fr]">
        <div>
            <h2 class="font-display text-3xl font-bold tracking-tight text-ink-900 sm:text-[2.6rem] sm:leading-[1.1]">Questions merchants ask us</h2>
            <p class="mt-5 text-lg text-ink-600">Something else? Create an account and read the full M-Pesa setup guide inside. It’s free.</p>
        </div>
        <div class="divide-y divide-ink-100 border-y border-ink-100">
            @foreach ($faqs as [$q, $a])
                <details class="group py-5" @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer items-start justify-between gap-6 font-display text-lg font-semibold text-ink-900">
                        <h3>{{ $q }}</h3>
                        <span class="cb-plus mt-1 grid size-6 shrink-0 place-items-center rounded-full border border-ink-200 text-ink-500 transition group-open:border-brand-300 group-open:text-brand-700">
                            <svg class="size-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 1v10M1 6h10" stroke-linecap="round"/></svg>
                        </span>
                    </summary>
                    <p class="mt-3 max-w-2xl leading-relaxed text-ink-600">{{ $a }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>

{{-- ========== Contact ========== --}}
@php
    $support = config('coinbox.contact.support');
    $sales = config('coinbox.contact.sales');
    $errorsBag = $errors->getBag('contact');
    $field = 'mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-[15px] text-ink-900 shadow-sm ring-1 ring-ink-200 placeholder:text-ink-400 focus:ring-2 focus:ring-brand-600 focus:outline-none';
@endphp
<section id="contact" class="scroll-mt-20 mx-auto max-w-6xl px-4 py-24 sm:px-6">
    <div class="grid gap-12 lg:grid-cols-[0.85fr_1.15fr]">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-700">Contact</p>
            <h2 class="mt-4 font-display text-3xl font-bold tracking-tight text-ink-900 sm:text-[2.6rem] sm:leading-[1.1]">Talk to a person, not a bot.</h2>
            <p class="mt-5 text-lg leading-relaxed text-ink-600">Questions about pricing, a large integration, or getting your PayBill connected? Send us a note and we’ll reply by email, usually within one business day.</p>

            <dl class="mt-10 space-y-6">
                <div class="flex gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l9 6 9-6M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg></span>
                    <div><dt class="font-display font-bold text-ink-900">Sales</dt><dd class="text-ink-600">Pricing, onboarding several businesses, partnerships<br><a href="mailto:{{ $sales }}" class="font-semibold text-brand-700 underline decoration-brand-300 underline-offset-4 hover:decoration-brand-600">{{ $sales }}</a></dd></div>
                </div>
                <div class="flex gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10a6 6 0 10-12 0v4l-2 3h16l-2-3v-4zM10 20a2 2 0 004 0"/></svg></span>
                    <div><dt class="font-display font-bold text-ink-900">Support</dt><dd class="text-ink-600">Already a merchant? Open a ticket under <span class="font-medium text-ink-800">Support</span> in your portal, or email<br><a href="mailto:{{ $support }}" class="font-semibold text-brand-700 underline decoration-brand-300 underline-offset-4 hover:decoration-brand-600">{{ $support }}</a></dd></div>
                </div>
            </dl>
        </div>

        <div class="rounded-3xl border border-ink-100 bg-white p-6 shadow-[0_24px_60px_-30px_rgb(20_27_22/0.35)] sm:p-9">
            @if (session('contact_sent'))
                <div role="status" class="flex h-full flex-col items-center justify-center py-10 text-center">
                    <span class="grid size-14 place-items-center rounded-full bg-brand-600 text-white"><svg class="size-7" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 8.5l3.2 3L13 4.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <h3 class="mt-5 font-display text-2xl font-bold text-ink-900">Message sent</h3>
                    <p class="mt-2 max-w-sm text-ink-600">Thanks. We’ve emailed you a copy{{ is_string(session('contact_sent')) ? ' with reference '.session('contact_sent') : '' }}. We’ll be in touch soon.</p>
                    <a href="{{ url('/') }}#contact" class="mt-6 text-sm font-semibold text-brand-700 hover:underline">Send another message</a>
                </div>
            @else
                <form method="POST" action="{{ route('contact.store') }}" class="grid gap-5 sm:grid-cols-2" novalidate>
                    @csrf
                    <div class="hidden" aria-hidden="true"><label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                    <fieldset class="sm:col-span-2">
                        <legend class="text-sm font-medium text-ink-700">I’d like to talk to</legend>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            @foreach (['sales' => ['Sales', 'Pricing & onboarding'], 'support' => ['Support', 'Help with my account']] as $value => [$label, $hint])
                                <label class="cursor-pointer rounded-xl p-3.5 ring-1 ring-ink-200 transition has-[:checked]:bg-brand-50 has-[:checked]:ring-2 has-[:checked]:ring-brand-600">
                                    <input type="radio" name="topic" value="{{ $value }}" class="sr-only" @checked(old('topic', 'sales') === $value)>
                                    <span class="block font-semibold text-ink-900">{{ $label }}</span>
                                    <span class="block text-sm text-ink-500">{{ $hint }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    @foreach ([['name', 'Your name', 'text', 'name', true], ['email', 'Work email', 'email', 'email', true], ['phone', 'Phone (optional)', 'tel', 'tel', false], ['company', 'Business (optional)', 'text', 'organization', false]] as [$name, $label, $type, $ac, $req])
                        <div>
                            <label for="c-{{ $name }}" class="text-sm font-medium text-ink-700">{{ $label }}</label>
                            <input id="c-{{ $name }}" name="{{ $name }}" type="{{ $type }}" autocomplete="{{ $ac }}" value="{{ old($name) }}" @if ($req) required @endif class="{{ $field }} @if ($errorsBag->has($name)) ring-red-500 @endif" @if ($errorsBag->has($name)) aria-invalid="true" aria-describedby="c-{{ $name }}-err" @endif>
                            @if ($errorsBag->has($name))<p id="c-{{ $name }}-err" class="mt-1.5 text-sm text-red-600">{{ $errorsBag->first($name) }}</p>@endif
                        </div>
                    @endforeach

                    <div class="sm:col-span-2">
                        <label for="c-message" class="text-sm font-medium text-ink-700">How can we help?</label>
                        <textarea id="c-message" name="message" rows="5" required class="{{ $field }} @if ($errorsBag->has('message')) ring-red-500 @endif" placeholder="Tell us about your business and what you need, e.g. how many PayBills or Tills you run.">{{ old('message') }}</textarea>
                        @if ($errorsBag->has('message'))<p class="mt-1.5 text-sm text-red-600">{{ $errorsBag->first('message') }}</p>@endif
                    </div>

                    <div class="flex flex-col gap-3 sm:col-span-2 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-ink-500">We only use your details to reply to you.</p>
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full bg-brand-600 px-7 py-3.5 font-semibold text-white transition hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">Send message</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</section>

{{-- ========== Final CTA ========== --}}
<section class="px-4 py-20 sm:px-6">
    <div class="relative mx-auto max-w-6xl overflow-hidden rounded-[2rem] bg-brand-700 px-6 py-16 text-center sm:px-12 sm:py-20">
        <svg aria-hidden="true" class="absolute -bottom-28 -right-20 size-[24rem] text-white/[0.06]" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="5.5" stroke-linecap="round"><path d="M27.75 31.5A8.5 8.5 0 1 0 27.75 42.5"/><path d="M35.25 17V45"/><circle cx="43.25" cy="37" r="8"/></svg>
        <div class="relative">
            <h2 class="mx-auto max-w-2xl font-display text-3xl font-extrabold tracking-tight text-white sm:text-5xl sm:leading-[1.05]">Your next M-Pesa payment could post itself.</h2>
            <p class="mx-auto mt-5 max-w-xl text-lg text-brand-100">Set up your PayBill or Till today. It’s free until you’re processing {{ $threshold }} a month.</p>
            <div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="{{ $register }}" class="inline-flex items-center justify-center rounded-full bg-white px-8 py-4 text-base font-semibold text-brand-800 shadow-lg transition hover:bg-brand-50">Start for free</a>
                <a href="{{ $login }}" class="inline-flex items-center justify-center rounded-full px-8 py-4 text-base font-semibold text-white ring-1 ring-white/40 transition hover:bg-white/10">I already have an account</a>
            </div>
        </div>
    </div>
</section>

</main>

{{-- ========== Footer ========== --}}
<footer class="border-t border-ink-100">
    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-10 text-sm text-ink-500 sm:px-6 md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-2.5">
            <img src="/favicon.svg" alt="" class="size-6">
            <span class="font-display font-bold text-ink-800">CoinBox</span>
            <span class="text-ink-300">·</span>
            <span>M-Pesa payment notifications for Kenyan businesses</span>
        </div>
        <nav aria-label="Footer" class="flex flex-wrap gap-x-6 gap-y-2">
            <a href="#pricing" class="hover:text-ink-800">Pricing</a>
            <a href="#developers" class="hover:text-ink-800">API</a>
            <a href="#faq" class="hover:text-ink-800">FAQ</a>
            <a href="#contact" class="hover:text-ink-800">Contact</a>
            <a href="mailto:{{ config('coinbox.contact.support') }}" class="hover:text-ink-800">{{ config('coinbox.contact.support') }}</a>
            <a href="mailto:{{ config('coinbox.contact.sales') }}" class="hover:text-ink-800">{{ config('coinbox.contact.sales') }}</a>
            <a href="{{ $login }}" class="hover:text-ink-800">Log in</a>
        </nav>
        <p>© {{ date('Y') }} CoinBox. M-Pesa is a trademark of Safaricom PLC.</p>
    </div>
</footer>

<script>
    // Mobile menu
    (() => {
        const btn = document.querySelector('[data-menu-toggle]');
        const menu = document.getElementById('mobile-menu');
        if (!btn || !menu) return;
        btn.addEventListener('click', () => {
            const open = menu.classList.toggle('hidden') === false;
            btn.setAttribute('aria-expanded', String(open));
        });
        menu.querySelectorAll('a[href^="#"]').forEach(a => a.addEventListener('click', () => { menu.classList.add('hidden'); btn.setAttribute('aria-expanded', 'false'); }));
    })();

    // Pricing calculator: free under the monthly threshold, then the fee % of the month's total (both set in admin → Pricing).
    (() => {
        const steps = [5000, 10000, 25000, 50000, 100000, 250000, 500000, 1000000, 2500000, 5000000, 10000000];
        const input = document.querySelector('[data-volume]');
        if (!input) return;
        const fmt = n => 'KES ' + Math.round(n).toLocaleString('en-KE');
        const vOut = document.querySelector('[data-volume-out]');
        const fOut = document.querySelector('[data-fee-out]');
        const note = document.querySelector('[data-fee-note]');
        const update = () => {
            const v = steps[Number(input.value)];
            const calc = document.querySelector('[data-calc]');
            const rate = Number(calc.dataset.rate), threshold = Number(calc.dataset.threshold);
            const fee = v >= threshold ? v * rate : 0;
            input.style.setProperty('--fill', (input.value / input.max * 100) + '%');
            vOut.textContent = fmt(v);
            fOut.textContent = fee ? fmt(fee) : 'KES 0';
            note.textContent = fee ? `That’s per month, on ${fmt(v)} processed.` : `Free. You’re under ${calc.dataset.thresholdLabel} this month.`;
        };
        input.addEventListener('input', update);
        update();
    })();
</script>
</body>
</html>
