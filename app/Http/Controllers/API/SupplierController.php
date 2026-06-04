<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::with('images');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('company_name', 'like', '%' . $request->search . '%');
        }

        $suppliers = $query->orderBy('name')->paginate($request->per_page ?? 15);
        return response()->json($suppliers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $supplier = Supplier::create($request->all());
        return response()->json($supplier, 201);
    }

    public function show($id)
    {
        $supplier = Supplier::with(['products', 'images'])->findOrFail($id);
        return response()->json($supplier);
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update($request->all());
        return response()->json($supplier);
    }

    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);

        if ($supplier->products()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el proveedor porque tiene productos asociados'
            ], 400);
        }

        $supplier->delete();
        return response()->json(['message' => 'Proveedor eliminado']);
    }
}
