<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReferralsRelationManager extends RelationManager
{
    protected static string $relationship = 'referrals';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('referral_date')->label('Referral Date'),
                Forms\Components\TextInput::make('referral_from')->label('Referral From'),
                Forms\Components\TextInput::make('referral_to')->label('Referral To'),
                Forms\Components\TextInput::make('reason')->label('Reason'),
                Forms\Components\TextInput::make('notes')->label('Notes'),
                Forms\Components\TextInput::make('shr_id')->label('Shared Health Record ID'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('referral_date')
            ->columns([
                Tables\Columns\TextColumn::make('referral_date'),
                Tables\Columns\TextColumn::make('referral_from'),
                Tables\Columns\TextColumn::make('referral_to'),
                Tables\Columns\TextColumn::make('reason'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
