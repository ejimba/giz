<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResponseResource\Pages;
use App\Filament\Resources\ResponseResource\RelationManagers;
use App\Models\Response;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ResponseResource extends Resource
{
    protected static ?string $model = Response::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'Client Responses';
    
    protected static ?string $navigationGroup = 'Conversation Flow';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Response Details')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->relationship('client', 'name')
                            ->required()
                            ->searchable()
                            ->label('Client')
                            ->columnSpan(1),
                            
                        Forms\Components\Select::make('conversation_id')
                            ->relationship('conversation', 'title')
                            ->required()
                            ->searchable()
                            ->label('Conversation')
                            ->columnSpan(1),
                            
                        Forms\Components\Select::make('prompt_id')
                            ->relationship('prompt', 'title')
                            ->required()
                            ->searchable()
                            ->label('Responding to Prompt')
                            ->columnSpan(2),
                            
                        Forms\Components\Textarea::make('content')
                            ->label('Response Content')
                            ->required()
                            ->columnSpan(2),
                            
                        Forms\Components\DateTimePicker::make('received_at')
                            ->label('Time Received')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Response Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Additional Data')
                            ->columnSpan(2),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('client.phone')
                    ->label('Phone')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('prompt.title')
                    ->label('Prompt')
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        return $column->getRecord()->prompt?->content;
                    })
                    ->description(function ($record): ?string {
                        return $record->prompt?->type;
                    })
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('content')
                    ->label('Response')
                    ->limit(40)
                    ->searchable()
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 40) {
                            return null;
                        }
                        return $state;
                    }),
                    
                Tables\Columns\TextColumn::make('received_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable()
                    ->since(),
                    
                Tables\Columns\TextColumn::make('conversation.status')
                    ->label('Conversation Status')
                    ->badge()
                    ->colors([
                        'primary' => 'active',
                        'success' => 'completed', 
                        'danger' => 'abandoned',
                    ]),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->latest('received_at');
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResponses::route('/'),
            'create' => Pages\CreateResponse::route('/create'),
            'edit' => Pages\EditResponse::route('/{record}/edit'),
        ];
    }
}
