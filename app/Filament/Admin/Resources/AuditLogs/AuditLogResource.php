<?php

namespace App\Filament\Admin\Resources\AuditLogs;

use App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 8;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime('d M Y H:i:s', 'Africa/Nairobi'),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('merchant_id')->label('Merchant')->formatStateUsing(fn ($record) => \App\Models\Merchant::find($record->merchant_id)?->name),
                TextColumn::make('user_id')->label('User')->formatStateUsing(fn ($record) => \App\Models\User::find($record->user_id)?->email),
                TextColumn::make('subject_type')->label('Subject')->formatStateUsing(fn ($state) => class_basename($state)),
                TextColumn::make('ip'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
