<?php

namespace App\Http\Controllers;

use App\Models\Cancion;
use App\Models\Genero;
use App\Models\Licencia;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use getID3;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CancionController extends Controller
{
    public function index()
    {
        return redirect()->route('welcome');
    }


    public function create()
    {
        $licencias = Licencia::all();
        $generos = Genero::all()->pluck('nombre');;
        return Inertia::render('canciones/Create', [
            'generos' => $generos,
            'licencias' => $licencias,
        ]);
    }

    public function store(Request $request)
        {
            $rules = [
                'titulo' => 'required|string|max:255',
                'genero' => 'nullable|array',
                'genero.*' => 'exists:generos,nombre',
                'publico' => 'required|boolean',
                'archivo' => 'required|file|mimes:mp3,wav|max:102400',
                'foto' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
                'licencia_id' => 'required|integer|exists:licencias,id',
                'userIds' => 'nullable|array',
                'userIds.*' => 'integer|exists:users,id',
                'remix' => 'required|boolean',
                'cancion_original_id' => 'nullable|integer|exists:canciones,id',
            ];
            $validated = $request->validate($rules);

            if ($validated['remix'] && !is_null($validated['cancion_original_id'])) {
                $existe = Cancion::join('licencias', 'canciones.licencia_id', '=', 'licencias.id')
                    ->where('canciones.id', $validated['cancion_original_id'])
                    ->where('licencias.id', 2)
                    ->exists();

                if (! $existe) {
                    return redirect()->back();
                }
            }

            $cancion = new Cancion();
            $cancion->titulo = $validated['titulo'];
            $cancion->publico = $validated['publico'];
            $cancion->licencia_id = $validated['licencia_id'];
            $cancion->remix = $validated['remix'];
            $cancion->cancion_original_id = $validated['cancion_original_id'] ?? null;

            if ($request->hasFile('archivo') && $request->file('archivo')->isValid()) {
                $archivoAudio = $request->file('archivo');
                $extension = $archivoAudio->getClientOriginalExtension();
                $nombre = Str::uuid() . "_song.{$extension}";

                try {
                    $getID3 = new getID3;
                    $infoAudio = $getID3->analyze($archivoAudio->getRealPath());
                    $cancion->duracion = isset($infoAudio['playtime_seconds'])
                        ? floor($infoAudio['playtime_seconds'])
                        : 0;
                } catch (\Exception $e) {
                    $cancion->duracion = 0;
                }

                try {
                    $disk = config('filesystems.default');
                    $path = Storage::disk($disk)
                        ->putFileAs('canciones', $archivoAudio, $nombre, 'public');
                    if (! $path) {
                        throw new \Exception('putFileAs devolvió false');
                    }
                    $cancion->archivo_url = Storage::disk($disk)->url($path);
                } catch (\Exception $e) {
                    return redirect()->back();
                }
            } else {
                return redirect()->back()
                    ->withErrors(['archivo' => 'Archivo de audio inválido o no presente.'])
                    ->withInput();
            }

            if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
                $archivoFoto = $request->file('foto');
                $extFoto = $archivoFoto->getClientOriginalExtension();
                $nombreFoto = Str::uuid() . "_foto.{$extFoto}";
                try {
                    $disk = config('filesystems.default');
                    $pathFoto = Storage::disk($disk)
                        ->putFileAs('imagenes', $archivoFoto, $nombreFoto, 'public');
                    $cancion->foto_url = $pathFoto
                        ? Storage::disk($disk)->url($pathFoto)
                        : null;
                } catch (\Exception $e) {
                    $cancion->foto_url = null;
                }
            }

            $cancion->save();

            if (!empty($validated['genero'])) {
                $ids = Genero::whereIn('nombre', $validated['genero'])->pluck('id');
                $cancion->generos()->attach($ids);
            }

            $idCreador = Auth::id();
            $colaboradores = $validated['userIds'] ?? [];
            $usuariosAsociar = [];

            if ($idCreador) {
                $usuariosAsociar[$idCreador] = ['propietario' => true];
            }
            foreach (array_unique($colaboradores) as $uid) {
                if ((int) $uid !== $idCreador) {
                    $usuariosAsociar[(int) $uid] = ['propietario' => false];
                }
            }
            if (!empty($usuariosAsociar) && method_exists($cancion, 'usuarios')) {
                $cancion->usuarios()->attach($usuariosAsociar);
            }

            $creador = Auth::user();
            $seguidores = $creador->seguidores;

            if ($cancion->publico) {
                foreach ($seguidores as $seguidor) {
                    Notificacion::create([
                        'user_id' => $seguidor->id,
                        'titulo' => 'Nuevo lanzamiento de ' . $creador->name,
                        'mensaje' => $creador->name . ' ha subido una nueva canción: ' . $cancion->titulo,
                    ]);
                }
            }

            return redirect()->route('profile.show', $creador->id);
        }


        public function show($id)
{
    try {
        $cancion = Cancion::with([
            'usuarios' => function ($query) {
                $query->withPivot('propietario');
            },
            'licencia',
            'generos',
            'cancionOriginal.usuarios' => function ($query) {
                $query->withPivot('propietario');
            }
        ])->findOrFail($id);

        $cancion->usuarios_mapeados = $cancion->usuarios->map(function($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'es_propietario' => (bool) $u->pivot->propietario
            ];
        })->all();

        $cancion->generos_mapeados = $cancion->generos->map(function($g) {
            return $g->nombre;
        })->implode(', ');
        unset($cancion->generos);

        if ($cancion->cancionOriginal) {
            $cancion->cancionOriginal->usuarios_mapeados = $cancion->cancionOriginal->usuarios->map(function($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'es_propietario' => (bool) $u->pivot->propietario
                ];
            })->all();
            unset($cancion->cancionOriginal->usuarios);
        }

        return Inertia::render('canciones/Show', [
            'cancion' => $cancion
        ]);
    } catch (ModelNotFoundException $e) {
        return redirect()->route('welcome');
    }
}

    public function edit($id)
    {
        $cancion = Cancion::with(['usuarios' => function ($query) {
            $query->withPivot('propietario');
        }])->findOrFail($id);
        $generosSeleccionados = $cancion->generos->pluck('nombre')->all();
        $this->authorize('update', $cancion);

            $usuariosMapeados = $cancion->usuarios->map(function ($usuario) {
                return [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'es_propietario' => (bool) $usuario->pivot->propietario,
                ];
            })->all();

            $cancionData = $cancion->toArray();
            unset($cancionData['usuarios']);
            $cancionData['usuarios'] = $usuariosMapeados;

        $generos = Genero::all()->pluck('nombre');

        return Inertia::render('canciones/Edit', [
            'cancion' => $cancionData,
            'generos' => $generos,
            'generosSeleccionados' => $generosSeleccionados,
        ]);
    }

    public function update(Request $request, $id)
{
    try {
        $cancion = Cancion::with(['usuarios' => fn($q) => $q->withPivot('propietario')])
                           ->findOrFail($id);

        $this->authorize('update', $cancion);

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'genero' => 'nullable|array',
            'genero.*' => 'string|max:255|exists:generos,nombre',
            'publico' => 'required|boolean',
            'archivo' => 'nullable|file|mimes:mp3,wav|max:102400',
            'foto' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'eliminar_foto' => 'nullable|boolean',
            'userIds' => 'nullable|array',
            'userIds.*' => 'integer|exists:users,id',
        ]);

        $cancion->titulo = $validated['titulo'];
        $cancion->publico = $validated['publico'];

        if ($request->hasFile('archivo')) {
            $nuevoArchivoAudio = $request->file('archivo');
            $disk = config('filesystems.default');
            $rutaAnterior = $this->getRelativePath($cancion->archivo_url);
            if ($rutaAnterior && Storage::disk($disk)->exists($rutaAnterior)) {
                Storage::disk($disk)->delete($rutaAnterior);
            }
            $nombre = Str::uuid() . '_song.' . $nuevoArchivoAudio->getClientOriginalExtension();
            $getID3 = new getID3;
            try {
                $info = $getID3->analyze($nuevoArchivoAudio->getRealPath());
                $cancion->duracion = isset($info['playtime_seconds']) ? floor($info['playtime_seconds']) : $cancion->duracion;
            } catch (\Exception $e) {
                $cancion->duracion = $cancion->duracion ?? 0;
            }
            $path = Storage::disk($disk)->putFileAs('canciones', $nuevoArchivoAudio, $nombre, ['visibility' => 'public']);
            if (!$path) {
                return back();
            }
            $cancion->archivo_url = Storage::disk($disk)->url($path);
        }

        $eliminarFoto = $request->boolean('eliminar_foto');
        if ($request->hasFile('foto')) {
            $nuevaFoto = $request->file('foto');
            $disk = config('filesystems.default');
            $rutaAnterior = $this->getRelativePath($cancion->foto_url);
            if ($rutaAnterior && Storage::disk($disk)->exists($rutaAnterior)) {
                Storage::disk($disk)->delete($rutaAnterior);
            }
            $nombre = Str::uuid() . '_pic.' . $nuevaFoto->getClientOriginalExtension();
            $path = Storage::disk($disk)->putFileAs('imagenes', $nuevaFoto, $nombre, ['visibility' => 'public']);
            if (!$path) {
                return back();
            }
            $cancion->foto_url = Storage::disk($disk)->url($path);
        } elseif ($eliminarFoto) {
            $disk = config('filesystems.default');
            $ruta = $this->getRelativePath($cancion->foto_url);
            if ($ruta && Storage::disk($disk)->exists($ruta)) {
                Storage::disk($disk)->delete($ruta);
            }
            $cancion->foto_url = null;
        }

        $cancion->save();

        $generoNombres = $validated['genero'] ?? [];
        $generoIds = Genero::whereIn('nombre', $generoNombres)->pluck('id')->toArray();
        $cancion->generos()->sync($generoIds);

        if (method_exists($cancion, 'usuarios')) {
            $propietario = $cancion->usuarios()->wherePivot('propietario', true)->first();
            $propietarioId = $propietario ? $propietario->id : null;

            if (!$propietarioId) {
                return Redirect::route('canciones.edit', $cancion->id);
            }

            $ids = array_map('intval', $request->input('userIds', []));
            if (!in_array($propietarioId, $ids)) {
                $ids[] = $propietarioId;
            }

            $usuariosParaSincronizar = [];
            foreach (array_unique($ids) as $uid) {
                $usuariosParaSincronizar[$uid] = ['propietario' => $uid === $propietarioId];
            }

            $cancion->usuarios()->sync($usuariosParaSincronizar);
        }

        return Redirect::route('canciones.show', $cancion->id);
    } catch (ModelNotFoundException $e) {
        return redirect()->route('canciones.index');
    } catch (\Exception $e) {
        Log::error("Error al actualizar canción: " . $e->getMessage());
        return Redirect::route('canciones.edit', $id);
    }
}


    public function destroy($id)
    {
        $usuario = Auth::user();

        $cancion = Cancion::findOrFail($id);
        $this->authorize('delete', $cancion);

        if (method_exists($cancion, 'usuarios')) {
            $cancion->usuarios()->detach();
        }

        $rutaAudio = $this->getRelativePath($cancion->archivo_url);
        if ($rutaAudio && Storage::disk(config('filesystems.default'))->exists($rutaAudio)) {
            Storage::disk(config('filesystems.default'))->delete($rutaAudio);
        }

        $rutaFoto = $this->getRelativePath($cancion->foto_url);
        if ($rutaFoto && Storage::disk(config('filesystems.default'))->exists($rutaFoto)) {
            Storage::disk(config('filesystems.default'))->delete($rutaFoto);
        }

        $cancion->delete();

        return redirect()->route('profile.show', $usuario->id);
    }

    public function buscarUsuarios(Request $request)
    {
        $termino = $request->query('q', '');
        $limite = 10;
        $query = User::query();

        $usuarioActualId = Auth::id();
        if ($usuarioActualId) {
            $query->where('id', '!=', $usuarioActualId);
        }

        if (!empty($termino)) {
            $query->where(function ($q) use ($termino) {
                $q->where('name', 'like', '%' . $termino . '%')
                  ->orWhere('email', 'like', '%' . $termino . '%');
            });
        } else {
            $query->orderBy('name', 'asc');
        }

        $usuarios = $query->select('id', 'name', 'email')
                          ->take($limite)
                          ->get();

        return response()->json($usuarios);
    }

    private function getRelativePath(?string $url): ?string
    {
        if (!$url) return null;
        try {
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $bucket = config('filesystems.disks.s3.bucket');
            $s3BasePath = '/' . $bucket . '/';

            if (Str::startsWith($path, $s3BasePath)) {
                $relativePath = Str::after($path, $s3BasePath);
                return ltrim($relativePath, '/');
            }
            $path = ltrim($path, '/');
            if (Str::startsWith($path, 'canciones/') || Str::startsWith($path, 'imagenes/')) {
                return $path;
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    public function cancionloopz($idCancion)
    {
        $cancion = Cancion::findOrFail($idCancion);
        $user = Auth::user();

        $playlistloopz = $user->perteneceContenedores()
            ->where('tipo', 'loopz')
            ->pluck('id');

        foreach ($playlistloopz as $contenedorId) {
            if ($cancion->contenedores()
                        ->wherePivot('contenedor_id', $contenedorId)
                        ->exists()
            ) {
                $cancion->contenedores()->detach($contenedorId);
            } else {
                $cancion->contenedores()->attach($contenedorId);
            }
        }
    }
    public function buscarCancionesOriginales(Request $request)
    {
        $termino = $request->query('q', '');
        $limite = 10;
        $query = Cancion::query();

        $query->where('canciones.licencia_id', 2);

        if (!empty($termino)) {
            $query->where('canciones.titulo', 'LIKE', '%' . $termino . '%');
        }

        $query->select('canciones.id', 'canciones.titulo', 'canciones.foto_url');


        $canciones = $query->take($limite)
                           ->get();

        return response()->json($canciones);
    }


    public function incrementarVisualizacion(Request $request, $id)
    {
        DB::table('canciones')
            ->where('id', $id)
            ->increment('visualizaciones');

        $visualizaciones = DB::table('canciones')->where('id', $id)->value('visualizaciones');

        $response = response()->json([
            'message' => 'Visualización incrementada con éxito',
            'visualizaciones' => $visualizaciones,
            'cancion_id' => $id,
        ]);

        if ($visualizaciones === 10) {
            $cancion = Cancion::with('usuarios')->find($id);

            if ($cancion && $cancion->usuarios->isNotEmpty()) {
                foreach ($cancion->usuarios as $usuario) {
                    Notificacion::create([
                        'titulo' => '¡Tu canción llegó a 100 visualizaciones!',
                        'mensaje' => "La canción '{$cancion->titulo}' ha alcanzado 10 reproducciones.",
                        'leido' => false,
                        'user_id' => $usuario->id,
                    ]);
                }
            }
        }

        return $response;
    }

}
