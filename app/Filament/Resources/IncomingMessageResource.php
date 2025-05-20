<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IncomingMessageResource\Pages;
use App\Models\IncomingMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IncomingMessageResource extends Resource
{
    protected static ?string $model = IncomingMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('client_id')
                    ->relationship('client', 'phone')
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('twilio_message_sid')
                    ->maxLength(255),
                Forms\Components\TextInput::make('from_number')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('message')
                    ->required()
                    ->columnSpan('full'),
                Forms\Components\KeyValue::make('media')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->columnSpan('full'),
                Forms\Components\DateTimePicker::make('processed_at'),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'received' => 'Received',
                        'processed' => 'Processed',
                        'failed' => 'Failed',
                    ])
                    ->required(),
                Forms\Components\KeyValue::make('metadata')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->columnSpan('full'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('from_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('message')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'received' => 'info',
                        'processed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'received' => 'Received',
                        'processed' => 'Processed',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\Filter::make('processed')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('processed_at')),
                Tables\Filters\Filter::make('not_processed')
                    ->query(fn (Builder $query): Builder => $query->whereNull('processed_at')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListIncomingMessages::route('/'),
            'create' => Pages\CreateIncomingMessage::route('/create'),
            'view' => Pages\ViewIncomingMessage::route('/{record}'),
            'edit' => Pages\EditIncomingMessage::route('/{record}/edit'),
        ];
    }
}
