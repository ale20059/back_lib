<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryMovement::with(['product', 'user']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $movements = $query->latest()->paginate($request->per_page ?? 20);
        return response()->json($movements);
    }

    public function byProduct($productId, Request $request)
    {
        $movements = InventoryMovement::with('user')
            ->where('product_id', $productId)
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($movements);
    }
}
