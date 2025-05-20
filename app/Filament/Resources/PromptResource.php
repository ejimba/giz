<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromptResource\Pages;
use App\Filament\Resources\PromptResource\RelationManagers;
use App\Models\Prompt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PromptResource extends Resource
{
    protected static ?string $model = Prompt::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    
    protected static ?string $navigationLabel = 'Conversation Prompts';
    
    protected static ?string $navigationGroup = 'Conversation Flow';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Prompt Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->label('Prompt Title')
                            ->placeholder('Enter a descriptive title for this prompt')
                            ->columnSpan(2),
                            
                        Forms\Components\Select::make('type')
                            ->required()
                            ->options([
                                'text' => 'Text (Free Input)',
                                'yes_no' => 'Yes/No Question',
                                'multiple_choice' => 'Multiple Choice',
                            ])
                            ->default('text')
                            ->reactive()
                            ->columnSpan(1),
                            
                        Forms\Components\Toggle::make('active')
                            ->required()
                            ->default(true)
                            ->label('Active')
                            ->helperText('Inactive prompts will not be used in conversations')
                            ->columnSpan(1),
                            
                        Forms\Components\Textarea::make('content')
                            ->required()
                            ->label('Prompt Content')
                            ->placeholder('The message that will be sent to the client')
                            ->helperText('This is the actual message that will be sent to the user')
                            ->rows(3)
                            ->columnSpan(2),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Flow Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('order')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->label('Display Order')
                            ->helperText('Lower numbers appear first in sequence')
                            ->columnSpan(1),
                            
                        Forms\Components\Select::make('next_prompt_id')
                            ->relationship('nextPrompt', 'title')
                            ->label('Next Prompt')
                            ->helperText('The prompt to show after this one (for linear flow)')
                            ->placeholder('Select next prompt in sequence')
                            ->columnSpan(1),
                            
                        Forms\Components\Select::make('parent_prompt_id')
                            ->relationship('parentPrompt', 'title')
                            ->label('Parent Prompt')
                            ->helperText('If this is a branching option, select the parent prompt')
                            ->placeholder('Select parent prompt (if any)')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Advanced Options')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'multiple_choice')
                            ->keyLabel('Option Key')
                            ->valueLabel('Option Text')
                            ->addButtonLabel('Add Option')
                            ->columnSpan(2)
                            ->helperText('For multiple choice, add the options here (e.g. 1: Option One)')
                            ->default(function (Forms\Get $get) {
                                if ($get('type') === 'multiple_choice') {
                                    return [
                                        '1' => 'Option One',
                                        '2' => 'Option Two',
                                        '3' => 'Option Three',
                                    ];
                                }
                                return [];
                            }),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                    
                Tables\Columns\TextColumn::make('content')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 40) {
                            return null;
                        }
                        return $state;
                    }),
                    
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'text',
                        'success' => 'yes_no',
                        'warning' => 'multiple_choice',
                    ])
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('order')
                    ->numeric()
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('nextPrompt.title')
                    ->label('Next Prompt')
                    ->limit(30)
                    ->default('-- End of flow --'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            // We'll add relation managers later
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrompts::route('/'),
            'create' => Pages\CreatePrompt::route('/create'),
            'edit' => Pages\EditPrompt::route('/{record}/edit'),
        ];
    }
}
