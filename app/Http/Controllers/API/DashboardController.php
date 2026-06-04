<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->toDateString();

        // Ventas del día
        $todaySales = Sale::whereDate('created_at', $today)->sum('total');
        $todaySalesCount = Sale::whereDate('created_at', $today)->count();

        // Productos con bajo stock
        $lowStockProducts = Product::where('stock', '<=', 5)
            ->where('is_active', true)
            ->count();

        // Productos agotados
        $outOfStock = Product::where('stock', 0)
            ->where('is_active', true)
            ->count();

        // Total de productos
        $totalProducts = Product::where('is_active', true)->count();

        // Ventas del mes
        $monthSales = Sale::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total');

        // Productos más vendidos
        $topProducts = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->select('products.id', 'products.name', DB::raw('SUM(sale_items.quantity) as total_sold'))
            ->whereMonth('sale_items.created_at', now()->month)
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get();

        // Últimas ventas
        $recentSales = Sale::with('user')
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'today_sales' => $todaySales,
            'today_sales_count' => $todaySalesCount,
            'month_sales' => $monthSales,
            'low_stock_products' => $lowStockProducts,
            'out_of_stock' => $outOfStock,
            'total_products' => $totalProducts,
            'top_products' => $topProducts,
            'recent_sales' => $recentSales,
        ]);
    }

    // Reporte de ventas por período
    public function salesReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $sales = Sale::whereBetween('created_at', [$request->start_date, $request->end_date])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(total) as total_amount'),
                DB::raw('SUM(tax) as total_tax')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $summary = [
            'total_sales' => $sales->sum('total_sales'),
            'total_amount' => $sales->sum('total_amount'),
            'total_tax' => $sales->sum('total_tax'),
            'average_sale' => $sales->sum('total_amount') / ($sales->sum('total_sales') ?: 1),
        ];

        return response()->json([
            'daily_data' => $sales,
            'summary' => $summary,
        ]);
    }
}
