<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'email',
        'password',
        'access_level',
    ];

    /**
     * The accessors to append to serialized model arrays.
     *
     * @var list<string>
     */
    protected $appends = [
        'full_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(function (): string {
            $parts = array_filter([
                $this->firstname,
                $this->lastname,
            ], fn (?string $part): bool => filled($part));

            return trim(implode(' ', $parts)) ?: $this->email;
        });
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $defaultRole = $user->access_level ?? 'patient';

            if (Role::query()->where('name', $defaultRole)->where('guard_name', 'web')->exists() && ! $user->hasRole($defaultRole)) {
                $user->assignRole($defaultRole);
            }
        });
    }
}
