<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContenedorRequest;
use App\Http\Requests\UpdateContenedorRequest;
use App\Models\Contenedor;
use App\Models\Cancion;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContenedorController extends Controller
{
    private function getTipoVista(Request $peticion)
    {
        $tipo = null;
        $vistaBase = null;
        $nombreRutaBase = null;

        if ($peticion->routeIs('albumes.*')) {
            $tipo = 'album';
            $vistaBase = 'albumes/';
            $nombreRutaBase = 'albumes';
        } elseif ($peticion->routeIs('playlists.*')) {
            $tipo = 'playlist';
            $vistaBase = 'playlists/';
            $nombreRutaBase = 'playlists';
        } elseif ($peticion->routeIs('eps.*')) {
            $tipo = 'ep';
            $vistaBase = 'eps/';
            $nombreRutaBase = 'eps';
        } elseif ($peticion->routeIs('singles.*')) {
            $tipo = 'single';
            $vistaBase = 'singles/';
            $nombreRutaBase = 'singles';
        } elseif ($peticion->routeIs('loopzs.*')) {
            $tipo = 'loopz';
            $vistaBase = 'loopzs/';
            $nombreRutaBase = 'loopzs';
        }
        return ['tipo' => $tipo, 'vista' => $vistaBase, 'ruta' => $nombreRutaBase];
    }

    private function validarTipoContenedor(Contenedor $contenedor, $tipoEsperado)
    {
        if ($contenedor->tipo !== $tipoEsperado) {
            abort(404);
        }
    }

    private function getNombreTipo($tipo)
    {
        switch ($tipo) {
            case 'album':
                return 'álbum';
            case 'playlist':
                return 'playlist';
            case 'ep':
                return 'EP';
            case 'single':
                return 'single';
        }
    }

    private function getLoopZUsuario($user): array
    {
        if (!$user) {
            return [];
        }
        $loopzPlaylist = $user->perteneceContenedores()
            ->where('tipo', 'loopz')
            ->first();

        if (!$loopzPlaylist) {
            return [];
        }

        return DB::table('cancion_contenedor')
            ->where('contenedor_id', $loopzPlaylist->id)
            ->pluck('cancion_id')
            ->all();
    }

    public function index()
    {

        return redirect()->route('welcome');
    }


    public function crearLanzamiento()
    {
        return Inertia::render('lanzamiento/Create');
    }

    public function storeLanzamiento(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'tipo' => 'required|in:album,ep,single',
            'imagen' => 'nullable|image|max:4096',
            'publico' => 'boolean',
            'tipo' => 'required|in:album,ep,single',
            'userIds' => 'nullable|array',
            'userIds.*' => 'integer|exists:users,id',
        ]);

        $imagenUrl = null;
        if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
            $archivoImagen = $request->file('imagen');
            $imagenUrl = Storage::disk(config('filesystems.default'))->url(
                Storage::disk(config('filesystems.default'))->putFileAs(
                    'contenedor_imagenes',
                    $archivoImagen,
                    Str::uuid() . "_img.{$archivoImagen->getClientOriginalExtension()}",
                )
            );
        }

        $contenedor = Contenedor::create([
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'tipo' => $validated['tipo'],
            'imagen' => $imagenUrl,
            'tipo' => $validated['tipo'],
            'publico' => $validated['publico'] ?? false,
        ]);

        $idCreador = Auth::id();
        $usuariosASincronizar = [];

        foreach ($validated['userIds'] ?? [] as $idUsuario) {
            if ($idUsuario != $idCreador) {
                $usuariosASincronizar[(int) $idUsuario] = ['propietario' => false];
            }
        }
        if ($idCreador) {
            $usuariosASincronizar[$idCreador] = ['propietario' => true];
        }

        if (!empty($usuariosASincronizar)) {
            $contenedor->usuarios()->attach($usuariosASincronizar);
        }


        $creador = Auth::user();
        $seguidores = $creador->seguidores;

        if ($contenedor->publico) {
            foreach ($seguidores as $seguidor) {
                Notificacion::create([
                    'user_id' => $seguidor->id,
                    'titulo' => 'Nuevo lanzamiento de ' . $creador->name,
                    'mensaje' => $creador->name . ' ha subido un nuevo ' . $contenedor->tipo . ': ' . $contenedor->nombre,
                ]);
            }
        }


        return redirect()->route('biblioteca')->with('success', 'Lanzamiento creado exitosamente.');
    }
    public function create(Request $peticion)
    {
        $infoRecurso = $this->getTipoVista($peticion);
        $nombreVista = $infoRecurso['vista'] . 'Create';
        return Inertia::render($nombreVista, ['tipo' => $infoRecurso['tipo']]);
    }

    public function store(StoreContenedorRequest $peticion)
    {
        $infoRecurso = $this->getTipoVista($peticion);
        $tipoContenedor = $infoRecurso['tipo'];

        $datosValidados = $peticion->validated();
        $datosValidados['tipo'] = $tipoContenedor;

        $campoImagen = 'imagen';

        if ($peticion->hasFile($campoImagen) && $peticion->file($campoImagen)->isValid()) {
            $archivoImagen = $peticion->file($campoImagen);

            $datosValidados[$campoImagen] = Storage::disk(config('filesystems.default'))->url(
                Storage::disk(config('filesystems.default'))->putFileAs(
                    'contenedor_imagenes',
                    $archivoImagen,
                    Str::uuid() . "_img.{$archivoImagen->getClientOriginalExtension()}",
                )
            );
        } else {
            if (isset($datosValidados[$campoImagen])) {
                unset($datosValidados[$campoImagen]);
            }
        }

        $contenedor = Contenedor::create($datosValidados);

        $idCreador = Auth::id();
        $usuariosASincronizar = [];

        foreach ($peticion->input('userIds', []) as $idUsuario) {
            if ($idUsuario != $idCreador) {
                $usuariosASincronizar[(int) $idUsuario] = ['propietario' => false];
            }
        }
        if ($idCreador) {
            $usuariosASincronizar[$idCreador] = ['propietario' => true];
        }

        if (!empty($usuariosASincronizar)) {
            $contenedor->usuarios()->attach($usuariosASincronizar);
        }

        return redirect()->route('biblioteca');
    }


    public function show(Request $peticion, $id)
    {
        $infoRecurso = $this->getTipoVista($peticion);
        $tipoEsperado = $infoRecurso['tipo'];
        $nombreVista = $infoRecurso['vista'] . 'Show';

        $contenedor = Contenedor::findOrFail($id);

        if ($contenedor->imagen && !filter_var($contenedor->imagen, FILTER_VALIDATE_URL)) {
            $contenedor->imagen = Storage::disk(config('filesystems.default'))->url($contenedor->imagen);
        }

        $this->validarTipoContenedor($contenedor, $tipoEsperado);

        $usuario = Auth::user();
        $cancionesLoopzIds = $this->getLoopZUsuario($usuario);

        $contenedor->load([
            'canciones' => function ($query) {
                $query->select('canciones.id', 'canciones.titulo', 'canciones.archivo_url', 'canciones.foto_url', 'canciones.duracion')
                    ->withPivot('id as pivot_id', 'created_at as pivot_created_at')
                    ->with(['usuarios' => function ($userQuery) {
                        $userQuery->select('users.id', 'users.name');
                    }])
                    ->orderBy('pivot_created_at');
            },
            'usuarios:id,name',
            'loopzusuarios:users.id'
        ]);

        $contenedor->canciones->each(function ($cancion) use ($cancionesLoopzIds) {
            $cancion->es_loopz = in_array($cancion->id, $cancionesLoopzIds);
        });

        if ($usuario) {
            $contenedor->can = [
                'view'   => $usuario->can('view', $contenedor),
                'edit'   => $usuario->can('update', $contenedor),
                'delete' => $usuario->can('delete', $contenedor),
            ];
            $contenedor->user_megusta = $contenedor->loopzusuarios->contains('id', $usuario->id);

            $userPlaylists = $usuario->perteneceContenedores()
                ->where('tipo', 'playlist')
                ->with('canciones:id')
                ->select('id', 'nombre', 'imagen')
                ->get();

            $userPlaylists->each(function ($playlist) {
                if ($playlist->imagen && !filter_var($playlist->imagen, FILTER_VALIDATE_URL)) {
                    $playlist->imagen = Storage::disk(config('filesystems.default'))->url($playlist->imagen);
                }
            });
        } else {
            $contenedor->can = [
                'view'   => $contenedor->publico ?? false,
                'edit'   => false,
                'delete' => false,
            ];
            $contenedor->user_megusta = false;
            $userPlaylists = collect();
        }

        return Inertia::render($nombreVista, [
            'contenedor' => $contenedor,
            'auth' => [
                'user' => $usuario ? [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'playlists' => $userPlaylists->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'nombre' => $p->nombre,
                            'imagen' => $p->imagen,
                            'canciones' => $p->canciones,
                        ];
                    }),
                ] : null,
            ],
        ]);
    }


    public function edit(Request $peticion, $id)
    {
        $infoRecurso = $this->getTipoVista($peticion);
        $tipoEsperado = $infoRecurso['tipo'];
        $nombreVista = $infoRecurso['vista'] . 'Edit';

        $contenedor = Contenedor::findOrFail($id);
        $this->validarTipoContenedor($contenedor, $tipoEsperado);
        $this->authorize('update', $contenedor);

        $contenedor->load(['usuarios' => function ($query) {
            $query->select('users.id', 'users.name', 'users.email')->withPivot('propietario');
        }]);

        $esPropietario = false;
        if (Auth::check()) {
            $propietario = $contenedor->usuarios()->wherePivot('propietario', true)->first();
            $esPropietario = $propietario && $propietario->id === Auth::id();
        }
        $contenedor->is_owner = $esPropietario;

        return Inertia::render($nombreVista, [
            'contenedor' => $contenedor,
        ]);
    }

    public function update(UpdateContenedorRequest $peticion, $id)
    {
        $infoRecurso = $this->getTipoVista($peticion);
        $tipoEsperado = $infoRecurso['tipo'];
        $rutaBase = $infoRecurso['ruta'];
        $rutaRedireccion = $rutaBase . '.show';

        $contenedor = Contenedor::findOrFail($id);
        $this->validarTipoContenedor($contenedor, $tipoEsperado);
        $this->authorize('update', $contenedor);

        $datosValidados = $peticion->validated();

        $esPropietario = false;
        if (Auth::check()) {
            $propietario = $contenedor->usuarios()->wherePivot('propietario', true)->first();
            $esPropietario = $propietario && $propietario->id === Auth::id();
        }

        if ($esPropietario) {
            $idsUsuariosValidados = $peticion->validate([
                'userIds' => 'nullable|array',
                'userIds.*' => 'integer|exists:users,id',
            ]);
        } else {
            unset($datosValidados['userIds']);
        }

        $campoImagenRequest = 'imagen_nueva';
        $campoImagenModelo = 'imagen';
        $directorioS3 = 'contenedor_imagenes';

        $rutaImagenAntigua = $contenedor->$campoImagenModelo;

        if ($peticion->hasFile($campoImagenRequest) && $peticion->file($campoImagenRequest)->isValid()) {
            if ($rutaImagenAntigua) {
                if (Storage::disk(config('filesystems.default'))->exists($rutaImagenAntigua)) {
                    Storage::disk(config('filesystems.default'))->delete($rutaImagenAntigua);
                }
            }

            $nuevoArchivoImagen = $peticion->file($campoImagenRequest);
            $nombreArchivo = Str::uuid() . "_img." . $nuevoArchivoImagen->getClientOriginalExtension();
            $pathGuardadoS3 = Storage::disk(config('filesystems.default'))->putFileAs(
                $directorioS3,
                $nuevoArchivoImagen,
                $nombreArchivo,
                'public'
            );

            $urlCompleta = Storage::disk(config('filesystems.default'))->url($pathGuardadoS3);
            $datosValidados[$campoImagenModelo] = $urlCompleta;
        } elseif ($peticion->boolean('eliminar_imagen')) {
            if ($rutaImagenAntigua) {
                if (Storage::disk(config('filesystems.default'))->exists($rutaImagenAntigua)) {
                    Storage::disk(config('filesystems.default'))->delete($rutaImagenAntigua);
                }
            }
            $datosValidados[$campoImagenModelo] = null;
        } else {
            unset($datosValidados[$campoImagenModelo]);
        }

        $contenedor->update($datosValidados);

        if ($esPropietario) {
            $idsUsuarios = $idsUsuariosValidados['userIds'] ?? [];
            $usuariosASincronizar = [];
            $idCreador = Auth::id();

            if ($idCreador) {
                $usuariosASincronizar[$idCreador] = ['propietario' => true];
            }

            foreach ($idsUsuarios as $idUsuario) {
                if ($idUsuario != $idCreador) {
                    $usuariosASincronizar[$idUsuario] = ['propietario' => false];
                }
            }

            $contenedor->usuarios()->sync($usuariosASincronizar);
        }

        return redirect()->route($rutaRedireccion, $contenedor->id);
    }

    public function destroy(Request $peticion, $id)
    {
        $infoRecurso = $this->getTipoVista($peticion);
        $tipoEsperado = $infoRecurso['tipo'];
        $rutaRedireccion = $infoRecurso['ruta'] . '.index';

        $contenedor = Contenedor::findOrFail($id);
        $this->validarTipoContenedor($contenedor, $tipoEsperado);
        $this->authorize('delete', $contenedor);

        $contenedor->usuarios()->detach();
        $contenedor->canciones()->detach();

        if ($contenedor->imagen) {
            Storage::disk('public')->delete($contenedor->imagen);
        }

        $contenedor->delete();

        return redirect()->route('welcome');
    }

    public function buscarCanciones(Request $peticion, Contenedor $contenedor)
    {
        if (!in_array($contenedor->tipo, ['playlist', 'album', 'ep', 'single', 'loopz'])) {
            return response()->json(['error' => 'Tipo de contenedor no válido para buscar canciones'], 400);
        }

        $usuario = Auth::user();
        $cancionesLoopzIds = $this->getLoopZUsuario($usuario);
        $consulta = $peticion->input('query', '');
        $minimoBusqueda = 1;
        $limite = 30;

        $idsCancionesExistentes = [];
        if (in_array($contenedor->tipo, ['album', 'ep', 'single'])) {
            $idsCancionesExistentes = $contenedor->canciones()->pluck('canciones.id')->all();
        }

        $consultaCanciones = Cancion::query()
            ->with('usuarios:id,name')
            ->where(function ($q) use ($usuario) {
                $q->where('publico', true);
                if ($usuario) {
                    $q->orWhereHas('usuarios', function ($q2) use ($usuario) {
                        $q2->where('users.id', $usuario->id);
                    });
                }
            });

        if (in_array($contenedor->tipo, ['album', 'ep', 'single'])) {
            $idsUsuariosContenedor = $contenedor->usuarios()->pluck('users.id')->all();
            if ($idsUsuariosContenedor) {
                $consultaCanciones->whereHas('usuarios', function ($q) use ($idsUsuariosContenedor) {
                    $q->whereIn('users.id', $idsUsuariosContenedor);
                });
            }
        }

        if ($idsCancionesExistentes) {
            $consultaCanciones->whereNotIn('canciones.id', $idsCancionesExistentes);
        }

        if (strlen($consulta) >= $minimoBusqueda) {
            $consultaCanciones->where('titulo', 'LIKE', "%{$consulta}%");
            $limite = 15;
        } else {
            $consultaCanciones->orderBy('titulo');
        }

        $resultados = $consultaCanciones
            ->select('canciones.id', 'canciones.titulo', 'canciones.foto_url', 'canciones.duracion')
            ->limit($limite)
            ->get();

        $resultados->each(function ($cancion) use ($cancionesLoopzIds) {
            $cancion->es_loopz = in_array($cancion->id, $cancionesLoopzIds);
        });

        return response()->json($resultados);
    }


    public function anadirCancion(Request $peticion, Contenedor $contenedor)
    {
        if (!in_array($contenedor->tipo, ['playlist', 'album', 'ep', 'single'])) {
            abort(404);
        }
        $this->authorize('update', $contenedor);

        $valido = $peticion->validate([
            'cancion_id' => 'required|exists:canciones,id',
        ]);
        $idCancion = $valido['cancion_id'];
        $mensaje = 'Error interno.';
        $tipoNombre = $this->getNombreTipo($contenedor->tipo);


        if (in_array($contenedor->tipo, ['album', 'ep', 'single'])) {
            $yaExiste = $contenedor->canciones()->where('canciones.id', $idCancion)->exists();
            if ($yaExiste) {
                $mensaje = 'Esta canción ya está en el ' . $tipoNombre . '.';
            } else {
                $contenedor->canciones()->attach($idCancion);
                $mensaje = 'Canción añadida al ' . $tipoNombre . '.';
            }
        } else {
            $contenedor->canciones()->attach($idCancion);
            $mensaje = 'Canción añadida a la ' . $tipoNombre . '.';
        }

        $rutaBase = match ($contenedor->tipo) {
            'album' => 'albumes',
            'ep' => 'eps',
            'single' => 'singles',
            default => 'playlists'
        };
        $rutaRedireccion = $rutaBase . '.show';

        $tipoMensaje = 'success';
        if (str_contains($mensaje, 'Error') || str_contains($mensaje, 'ya está')) {
            $tipoMensaje = 'error';
        }

        return redirect()->route($rutaRedireccion, $contenedor->id)
            ->with($tipoMensaje, $mensaje);
    }

    public function quitarCancionPorPivot(Request $peticion, Contenedor $contenedor, $idPivot)
    {
        $this->authorize('update', $contenedor);

        $contenedor->canciones()
            ->wherePivot('id', $idPivot)
            ->detach();

        $rutaBase = match ($contenedor->tipo) {
            'album' => 'albumes',
            'ep' => 'eps',
            'single' => 'singles',
            default => 'playlists'
        };
        $rutaRedireccion = $rutaBase . '.show';

        return redirect()->route($rutaRedireccion, $contenedor->id);
    }

    public function toggleCancion(Request $request, $playlistId, $songId)
    {
        $user = $request->user();

        $playlist = Contenedor::where('id', $playlistId)
            ->where('tipo', 'playlist')
            ->whereHas('usuarios', fn($q) => $q->where('users.id', $user->id))
            ->firstOrFail();

        $cancion = Cancion::findOrFail($songId);

        if ($playlist->canciones()->where('cancion_id', $cancion->id)->exists()) {
            $playlist->canciones()->detach($cancion);
        } else {
            $playlist->canciones()->attach($cancion);
        }

        return back();
    }


    public function toggleLoopz(Request $request, Contenedor $contenedor)
    {
        $user = Auth::user();

        if (!$user) {
            return Redirect::back();
        }

        $isLiked = $contenedor->loopzusuarios()->where('user_id', $user->id)->exists();

        if ($isLiked) {
            $contenedor->loopzusuarios()->detach($user->id);
        } else {
            $contenedor->loopzusuarios()->attach($user->id);
        }

        return Redirect::back();
    }

    public function incrementarVisualizacion(Request $request, Cancion $cancion)
    {
        $cancion->increment('visualizaciones');
        $cancion->refresh();

        $objetivo = 3;
        $notificacionEnviada = false;

        $visualizaciones = (int) $cancion->visualizaciones;
        if ($visualizaciones === $objetivo) {
            $usuarios = $cancion->propietarios;

            if ($usuarios && $usuarios->isNotEmpty()) {
                foreach ($usuarios as $usuario) {
                    Notificacion::create([
                        'titulo' => '¡Felicidades!',
                        'mensaje' => "Tu canción '{$cancion->titulo}' ha alcanzado las {$objetivo} visualizaciones. ¡Sigue así!",
                        'leido' => false,
                        'user_id' => $usuario->id,
                    ]);
                }

                $notificacionEnviada = true;
            } else {
            }
        }

        return response()->json([
            'message' => 'Visualización incrementada con éxito.',
            'visualizaciones' => $cancion->visualizaciones,
            'notificacion_enviada' => $notificacionEnviada,
        ]);
    }
}
