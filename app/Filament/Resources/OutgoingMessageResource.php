<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OutgoingMessageResource\Pages;
use App\Models\OutgoingMessage;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class OutgoingMessageResource extends Resource
{
    protected static ?int $navigationSort = 7;

    protected static ?string $model = OutgoingMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $slug = 'outgoing-messages';

    protected static ?string $modelLabel = 'Outgoing Message';

    protected static ?string $pluralModelLabel = 'Outgoing Messages';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')->label('User')->relationship('user', 'name')->searchable()->preload()->columnSpan('full')->required(),
            Forms\Components\Select::make('type')->label('Type')->options(['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'])->columnSpan('full')->required(),
            Forms\Components\TextInput::make('email')->columnSpan('full')->readOnly(),
            Forms\Components\TextInput::make('phone')->columnSpan('full')->readOnly(),
            Forms\Components\Textarea::make('message')->label('Message')->required()->columnSpan('full'),
            Forms\Components\TextInput::make('processed_at')->label('Processed Date')->readOnly()->columnSpan('full'),
            Forms\Components\TextInput::make('status')->readOnly()->columnSpan('full'),
            Forms\Components\TextInput::make('status_date')->label('Status Date')->readOnly()->columnSpan('full'),
            Forms\Components\TextInput::make('created_at')->label('Date Created')->readOnly()->columnSpan('full'),
            Forms\Components\TextInput::make('twilio_message_sid')->label('Twilio Message SID')->readOnly()->columnSpan('full'),
            Forms\Components\KeyValue::make('metadata')->keyLabel('Key')->valueLabel('Value')->columnSpan('full'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $users = User::orderBy('name')->pluck('name', 'id')->toArray();
        return $table->columns([
            Tables\Columns\TextColumn::make('user.name')->label('User'),
            Tables\Columns\TextColumn::make('type')->label('Type'),
            Tables\Columns\TextColumn::make('email'),
            Tables\Columns\TextColumn::make('phone'),
            Tables\Columns\TextColumn::make('processed_at')->label('Processed Date'),
            Tables\Columns\TextColumn::make('status'),
        ])->filters([
            Tables\Filters\SelectFilter::make('user_id')->label('User')->options($users)->multiple(),
            Filter::make('processed_at')->form([
                DatePicker::make('from')->label('Processed (From)'),
                DatePicker::make('to')->label('Processed (To)'),
            ])->query(function (Builder $query, array $data): Builder {
                return $query->when(
                    $data['from'], fn (Builder $query, $date): Builder => $query->whereDate('processed_at', '>=', $date))->when(
                    $data['to'], fn (Builder $query, $date): Builder => $query->whereDate('processed_at', '<=', $date),
                );
            }),
        ])
        ->actions([
            Tables\Actions\ViewAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOutgoingMessages::route('/'),
            'create' => Pages\CreateOutgoingMessage::route('/create'),
            'view' => Pages\ViewOutgoingMessage::route('/{record}'),
            'edit' => Pages\EditOutgoingMessage::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
