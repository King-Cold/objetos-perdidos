<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Definición de roles institucionales
     */
    public const ROLE_USUARIO_GENERAL = 'Usuario general';
    public const ROLE_ADMIN = 'Administrador/bibliotecario';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
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

    /**
     * Comprueba si el usuario tiene el rol de «Usuario general».
     */
    public function isUsuarioGeneral(): bool
    {
        return $this->role === self::ROLE_USUARIO_GENERAL;
    }

    /**
     * Comprueba si el usuario tiene el rol de «Administrador/bibliotecario».
     * Administrador y bibliotecario corresponden al mismo rol y conjunto de permisos (CA-09).
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->role === 'Administrador' || $this->role === 'Bibliotecario';
    }
}
