<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\StockOpnameSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send notification when stock opname is approved
     */
    public function sendStockOpnameApprovedNotification(StockOpnameSession $session, User $admin)
    {
        try {
            // Load session with creator relationship
            $session->load('creator', 'branch');

            $title = 'Stock Opname Disetujui';
            $message = "Stock Opname {$session->code} telah disetujui oleh {$admin->name}. ".
                "Total penyesuaian: +{$session->total_positive_adjustment}, -{$session->total_negative_adjustment} item. ".
                'Stok produk telah diperbarui secara otomatis.';

            // Create notification for the creator of stock opname
            if ($session->creator) {
                Notification::create([
                    'user_id' => $session->creator->id,
                    'branch_id' => $session->branch_id,
                    'title' => $title,
                    'message' => $message,
                    'category' => 'stock_opname_approved',
                    'link_url' => '/inventory/stock-opname/'.$session->id,
                    'is_read' => false,
                    'is_dismissed' => false,
                    'created_by' => $admin->id,
                    'created_by_name' => $admin->name,
                ]);
            }

            // Create broadcast notification for all users in the same branch
            Notification::create([
                'user_id' => null, // broadcast to all users in branch
                'branch_id' => $session->branch_id,
                'title' => $title,
                'message' => "Stock Opname {$session->code} di {$session->branch->name} telah disetujui. ".
                    "Stok produk telah diperbarui. Total penyesuaian: +{$session->total_positive_adjustment}, -{$session->total_negative_adjustment} item.",
                'category' => 'stock_opname_approved_broadcast',
                'link_url' => '/inventory/stock-opname/'.$session->id,
                'is_read' => false,
                'is_dismissed' => false,
                'created_by' => $admin->id,
                'created_by_name' => $admin->name,
            ]);

            Log::info("Stock opname approved notification sent for session: {$session->code}");
        } catch (\Exception $e) {
            Log::error('Failed to send stock opname approved notification: '.$e->getMessage());
        }
    }

    /**
     * Send notification when stock opname is rejected
     */
    public function sendStockOpnameRejectedNotification(StockOpnameSession $session, User $admin, string $reason)
    {
        try {
            // Load session with creator relationship
            $session->load('creator', 'branch');

            $title = 'Stock Opname Ditolak';
            $message = "Stock Opname {$session->code} telah ditolak oleh {$admin->name}. ".
                "Alasan: {$reason}. ".
                'Silakan perbaiki dan submit ulang stock opname Anda.';

            // Create notification for the creator of stock opname
            if ($session->creator) {
                Notification::create([
                    'user_id' => $session->creator->id,
                    'branch_id' => $session->branch_id,
                    'title' => $title,
                    'message' => $message,
                    'category' => 'stock_opname_rejected',
                    'link_url' => '/inventory/stock-opname/'.$session->id,
                    'is_read' => false,
                    'is_dismissed' => false,
                    'created_by' => $admin->id,
                    'created_by_name' => $admin->name,
                ]);
            }

            // Create broadcast notification for all users in the same branch
            Notification::create([
                'user_id' => null, // broadcast to all users in branch
                'branch_id' => $session->branch_id,
                'title' => $title,
                'message' => "Stock Opname {$session->code} di {$session->branch->name} telah ditolak oleh admin. ".
                    "Alasan: {$reason}",
                'category' => 'stock_opname_rejected_broadcast',
                'link_url' => '/inventory/stock-opname/'.$session->id,
                'is_read' => false,
                'is_dismissed' => false,
                'created_by' => $admin->id,
                'created_by_name' => $admin->name,
            ]);

            Log::info("Stock opname rejected notification sent for session: {$session->code}");
        } catch (\Exception $e) {
            Log::error('Failed to send stock opname rejected notification: '.$e->getMessage());
        }
    }

    /**
     * Send notification when stock opname is submitted for review
     */
    public function sendStockOpnameSubmittedNotification(StockOpnameSession $session, User $submitter)
    {
        try {
            // Load session with branch relationship
            $session->load('branch');

            $title = 'Stock Opname Menunggu Review';
            $message = "Stock Opname {$session->code} dari {$session->branch->name} telah disubmit oleh {$submitter->name} ".
                "dan menunggu review admin. Total penyesuaian: +{$session->total_positive_adjustment}, -{$session->total_negative_adjustment} item.";

            // Create notification for all admin users
            $adminUsers = User::where('role', 'admin')->get();

            foreach ($adminUsers as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'branch_id' => null, // admin can see all branches
                    'title' => $title,
                    'message' => $message,
                    'category' => 'stock_opname_submitted',
                    'link_url' => '/admin/stock-opname-review',
                    'is_read' => false,
                    'is_dismissed' => false,
                    'created_by' => $submitter->id,
                    'created_by_name' => $submitter->name,
                ]);
            }

            Log::info("Stock opname submitted notification sent for session: {$session->code}");
        } catch (\Exception $e) {
            Log::error('Failed to send stock opname submitted notification: '.$e->getMessage());
        }
    }
}
