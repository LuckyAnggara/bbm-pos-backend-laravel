<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $limit = (int)$request->input('limit', 50);
            $user = $request->user();
            $scope = $request->input('scope'); // 'sent' to view those created by current user
            $query = Notification::query();

            if ($scope === 'sent') {
                $query->where('created_by', $user->id);
            } else {
                $query->where(function ($q) use ($user) {
                    $q->whereNull('user_id')->orWhere('user_id', $user->id);
                })
                    ->where('is_dismissed', false);
            }

            if ($branchId = $request->input('branch_id')) {
                $query->where('branch_id', $branchId);
            }

            if ($category = $request->input('category')) {
                $query->where('category', $category);
            }

            $query->latest();
            return response()->json($query->paginate($limit));
        } catch (\Exception $e) {
            Log::error('Notification index error: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'message' => 'required|string|max:2000',
                'category' => 'required|string|max:100',
                'link_url' => 'nullable|url|max:500',
                'branch_id' => 'nullable|integer' // null = all branches
            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();
            $notification = Notification::create([
                'user_id' => null, // broadcast or branch-specific broadcast
                'branch_id' => $data['branch_id'] ?? null,
                'title' => $data['title'],
                'message' => $data['message'],
                'category' => $data['category'],
                'link_url' => $data['link_url'] ?? null,
                'is_read' => false,
                'is_dismissed' => false,
                'created_by' => $user->id,
                'created_by_name' => $user->name ?? 'System'
            ]);

            return response()->json(['success' => true, 'data' => $notification], 201);
        } catch (\Exception $e) {
            Log::error('Notification store error: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    public function markRead(Request $request, Notification $notification)
    {
        $user = $request->user();
        if ($notification->user_id && $notification->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $notification->update(['is_read' => true, 'read_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        Notification::where(function ($q) use ($user) {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        })->where('is_dismissed', false)->update(['is_read' => true, 'read_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function dismiss(Request $request, Notification $notification)
    {
        $user = $request->user();
        if ($notification->user_id && $notification->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $notification->update(['is_dismissed' => true, 'dismissed_at' => now()]);
        return response()->json(['success' => true]);
    }
}
