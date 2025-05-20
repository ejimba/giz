<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConversationResource\Pages;
use App\Filament\Resources\ConversationResource\RelationManagers;
use App\Models\Conversation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-oval-left-ellipsis';
    
    protected static ?string $navigationLabel = 'Active Conversations';
    
    protected static ?string $navigationGroup = 'Conversation Flow';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Conversation Details')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->required()
                            ->label('Client')
                            ->columnSpan(1),
                            
                        Forms\Components\TextInput::make('title')
                            ->maxLength(255)
                            ->label('Conversation Title')
                            ->placeholder('Descriptive title for this conversation')
                            ->columnSpan(1),
                            
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'completed' => 'Completed',
                                'abandoned' => 'Abandoned',
                            ])
                            ->required()
                            ->default('active')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Prompt & Timing')
                    ->schema([
                        Forms\Components\Select::make('current_prompt_id')
                            ->relationship('currentPrompt', 'title')
                            ->label('Current Prompt')
                            ->helperText('The current prompt in the conversation flow')
                            ->searchable()
                            ->columnSpan(2),
                            
                        Forms\Components\DateTimePicker::make('started_at')
                            ->required()
                            ->label('Started At')
                            ->default(now())
                            ->columnSpan(1),
                            
                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Completed At')
                            ->helperText('Automatically set when conversation completes')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Additional Data')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Conversation Metadata')
                            ->columnSpan(2),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Client')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('client.phone')
                    ->label('Phone Number')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(30),
                    
                Tables\Columns\TextColumn::make('currentPrompt.title')
                    ->label('Current Prompt')
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }
                        return $state;
                    })
                    ->description(function (Conversation $record): ?string {
                        return $record->currentPrompt ? "Type: {$record->currentPrompt->type}" : null;
                    }),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'primary' => 'active',
                        'success' => 'completed',
                        'danger' => 'abandoned',
                    ]),
                    
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('In Progress'),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Activity')
                    ->dateTime()
                    ->sortable()
                    ->since(),
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
            // We'll implement a different approach for viewing responses
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->latest();
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversations::route('/'),
            'create' => Pages\CreateConversation::route('/create'),
            'edit' => Pages\EditConversation::route('/{record}/edit'),
        ];
    }
}
