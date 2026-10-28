<?php

namespace App\Filament\Merchant\Resources\ApiKeys\Pages;

use App\Filament\Merchant\Resources\ApiKeys\ApiKeyResource;
use App\Models\ApiKey;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageApiKeys extends ManageRecords
{
    protected static string $resource = ApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label('Create API key')->icon(Heroicon::OutlinedPlus)
                ->schema([
                    TextInput::make('name')->required()->maxLength(100)->placeholder('e.g. ERP integration'),
                    CheckboxList::make('abilities')->options(ApiKey::ABILITIES)->helperText('Leave all unticked for full read access.'),
                    DatePicker::make('expires_at')->label('Expires (optional)')->minDate(now()->addDay()),
                ])
                ->action(function (array $data) {
                    [$key, $token] = ApiKey::issue(auth()->user()->merchant, $data['name'], $data['abilities'] ?: null, $data['expires_at'] ?? null);
                    AuditLog::record('api_key.created', $key);

                    Notification::make()
                        ->success()
                        ->title('API key created — copy it now, it will not be shown again')
                        ->body($token)
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
