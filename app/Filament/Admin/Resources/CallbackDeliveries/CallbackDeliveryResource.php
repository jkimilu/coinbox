<?php

namespace App\Filament\Admin\Resources\CallbackDeliveries;

use App\Filament\Admin\Resources\CallbackDeliveries\Pages\ListCallbackDeliveries;
use App\Filament\Merchant\Resources\CallbackDeliveries\CallbackDeliveryResource as MerchantDeliveries;
use App\Models\CallbackDelivery;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CallbackDeliveryResource extends Resource
{
    protected static ?string $model = CallbackDelivery::class;

    protected static ?string $modelLabel = 'webhook';

    protected static ?string $navigationLabel = 'Webhooks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function infolist(Schema $schema): Schema
    {
        return MerchantDeliveries::infolist($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchantDeliveries::table($table)
            ->pushColumns([TextColumn::make('endpoint.merchant.name')->label('Merchant')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCallbackDeliveries::route('/')];
    }
}
