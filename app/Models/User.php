<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Scout\Searchable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Searchable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'foto_perfil',
        'banner_perfil',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * Atributos que no se incluirán cuando el modelo se convierta a array o JSON.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function perteneceCanciones()
    {
        return $this->morphedByMany(Cancion::class, 'perteneceable', 'pertenece_user');
    }

    public function perteneceContenedores()
    {
        return $this->morphedByMany(Contenedor::class, 'perteneceable', 'pertenece_user');
    }

    public function loopzContenedores()
    {
        return $this->belongsToMany(Contenedor::class, 'loopzs_contenedores', 'user_id', 'contenedor_id');
    }

    public function loopzCanciones()
    {
        return $this->belongsToMany(Cancion::class, 'loopzs_canciones', 'user_id', 'cancion_id');
    }

    public function seguidores()
    {
        return $this->belongsToMany(User::class, 'seguidores', 'user_id', 'seguido_id');
    }

    public function seguidos()
    {
        return $this->belongsToMany(User::class, 'seguidores', 'seguido_id', 'user_id');
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'rol_user', 'user_id', 'rol_id');
    }

    public function toSearchableArray(): array
    {
        $array = $this->toArray();

        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
