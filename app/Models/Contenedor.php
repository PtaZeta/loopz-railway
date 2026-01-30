<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;

class Contenedor extends Model
{
    /** @use HasFactory<\Database\Factories\ContenedorFactory> */
    use HasFactory, Searchable;

    protected $fillable = [
        'nombre',
        'descripcion',
        'imagen',
        'publico',
        'tipo',
    ];

    protected $table = 'contenedores';

    public function obtenerUrlImagen()
    {
        return $this->imagen
            ? Storage::disk('public')->url($this->imagen)
            : null;
    }

    public function canciones()
    {
        return $this->belongsToMany(Cancion::class, 'cancion_contenedor', 'contenedor_id', 'cancion_id')
                    ->withPivot('id')
                    ->withTimestamps();
    }

    public function usuarios()
    {
        return $this->morphToMany(User::class, 'perteneceable', 'pertenece_user');
    }

    public function loopzusuarios()
    {
        return $this->belongsToMany(User::class, 'loopzs_contenedores', 'contenedor_id', 'user_id');
    }

    public function loopzcanciones()
    {
        return $this->belongsToMany(Cancion::class, 'loopzs_canciones', 'contenedor_id', 'cancion_id');
    }

    public function generoPredominante()
    {
        $generos = $this->canciones()->with('generos')->get()->pluck('generos.*.nombre')->flatten();
        if ($generos->isEmpty()) {
            return 'Sin género';
        }
        $frecuencias = $generos->countBy();
        return $frecuencias->sortDesc()->keys()->first();
    }

    public function toSearchableArray(): array
    {
        $array = $this->toArray();

        // Solo agregar campos virtuales si NO estamos usando el driver database
        if (config('scout.driver') !== 'database') {
            $this->loadMissing('usuarios');
            $array['usuario_names'] = $this->usuarios->pluck('name')->toArray();

            $this->loadMissing('canciones');
            $array['cancion_titles'] = $this->canciones->pluck('titulo')->toArray();
        }

        return $array;
    }

    /*public function getScoutFilterableAttributes()
    {
        return ['tipo'];
    }*/

}
