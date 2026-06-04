<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class BarcodeScannerController extends Controller
{
    // Buscar por código de barras (para escáner o cámara)
    public function scan(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
        ]);

        $product = Product::with(['supplier', 'category', 'images' => function ($q) {
            $q->where('is_main', true);
        }])->where('barcode', $request->barcode)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            return response()->json([
                'found' => false,
                'message' => 'Producto no encontrado con este código de barras',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'product' => $product,
            'stock_status' => $product->stock > 0 ? 'available' : 'out_of_stock',
            'alert' => $product->is_low_stock ? 'Stock bajo' : null,
        ]);
    }

    // Búsqueda múltiple (para ventas rápidas)
    public function scanMultiple(Request $request)
    {
        $request->validate([
            'barcodes' => 'required|array',
            'barcodes.*' => 'string',
        ]);

        $products = [];
        $notFound = [];

        foreach ($request->barcodes as $barcode) {
            $product = Product::where('barcode', $barcode)
                ->where('is_active', true)
                ->first();

            if ($product) {
                $products[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'price' => $product->selling_price,
                    'stock' => $product->stock,
                ];
            } else {
                $notFound[] = $barcode;
            }
        }

        return response()->json([
            'found_products' => $products,
            'not_found_barcodes' => $notFound,
            'total_found' => count($products),
        ]);
    }

    // Sugerir producto por código parcial
    public function searchByPartialBarcode(Request $request)
    {
        $request->validate([
            'partial' => 'required|string|min:2',
        ]);

        $products = Product::where('barcode', 'like', '%' . $request->partial . '%')
            ->orWhere('name', 'like', '%' . $request->partial . '%')
            ->limit(10)
            ->get(['id', 'name', 'barcode', 'selling_price', 'stock']);

        return response()->json($products);
    }
}
