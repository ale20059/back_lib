<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            // 💡 Traemos la orden con sus items, la venta y los productos de la venta
            $query = Order::with([
                'creator',
                'assignedUser',
                'items.sale.items', // Carga los ítems de la venta
                'items.internalProduct'
            ]);

            if ($request->has('status') && $request->status != '') {
                $query->where('status', $request->status);
            }

            return response()->json($query->latest()->get());
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener órdenes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'assigned_to_user_id' => 'required|exists:users,id',
            'destination' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.sale_id' => 'nullable',
            'items.*.internal_product_id' => 'nullable',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $lastOrder = Order::latest('id')->first();
            $nextNumber = $lastOrder ? ($lastOrder->id + 1) : 1;
            $orderNumber = 'ORD-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            $order = Order::create([
                'order_number' => $orderNumber,
                'created_by_user_id' => Auth::id() ?? 1,
                'assigned_to_user_id' => $request->assigned_to_user_id,
                'destination' => $request->destination,
                'notes' => $request->notes,
                'status' => 'pending',
            ]);

            foreach ($request->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'sale_id' => $item['sale_id'] ?? null,
                    'internal_product_id' => $item['internal_product_id'] ?? null,
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Orden creada con éxito',
                'order' => $order
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la orden: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,in_progress,completed,cancelled'
        ]);

        $order = Order::findOrFail($id);
        $order->status = $request->status;
        $order->save();

        return response()->json([
            'message' => 'Estado actualizado correctamente',
            'order' => $order
        ]);
    }
}
