<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Merchant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class TopMerchants extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 7];

    protected static ?string $heading = 'Top merchants · 30 days';

    public function table(Table $table): Table
    {
        $since = now()->subDays(30);

        return $table
            ->query(fn () => Merchant::query()
                ->select('merchants.*')
                ->selectSub(DB::table('transactions')->whereColumn('merchant_id', 'merchants.id')->where('type', 'payment')->whereIn('status', ['succeeded', 'reversed'])->where('paid_at', '>=', $since)->selectRaw('coalesce(sum(amount),0)'), 'volume_30d')
                ->selectSub(DB::table('transactions')->whereColumn('merchant_id', 'merchants.id')->where('type', 'payment')->where('paid_at', '>=', $since)->selectRaw('count(*)'), 'count_30d')
                ->selectSub(DB::table('transactions')->whereColumn('merchant_id', 'merchants.id')->selectRaw('max(paid_at)'), 'last_paid_at')
                ->orderByDesc('volume_30d')->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('account_number')->label('No.')->fontFamily('mono'),
                TextColumn::make('name')->weight('semibold')->limit(28)->tooltip(fn ($record) => $record->email),
                TextColumn::make('count_30d')->label('Payments')->numeric()->alignEnd(),
                TextColumn::make('volume_30d')->label('Processed')->alignEnd()->weight('bold')
                    ->formatStateUsing(fn ($state) => 'KES '.number_format((float) $state, 0))
                    ->tooltip(fn ($record) => 'Estimated fee: KES '.number_format(\App\Support\Pricing::feeFor((float) $record->volume_30d), 2)),
                TextColumn::make('last_paid_at')->label('Last payment')->since()->placeholder('Never'),
            ])
            ->emptyStateHeading('No merchants yet');
    }
}
