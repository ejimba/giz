<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Propaganistas\LaravelPhone\PhoneNumber;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use CausesActivity, HasFactory, HasRoles, HasUuids, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'email_otp',
        'email_consent',
        'phone',
        'phone_country',
        'phone_otp',
        'phone_consent',
        'password',
        'organisation_unit_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getSimplePhoneAttribute()
    {
        if (!$this->phone || !$this->phone_country) return;
        $phone = new PhoneNumber($this->phone, $this->phone_country);
        return $phone->formatForMobileDialingInCountry($this->phone_country);
    }

    public function getRoleIdsAttribute()
    {
        return $this->roles->pluck('id')->toArray();
    }

    public function logs()
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['*']);
    }

    public function organisationUnit()
    {
        return $this->belongsTo(OrganisationUnit::class);
    }

    public function organisationUnits()
    {
        return $this->belongsToMany(
            OrganisationUnit::class,
            'organisation_unit_users',
            'user_id',
            'organisation_unit_id'
        )->using(OrganisationUnitUser::class)->withTimestamps();
    }

    public function echisOrganisationUnits()
    {
        return $this->belongsToMany(
            EchisOrganisationUnit::class,
            'echis_organisation_unit_users',
            'user_id',
            'echis_organisation_unit_id'
        )->using(EchisOrganisationUnitUser::class)->withTimestamps();
    }

    public function echisOrganisationUnitCHUs()
    {
        return $this->belongsToMany(
            EchisOrganisationUnit::class,
            'echis_organisation_unit_users',
            'user_id',
            'echis_organisation_unit_id'
        )->using(EchisOrganisationUnitUser::class)->withTimestamps()->where('echis_organisation_units.type', 'c_community_health_unit');
    }

    public function echisOrganisationUnitCHPAreas()
    {
        return $this->belongsToMany(
            EchisOrganisationUnit::class,
            'echis_organisation_unit_users',
            'user_id',
            'echis_organisation_unit_id'
        )->using(EchisOrganisationUnitUser::class)->withTimestamps()->where('echis_organisation_units.type', 'd_community_health_volunteer_area');
    }
}
