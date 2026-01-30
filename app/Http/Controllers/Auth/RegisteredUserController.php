<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Contenedor;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'foto_perfil' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'banner_perfil' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ]);

        $rutaFotoPerfil = null;
        if ($request->hasFile('foto_perfil')) {
            $archivo = $request->file('foto_perfil');
            $nombre = Str::uuid() . '_perfil.' . $archivo->getClientOriginalExtension();
            $key = Storage::disk(config('filesystems.default'))->putFileAs('perfiles/fotos', $archivo, $nombre, 'public');
            // Guardar solo la ruta relativa, el frontend construirá la URL completa
            if (config('filesystems.default') === 'public') {
                $rutaFotoPerfil = '/storage/' . $key;
            } else {
                $rutaFotoPerfil = Storage::disk(config('filesystems.default'))->url($key);
            }
        }

        $rutaBannerPerfil = null;
        if ($request->hasFile('banner_perfil')) {
            $archivo = $request->file('banner_perfil');
            $nombre = Str::uuid() . '_banner.' . $archivo->getClientOriginalExtension();
            $key = Storage::disk(config('filesystems.default'))->putFileAs('perfiles/banners', $archivo, $nombre, 'public');
            // Guardar solo la ruta relativa, el frontend construirá la URL completa
            if (config('filesystems.default') === 'public') {
                $rutaBannerPerfil = '/storage/' . $key;
            } else {
                $rutaBannerPerfil = Storage::disk(config('filesystems.default'))->url($key);
            }
        }

        // Crear el usuario directamente sin verificación por código
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'foto_perfil' => $rutaFotoPerfil,
            'banner_perfil' => $rutaBannerPerfil,
            'email_verified_at' => now(),
        ]);

        // Crear el contenedor LoopZs para el usuario
        $playlist = Contenedor::create([
            'user_id' => $user->id,
            'nombre' => 'LoopZs',
            'descripcion' => '',
            'tipo' => 'loopz',
        ]);

        $playlist->usuarios()->attach($user->id, ['propietario' => true]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('welcome', absolute: false));
    }
}
