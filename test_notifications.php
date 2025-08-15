<?php

use App\Models\Notification;
use App\Models\StockOpnameSession;
use App\Models\User;
use App\Services\NotificationService;

$admin = User::find(1); // Admin user
$session = StockOpnameSession::find(19); // Stock opname that was approved

// Test notification service
$notificationService = new NotificationService();

echo "Testing notification service...\n";

// Test approved notification
$notificationService->sendStockOpnameApprovedNotification($session, $admin);
echo "Approved notification sent!\n";

// Test rejected notification (simulate)
$session->status = 'SUBMIT'; // Reset status for testing
$notificationService->sendStockOpnameRejectedNotification($session, $admin, "Test rejection reason");
echo "Rejected notification sent!\n";

// Test submitted notification (simulate)
$notificationService->sendStockOpnameSubmittedNotification($session, $admin);
echo "Submitted notification sent!\n";

// Check notifications
$notifications = Notification::latest()->take(10)->get();
echo "\nLatest notifications:\n";
foreach ($notifications as $notification) {
    echo "- {$notification->title}: {$notification->message}\n";
}
