<?php

namespace App\Http\Controllers;

use App\Models\{StockOpnameSession, StockOpnameItem, Product, StockMutation};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockOpnameController extends Controller
{
    public function index(Request $request)
    {
        $query = StockOpnameSession::with('creator', 'approver', 'submitter')
            ->where('branch_id', $request->user()->branch_id);

        if ($status = $request->query('status')) {
            if ($query->$request->query('status') === 'semua') {
                // No filter for 'semua'
            } else {
                $query->where('status', $status);
            }
        }
        return response()->json($query->orderByDesc('id')->paginate(25));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'notes' => 'nullable|string',
        ]);
        $code = 'SO-' . date('Ymd-His') . '-' . strtoupper(substr(uniqid(), -4));
        $session = StockOpnameSession::create([
            'branch_id' => $request->user()->branch_id,
            'created_by' => $request->user()->id,
            'status' => 'DRAFT',
            'code' => $code,
            'notes' => $data['notes'] ?? null,
        ]);
        return response()->json($session->fresh(), 201);
    }

    public function show(StockOpnameSession $session)
    {
        $session->load('items');
        return response()->json($session);
    }

    public function update(Request $request, StockOpnameSession $session)
    {
        if ($session->status !== 'DRAFT') {
            return response()->json(['message' => 'Only DRAFT can be edited'], 422);
        }
        $data = $request->validate([
            'notes' => 'nullable|string'
        ]);
        $session->update($data);
        return response()->json($session->fresh());
    }

    public function addItem(Request $request, StockOpnameSession $session)
    {
        if ($session->status !== 'DRAFT') {
            return response()->json(['message' => 'Only DRAFT can be edited'], 422);
        }
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'counted_quantity' => 'required|integer|min:0',
            'notes' => 'nullable|string'
        ]);
        $product = Product::findOrFail($data['product_id']);
        $item = StockOpnameItem::updateOrCreate([
            'session_id' => $session->id,
            'product_id' => $product->id,
        ], [
            'branch_id' => $session->branch_id,
            'product_name' => $product->name,
            'system_quantity' => $product->quantity,
            'counted_quantity' => $data['counted_quantity'],
            'difference' => $data['counted_quantity'] - $product->quantity,
            'notes' => $data['notes'] ?? null,
        ]);
        $this->recalcSession($session);
        return response()->json($item->fresh(), 201);
    }

    public function removeItem(StockOpnameSession $session, StockOpnameItem $item)
    {
        if ($session->id !== $item->session_id) {
            return response()->json(['message' => 'Item mismatch'], 400);
        }
        if ($session->status !== 'DRAFT') {
            return response()->json(['message' => 'Only DRAFT can be edited'], 422);
        }
        $item->delete();
        $this->recalcSession($session);
        return response()->json(['message' => 'Deleted']);
    }

    public function submit(Request $request, StockOpnameSession $session)
    {
        if ($session->status !== 'DRAFT') {
            return response()->json(['message' => 'Only DRAFT can be submitted'], 422);
        }
        if ($session->items()->count() === 0) {
            return response()->json(['message' => 'No items to submit'], 422);
        }
        $session->update(['status' => 'SUBMIT', 'submitted_by' => $request->user()->id, 'submitted_at' => now()]);
        return response()->json($session->fresh());
    }

    public function approve(Request $request, StockOpnameSession $session)
    {
        if ($session->status !== 'SUBMIT') {
            return response()->json(['message' => 'Only SUBMIT can be approved'], 422);
        }
        DB::transaction(function () use ($session, $request) {
            foreach ($session->items as $item) {
                if ($item->difference !== 0) {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $before = $product->quantity;
                        $product->quantity = $item->counted_quantity; // set to counted
                        $product->save();
                        // Record stock mutation
                        DB::table('stock_mutations')->insert([
                            'branch_id' => $session->branch_id,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity_change' => $item->difference,
                            'stock_before' => $before,
                            'stock_after' => $product->quantity,
                            'type' => 'stock_opname',
                            'description' => 'Stock Opname ' . $session->code,
                            'reference_type' => StockOpnameSession::class,
                            'reference_id' => $session->id,
                            'user_id' => $request->user()->id,
                            'user_name' => $request->user()->name,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
            $session->update(['status' => 'APPROVED', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        });
        return response()->json($session->fresh());
    }

    public function reject(Request $request, StockOpnameSession $session)
    {
        if ($session->status !== 'SUBMIT') {
            return response()->json(['message' => 'Only SUBMIT can be rejected'], 422);
        }
        $data = $request->validate(['admin_notes' => 'required|string']);
        $session->update(['status' => 'REJECTED', 'admin_notes' => $data['admin_notes'], 'rejected_at' => now()]);
        return response()->json($session->fresh());
    }

    public function importCsv(Request $request, StockOpnameSession $session)
    {
        if ($session->status !== 'DRAFT') {
            return response()->json(['message' => 'Only DRAFT can be edited'], 422);
        }
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        // Expect header: sku,counted_quantity
        $header = fgetcsv($handle);
        $map = array_map('strtolower', $header);
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($map, $row);
            if (!isset($data['sku']) || !isset($data['counted_quantity'])) continue;
            $product = Product::where('sku', $data['sku'])->first();
            if (!$product) continue;
            StockOpnameItem::updateOrCreate([
                'session_id' => $session->id,
                'product_id' => $product->id,
            ], [
                'branch_id' => $session->branch_id,
                'product_name' => $product->name,
                'system_quantity' => $product->quantity,
                'counted_quantity' => (int)$data['counted_quantity'],
                'difference' => (int)$data['counted_quantity'] - $product->quantity,
                'notes' => null,
            ]);
        }
        fclose($handle);
        $this->recalcSession($session);
        return response()->json(['message' => 'Imported', 'session' => $session->fresh('items')]);
    }

    public function exportCsv(StockOpnameSession $session): StreamedResponse
    {
        $filename = 'stock-opname-' . $session->code . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        $callback = function () use ($session) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['product_id', 'product_name', 'system_quantity', 'counted_quantity', 'difference', 'notes']);
            foreach ($session->items as $i) {
                fputcsv($out, [$i->product_id, $i->product_name, $i->system_quantity, $i->counted_quantity, $i->difference, $i->notes]);
            }
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
    }

    private function recalcSession(StockOpnameSession $session): void
    {
        $session->load('items');
        $totalItems = $session->items->count();
        $pos = $session->items->where('difference', '>', 0)->sum('difference');
        $neg = abs($session->items->where('difference', '<', 0)->sum('difference'));
        $session->update([
            'total_items' => $totalItems,
            'total_positive_adjustment' => $pos,
            'total_negative_adjustment' => $neg,
        ]);
    }
}
