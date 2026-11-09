<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'document_number', 'phone', 'academic_credentials'])]
#[Hidden(['password', 'remember_token', 'academic_credentials'])]
class User extends Authenticatable
{
    protected $attributes = ['role' => 'postulante'];

    public function isManager(): bool
    {
        return in_array($this->role, ['administrador', 'gestor'], true);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'academic_credentials' => 'encrypted:array',
        ];
    }
}
