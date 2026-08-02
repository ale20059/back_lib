<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    // Listar productos con filtros y fotos (incluye eliminados si se solicita)
    public function index(Request $request)
    {
        $query = Product::with(['supplier', 'category', 'images' => function ($q) {
            $q->where('is_main', true);
        }]);

        // Si se solicita ver eliminados
        if ($request->has('trashed') && $request->trashed == 'true') {
            $query->onlyTrashed();
        }

        // Filtros de búsqueda
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('barcode', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('low_stock')) {
            $query->where('stock', '<=', 5);
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->active);
        }

        $products = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return response()->json($products);
    }

    // Obtener solo productos eliminados (Soft Delete)
    public function trashed(Request $request)
    {
        $products = Product::onlyTrashed()
            ->with(['supplier', 'category', 'images' => function ($q) {
                $q->where('is_main', true);
            }])
            ->orderBy('deleted_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($products);
    }

    // Crear producto con Imagen
    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'barcode'        => 'nullable|string|unique:products',
            'supplier_id'    => 'required|exists:suppliers,id',
            'category_id'    => 'nullable|exists:categories,id',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price'  => 'required|numeric|min:0',
            'stock'          => 'required|integer|min:0',
            'location'       => 'nullable|string',
            'description'    => 'nullable|string',
            'image'          => 'nullable|image|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $product = Product::create($request->all());

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $path = $file->store("products/{$product->id}", 'public');

                $product->images()->create([
                    'url'       => Storage::url($path),
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'is_main'   => true,
                ]);
            }

            if ($product->stock > 0) {
                InventoryMovement::create([
                    'product_id' => $product->id,
                    'type'       => 'in',
                    'quantity'   => $product->stock,
                    'unit_cost'  => $product->purchase_price,
                    'reason'     => 'Inventario inicial',
                    'user_id'    => $request->user()->id,
                ]);
            }

            DB::commit();
            return response()->json($product->load('supplier', 'category', 'images'), 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    // Ver un producto a detalle
    public function show($id)
    {
        $product = Product::with(['supplier', 'category', 'images', 'inventoryMovements' => function ($q) {
            $q->latest()->limit(10);
        }])->withTrashed()->findOrFail($id);

        return response()->json($product);
    }

    // Actualizar producto e imagen
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name'           => 'sometimes|string|max:255',
            'barcode'        => 'sometimes|string|unique:products,barcode,' . $id,
            'supplier_id'    => 'sometimes|exists:suppliers,id',
            'category_id'    => 'nullable|exists:categories,id',
            'purchase_price' => 'sometimes|numeric|min:0',
            'selling_price'  => 'sometimes|numeric|min:0',
            'image'          => 'nullable|image|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $product->update($request->all());

            if ($request->hasFile('image')) {
                $oldImage = $product->images()->where('is_main', true)->first();
                if ($oldImage) {
                    $oldPath = str_replace('/storage/', '', $oldImage->url);
                    Storage::disk('public')->delete($oldPath);
                    $oldImage->delete();
                }

                $file = $request->file('image');
                $path = $file->store("products/{$product->id}", 'public');

                $product->images()->create([
                    'url'       => Storage::url($path),
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'is_main'   => true,
                ]);
            }

            DB::commit();
            return response()->json($product->load('images'));
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Error al actualizar'], 500);
        }
    }

    public function updateStock(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|not_in:0',
            'reason'   => 'required|string',
            'type'     => 'required|in:in,out',
        ]);

        $product = Product::findOrFail($id);
        $newStock = $request->type === 'in'
            ? $product->stock + $request->quantity
            : $product->stock - $request->quantity;

        if ($newStock < 0) {
            return response()->json(['message' => 'Stock insuficiente'], 400);
        }

        DB::beginTransaction();
        try {
            $product->stock = $newStock;
            $product->save();

            InventoryMovement::create([
                'product_id' => $product->id,
                'type'       => $request->type,
                'quantity'   => $request->quantity,
                'unit_cost'  => $product->purchase_price,
                'reason'     => $request->reason,
                'user_id'    => $request->user()->id,
            ]);

            DB::commit();
            return response()->json([
                'message'   => 'Stock actualizado',
                'product'   => $product,
                'new_stock' => $newStock,
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Error al actualizar stock'], 500);
        }
    }

    // ELIMINAR PRODUCTO (Soft Delete)
    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        try {
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar producto: ' . $e->getMessage()
            ], 500);
        }
    }

    // RESTAURAR PRODUCTO ELIMINADO
    public function restore($id)
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $product->restore();

        return response()->json([
            'success' => true,
            'message' => 'Producto restaurado exitosamente'
        ]);
    }

    // ELIMINAR PERMANENTEMENTE (FORCE DELETE)
    public function forceDelete($id)
    {
        $product = Product::onlyTrashed()->findOrFail($id);

        DB::beginTransaction();
        try {
            $product->saleItems()->delete();
            $product->inventoryMovements()->delete();
            $product->details()->delete();

            foreach ($product->images as $image) {
                $relativePath = str_replace('/storage/', '', $image->url);
                Storage::disk('public')->delete($relativePath);
                $image->delete();
            }

            $product->forceDelete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado permanentemente'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar permanentemente: ' . $e->getMessage()
            ], 500);
        }
    }
}
