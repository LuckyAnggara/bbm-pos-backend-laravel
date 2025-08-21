<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'search' => 'string|max:255',
            'status' => 'string|in:active,inactive,terminated',
        ]);

        $branchId = $request->branch_id;
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 20);
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Employee::query()
            ->with('branch')
            ->byBranch($branchId)
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $employees = $query->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return response()->json([
            'data' => $employees,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'position' => 'required|string|max:255',
            'employment_type' => 'required|in:full_time,part_time,contract',
            'daily_salary' => 'required|numeric|min:0',
            'monthly_salary' => 'required|numeric|min:0',
            'hire_date' => 'required|date',
            'status' => 'sometimes|in:active,inactive,terminated',
            'notes' => 'nullable|string',
        ]);

        $employee = Employee::create($request->all());
        $employee->load('branch');

        return response()->json($employee, 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        $employee->load('branch');

        return response()->json($employee);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'position' => 'sometimes|required|string|max:255',
            'employment_type' => 'sometimes|required|in:full_time,part_time,contract',
            'daily_salary' => 'sometimes|required|numeric|min:0',
            'monthly_salary' => 'sometimes|required|numeric|min:0',
            'hire_date' => 'sometimes|required|date',
            'termination_date' => 'nullable|date|after_or_equal:hire_date',
            'status' => 'sometimes|in:active,inactive,terminated',
            'notes' => 'nullable|string',
        ]);

        $employee->update($request->all());
        $employee->load('branch');

        return response()->json($employee);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return response()->json(['message' => 'Employee deleted successfully']);
    }
}
