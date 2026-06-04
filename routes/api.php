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
use App\Http\Controllers\API\InventoryMovementController;
use Illuminate\Support\Facades\Storage;

// Rutas públicas

// Ruta de prueba para verificar storage

Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas (requieren token)
Route::middleware('auth:sanctum')->group(function () {


    Route::post('/register', [AuthController::class, 'register']);
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/sales-report', [DashboardController::class, 'salesReport']);

    // Productos
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{id}/update-stock', [ProductController::class, 'updateStock']);

    // Proveedores
    Route::apiResource('suppliers', SupplierController::class);

    // Categorías
    Route::apiResource('categories', CategoryController::class);

    // Ventas
    Route::apiResource('sales', SaleController::class);
    Route::post('/sales/{id}/cancel', [SaleController::class, 'cancel']);
    Route::get('/sales/{id}/invoice-data', [SaleController::class, 'getInvoiceData']);

    // Escáner de código de barras
    Route::post('/barcode/scan', [BarcodeScannerController::class, 'scan']);
    Route::post('/barcode/scan-multiple', [BarcodeScannerController::class, 'scanMultiple']);
    Route::get('/barcode/search', [BarcodeScannerController::class, 'searchByPartialBarcode']);

    // Imágenes
    Route::post('/images/upload', [ImageController::class, 'upload']);
    Route::put('/images/{id}/set-main', [ImageController::class, 'setMain']);
    Route::delete('/images/{id}', [ImageController::class, 'destroy']);
    Route::post('/images/reorder', [ImageController::class, 'reorder']);

    // Inventario
    Route::get('/inventory-movements', [InventoryMovementController::class, 'index']);
    Route::get('/inventory-movements/product/{productId}', [InventoryMovementController::class, 'byProduct']);
    // En routes/api.php
    Route::get('/sales/by-invoice/{invoiceNumber}', [SaleController::class, 'findByInvoice']);
    Route::post('/sales/{saleId}/refund', [SaleController::class, 'refundItems']);
});
