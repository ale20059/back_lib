<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InternalProduct;
use App\Models\InternalMovement;
use App\Models\InternalYearlySummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternalProductController extends Controller
{
    // Listar productos internos
    public function index(Request $request)
    {
        $query = InternalProduct::withCount('movements');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('category', 'like', '%' . $request->search . '%');
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('low_stock')) {
            $query->whereColumn('current_stock', '<=', 'minimum_stock');
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->active);
        }

        $products = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return response()->json($products);
    }

    // Obtener categorías únicas
    public function categories()
    {
        $categories = InternalProduct::whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return response()->json($categories);
    }

    // Crear producto interno
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'current_stock' => 'required|integer|min:0',
            'minimum_stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $product = InternalProduct::create($request->all());

            // Registrar movimiento inicial si hay stock
            if ($product->current_stock > 0) {
                InternalMovement::create([
                    'internal_product_id' => $product->id,
                    'type' => 'add',
                    'quantity' => $product->current_stock,
                    'reason' => 'Inventario inicial',
                    'year' => now()->year,
                    'user_id' => $request->user()->id,
                ]);
            }

            DB::commit();
            return response()->json($product, 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    // Ver producto
    public function show($id)
    {
        $product = InternalProduct::with(['movements' => function ($q) {
            $q->latest()->limit(20);
        }])->findOrFail($id);

        return response()->json($product);
    }

    // Actualizar producto
    public function update(Request $request, $id)
    {
        $product = InternalProduct::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'minimum_stock' => 'sometimes|integer|min:0',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $product->update($request->all());

        return response()->json($product);
    }

    // Agregar stock
    public function addStock(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
        ]);

        $product = InternalProduct::findOrFail($id);

        DB::beginTransaction();
        try {
            // Actualizar stock
            $product->current_stock += $request->quantity;
            $product->save();

            // Registrar movimiento
            $movement = InternalMovement::create([
                'internal_product_id' => $product->id,
                'type' => 'add',
                'quantity' => $request->quantity,
                'reason' => $request->reason,
                'year' => now()->year,
                'user_id' => $request->user()->id,
            ]);

            // Actualizar resumen anual
            $this->updateYearlySummary($product->id, now()->year);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock agregado exitosamente',
                'product' => $product->fresh(),
                'movement' => $movement
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Error al agregar stock: ' . $e->getMessage()], 500);
        }
    }

    // Usar stock (consumir producto)
    public function useStock(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'used_by' => 'nullable|string|max:255',
            'destination' => 'nullable|string|max:255',
        ]);

        $product = InternalProduct::findOrFail($id);

        if ($product->current_stock < $request->quantity) {
            return response()->json([
                'message' => 'Stock insuficiente. Disponible: ' . $product->current_stock
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Actualizar stock
            $product->current_stock -= $request->quantity;
            $product->save();

            // Registrar movimiento
            $movement = InternalMovement::create([
                'internal_product_id' => $product->id,
                'type' => 'use',
                'quantity' => $request->quantity,
                'reason' => $request->reason,
                'used_by' => $request->used_by,
                'destination' => $request->destination,
                'year' => now()->year,
                'user_id' => $request->user()->id,
            ]);

            // Actualizar resumen anual
            $this->updateYearlySummary($product->id, now()->year);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock usado exitosamente',
                'product' => $product->fresh(),
                'movement' => $movement
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Error al usar stock: ' . $e->getMessage()], 500);
        }
    }

    // Actualizar resumen anual
    private function updateYearlySummary($productId, $year)
    {
        $summary = InternalYearlySummary::firstOrNew([
            'internal_product_id' => $productId,
            'year' => $year
        ]);

        $product = InternalProduct::find($productId);

        // Si es nuevo, calcular stock inicial
        if (!$summary->exists) {
            // Buscar movimientos anteriores a este año
            $previousMovements = InternalMovement::where('internal_product_id', $productId)
                ->where('year', '<', $year)
                ->get();

            $totalAdd = $previousMovements->where('type', 'add')->sum('quantity');
            $totalUse = $previousMovements->where('type', 'use')->sum('quantity');
            $summary->starting_stock = $totalAdd - $totalUse;
            $summary->total_added = 0;
            $summary->total_used = 0;
        }

        // Calcular totales del año
        $movements = InternalMovement::where('internal_product_id', $productId)
            ->where('year', $year)
            ->get();

        $summary->total_added = $movements->where('type', 'add')->sum('quantity');
        $summary->total_used = $movements->where('type', 'use')->sum('quantity');
        $summary->ending_stock = $summary->starting_stock + $summary->total_added - $summary->total_used;

        $summary->save();
    }

    // Obtener resumen por año
    public function yearlySummary($id)
    {
        $product = InternalProduct::findOrFail($id);
        $summaries = $product->yearlySummaries()->orderBy('year', 'desc')->get();

        return response()->json($summaries);
    }

    // Obtener movimientos por año
    public function movementsByYear($id, $year)
    {
        $movements = InternalMovement::where('internal_product_id', $id)
            ->where('year', $year)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($movements);
    }

    // Eliminar producto
    public function destroy($id)
    {
        $product = InternalProduct::findOrFail($id);

        if ($product->movements()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el producto porque tiene movimientos asociados'
            ], 400);
        }

        $product->delete();
        return response()->json(['message' => 'Producto eliminado']);
    }
}
