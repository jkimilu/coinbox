<?php

namespace App\Filament\Merchant\Resources\Support\Pages;

use App\Filament\Merchant\Resources\Support\SupportResource;
use App\Models\Ticket;
use App\Support\Tickets\SupportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListTickets extends ListRecords
{
    protected static string $resource = SupportResource::class;

    public function getSubheading(): ?string
    {
        return 'Our team replies by email to '.auth()->user()->email.'. You can also write to '.config('coinbox.contact.support').'.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new')->label('New ticket')->icon(Heroicon::OutlinedPlus)
                ->modalHeading('Contact CoinBox support')
                ->modalDescription('Include M-Pesa receipt numbers, PayBill numbers or webhook URLs if relevant. It helps us answer faster.')
                ->modalSubmitActionLabel('Send')
                ->schema([
                    TextInput::make('subject')->required()->maxLength(150)->placeholder('e.g. Payments to PayBill 4072129 not arriving'),
                    Select::make('category')->options(Ticket::CATEGORIES)->required()->default('payments')->native(false),
                    Select::make('priority')->options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High: payments affected'])->default('normal')->required()->native(false),
                    Textarea::make('message')->required()->rows(6)->minLength(10)->maxLength(5000),
                ])
                ->action(function (array $data) {
                    $ticket = app(SupportService::class)->fromMerchant(auth()->user(), $data);
                    Notification::make()->success()->title('Ticket '.$ticket->reference().' sent')->body('We’ve emailed you a copy and will reply soon.')->send();
                    $this->redirect(SupportResource::getUrl('view', ['record' => $ticket]));
                }),
        ];
    }
}
