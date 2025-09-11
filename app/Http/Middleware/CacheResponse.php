<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    /**
     * Handle an incoming request with response caching
     */
    public function handle(Request $request, Closure $next, $ttl = 300): Response
    {
        // Only cache GET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        // Skip caching for authenticated requests with user-specific data
        if ($request->user() && $this->hasUserSpecificData($request)) {
            return $next($request);
        }

        // Generate cache key
        $cacheKey = $this->generateCacheKey($request);
        
        // Try to get cached response
        $cachedResponse = Cache::get($cacheKey);
        
        if ($cachedResponse) {
            $response = response($cachedResponse['content'], $cachedResponse['status'])
                ->withHeaders($cachedResponse['headers']);
                
            // Add cache hit header
            $response->headers->set('X-Cache', 'HIT');
            $response->headers->set('X-Cache-Key', $cacheKey);
            
            return $response;
        }

        // Process request
        $response = $next($request);
        
        // Cache successful responses
        if ($this->shouldCacheResponse($request, $response)) {
            $cacheData = [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $this->getCacheableHeaders($response),
                'cached_at' => now()->toISOString(),
            ];
            
            Cache::put($cacheKey, $cacheData, $ttl);
            
            // Add cache miss header
            $response->headers->set('X-Cache', 'MISS');
            $response->headers->set('X-Cache-Key', $cacheKey);
        }

        return $response;
    }

    /**
     * Generate cache key for request
     */
    protected function generateCacheKey(Request $request): string
    {
        $uri = $request->getRequestUri();
        $method = $request->getMethod();
        $params = $request->query->all();
        
        // Sort parameters for consistent caching
        ksort($params);
        
        $key = md5($method . '|' . $uri . '|' . http_build_query($params));
        
        return "response_cache:{$key}";
    }

    /**
     * Check if request has user-specific data that shouldn't be cached
     */
    protected function hasUserSpecificData(Request $request): bool
    {
        $userSpecificRoutes = [
            'api/user',
            'api/cart',
            'api/orders',
            'api/wishlist',
            'api/recommendations',
            'api/loyalty',
        ];

        foreach ($userSpecificRoutes as $route) {
            if (str_contains($request->path(), $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if response should be cached
     */
    protected function shouldCacheResponse(Request $request, Response $response): bool
    {
        // Only cache successful responses
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        // Don't cache responses with errors
        if ($response->headers->has('X-Error')) {
            return false;
        }

        // Don't cache very large responses (>1MB)
        if (strlen($response->getContent()) > 1024 * 1024) {
            return false;
        }

        // Cache product listings, category pages, static content
        $cacheableRoutes = [
            'api/products',
            'api/categories',
            'api/search',
            'api/featured',
            'api/trending',
            'api/facets',
        ];

        foreach ($cacheableRoutes as $route) {
            if (str_contains($request->path(), $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get headers that should be cached
     */
    protected function getCacheableHeaders(Response $response): array
    {
        $cacheableHeaders = [
            'Content-Type',
            'Content-Encoding',
            'X-Total-Count',
            'X-Per-Page',
            'X-Current-Page',
        ];

        $headers = [];
        foreach ($cacheableHeaders as $header) {
            if ($response->headers->has($header)) {
                $headers[$header] = $response->headers->get($header);
            }
        }

        return $headers;
    }
}