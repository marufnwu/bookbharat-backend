<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get audit logs with filters and pagination
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'event',
            'user_id',
            'start_date',
            'end_date',
            'recent_days',
            'auditable_type',
            'per_page',
        ]);

        $logs = $this->auditLogService->getLogs($filters);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ]
        ]);
    }

    /**
     * Get audit log statistics
     */
    public function stats(Request $request)
    {
        $days = $request->get('days', 30);
        $stats = $this->auditLogService->getStats($days);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get single audit log
     */
    public function show($id)
    {
        $log = \App\Models\AuditLog::with('user')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $log
        ]);
    }

    /**
     * Get available event types
     */
    public function events()
    {
        $events = \App\Models\AuditLog::distinct('event')
            ->pluck('event')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * Purge old logs (admin only)
     */
    public function purge(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:30|max:365',
        ]);

        $deletedCount = $this->auditLogService->purgeOldLogs($request->days);

        return response()->json([
            'success' => true,
            'message' => "Purged {$deletedCount} old audit log(s)",
            'deleted_count' => $deletedCount
        ]);
    }
}

