<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Cancion extends Model
{
    /** @use HasFactory<\Database\Factories\CancionFactory> */
    use HasFactory, Searchable;

    protected $table = 'canciones';

    protected $fillable = [
        'titulo',
        'genero',
        'duracion',
        'licencia_id',
        'foto_url',
        'archivo_url',
        'url_amigable',
        'publico',
        'remix',
        'cancion_original_id',
        'visualizaciones',
    ];

    public function contenedores()
    {
        return $this->belongsToMany(Contenedor::class, 'cancion_contenedor', 'cancion_id', 'contenedor_id')
                    ->withPivot('id')
                    ->withTimestamps();
    }

    public function usuarios()
    {
        return $this->morphToMany(User::class, 'perteneceable', 'pertenece_user');
    }

    public function loopz()
    {
        return $this->belongsToMany(User::class, 'loopzs_canciones', 'cancion_id', 'user_id')
                    ->withPivot('id')
                    ->withTimestamps();
    }

    public function generos()
    {
        return $this->belongsToMany(Genero::class, 'cancion_genero', 'cancion_id', 'genero_id')
                    ->withPivot('id')
                    ->withTimestamps();
    }
    public function licencia()
    {
        return $this->belongsTo(Licencia::class);
    }

    public function cancionOriginal()
    {
        return $this->belongsTo(Cancion::class, 'cancion_original_id');
    }

    /*use HasSlug;


    public function getSlugOptions(): \Spatie\Sluggable\SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('titulo')
            ->saveSlugsTo('url_amigable');
    }

    public function getRouteKeyName()
    {
        return 'url_amigable';
    }*/

    public function toSearchableArray(): array
    {
        $array = $this->toArray();

        // Solo agregar campos virtuales si NO estamos usando el driver database
        if (config('scout.driver') !== 'database') {
            $this->loadMissing('usuarios');
            $array['usuario_names'] = $this->usuarios->pluck('name')->toArray();
        }

        return $array;
    }

}
