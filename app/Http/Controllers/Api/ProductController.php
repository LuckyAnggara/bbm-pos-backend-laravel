<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource with pagination, search, and filtering.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', 15);
            $search = $request->input('search');
            $categoryId = $request->input('category_id');
            $query = Product::with(['category', 'branch']);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            $products = $query->latest()->paginate($limit);

            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error fetching products: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'quantity' => 'required|integer|min:0',
            'cost_price' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'branch_id' => 'required|exists:branches,id',
            'image_url' => 'nullable|string|max:255',
            'image_hint' => 'nullable|string|max:255',
        ]);

        try {
            $product = DB::transaction(function () use ($validated) {
                // Ambil nama kategori untuk konsistensi data
                $category = Category::find($validated['category_id']);
                $validated['category_name'] = $category->name;

                return Product::create($validated);
            });
            return response()->json($product, 201);
        } catch (\Exception $e) {
            Log::error('Error creating product: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create product. Please try again.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return response()->json($product->load(['category', 'branch']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sku' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products')->ignore($product->id)],
            'quantity' => 'sometimes|required|integer|min:0',
            'cost_price' => 'sometimes|required|numeric|min:0',
            'price' => 'sometimes|required|numeric|min:0',
            'category_id' => 'sometimes|required|exists:categories,id',
            'branch_id' => 'sometimes|required|exists:branches,id',
            'image_url' => 'nullable|string|max:255',
            'image_hint' => 'nullable|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($product, $validated, $request) {
                // Jika kategori diubah, update juga category_name
                if ($request->has('category_id')) {
                    $category = Category::find($validated['category_id']);
                    $validated['category_name'] = $category->name;
                }
                $product->update($validated);
            });
            return response()->json($product);
        } catch (\Exception $e) {
            Log::error("Error updating product {$product->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update product. Please try again.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        try {
            // Kita bisa tambahkan pengecekan di sini, misal:
            // "Tidak bisa hapus produk jika pernah ada transaksi"
            // Namun untuk saat ini, kita langsung hapus.
            $product->delete();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error("Error deleting product {$product->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete product. Please try again.'], 500);
        }
    }
}
