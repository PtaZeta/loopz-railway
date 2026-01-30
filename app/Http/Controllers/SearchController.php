<?php

namespace App\Http\Controllers;

use App\Models\Cancion;
use App\Models\Contenedor;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $consulta = $request->input('query', '');

        $limiteMinimoCanciones = 10;
        $limiteMinimoOtros = 6;

        $idsCanciones = Cancion::search($consulta)->keys();
        $idsUsuarios = User::search($consulta)->keys();
        $idsContenedores = Contenedor::search($consulta)->keys();

        $todosContenedores = Contenedor::whereIn('id', $idsContenedores)
            ->with('usuarios', 'canciones.usuarios', 'canciones.generos')
            ->get()
            ->map(function ($contenedor) use ($consulta) {
                $contenedor->puntuacion_coincidencia = $this->calcularPuntuacionCoincidencia($contenedor->nombre, $consulta);
                return $contenedor;
            })
            ->sortByDesc('puntuacion_coincidencia');

        $todasCanciones = Cancion::whereIn('id', $idsCanciones)
            ->with('usuarios', 'generos')
            ->get()
            ->map(function ($cancion) use ($consulta) {
                $cancion->puntuacion_coincidencia = $this->calcularPuntuacionCoincidencia($cancion->titulo, $consulta);
                return $cancion;
            })
            ->sortByDesc('puntuacion_coincidencia');

        $todosUsuarios = User::whereIn('id', $idsUsuarios)
            ->get()
            ->map(function ($usuario) use ($consulta) {
                $usuario->puntuacion_coincidencia = $this->calcularPuntuacionCoincidencia($usuario->name, $consulta);
                return $usuario;
            })
            ->sortByDesc('puntuacion_coincidencia');

        $playlistsIniciales = $todosContenedores->filter(fn($contenedor) => $contenedor->tipo === 'playlist');
        $epsIniciales = $todosContenedores->filter(fn($contenedor) => $contenedor->tipo === 'ep');
        $singlesIniciales = $todosContenedores->filter(fn($contenedor) => $contenedor->tipo === 'single');
        $albumesIniciales = $todosContenedores->filter(fn($contenedor) => $contenedor->tipo === 'album');

        $itemPrincipal = null;
        $clavePrincipal = null;

        if ($consulta) {
            $itemPrincipal = $this->encontrarPrimeraCoincidenciaExactaOMejor([
                ['items' => $todosUsuarios, 'key' => 'name', 'type' => 'user'],
                ['items' => $todasCanciones, 'key' => 'titulo', 'type' => 'cancion'],
                ['items' => $playlistsIniciales, 'key' => 'nombre', 'type' => 'playlist'],
                ['items' => $albumesIniciales, 'key' => 'nombre', 'type' => 'album'],
                ['items' => $epsIniciales, 'key' => 'nombre', 'type' => 'ep'],
                ['items' => $singlesIniciales, 'key' => 'nombre', 'type' => 'single'],
            ], $consulta);

            if (!$itemPrincipal) {
                $itemPrincipal =
                    $todosUsuarios->first() ?:
                    $todasCanciones->first() ?:
                    $playlistsIniciales->first() ?:
                    $albumesIniciales->first() ?:
                    $epsIniciales->first() ?:
                    $singlesIniciales->first();
                $clavePrincipal = $itemPrincipal ? class_basename(get_class($itemPrincipal)) : null;
            } else {
                $clavePrincipal = $itemPrincipal['type'];
                $itemPrincipal = $itemPrincipal['item'];
            }
        }

        $contenidoRelacionado = [
            'canciones' => collect(),
        ];
        $otrasSeccionesRelacionadas = [];

        $idsCancionesIncluidas = $todasCanciones->pluck('id');


        if ($itemPrincipal) {
            switch ($clavePrincipal) {
                case 'user':
                    $contenidoRelacionado['canciones'] = $contenidoRelacionado['canciones']->merge(
                        $itemPrincipal->perteneceCanciones()
                            ->with('usuarios', 'generos')
                            ->whereNotIn('id', $idsCancionesIncluidas)
                            ->inRandomOrder()
                            ->limit($limiteMinimoCanciones)
                            ->get()
                            ->map(fn($c) => $this->añadirPuntuacionCoincidencia($c, $consulta, 'titulo'))
                    );
                    $idsCancionesIncluidas = $idsCancionesIncluidas->merge($contenidoRelacionado['canciones']->pluck('id'));

                    $otrasSeccionesRelacionadas['playlists_del_artista'] = $itemPrincipal->perteneceContenedores()
                        ->where('tipo', 'playlist')
                        ->with('usuarios')
                        ->inRandomOrder()
                        ->limit($limiteMinimoOtros)
                        ->get();

                    $otrasSeccionesRelacionadas['albumes_del_artista'] = $itemPrincipal->perteneceContenedores()
                        ->where('tipo', 'album')
                        ->with('usuarios')
                        ->inRandomOrder()
                        ->limit($limiteMinimoOtros)
                        ->get();
                    break;

                case 'playlist':
                case 'album':
                case 'ep':
                case 'single':
                    $contenidoRelacionado['canciones'] = $contenidoRelacionado['canciones']->merge(
                        $itemPrincipal->canciones()
                            ->with('usuarios', 'generos')
                            ->whereNotIn('id', $idsCancionesIncluidas)
                            ->inRandomOrder()
                            ->limit($limiteMinimoCanciones)
                            ->get()
                            ->map(fn($c) => $this->añadirPuntuacionCoincidencia($c, $consulta, 'titulo'))
                    );
                    $idsCancionesIncluidas = $idsCancionesIncluidas->merge($contenidoRelacionado['canciones']->pluck('id'));


                    $artistas = $itemPrincipal->usuarios;
                    if ($artistas->isNotEmpty()) {
                        $otrasSeccionesRelacionadas['artistas_relacionados'] = $artistas->take($limiteMinimoOtros);
                    }

                    $generosContenedor = $itemPrincipal->canciones->pluck('generos')->flatten()->unique('id')->pluck('nombre');
                    if ($generosContenedor->isNotEmpty()) {
                        $contenidoRelacionado['canciones'] = $contenidoRelacionado['canciones']->merge(
                            Cancion::whereHas('generos', function ($q) use ($generosContenedor) {
                                $q->whereIn('nombre', $generosContenedor);
                            })
                            ->whereNotIn('id', $idsCancionesIncluidas)
                            ->with('usuarios', 'generos')
                            ->inRandomOrder()
                            ->limit($limiteMinimoCanciones)
                            ->get()
                            ->map(fn($c) => $this->añadirPuntuacionCoincidencia($c, $consulta, 'titulo'))
                        );
                        $idsCancionesIncluidas = $idsCancionesIncluidas->merge($contenidoRelacionado['canciones']->pluck('id'));
                    }
                    break;

                case 'cancion':
                    $artistasCancion = $itemPrincipal->usuarios;
                    if ($artistasCancion->isNotEmpty()) {
                        $otrasSeccionesRelacionadas['artistas_de_la_cancion'] = $artistasCancion->take($limiteMinimoOtros);

                        foreach($artistasCancion as $artista) {
                            $contenidoRelacionado['canciones'] = $contenidoRelacionado['canciones']->merge(
                                $artista->perteneceCanciones()
                                    ->with('usuarios', 'generos')
                                    ->whereNotIn('id', $idsCancionesIncluidas)
                                    ->inRandomOrder()
                                    ->limit(floor($limiteMinimoCanciones / count($artistasCancion) + 1))
                                    ->get()
                                    ->map(fn($c) => $this->añadirPuntuacionCoincidencia($c, $consulta, 'titulo'))
                            );
                            $idsCancionesIncluidas = $idsCancionesIncluidas->merge($contenidoRelacionado['canciones']->pluck('id'));
                            if ($contenidoRelacionado['canciones']->count() >= $limiteMinimoCanciones) break;
                        }
                    }

                    $generosCancion = $itemPrincipal->generos->pluck('nombre');
                    if ($generosCancion->isNotEmpty()) {
                        $contenidoRelacionado['canciones'] = $contenidoRelacionado['canciones']->merge(
                            Cancion::whereHas('generos', function ($q) use ($generosCancion) {
                                $q->whereIn('nombre', $generosCancion);
                            })
                            ->whereNotIn('id', $idsCancionesIncluidas)
                            ->with('usuarios', 'generos')
                            ->inRandomOrder()
                            ->limit($limiteMinimoCanciones)
                            ->get()
                            ->map(fn($c) => $this->añadirPuntuacionCoincidencia($c, $consulta, 'titulo'))
                        );
                        $idsCancionesIncluidas = $idsCancionesIncluidas->merge($contenidoRelacionado['canciones']->pluck('id'));
                    }
                    break;
            }

            $contenidoRelacionado['canciones'] = $contenidoRelacionado['canciones']
                ->unique('id')
                ->sortByDesc('puntuacion_coincidencia')
                ->take($limiteMinimoCanciones);
        }

        $cancionesFinales = $todasCanciones->merge($contenidoRelacionado['canciones'])->unique('id')->sortByDesc('puntuacion_coincidencia');
        $usuariosFinales = $todosUsuarios->unique('id')->sortByDesc('puntuacion_coincidencia');
        $playlistsFinales = $playlistsIniciales->unique('id')->sortByDesc('puntuacion_coincidencia');
        $epsFinales = $epsIniciales->unique('id')->sortByDesc('puntuacion_coincidencia');
        $singlesFinales = $singlesIniciales->unique('id')->sortByDesc('puntuacion_coincidencia');
        $albumesFinales = $albumesIniciales->unique('id')->sortByDesc('puntuacion_coincidencia');


        if ($cancionesFinales->count() < $limiteMinimoCanciones) {
            $necesarias = $limiteMinimoCanciones - $cancionesFinales->count();
            $cancionesAdicionales = collect();

            $todosIdsGenerosRelevantes = $cancionesFinales->pluck('generos')->flatten()->pluck('id')->unique()
                                ->merge($playlistsIniciales->pluck('canciones')->flatten()->pluck('generos')->flatten()->pluck('id')->unique())
                                ->merge($epsIniciales->pluck('canciones')->flatten()->pluck('generos')->flatten()->pluck('id')->unique())
                                ->merge($singlesIniciales->pluck('canciones')->flatten()->pluck('generos')->flatten()->pluck('id')->unique())
                                ->merge($albumesIniciales->pluck('canciones')->flatten()->pluck('generos')->flatten()->pluck('id')->unique());

            $todosIdsArtistasRelevantes = $cancionesFinales->pluck('usuarios')->flatten()->pluck('id')->unique()
                                ->merge($playlistsIniciales->pluck('usuarios')->flatten()->pluck('id')->unique())
                                ->merge($epsIniciales->pluck('usuarios')->flatten()->pluck('id')->unique())
                                ->merge($singlesIniciales->pluck('usuarios')->flatten()->pluck('id')->unique())
                                ->merge($albumesIniciales->pluck('usuarios')->flatten()->pluck('id')->unique());

            if ($todosIdsGenerosRelevantes->isNotEmpty()) {
                $cancionesAdicionales = $cancionesAdicionales->merge(
                    Cancion::whereHas('generos', function ($q) use ($todosIdsGenerosRelevantes) {
                        $q->whereIn('generos.id', $todosIdsGenerosRelevantes);
                    })
                    ->whereNotIn('id', $cancionesFinales->pluck('id'))
                    ->with('usuarios', 'generos')
                    ->inRandomOrder()
                    ->limit($necesarias)
                    ->get()
                );
                $cancionesFinales = $cancionesFinales->merge($cancionesAdicionales)->unique('id');
                $necesarias = $limiteMinimoCanciones - $cancionesFinales->count();
                if ($necesarias <= 0) goto fin_llenado_canciones;
            }

            if ($todosIdsArtistasRelevantes->isNotEmpty()) {
                $cancionesAdicionales = $cancionesAdicionales->merge(
                    Cancion::whereHas('usuarios', function ($q) use ($todosIdsArtistasRelevantes) {
                        $q->whereIn('users.id', $todosIdsArtistasRelevantes);
                    })
                    ->whereNotIn('id', $cancionesFinales->pluck('id'))
                    ->with('usuarios', 'generos')
                    ->inRandomOrder()
                    ->limit($necesarias)
                    ->get()
                );
                $cancionesFinales = $cancionesFinales->merge($cancionesAdicionales)->unique('id');
                $necesarias = $limiteMinimoCanciones - $cancionesFinales->count();
                if ($necesarias <= 0) goto fin_llenado_canciones;
            }

            if ($necesarias > 0) {
                $cancionesAleatorias = Cancion::whereNotIn('id', $cancionesFinales->pluck('id'))
                                        ->with('usuarios', 'generos')
                                        ->inRandomOrder()
                                        ->limit($necesarias)
                                        ->get();
                $cancionesFinales = $cancionesFinales->merge($cancionesAleatorias)->unique('id');
            }
            fin_llenado_canciones:;
        }


        if ($usuariosFinales->count() < $limiteMinimoOtros) {
            $necesarias = $limiteMinimoOtros - $usuariosFinales->count();
            $usuariosAleatorios = User::whereNotIn('id', $usuariosFinales->pluck('id'))
                                ->inRandomOrder()
                                ->limit($necesarias)
                                ->get();
            $usuariosFinales = $usuariosFinales->merge($usuariosAleatorios)->unique('id');
        }

        if ($playlistsFinales->count() < $limiteMinimoOtros) {
            $necesarias = $limiteMinimoOtros - $playlistsFinales->count();
            $playlistsAleatorias = Contenedor::where('tipo', 'playlist')
                                        ->whereNotIn('id', $playlistsFinales->pluck('id'))
                                        ->with('usuarios', 'canciones.usuarios')
                                        ->inRandomOrder()
                                        ->limit($necesarias)
                                        ->get();
            $playlistsFinales = $playlistsFinales->merge($playlistsAleatorias)->unique('id');
        }

        if ($epsFinales->count() < $limiteMinimoOtros) {
            $necesarias = $limiteMinimoOtros - $epsFinales->count();
            $epsAleatorios = Contenedor::where('tipo', 'ep')
                                ->whereNotIn('id', $epsFinales->pluck('id'))
                                ->with('usuarios', 'canciones.usuarios')
                                ->inRandomOrder()
                                ->limit($necesarias)
                                ->get();
            $epsFinales = $epsFinales->merge($epsAleatorios)->unique('id');
        }

        if ($singlesFinales->count() < $limiteMinimoOtros) {
            $necesarias = $limiteMinimoOtros - $singlesFinales->count();
            $singlesAleatorios = Contenedor::where('tipo', 'single')
                                    ->whereNotIn('id', $singlesFinales->pluck('id'))
                                    ->with('usuarios', 'canciones.usuarios')
                                    ->inRandomOrder()
                                    ->limit($necesarias)
                                    ->get();
            $singlesFinales = $singlesFinales->merge($singlesAleatorios)->unique('id');
        }

        if ($albumesFinales->count() < $limiteMinimoOtros) {
            $necesarias = $limiteMinimoOtros - $albumesFinales->count();
            $albumesAleatorios = Contenedor::where('tipo', 'album')
                                    ->whereNotIn('id', $albumesFinales->pluck('id'))
                                    ->with('usuarios', 'canciones.usuarios')
                                    ->inRandomOrder()
                                    ->limit($necesarias)
                                    ->get();
            $albumesFinales = $albumesFinales->merge($albumesAleatorios)->unique('id');
        }


        $idsCancionesLoopz = [];
        if (Auth::check()) {
            $contenedorLoopz = Auth::user()->perteneceContenedores()
                ->where('tipo', 'loopz')
                ->with('canciones:id')
                ->first();

            if ($contenedorLoopz && $contenedorLoopz->canciones) {
                $idsCancionesLoopz = $contenedorLoopz->canciones->pluck('id')->toArray();
            }
        }

        $cancionesFinales->each(fn($c) => $c->loopz_user = in_array($c->id, $idsCancionesLoopz));
        $playlistsFinales->each(fn($p) => $p->canciones->each(fn($c) => $c->loopz_user = in_array($c->id, $idsCancionesLoopz)));
        $epsFinales->each(fn($ep) => $ep->canciones->each(fn($c) => $c->loopz_user = in_array($c->id, $idsCancionesLoopz)));
        $singlesFinales->each(fn($s) => $s->canciones->each(fn($c) => $c->loopz_user = in_array($c->id, $idsCancionesLoopz)));
        $albumesFinales->each(fn($a) => $a->canciones->each(fn($c) => $c->loopz_user = in_array($c->id, $idsCancionesLoopz)));


        $resultados = [
            'canciones' => $cancionesFinales->take($limiteMinimoCanciones)->values(),
            'users' => $usuariosFinales->take($limiteMinimoOtros)->values(),
            'playlists' => $playlistsFinales->take($limiteMinimoOtros)->values(),
            'eps' => $epsFinales->take($limiteMinimoOtros)->values(),
            'singles' => $singlesFinales->take($limiteMinimoOtros)->values(),
            'albumes' => $albumesFinales->take($limiteMinimoOtros)->values(),
        ];

        $usuarioPlaylistsAñadir = Auth::user();
        $playlistsUsuario = $usuarioPlaylistsAñadir
            ? $usuarioPlaylistsAñadir->perteneceContenedores()
                ->where('tipo', 'playlist')
                ->select('id', 'nombre', 'imagen')
                ->with('canciones:id')
                ->get()
                ->map(function ($playlist) {
                    if ($playlist->imagen && !Str::startsWith($playlist->imagen, 'http')) {
                        $playlist->imagen = \Storage::disk(config('filesystems.default'))->url($playlist->imagen);
                    }
                    return $playlist;
                })
            : collect();

        return Inertia::render('Search/Index', [
            'searchQuery' => $consulta,
            'results' => $resultados,
            'principal' => $itemPrincipal,
            'principalKey' => $clavePrincipal,
            'relatedContent' => $otrasSeccionesRelacionadas,
            'relatedSongs' => $cancionesFinales->take($limiteMinimoCanciones)->values(),
            'filters' => [],
            'auth' => $usuarioPlaylistsAñadir
                ? [
                    'user' => [
                        'id' => $usuarioPlaylistsAñadir->id,
                        'name' => $usuarioPlaylistsAñadir->name,
                        'playlists' => $playlistsUsuario->map(function ($p) {
                            return [
                                'id' => $p->id,
                                'nombre' => $p->nombre,
                                'imagen' => $p->imagen,
                                'canciones' => $p->canciones,
                            ];
                        }),
                    ],
                ]
                : null,
        ]);
    }

    private function calcularPuntuacionCoincidencia(string $texto, string $consulta): int
    {
        $texto = strtolower($texto);
        $consulta = strtolower($consulta);

        if ($texto === $consulta) return 100;
        if (str_starts_with($texto, $consulta)) return 80;
        if (str_contains($texto, " $consulta ") ||
            str_starts_with($texto, "$consulta ") ||
            str_ends_with($texto, " $consulta")
        ) return 60;
        if (str_contains($texto, $consulta)) return 40;
        if (similar_text($texto, $consulta, $porcentaje)) {
            return (int) $porcentaje;
        }
        return 0;
    }

    private function encontrarPrimeraCoincidenciaExactaOMejor(array $candidatos, string $consulta): ?array
    {
        foreach ($candidatos as $candidato) {
            $exacto = $candidato['items']->firstWhere($candidato['key'], $consulta);
            if ($exacto) {
                return ['item' => $exacto, 'type' => $candidato['type']];
            }
        }

        foreach ($candidatos as $candidato) {
            $mejor = $candidato['items']->sortByDesc('puntuacion_coincidencia')->first();
            if ($mejor) {
                return ['item' => $mejor, 'type' => $candidato['type']];
            }
        }

        return null;
    }

    private function añadirPuntuacionCoincidencia($modelo, string $consulta, string $campo)
    {
        $modelo->puntuacion_coincidencia = $this->calcularPuntuacionCoincidencia($modelo->{$campo}, $consulta);
        return $modelo;
    }
}
