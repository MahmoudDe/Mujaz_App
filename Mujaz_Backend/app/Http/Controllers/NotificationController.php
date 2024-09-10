<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;
use App\Models\NotificationContent;

class NotificationController extends Controller
{
    public function saveFCMToken(Request $request)
    {
        $validatedData = $request->validate([
            'userId' => 'required|integer',
            'deviceToken' => 'required|string|max:2000',
        ]);

        // Save or update the FCM token
        Notification::updateOrCreate(
            ['user_id' => $validatedData['userId']],
            ['device_token' => $validatedData['deviceToken']]
        );

        return response()->json(['message' => 'Device token saved successfully']);
    }
        // Retrieve previous notifications for a user
        public function getUserNotifications($userId)
        {
            $notifications = NotificationContent::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();
    
            return response()->json($notifications);
        }
    
        // Mark notification as read
        public function markAsRead($notificationId)
        {
            $notification = NotificationContent::find($notificationId);
            if ($notification) {
                $notification->update(['is_read' => true]);
                return response()->json(['message' => 'Notification marked as read']);
            }
    
            return response()->json(['message' => 'Notification not found'], 404);
        }
    }
    

