<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    // Listar ventas
    public function index(Request $request)
    {
        $query = Sale::with(['user', 'items.product']);

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $sales = $query->orderBy('id', 'desc')->paginate($request->per_page ?? 15);

        return response()->json($sales);
    }

    // Crear nueva venta
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,card,transfer',
            'tax' => 'nullable|numeric|min:0',
            // === NUEVOS CAMPOS DEL PEDIDO ===
            'grado' => 'nullable|string|max:50',
            'estudiante' => 'nullable|string|max:100',
            'talla' => 'nullable|string|max:10',
            'boleta' => 'nullable|string|max:20',
            'quien_entrego' => 'nullable|string|max:100'
        ]);

        DB::beginTransaction();
        try {
            $subtotal = 0;
            $items = [];

            // Calcular subtotal y validar stock
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stock insuficiente para {$product->name}. Disponible: {$product->stock}");
                }

                $itemSubtotal = $product->selling_price * $item['quantity'];
                $subtotal += $itemSubtotal;

                $items[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->selling_price,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $tax = $request->tax ?? 0;
            $total = $subtotal + $tax;

            // Crear venta con los nuevos campos
            $sale = Sale::create([
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'payment_method' => $request->payment_method,
                // === GUARDAR NUEVOS CAMPOS ===
                'grado' => $request->grado ?? null,
                'estudiante' => $request->estudiante ?? null,
                'talla' => $request->talla ?? null,
                'boleta' => $request->boleta ?? null,
                'quien_entrego' => $request->quien_entrego ?? null
            ]);

            // Crear items y actualizar stock
            foreach ($items as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ]);

                // Actualizar stock
                $item['product']->stock -= $item['quantity'];
                $item['product']->save();

                // Registrar movimiento de inventario
                InventoryMovement::create([
                    'product_id' => $item['product']->id,
                    'type' => 'out',
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['product']->purchase_price,
                    'reason' => 'Venta #' . $sale->invoice_number,
                    'user_id' => $request->user()->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Venta registrada exitosamente',
                'sale' => $sale->load('items.product'),
                'invoice_number' => $sale->invoice_number,
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    // Mostrar una venta específica
    public function show($id)
    {
        $sale = Sale::with(['user', 'items.product.images' => function ($q) {
            $q->where('is_main', true);
        }])->findOrFail($id);

        return response()->json($sale);
    }

    // Anular venta
    public function cancel(Request $request, $id)
    {
        $sale = Sale::findOrFail($id);

        if ($sale->created_at->diffInMinutes(now()) > 60) {
            return response()->json([
                'message' => 'Solo se pueden anular ventas de la última hora'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Revertir stock
            foreach ($sale->items as $item) {
                $product = $item->product;
                $product->stock += $item->quantity;
                $product->save();

                // Registrar movimiento de reversión
                InventoryMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'unit_cost' => $product->purchase_price,
                    'reason' => 'Anulación de venta #' . $sale->invoice_number,
                    'user_id' => $request->user()->id,
                ]);
            }

            $sale->delete();

            DB::commit();
            return response()->json(['message' => 'Venta anulada exitosamente']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Error al anular venta'], 500);
        }
    }

    // Buscar venta por factura
    public function findByInvoice($invoiceNumber)
    {
        $sale = Sale::where('invoice_number', $invoiceNumber)
            ->with('items.product')
            ->first();

        if (!$sale) {
            return response()->json(['message' => 'Factura no encontrada'], 404);
        }

        return response()->json([
            'sale' => $sale,
            'can_refund' => $sale->created_at->diffInDays(now()) <= 7,
            'days_left' => 7 - $sale->created_at->diffInDays(now())
        ]);
    }

    // Devolver productos
    public function refundItems(Request $request, $saleId)
    {
        $sale = Sale::findOrFail($saleId);

        if ($sale->created_at->diffInDays(now()) > 7) {
            return response()->json([
                'message' => 'Ya pasaron más de 7 días, no se puede devolver'
            ], 400);
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.sale_item_id' => 'required|exists:sale_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'reason' => 'required|string'
        ]);

        DB::beginTransaction();
        try {
            $refundTotal = 0;

            foreach ($request->items as $refundItem) {
                $saleItem = SaleItem::findOrFail($refundItem['sale_item_id']);

                if ($saleItem->sale_id != $saleId) {
                    throw new \Exception('Item no pertenece a esta venta');
                }

                if ($refundItem['quantity'] > $saleItem->quantity) {
                    throw new \Exception("No se puede devolver más de lo vendido");
                }

                $refundAmount = ($saleItem->unit_price / $saleItem->quantity) * $refundItem['quantity'];
                $refundTotal += $refundAmount;

                $product = $saleItem->product;
                $product->stock += $refundItem['quantity'];
                $product->save();

                InventoryMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity' => $refundItem['quantity'],
                    'reason' => "Devolución factura {$sale->invoice_number} - {$request->reason}",
                    'user_id' => $request->user()->id,
                ]);

                if ($refundItem['quantity'] == $saleItem->quantity) {
                    $saleItem->delete();
                } else {
                    $saleItem->quantity -= $refundItem['quantity'];
                    $saleItem->subtotal = $saleItem->unit_price * $saleItem->quantity;
                    $saleItem->save();
                }
            }

            $newSubtotal = $sale->items()->sum('subtotal');
            $newTotal = $newSubtotal + $sale->tax;

            $sale->update([
                'subtotal' => $newSubtotal,
                'total' => $newTotal
            ]);

            $creditNote = [
                'invoice_number' => $sale->invoice_number,
                'refund_amount' => $refundTotal,
                'refund_date' => now(),
                'reason' => $request->reason
            ];

            DB::commit();

            return response()->json([
                'message' => 'Devolución procesada exitosamente',
                'refund_total' => $refundTotal,
                'credit_note' => $creditNote,
                'sale' => $sale->fresh()->load('items.product')
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    // Obtener datos de factura (MODIFICADO con los nuevos campos)
    public function getInvoiceData($id)
    {
        $sale = Sale::with(['items.product', 'user'])->findOrFail($id);

        $invoiceData = [
            'success' => true,
            'data' => [
                'invoice_number' => $sale->invoice_number,
                'date' => $sale->created_at->format('d/m/Y H:i:s'),
                'payment_method' => $sale->payment_method_formatted ?? $sale->payment_method,

                // Información de la tienda
                'store' => [
                    'name' => 'KARDEX',
                    'address' => '3C Callejón | 3-09 Zona 2, Santo Tomás Milpas Altas, Sacatepéqez, Guatemala',
                    'phone' => '+502 39477441',
                    'email' => 'kardexsistemasycontroles@gmail.com',
                    'nit' => '254563354',
                ],

                // Información del cajero
                'cashier' => [
                    'name' => $sale->user->name ?? 'Admin',
                ],

                // === NUEVOS CAMPOS DEL PEDIDO ===
                'grado' => $sale->grado ?? '---',
                'estudiante' => $sale->estudiante ?? '---',
                'talla' => $sale->talla ?? '---',
                'boleta' => $sale->boleta ?? '---',
                'quien_entrego' => $sale->quien_entrego ?? '---',

                // Lista de productos
                'items' => $sale->items->map(function ($item) {
                    return [
                        'name' => $item->product->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'subtotal' => $item->subtotal,
                        'product' => [
                            'image' => $item->product->image ?? null,
                            'barcode' => $item->product->barcode ?? null,
                            'description' => $item->product->description ?? null,
                        ]
                    ];
                }),

                // Totales
                'subtotal' => $sale->subtotal,
                'tax' => $sale->tax,
                'total' => $sale->total,
            ],
        ];

        return response()->json($invoiceData);
    }
}
