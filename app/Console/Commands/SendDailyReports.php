<?php

namespace App\Console\Commands;

use App\Mail\DailyCollectionReportMail;
use App\Models\Merchant;
use App\Reports\DailyCollectionReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDailyReports extends Command
{
    protected $signature = 'coinbox:daily-report
        {--date= : Day to report on (YYYY-MM-DD, Nairobi). Defaults to yesterday}
        {--merchant= : Only this merchant id}
        {--force : Send even if this day was already sent}';

    protected $description = 'Email each merchant the previous day\'s collections and reversals';

    public function handle(DailyCollectionReport $builder): int
    {
        $day = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'), DailyCollectionReport::TZ)
            : CarbonImmutable::now(DailyCollectionReport::TZ)->subDay();
        $dateKey = $day->toDateString();
        $sent = 0;

        Merchant::query()
            ->where('status', 'active')
            ->whereHas('paymentAccounts')
            ->when($this->option('merchant'), fn ($q, $id) => $q->whereKey($id))
            ->when(! $this->option('force'), fn ($q) => $q->where(fn ($q) => $q
                ->whereNull('daily_report_sent_for')->orWhere('daily_report_sent_for', '<', $dateKey)))
            ->each(function (Merchant $merchant) use ($builder, $day, $dateKey, &$sent) {
                $users = $merchant->users()->where('daily_report', true)->whereNotNull('email_verified_at')->get();
                if ($users->isEmpty()) {
                    return;
                }

                try {
                    $report = $builder->build($merchant, $day);
                    foreach ($users as $user) {
                        Mail::to($user)->send(new DailyCollectionReportMail($report, $user));
                    }
                    $merchant->forceFill(['daily_report_sent_for' => $dateKey])->saveQuietly();
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                    $this->error("{$merchant->name}: {$e->getMessage()}");
                }
            });

        $this->info("Sent {$dateKey} report to {$sent} merchant(s).");

        return self::SUCCESS;
    }
}
