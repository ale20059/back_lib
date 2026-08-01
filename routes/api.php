<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\SupplierController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\BarcodeScannerController;
use App\Http\Controllers\API\ImageController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\InternalProductController;
use App\Http\Controllers\API\InventoryMovementController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\UserController;

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas (requieren token)
Route::middleware('auth:sanctum')->group(function () {

    // ===== AUTENTICACIÓN =====
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    // ===== GESTIÓN DE USUARIOS (SOLO ADMIN) =====
    Route::get('/users', [AuthController::class, 'index']);
    Route::get('/users/{id}', [AuthController::class, 'show']);
    Route::put('/users/{id}', [AuthController::class, 'update']);
    Route::delete('/users/{id}', [AuthController::class, 'destroy']);
    Route::put('/users/{id}/toggle-active', [AuthController::class, 'toggleActive']);

    // ===== DASHBOARD =====
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/sales-report', [DashboardController::class, 'salesReport']);

    // ===== PRODUCTOS =====
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{id}/update-stock', [ProductController::class, 'updateStock']);
    Route::get('/products/trashed', [ProductController::class, 'trashed']);
    Route::post('/products/{id}/restore', [ProductController::class, 'restore']);
    Route::delete('/products/{id}/force-delete', [ProductController::class, 'forceDelete']);

    // ===== PROVEEDORES =====
    Route::apiResource('suppliers', SupplierController::class);

    // ===== CATEGORÍAS =====
    Route::apiResource('categories', CategoryController::class);

    // ===== VENTAS =====
    Route::apiResource('sales', SaleController::class);
    Route::post('/sales/{id}/cancel', [SaleController::class, 'cancel']);
    Route::get('/sales/{id}/invoice-data', [SaleController::class, 'getInvoiceData']);
    Route::get('/sales/by-invoice/{invoiceNumber}', [SaleController::class, 'findByInvoice']);
    Route::post('/sales/{saleId}/refund', [SaleController::class, 'refundItems']);

    // ===== ESCÁNER DE CÓDIGO DE BARRAS =====
    Route::post('/barcode/scan', [BarcodeScannerController::class, 'scan']);
    Route::post('/barcode/scan-multiple', [BarcodeScannerController::class, 'scanMultiple']);
    Route::get('/barcode/search', [BarcodeScannerController::class, 'searchByPartialBarcode']);

    // ===== IMÁGENES =====
    Route::post('/images/upload', [ImageController::class, 'upload']);
    Route::put('/images/{id}/set-main', [ImageController::class, 'setMain']);
    Route::delete('/images/{id}', [ImageController::class, 'destroy']);
    Route::post('/images/reorder', [ImageController::class, 'reorder']);

    // ===== INVENTARIO =====
    Route::get('/inventory-movements', [InventoryMovementController::class, 'index']);
    Route::get('/inventory-movements/product/{productId}', [InventoryMovementController::class, 'byProduct']);


    // ===== PRODUCTOS INTERNOS =====
    Route::prefix('internal')->group(function () {
        Route::get('/products', [InternalProductController::class, 'index']);
        Route::get('/products/categories', [InternalProductController::class, 'categories']);
        Route::post('/products', [InternalProductController::class, 'store']);
        Route::get('/products/{id}', [InternalProductController::class, 'show']);
        Route::put('/products/{id}', [InternalProductController::class, 'update']);
        Route::delete('/products/{id}', [InternalProductController::class, 'destroy']);

        Route::post('/products/{id}/add-stock', [InternalProductController::class, 'addStock']);
        Route::post('/products/{id}/use-stock', [InternalProductController::class, 'useStock']);
        Route::get('/products/{id}/yearly-summary', [InternalProductController::class, 'yearlySummary']);
        Route::get('/products/{id}/movements/{year}', [InternalProductController::class, 'movementsByYear']);
    });

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::get('/users', [UserController::class, 'index']);
});
