<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource with pagination and search.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $search = $request->input('search');

            $query = Category::with('branch');

            if ($search) {
                $query->where('name', 'like', "%{$search}%");
            }

            $categories = $query->latest()->paginate($limit);

            return response()->json($categories);
        } catch (\Exception $e) {
            Log::error('Error fetching categories: '.$e->getMessage());

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'branch_id' => 'nullable|exists:branches,id',
            'description' => 'nullable|string',
        ]);

        try {
            $category = DB::transaction(function () use ($validated) {
                return Category::create($validated);
            });

            return response()->json($category, 201);
        } catch (\Exception $e) {
            Log::error('Error creating category: '.$e->getMessage());

            return response()->json(['message' => 'Failed to create category. Please try again.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        // Load relasi products untuk melihat item apa saja di kategori ini
        return response()->json($category->load(['branch', 'products']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories')->ignore($category->id)],
            'branch_id' => 'nullable|exists:branches,id',
            'description' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($category, $validated) {
                $category->update($validated);
            });

            return response()->json($category);
        } catch (\Exception $e) {
            Log::error("Error updating category {$category->id}: ".$e->getMessage());

            return response()->json(['message' => 'Failed to update category. Please try again.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        try {
            DB::transaction(function () use ($category) {
                $category->delete();
            });

            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error("Error deleting category {$category->id}: ".$e->getMessage());

            return response()->json(['message' => 'Failed to delete category. Please try again.'], 500);
        }
    }
}
