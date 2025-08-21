<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\StockOpnameSession;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class TestNotifications extends Command
{
    protected $signature = 'test:notifications';

    protected $description = 'Test stock opname notification system';

    public function handle()
    {
        $this->info('Testing notification system...');

        $admin = User::find(1); // Admin user
        $session = StockOpnameSession::find(19); // Stock opname that was approved

        if (! $admin || ! $session) {
            $this->error('Admin user or stock opname session not found');

            return;
        }

        // Test notification service
        $notificationService = new NotificationService;

        $this->info('1. Testing approved notification...');
        $notificationService->sendStockOpnameApprovedNotification($session, $admin);
        $this->info('✓ Approved notification sent!');

        $this->info('2. Testing rejected notification...');
        $notificationService->sendStockOpnameRejectedNotification($session, $admin, 'Test rejection reason for notification system');
        $this->info('✓ Rejected notification sent!');

        $this->info('3. Testing submitted notification...');
        $notificationService->sendStockOpnameSubmittedNotification($session, $admin);
        $this->info('✓ Submitted notification sent!');

        // Check notifications
        $notifications = Notification::latest()->take(10)->get();
        $this->info("\nLatest notifications:");
        foreach ($notifications as $notification) {
            $this->line("- {$notification->title}: {$notification->category}");
            $this->line("  {$notification->message}");
            $this->line("  User: {$notification->user_id}, Branch: {$notification->branch_id}");
            $this->line("  Created by: {$notification->created_by_name}");
            $this->line('');
        }

        $this->info('Total notifications: '.Notification::count());
    }
}
