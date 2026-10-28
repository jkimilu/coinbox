<?php

namespace App\Filament\Merchant\Resources\CallbackEndpoints;

use App\Filament\Merchant\Concerns\ScopedToMerchant;
use App\Filament\Merchant\Resources\CallbackEndpoints\Pages\ManageCallbackEndpoints;
use App\Jobs\DeliverCallback;
use App\Models\CallbackDelivery;
use App\Models\CallbackEndpoint;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CallbackEndpointResource extends Resource
{
    use ScopedToMerchant;

    protected static ?string $model = CallbackEndpoint::class;

    protected static ?string $modelLabel = 'webhook endpoint';

    protected static ?string $navigationLabel = 'Webhooks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('url')->label('Endpoint URL')->url()->required()->maxLength(500)
                ->rule('starts_with:https://')
                ->validationMessages(['starts_with' => 'Webhook URLs must use HTTPS.'])
                ->placeholder('https://your-system.example.com/coinbox/webhook')
                ->columnSpanFull(),
            CheckboxList::make('events')->options(CallbackEndpoint::EVENTS)
                ->helperText('Leave all unticked to receive every event.'),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')->limit(60)->tooltip(fn ($record) => $record->url),
                TextColumn::make('events')->badge()->placeholder('All events'),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('deliveries_count')->counts('deliveries')->label('Deliveries'),
            ])
            ->recordActions([
                Action::make('secret')->label('Signing secret')->icon(Heroicon::OutlinedKey)->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalDescription(fn (CallbackEndpoint $record) => 'Verify the X-CoinBox-Signature header with this secret: '.$record->secret),
                ActionGroup::make([
                    Action::make('test')->label('Send test event')->icon(Heroicon::OutlinedPaperAirplane)
                        ->action(function (CallbackEndpoint $record) {
                            $d = CallbackDelivery::create([
                                'callback_endpoint_id' => $record->id,
                                'event' => 'test',
                                'payload' => ['id' => 'evt_'.Str::ulid(), 'type' => 'test', 'created_at' => now()->toIso8601String(), 'data' => ['message' => 'Test webhook from CoinBox']],
                                'status' => 'pending',
                            ]);
                            DeliverCallback::dispatchSync($d->id);
                            $d->refresh();
                            Notification::make()->title($d->status === 'delivered' ? 'Delivered ('.$d->last_response_code.')' : 'Delivery failed')
                                ->body($d->last_error ?? Str::limit((string) $d->last_response_body, 200))
                                ->status($d->status === 'delivered' ? 'success' : 'danger')->send();
                        }),
                    Action::make('rotate')->label('Rotate secret')->icon(Heroicon::OutlinedArrowPath)->requiresConfirmation()
                        ->action(fn (CallbackEndpoint $record) => $record->update(['secret' => 'whsec_'.Str::random(40)])),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCallbackEndpoints::route('/')];
    }
}
