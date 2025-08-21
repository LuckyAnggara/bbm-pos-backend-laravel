<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'payment_type' => 'nullable|in:daily,monthly',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $branchId = $request->branch_id;
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 20);
        $paymentType = $request->input('payment_type');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = Payroll::query()
            ->with(['branch', 'details.employee'])
            ->byBranch($branchId)
            ->orderBy('payment_date', 'desc');

        if ($paymentType) {
            if ($paymentType === 'all') {
                // If payment type is "all", we don't need to filter by payment type
            } else {
                $query->where('payment_type', $paymentType);
            }
        }

        if ($startDate && $endDate) {
            $query->byPeriod($startDate, $endDate);
        }

        $total = $query->count();
        $payrolls = $query->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return response()->json([
            'data' => $payrolls,
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'payment_type' => 'required|in:daily,monthly',
            'payment_date' => 'required|date',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'employees' => 'required|array|min:1',
            'employees.*.employee_id' => 'required|exists:employees,id',
            'employees.*.base_salary' => 'required|numeric|min:0',
            'employees.*.meal_allowance' => 'nullable|numeric|min:0',
            'employees.*.bonus' => 'nullable|numeric|min:0',
            'employees.*.overtime_amount' => 'nullable|numeric|min:0',
            'employees.*.loan_deduction' => 'nullable|numeric|min:0',
            'employees.*.other_deduction' => 'nullable|numeric|min:0',
            'employees.*.notes' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Calculate total amount for the payroll
            $totalPayrollAmount = 0;
            $employeeDetails = [];

            foreach ($request->employees as $empData) {
                $baseSalary = $empData['base_salary'];
                $mealAllowance = $empData['meal_allowance'] ?? 0;
                $bonus = $empData['bonus'] ?? 0;
                $overtimeAmount = $empData['overtime_amount'] ?? 0;
                $loanDeduction = $empData['loan_deduction'] ?? 0;
                $otherDeduction = $empData['other_deduction'] ?? 0;

                $totalAmount = $baseSalary + $mealAllowance + $bonus + $overtimeAmount - $loanDeduction - $otherDeduction;
                $totalPayrollAmount += $totalAmount;

                $employeeDetails[] = [
                    'employee_id' => $empData['employee_id'],
                    'base_salary' => $baseSalary,
                    'meal_allowance' => $mealAllowance,
                    'bonus' => $bonus,
                    'overtime_amount' => $overtimeAmount,
                    'loan_deduction' => $loanDeduction,
                    'other_deduction' => $otherDeduction,
                    'total_amount' => $totalAmount,
                    'notes' => $empData['notes'] ?? null,
                ];
            }

            // Create main payroll record
            $payroll = Payroll::create([
                'branch_id' => $request->branch_id,
                'title' => $request->title,
                'description' => $request->description,
                'payment_type' => $request->payment_type,
                'payment_date' => $request->payment_date,
                'period_start' => $request->period_start,
                'period_end' => $request->period_end,
                'total_amount' => $totalPayrollAmount,
                'notes' => $request->notes,
                'status' => 'pending',
            ]);

            // Create payroll details
            foreach ($employeeDetails as $detail) {
                PayrollDetail::create([
                    'payroll_id' => $payroll->id,
                    ...$detail,
                ]);
            }

            // Update loan deductions
            foreach ($request->employees as $empData) {
                if (isset($empData['loan_deduction']) && $empData['loan_deduction'] > 0) {
                    $employee = Employee::find($empData['employee_id']);
                    $activeLoan = $employee->activeLoan();

                    if ($activeLoan) {
                        $activeLoan->remaining_amount -= $empData['loan_deduction'];
                        if ($activeLoan->remaining_amount <= 0) {
                            $activeLoan->status = 'paid_off';
                            $activeLoan->remaining_amount = 0;
                        }
                        $activeLoan->save();
                    }
                }
            }

            DB::commit();

            $payroll->load(['branch', 'details.employee']);

            return response()->json([
                'message' => 'Payroll created successfully',
                'data' => $payroll,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create payroll',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        $payroll = Payroll::with(['branch', 'details.employee'])
            ->findOrFail($id);

        return response()->json([
            'data' => $payroll,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $payroll = Payroll::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'payment_date' => 'sometimes|date',
            'period_start' => 'sometimes|date',
            'period_end' => 'sometimes|date|after_or_equal:period_start',
            'notes' => 'nullable|string',
            'status' => 'sometimes|in:pending,paid,cancelled',
        ]);

        $payroll->update($request->only([
            'title',
            'description',
            'payment_date',
            'period_start',
            'period_end',
            'notes',
            'status',
        ]));

        $payroll->load(['branch', 'details.employee']);

        return response()->json([
            'message' => 'Payroll updated successfully',
            'data' => $payroll,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $payroll = Payroll::findOrFail($id);

        if ($payroll->status === 'paid') {
            return response()->json([
                'message' => 'Cannot delete paid payroll',
            ], 422);
        }

        $payroll->delete();

        return response()->json([
            'message' => 'Payroll deleted successfully',
        ]);
    }

    public function getEmployeesForPayroll(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        $employees = Employee::with(['loans' => function ($query) {
            $query->where('status', 'active');
        }])
            ->byBranch($request->branch_id)
            ->active()
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'employee_code' => $employee->employee_code,
                    'position' => $employee->position,
                    'employment_type' => $employee->employment_type,
                    'daily_salary' => $employee->daily_salary,
                    'monthly_salary' => $employee->monthly_salary,
                    'daily_meal_allowance' => $employee->daily_meal_allowance,
                    'monthly_meal_allowance' => $employee->monthly_meal_allowance,
                    'bonus' => $employee->bonus,
                    'active_loan' => $employee->activeLoan(),
                    'total_savings' => $employee->totalSavings(),
                ];
            });

        return response()->json([
            'data' => $employees,
        ]);
    }

    public function getEmployeePayslip(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $employeeId = $request->employee_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $employee = Employee::with(['loans', 'savings'])
            ->findOrFail($employeeId);

        $payrollDetails = PayrollDetail::with(['payroll'])
            ->where('employee_id', $employeeId)
            ->whereHas('payroll', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('payment_date', [$startDate, $endDate]);
            })
            ->get();

        $totalGaji = $payrollDetails->sum('base_salary');
        $totalUangMakan = $payrollDetails->sum('meal_allowance');
        $totalBonus = $payrollDetails->sum('bonus');
        $totalPotongan = $payrollDetails->sum('loan_deduction') + $payrollDetails->sum('other_deduction');

        $activeLoan = $employee->activeLoan();
        $totalPinjaman = $activeLoan ? $activeLoan->amount : 0;
        $sisaPinjaman = $activeLoan ? $activeLoan->remaining_amount : 0;

        $totalTabungan = $employee->totalSavings();

        return response()->json([
            'employee' => $employee,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'payroll_details' => $payrollDetails,
            'summary' => [
                'total_gaji' => $totalGaji,
                'total_uang_makan' => $totalUangMakan,
                'total_bonus' => $totalBonus,
                'total_potongan' => $totalPotongan,
                'total_pinjaman' => $totalPinjaman,
                'sisa_pinjaman' => $sisaPinjaman,
                'total_tabungan' => $totalTabungan,
                'total_diterima' => $totalGaji + $totalUangMakan + $totalBonus - $totalPotongan,
            ],
        ]);
    }

    /**
     * Get employee payslip for specific payroll
     */
    public function getPayslip($payrollId, $employeeId): JsonResponse
    {
        $payroll = Payroll::with('branch')->findOrFail($payrollId);
        $employee = Employee::findOrFail($employeeId);

        $payrollDetail = PayrollDetail::where('payroll_id', $payrollId)
            ->where('employee_id', $employeeId)
            ->firstOrFail();

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_code' => $employee->employee_code,
                'position' => $employee->position,
                'department' => $employee->department,
                'email' => $employee->email,
                'phone' => $employee->phone,
            ],
            'payroll' => [
                'id' => $payroll->id,
                'title' => $payroll->title,
                'payment_type' => $payroll->payment_type,
                'payment_date' => $payroll->payment_date,
                'period_start' => $payroll->period_start,
                'period_end' => $payroll->period_end,
                'notes' => $payroll->notes,
            ],
            'payroll_detail' => [
                'base_salary' => $payrollDetail->base_salary,
                'meal_allowance' => $payrollDetail->meal_allowance,
                'bonus' => $payrollDetail->bonus,
                'overtime_amount' => $payrollDetail->overtime_amount,
                'loan_deduction' => $payrollDetail->loan_deduction,
                'other_deduction' => $payrollDetail->other_deduction,
                'total_amount' => $payrollDetail->total_amount,
                'notes' => $payrollDetail->notes,
            ],
            'branch' => [
                'name' => $payroll->branch->name,
                'address' => $payroll->branch->address,
                'phone' => $payroll->branch->phone,
                'email' => $payroll->branch->email,
            ],
        ]);
    }
}
