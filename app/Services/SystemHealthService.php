<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class SystemHealthService
{
    /**
     * Run all health checks
     */
    public function check(): array
    {
        return [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
                'queue' => $this->checkQueue(),
            ],
            'overall_status' => $this->getOverallStatus(),
        ];
    }
    
    /**
     * Check database connectivity
     */
    public function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = round((microtime(true) - $start) * 1000, 2);
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            Log::error('Database health check failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check cache functionality
     */
    public function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test_value';
            
            // Test write
            Cache::put($testKey, $testValue, 60);
            
            // Test read
            $retrieved = Cache::get($testKey);
            
            // Test delete
            Cache::forget($testKey);
            
            $isWorking = ($retrieved === $testValue);
            
            return [
                'status' => $isWorking ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default'),
                'working' => $isWorking,
            ];
        } catch (\Exception $e) {
            Log::error('Cache health check failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check storage accessibility
     */
    public function checkStorage(): array
    {
        try {
            $testPath = 'health_check_' . time() . '.txt';
            $testContent = 'health_check';
            
            // Test write
            Storage::put($testPath, $testContent);
            
            // Test read
            $retrieved = Storage::get($testPath);
            
            // Test delete
            Storage::delete($testPath);
            
            $isWorking = ($retrieved === $testContent);
            
            return [
                'status' => $isWorking ? 'healthy' : 'unhealthy',
                'disk' => config('filesystems.default'),
                'working' => $isWorking,
            ];
        } catch (\Exception $e) {
            Log::error('Storage health check failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check queue system
     */
    public function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            $size = Queue::size();
            
            return [
                'status' => 'healthy',
                'connection' => $connection,
                'pending_jobs' => $size,
            ];
        } catch (\Exception $e) {
            Log::error('Queue health check failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Get overall system status
     */
    protected function getOverallStatus(): string
    {
        $checks = [
            $this->checkDatabase(),
            $this->checkCache(),
            $this->checkStorage(),
            $this->checkQueue(),
        ];
        
        foreach ($checks as $check) {
            if ($check['status'] !== 'healthy') {
                return 'degraded';
            }
        }
        
        return 'healthy';
    }
}
