<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'telephone',
        'avatar_chemin',
        'actif',
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
            'actif' => 'boolean',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    private ?array $permissionsCache = null;

    /**
     * Permissions du rôle, gardées en mémoire pour la durée de la requête.
     *
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        return $this->permissionsCache ??= $this->role
            ? $this->role->permissions()->pluck('slug')->all()
            : [];
    }

    public function hasPermission(string $slug): bool
    {
        return $this->actif && in_array($slug, $this->permissionSlugs(), true);
    }
}
