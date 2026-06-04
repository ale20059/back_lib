<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    // Subir imagen para cualquier modelo (polimórfico)
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:2048', // max 2MB
            'model_type' => 'required|string|in:Product,User,Supplier',
            'model_id' => 'required|integer',
            'is_main' => 'boolean',
        ]);

        $modelClass = "App\\Models\\{$request->model_type}";
        $model = $modelClass::findOrFail($request->model_id);

        $file = $request->file('image');
        $path = $file->store("images/{$request->model_type}/{$request->model_id}", 'public');

        // Crear miniatura (opcional, requerir Intervention Image)
        // $thumbnail = $this->createThumbnail($file, $path);

        $image = $model->images()->create([
            'url' => Storage::url($path),
            'thumbnail_url' => null, // Storage::url($thumbnail),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'is_main' => $request->is_main ?? false,
        ]);

        // Si es imagen principal, quitar main de otras
        if ($image->is_main) {
            $model->images()->where('id', '!=', $image->id)->update(['is_main' => false]);
        }

        return response()->json($image, 201);
    }

    // Cambiar imagen principal
    public function setMain($id)
    {
        $image = Image::findOrFail($id);

        // Quitar main de otras imágenes del mismo modelo
        $image->imageable->images()->update(['is_main' => false]);

        // Establecer esta como principal
        $image->is_main = true;
        $image->save();

        return response()->json(['message' => 'Imagen principal actualizada']);
    }

    // Eliminar imagen
    public function destroy($id)
    {
        $image = Image::findOrFail($id);

        // Eliminar archivo físico
        $path = str_replace('/storage', 'public', $image->url);
        Storage::delete($path);

        if ($image->thumbnail_url) {
            $thumbnailPath = str_replace('/storage', 'public', $image->thumbnail_url);
            Storage::delete($thumbnailPath);
        }

        $image->delete();

        return response()->json(['message' => 'Imagen eliminada']);
    }

    // Reordenar imágenes
    public function reorder(Request $request)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*.id' => 'required|exists:images,id',
            'images.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->images as $item) {
            Image::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return response()->json(['message' => 'Orden actualizado']);
    }
}
