<?php

namespace App\Filament\Merchant\Pages\Auth;

use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/** Merchant sign-up: creates the merchant business and its first user. */
class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('business_name')->label('Business name')->required()->maxLength(150)->autofocus(),
            TextInput::make('phone')->label('Business phone')->tel()->required()->maxLength(20)
                ->placeholder('2547XXXXXXXX'),
            TextInput::make('name')->label('Your full name')->required()->maxLength(255),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ]);
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $merchant = Merchant::create([
            'name' => $data['business_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => 'active',
        ]);

        $user = User::create([
            'merchant_id' => $merchant->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // already hashed by the form component
        ]);

        AuditLog::create(['merchant_id' => $merchant->id, 'user_id' => $user->id, 'action' => 'merchant.registered', 'ip' => request()->ip()]);

        return $user;
    }
}
