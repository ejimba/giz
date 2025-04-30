<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\EchisOrganisationUnit;
use App\Models\OrganisationUnit;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?int $navigationSort = 11;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Admin';

    public static function form(Form $form): Form
    {
        $countries = [];
        foreach (countries() as $code => $country) {
            $countries[$code] = $country['name'].' '.$country['emoji'].' +'.$country['calling_code'];
        }
        $organisationUnits = OrganisationUnit::where('echis_enable', true)->where('level', 5)->orderBy('name')->pluck('name', 'id')->toArray();
        $echisOrganisationUnitChus = EchisOrganisationUnit::where('type', 'c_community_health_unit')->orderBy('name')->pluck('name', 'id')->toArray();
        $echisOrganisationUnitChpAreas = EchisOrganisationUnit::where('type', 'd_community_health_volunteer_area')->orderBy('name')->pluck('name', 'id')->toArray();
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->maxLength(255)->nullable(),
                Forms\Components\Select::make('phone_country')->options($countries),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(15)->nullable(),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrated(fn($state) => filled($state))
                    ->dehydrateStateUsing(fn($state) => filled($state) ? Hash::make($state) : null)->columnSpan('full'),
                Forms\Components\Select::make('email_consent')->options([0 => 'No', 1 => 'Yes'])->default(0),
                Forms\Components\Select::make('phone_consent')->options([0 => 'No', 1 => 'Yes'])->default(0),
                Forms\Components\Select::make('roles')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->options(Role::all()->pluck('name', 'id'))
                    ->preload()
                    ->columnSpan('full'),
                Forms\Components\Select::make('organisation_units')
                    ->label('Assigned Facilities')
                    ->multiple()
                    ->relationship('organisationUnits', 'name')
                    ->options($organisationUnits)
                    // ->searchable() TODO: temporary fix
                    ->columnSpan('full'),
                Forms\Components\Select::make('echis_organisation_unit_chus')
                    ->label('Assigned CHUs')
                    ->multiple()
                    ->relationship('echisOrganisationUnitCHUs', 'name')
                    ->options($echisOrganisationUnitChus)
                    ->searchable()
                    ->columnSpan('full'),
                Forms\Components\Select::make('echis_organisation_unit_chp_areas')
                    ->label('Assigned CHP Areas')
                    ->multiple()
                    ->relationship('echisOrganisationUnitCHPAreas', 'name')
                    ->options($echisOrganisationUnitChpAreas)
                    ->searchable()
                    ->columnSpan('full'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        return static::validateEmailOrPhone($data);
    }

    public static function mutateFormDataBeforeUpdate(array $data, $record): array
    {
        return static::validateEmailOrPhone($data);
    }

    protected static function validateEmailOrPhone(array $data): array
    {
        if (empty($data['email']) && empty($data['phone'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'Either email or phone must be provided.',
                'phone' => 'Either email or phone must be provided.',
            ]);
        }
        return $data;
    }
}
