<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Log an audit event
     *
     * @param string $event
     * @param mixed $auditable
     * @param array $oldValues
     * @param array $newValues
     * @param array $metadata
     * @return AuditLog
     */
    public function log(
        string $event,
        $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = []
    ): AuditLog {
        return AuditLog::create([
            'event' => $event,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable ? $auditable->id : null,
            'user_id' => Auth::id(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'old_values' => $this->sanitizeValues($oldValues),
            'new_values' => $this->sanitizeValues($newValues),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log a configuration change
     *
     * @param string $configType (e.g., 'site', 'homepage', 'navigation')
     * @param array $oldConfig
     * @param array $newConfig
     * @return AuditLog
     */
    public function logConfigChange(string $configType, array $oldConfig, array $newConfig): AuditLog
    {
        return $this->log(
            "config.{$configType}.updated",
            null,
            $oldConfig,
            $newConfig,
            [
                'config_type' => $configType,
                'changed_fields' => $this->getChangedFields($oldConfig, $newConfig),
            ]
        );
    }

    /**
     * Log model creation
     *
     * @param mixed $model
     * @param string|null $eventName
     * @return AuditLog
     */
    public function logCreated($model, ?string $eventName = null): AuditLog
    {
        $event = $eventName ?? strtolower(class_basename($model)) . '.created';

        return $this->log(
            $event,
            $model,
            [],
            $model->toArray()
        );
    }

    /**
     * Log model update
     *
     * @param mixed $model
     * @param array $oldValues
     * @param string|null $eventName
     * @return AuditLog
     */
    public function logUpdated($model, array $oldValues, ?string $eventName = null): AuditLog
    {
        $event = $eventName ?? strtolower(class_basename($model)) . '.updated';

        return $this->log(
            $event,
            $model,
            $oldValues,
            $model->toArray(),
            [
                'changed_fields' => $this->getChangedFields($oldValues, $model->toArray()),
            ]
        );
    }

    /**
     * Log model deletion
     *
     * @param mixed $model
     * @param string|null $eventName
     * @return AuditLog
     */
    public function logDeleted($model, ?string $eventName = null): AuditLog
    {
        $event = $eventName ?? strtolower(class_basename($model)) . '.deleted';

        return $this->log(
            $event,
            $model,
            $model->toArray(),
            []
        );
    }

    /**
     * Get audit logs with filters
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getLogs(array $filters = [])
    {
        $query = AuditLog::with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        // Filter by event
        if (!empty($filters['event'])) {
            $query->forEvent($filters['event']);
        }

        // Filter by user
        if (!empty($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        // Filter by date range
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->dateRange($filters['start_date'], $filters['end_date']);
        }

        // Filter by recent days
        if (!empty($filters['recent_days'])) {
            $query->recent($filters['recent_days']);
        }

        // Filter by auditable type
        if (!empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->paginate($perPage);
    }

    /**
     * Get audit statistics
     *
     * @param int $days
     * @return array
     */
    public function getStats(int $days = 30): array
    {
        $logs = AuditLog::recent($days)->get();

        return [
            'total_changes' => $logs->count(),
            'unique_users' => $logs->pluck('user_id')->unique()->count(),
            'events_breakdown' => $logs->groupBy('event')->map->count()->toArray(),
            'users_breakdown' => $logs->groupBy('user_id')->map->count()->toArray(),
            'daily_activity' => $logs->groupBy(function ($log) {
                return $log->created_at->format('Y-m-d');
            })->map->count()->toArray(),
        ];
    }

    /**
     * Sanitize sensitive values before logging
     *
     * @param array $values
     * @return array
     */
    protected function sanitizeValues(array $values): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'private_key'];

        foreach ($values as $key => $value) {
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    $values[$key] = '[REDACTED]';
                    break;
                }
            }

            // Recursively sanitize nested arrays
            if (is_array($value)) {
                $values[$key] = $this->sanitizeValues($value);
            }
        }

        return $values;
    }

    /**
     * Get list of changed fields between old and new values
     *
     * @param array $oldValues
     * @param array $newValues
     * @return array
     */
    protected function getChangedFields(array $oldValues, array $newValues): array
    {
        $changed = [];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            // Compare values (handle arrays specially)
            if (is_array($newValue) && is_array($oldValue)) {
                if (json_encode($oldValue) !== json_encode($newValue)) {
                    $changed[] = $key;
                }
            } elseif ($oldValue !== $newValue) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * Purge old audit logs
     *
     * @param int $days
     * @return int Number of deleted records
     */
    public function purgeOldLogs(int $days = 90): int
    {
        return AuditLog::where('created_at', '<', now()->subDays($days))->delete();
    }
}

