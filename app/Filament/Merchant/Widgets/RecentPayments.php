<?php

namespace App\Filament\Merchant\Widgets;

use App\Filament\Merchant\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentPayments extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent payments';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Transaction::query()->where('merchant_id', auth()->user()->merchant_id)->with(['paymentAccount', 'accountRoute'])->latest('paid_at')->limit(8))
            ->paginated(false)
            ->poll('20s')
            ->columns(TransactionResource::compactColumns(summary: true))
            ->recordUrl(fn () => TransactionResource::getUrl())
            ->headerActions([
                Action::make('all')->label('View all')->link()->url(TransactionResource::getUrl()),
            ])
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading('No payments yet')
            ->emptyStateDescription('Payments to your connected PayBills and Tills will appear here within seconds.');
    }
}
