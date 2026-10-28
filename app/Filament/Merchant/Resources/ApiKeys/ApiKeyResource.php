<?php

namespace App\Filament\Merchant\Resources\ApiKeys;

use App\Filament\Merchant\Concerns\ScopedToMerchant;
use App\Filament\Merchant\Resources\ApiKeys\Pages\ManageApiKeys;
use App\Models\ApiKey;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ApiKeyResource extends Resource
{
    use ScopedToMerchant;

    protected static ?string $model = ApiKey::class;

    protected static ?string $modelLabel = 'API key';

    protected static ?string $navigationLabel = 'API keys';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?int $navigationSort = 4;

    public static function canEdit(Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->weight('bold'),
                TextColumn::make('prefix')->formatStateUsing(fn ($state) => "cbx_{$state}_…")->fontFamily('mono'),
                TextColumn::make('abilities')->badge()->placeholder('All read access'),
                TextColumn::make('last_used_at')->since()->placeholder('Never'),
                TextColumn::make('expires_at')->date()->placeholder('Never'),
                TextColumn::make('revoked_at')->label('Status')
                    ->state(fn (ApiKey $r) => $r->isUsable() ? 'Active' : ($r->revoked_at ? 'Revoked' : 'Expired'))
                    ->badge()->color(fn ($state) => $state === 'Active' ? 'success' : 'danger'),
            ])
            ->recordActions([
                Action::make('revoke')->color('danger')->icon(Heroicon::OutlinedNoSymbol)->requiresConfirmation()
                    ->visible(fn (ApiKey $r) => $r->revoked_at === null)
                    ->action(function (ApiKey $record) {
                        $record->forceFill(['revoked_at' => now()])->save();
                        AuditLog::record('api_key.revoked', $record);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageApiKeys::route('/')];
    }
}
