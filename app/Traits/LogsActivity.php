<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    /**
     * Log user activity.
     *
     * @param string $module
     * @param string $action
     * @param string|null $description
     * @param array|null $payload
     * @return void
     */
    public function logActivity(string $module, string $action, ?string $description = null, ?array $payload = null): void
    {
        try {
            $user = auth('api')->user();
            $userId = $user ? $user->id : null;
            $userName = $user ? $user->name : 'System';

            $data = [
                'user_id' => $userId,
                'module' => $module,
                'action' => $action,
                'description' => $description,
                'ip_address' => Request::ip(),
                'user_agent' => Request::header('User-Agent'),
                'payload' => $payload,
            ];

            // 1. Store in Database
            ActivityLog::create($data);

            // 2. Store in Log File
            $logMessage = sprintf(
                "Activity Log [%s]: User: %s (%s) | Module: %s | Action: %s | Description: %s",
                now(),
                $userName,
                $userId ?: 'N/A',
                $module,
                $action,
                $description ?: 'N/A'
            );

            if ($payload) {
                $logMessage .= " | Payload: " . json_encode($payload);
            }

            Log::info($logMessage);

        } catch (\Throwable $th) {
            // We don't want to break the main flow if logging fails
            Log::error("Failed to store activity log: " . $th->getMessage());
        }
    }
}
