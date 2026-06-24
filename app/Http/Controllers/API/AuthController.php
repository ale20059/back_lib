<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{

    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6', // Quitamos 'confirmed' si no quieres enviar password_confirmation
            'phone'    => 'nullable|string',
            'position' => 'nullable|string',
            'image'    => 'nullable|image|max:2048',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'phone'    => $request->phone,
            'position' => $request->position ?? 'Cajero',
            'is_active' => true,
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            // Guardamos en la carpeta storage/app/public/users/{id}
            $path = $file->store("users/{$user->id}", 'public');

            $user->images()->create([
                'url'       => Storage::url($path),
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'is_main'   => true,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'user'    => $user->load('images'),
            'token'   => $token,
        ], 201);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'     => 'sometimes|string|max:255',
            'phone'    => 'nullable|string',
            'position' => 'nullable|string',
            'image'    => 'nullable|image|max:2048',
        ]);

        $user->update($request->only(['name', 'phone', 'position']));

        if ($request->hasFile('image')) {
            // 1. Buscar imagen principal anterior
            $oldImage = $user->images()->where('is_main', true)->first();

            if ($oldImage) {
                // 2. Extraer el path relativo para borrarlo correctamente
                // Si la URL es /storage/users/1/foto.jpg, el path es users/1/foto.jpg
                $relativePath = str_replace('/storage/', '', $oldImage->url);
                Storage::disk('public')->delete($relativePath);
                $oldImage->delete();
            }

            // 3. Guardar la nueva
            $file = $request->file('image');
            $path = $file->store("users/{$user->id}", 'public');

            $user->images()->create([
                'url'       => Storage::url($path),
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'is_main'   => true,
            ]);
        }

        return response()->json([
            'message' => 'Perfil actualizado',
            'user'    => $user->load('images'),
        ]);
    }


    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales incorrectas.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Tu cuenta está desactivada.',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => $user->load('images'),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    // Actualizar perfil con imagen


    // Subir imagen específica para usuario
    public function uploadUserImage(Request $request, $id)
    {
        $request->validate([
            'image' => 'required|image|max:2048',
            'is_main' => 'boolean',
        ]);

        $user = User::findOrFail($id);

        $file = $request->file('image');
        $path = $file->store("users/{$user->id}", 'public');

        $isMain = $request->is_main ?? false;

        // Si es imagen principal, quitar main de otras
        if ($isMain) {
            $user->images()->update(['is_main' => false]);
        }

        $image = $user->images()->create([
            'url' => Storage::url($path),
            'thumbnail_url' => null,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'is_main' => $isMain,
        ]);

        return response()->json([
            'message' => 'Imagen subida exitosamente',
            'image' => $image,
        ], 201);
    }

    // Obtener usuario actual
    public function me(Request $request)
    {
        return response()->json($request->user()->load('images'));
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    public function index(Request $request)
    {
        // Verificar que el usuario autenticado sea Administrador
        if ($request->user()->position !== 'Administrador') {
            return response()->json([
                'message' => 'No autorizado. Solo administradores pueden ver la lista de usuarios.'
            ], 403);
        }

        $users = User::with('images')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Obtener un usuario específico (SOLO ADMIN)
     */
    public function show(Request $request, $id)
    {
        if ($request->user()->position !== 'Administrador') {
            return response()->json([
                'message' => 'No autorizado.'
            ], 403);
        }

        $user = User::with('images')->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Actualizar un usuario (SOLO ADMIN)
     */
    public function update(Request $request, $id)
    {
        if ($request->user()->position !== 'Administrador') {
            return response()->json([
                'message' => 'No autorizado.'
            ], 403);
        }

        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string',
            'position' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = $request->only(['name', 'email', 'phone', 'position']);

        // Si se envía password, actualizarla
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Si se envía is_active
        if ($request->has('is_active')) {
            $data['is_active'] = $request->is_active;
        }

        $user->update($data);

        // Manejar imagen
        if ($request->hasFile('image')) {
            $oldImage = $user->images()->where('is_main', true)->first();
            if ($oldImage) {
                $relativePath = str_replace('/storage/', '', $oldImage->url);
                Storage::disk('public')->delete($relativePath);
                $oldImage->delete();
            }

            $file = $request->file('image');
            $path = $file->store("users/{$user->id}", 'public');

            $user->images()->create([
                'url' => Storage::url($path),
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'is_main' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente',
            'data' => $user->load('images')
        ]);
    }

    /**
     * Eliminar un usuario (SOLO ADMIN)
     */
    public function destroy(Request $request, $id)
    {
        if ($request->user()->position !== 'Administrador') {
            return response()->json([
                'message' => 'No autorizado.'
            ], 403);
        }

        // No permitir eliminar a sí mismo
        if ($request->user()->id == $id) {
            return response()->json([
                'message' => 'No puedes eliminar tu propia cuenta.'
            ], 400);
        }

        $user = User::findOrFail($id);

        // Eliminar imágenes asociadas
        foreach ($user->images as $image) {
            $relativePath = str_replace('/storage/', '', $image->url);
            Storage::disk('public')->delete($relativePath);
            $image->delete();
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }

    /**
     * Activar/Desactivar un usuario (SOLO ADMIN)
     */
    public function toggleActive(Request $request, $id)
    {
        if ($request->user()->position !== 'Administrador') {
            return response()->json([
                'message' => 'No autorizado.'
            ], 403);
        }

        $user = User::findOrFail($id);

        // No permitir desactivar a sí mismo
        if ($request->user()->id == $id) {
            return response()->json([
                'message' => 'No puedes desactivar tu propia cuenta.'
            ], 400);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'Usuario activado' : 'Usuario desactivado',
            'data' => $user
        ]);
    }
}
