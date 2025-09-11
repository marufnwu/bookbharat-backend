<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Models\SearchQuery;
use App\Models\User;

class SearchService
{
    protected $elasticsearchUrl;
    protected $indexName;

    public function __construct()
    {
        $this->elasticsearchUrl = config('services.elasticsearch.host', 'http://localhost:9200');
        $this->indexName = config('services.elasticsearch.index', 'products');
    }

    /**
     * Search products with advanced filtering
     */
    public function searchProducts(Request $request, ?User $user = null)
    {
        $query = $request->get('q', '');
        $filters = $this->parseFilters($request);
        $sort = $request->get('sort', 'relevance');
        $page = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 20), 100);
        $from = ($page - 1) * $perPage;

        // Build Elasticsearch query
        $searchQuery = $this->buildElasticsearchQuery($query, $filters, $sort, $from, $perPage, $user);
        
        try {
            // Execute search
            $response = Http::post("{$this->elasticsearchUrl}/{$this->indexName}/_search", $searchQuery);
            $results = $response->json();

            // Process results
            $processed = $this->processSearchResults($results, $query, $filters, $user);
            
            // Log search query for analytics
            $this->logSearchQuery($query, $filters, $processed['total'], $user, $request);
            
            return $processed;

        } catch (\Exception $e) {
            Log::error('Elasticsearch search failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            // Fallback to database search
            return $this->fallbackDatabaseSearch($query, $filters, $page, $perPage);
        }
    }

    /**
     * Get search suggestions/autocomplete
     */
    public function getSuggestions($query, $limit = 10)
    {
        if (strlen($query) < 2) {
            return [];
        }

        $searchQuery = [
            'suggest' => [
                'product_suggest' => [
                    'prefix' => $query,
                    'completion' => [
                        'field' => 'suggest',
                        'size' => $limit,
                    ]
                ]
            ]
        ];

        try {
            $response = Http::post("{$this->elasticsearchUrl}/{$this->indexName}/_search", $searchQuery);
            $results = $response->json();

            $suggestions = [];
            if (isset($results['suggest']['product_suggest'][0]['options'])) {
                foreach ($results['suggest']['product_suggest'][0]['options'] as $option) {
                    $suggestions[] = [
                        'text' => $option['text'],
                        'score' => $option['_score'],
                        'type' => 'product'
                    ];
                }
            }

            return $suggestions;

        } catch (\Exception $e) {
            Log::error('Elasticsearch suggestions failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            // Fallback to database suggestions
            return $this->fallbackDatabaseSuggestions($query, $limit);
        }
    }

    /**
     * Get faceted filters for search results
     */
    public function getFacets($query = '', $filters = [])
    {
        $searchQuery = [
            'size' => 0,
            'query' => $this->buildQueryClause($query, $filters),
            'aggs' => [
                'categories' => [
                    'terms' => [
                        'field' => 'category_id',
                        'size' => 50
                    ],
                    'aggs' => [
                        'category_names' => [
                            'terms' => ['field' => 'category_name.keyword']
                        ]
                    ]
                ],
                'brands' => [
                    'terms' => [
                        'field' => 'brand.keyword',
                        'size' => 30
                    ]
                ],
                'price_ranges' => [
                    'range' => [
                        'field' => 'price',
                        'ranges' => [
                            ['to' => 500],
                            ['from' => 500, 'to' => 1000],
                            ['from' => 1000, 'to' => 2500],
                            ['from' => 2500, 'to' => 5000],
                            ['from' => 5000]
                        ]
                    ]
                ],
                'ratings' => [
                    'range' => [
                        'field' => 'average_rating',
                        'ranges' => [
                            ['from' => 4],
                            ['from' => 3, 'to' => 4],
                            ['from' => 2, 'to' => 3],
                            ['to' => 2]
                        ]
                    ]
                ],
                'availability' => [
                    'terms' => [
                        'field' => 'in_stock'
                    ]
                ]
            ]
        ];

        try {
            $response = Http::post("{$this->elasticsearchUrl}/{$this->indexName}/_search", $searchQuery);
            $results = $response->json();

            return $this->processFacets($results['aggregations'] ?? []);

        } catch (\Exception $e) {
            Log::error('Elasticsearch facets failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Index a product for search
     */
    public function indexProduct(Product $product)
    {
        $document = [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'sku' => $product->sku,
            'price' => $product->price,
            'compare_price' => $product->compare_price,
            'category_id' => $product->category_id,
            'category_name' => $product->category->name ?? '',
            'brand' => $product->brand,
            'is_featured' => $product->is_featured,
            'in_stock' => $product->in_stock,
            'stock_quantity' => $product->stock_quantity,
            'average_rating' => $product->average_rating,
            'total_reviews' => $product->total_reviews,
            'created_at' => $product->created_at->toISOString(),
            'updated_at' => $product->updated_at->toISOString(),
            'suggest' => [
                'input' => [
                    $product->name,
                    $product->brand,
                    $product->sku
                ],
                'weight' => $this->calculateProductWeight($product)
            ]
        ];

        // Add variant information if available
        if ($product->variants) {
            $document['variants'] = $product->variants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'stock_quantity' => $variant->stock_quantity,
                    'attributes' => $variant->formatted_attributes
                ];
            })->toArray();
        }

        try {
            Http::put("{$this->elasticsearchUrl}/{$this->indexName}/_doc/{$product->id}", $document);
            Log::info("Product indexed successfully", ['product_id' => $product->id]);
            
        } catch (\Exception $e) {
            Log::error("Failed to index product", [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Build Elasticsearch query
     */
    protected function buildElasticsearchQuery($query, $filters, $sort, $from, $size, $user)
    {
        $searchQuery = [
            'from' => $from,
            'size' => $size,
            'query' => $this->buildQueryClause($query, $filters),
            'sort' => $this->buildSortClause($sort, $user),
            '_source' => [
                'includes' => [
                    'id', 'name', 'description', 'short_description', 'sku', 'price', 
                    'compare_price', 'brand', 'category_name', 'in_stock', 'stock_quantity',
                    'average_rating', 'total_reviews', 'is_featured'
                ]
            ]
        ];

        // Add highlighting
        if (!empty($query)) {
            $searchQuery['highlight'] = [
                'fields' => [
                    'name' => ['fragment_size' => 150],
                    'description' => ['fragment_size' => 200]
                ]
            ];
        }

        return $searchQuery;
    }

    protected function buildQueryClause($query, $filters)
    {
        $must = [];
        $filter = [];

        // Text search
        if (!empty($query)) {
            $must[] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => [
                        'name^3',
                        'brand^2',
                        'description',
                        'short_description',
                        'sku^2'
                    ],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO'
                ]
            ];
        } else {
            $must[] = ['match_all' => new \stdClass()];
        }

        // Apply filters
        foreach ($filters as $field => $value) {
            switch ($field) {
                case 'category_id':
                    if (is_array($value)) {
                        $filter[] = ['terms' => ['category_id' => $value]];
                    } else {
                        $filter[] = ['term' => ['category_id' => $value]];
                    }
                    break;

                case 'brand':
                    if (is_array($value)) {
                        $filter[] = ['terms' => ['brand.keyword' => $value]];
                    } else {
                        $filter[] = ['term' => ['brand.keyword' => $value]];
                    }
                    break;

                case 'price_min':
                    $filter[] = ['range' => ['price' => ['gte' => (float)$value]]];
                    break;

                case 'price_max':
                    $filter[] = ['range' => ['price' => ['lte' => (float)$value]]];
                    break;

                case 'rating_min':
                    $filter[] = ['range' => ['average_rating' => ['gte' => (float)$value]]];
                    break;

                case 'in_stock':
                    $filter[] = ['term' => ['in_stock' => (bool)$value]];
                    break;

                case 'is_featured':
                    $filter[] = ['term' => ['is_featured' => (bool)$value]];
                    break;
            }
        }

        return [
            'bool' => [
                'must' => $must,
                'filter' => $filter
            ]
        ];
    }

    protected function buildSortClause($sort, $user)
    {
        switch ($sort) {
            case 'price_low':
                return [['price' => 'asc']];
            
            case 'price_high':
                return [['price' => 'desc']];
            
            case 'rating':
                return [['average_rating' => 'desc'], ['total_reviews' => 'desc']];
            
            case 'newest':
                return [['created_at' => 'desc']];
            
            case 'popularity':
                return [['total_reviews' => 'desc'], ['average_rating' => 'desc']];
            
            case 'relevance':
            default:
                $sort = ['_score' => 'desc'];
                
                // Boost featured products for logged-in users
                if ($user) {
                    $sort = [
                        '_script' => [
                            'type' => 'number',
                            'script' => [
                                'source' => "Math.log(2 + doc['total_reviews'].value) * (doc['is_featured'].value ? 1.2 : 1.0) * _score"
                            ],
                            'order' => 'desc'
                        ]
                    ];
                }
                
                return [$sort];
        }
    }

    protected function processSearchResults($results, $query, $filters, $user)
    {
        $products = [];
        $total = $results['hits']['total']['value'] ?? 0;

        foreach ($results['hits']['hits'] ?? [] as $hit) {
            $product = $hit['_source'];
            $product['score'] = $hit['_score'];
            
            // Add highlighted text if available
            if (isset($hit['highlight'])) {
                $product['highlighted'] = $hit['highlight'];
            }
            
            $products[] = $product;
        }

        return [
            'products' => $products,
            'total' => $total,
            'query' => $query,
            'filters' => $filters,
            'took' => $results['took'] ?? 0,
        ];
    }

    protected function processFacets($aggregations)
    {
        $facets = [];

        foreach ($aggregations as $facetName => $facetData) {
            switch ($facetName) {
                case 'categories':
                    $facets['categories'] = array_map(function ($bucket) {
                        return [
                            'id' => $bucket['key'],
                            'name' => $bucket['category_names']['buckets'][0]['key'] ?? 'Category ' . $bucket['key'],
                            'count' => $bucket['doc_count']
                        ];
                    }, $facetData['buckets'] ?? []);
                    break;

                case 'brands':
                    $facets['brands'] = array_map(function ($bucket) {
                        return [
                            'name' => $bucket['key'],
                            'count' => $bucket['doc_count']
                        ];
                    }, $facetData['buckets'] ?? []);
                    break;

                case 'price_ranges':
                    $facets['price_ranges'] = $this->formatPriceRanges($facetData['buckets'] ?? []);
                    break;

                case 'ratings':
                    $facets['ratings'] = $this->formatRatingRanges($facetData['buckets'] ?? []);
                    break;

                case 'availability':
                    $facets['availability'] = array_map(function ($bucket) {
                        return [
                            'available' => (bool)$bucket['key'],
                            'label' => $bucket['key'] ? 'In Stock' : 'Out of Stock',
                            'count' => $bucket['doc_count']
                        ];
                    }, $facetData['buckets'] ?? []);
                    break;
            }
        }

        return $facets;
    }

    protected function formatPriceRanges($buckets)
    {
        $ranges = [];
        foreach ($buckets as $bucket) {
            $from = $bucket['from'] ?? 0;
            $to = $bucket['to'] ?? null;
            
            if ($to === null) {
                $label = "₹{$from}+";
            } else {
                $label = "₹{$from} - ₹{$to}";
            }
            
            $ranges[] = [
                'from' => $from,
                'to' => $to,
                'label' => $label,
                'count' => $bucket['doc_count']
            ];
        }
        return $ranges;
    }

    protected function formatRatingRanges($buckets)
    {
        $ranges = [];
        foreach ($buckets as $bucket) {
            $from = $bucket['from'] ?? 0;
            $to = $bucket['to'] ?? null;
            
            if ($to === null) {
                $label = "{$from}+ Stars";
            } else {
                $label = "{$from} - {$to} Stars";
            }
            
            $ranges[] = [
                'from' => $from,
                'to' => $to,
                'label' => $label,
                'count' => $bucket['doc_count']
            ];
        }
        return $ranges;
    }

    protected function parseFilters(Request $request)
    {
        $filters = [];
        
        if ($request->filled('category')) {
            $filters['category_id'] = is_array($request->category) ? 
                $request->category : [$request->category];
        }
        
        if ($request->filled('brand')) {
            $filters['brand'] = is_array($request->brand) ? 
                $request->brand : [$request->brand];
        }
        
        if ($request->filled('price_min')) {
            $filters['price_min'] = $request->price_min;
        }
        
        if ($request->filled('price_max')) {
            $filters['price_max'] = $request->price_max;
        }
        
        if ($request->filled('rating')) {
            $filters['rating_min'] = $request->rating;
        }
        
        if ($request->filled('in_stock')) {
            $filters['in_stock'] = $request->boolean('in_stock');
        }
        
        if ($request->filled('featured')) {
            $filters['is_featured'] = $request->boolean('featured');
        }
        
        return $filters;
    }

    protected function calculateProductWeight(Product $product)
    {
        $weight = 1;
        
        if ($product->is_featured) $weight += 2;
        if ($product->average_rating >= 4) $weight += 1;
        if ($product->total_reviews > 10) $weight += 1;
        if ($product->in_stock) $weight += 1;
        
        return $weight;
    }

    protected function logSearchQuery($query, $filters, $resultCount, $user, $request)
    {
        if (empty($query)) return;

        SearchQuery::create([
            'user_id' => $user?->id,
            'session_id' => $request->session()->getId(),
            'query' => $query,
            'normalized_query' => strtolower(trim($query)),
            'results_count' => $resultCount,
            'filters_applied' => $filters,
            'search_time_ms' => 0, // Would be populated from Elasticsearch response
        ]);
    }

    protected function fallbackDatabaseSearch($query, $filters, $page, $perPage)
    {
        $queryBuilder = Product::active()->inStock();

        if (!empty($query)) {
            $queryBuilder->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('brand', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%");
            });
        }

        foreach ($filters as $field => $value) {
            switch ($field) {
                case 'category_id':
                    $queryBuilder->whereIn('category_id', (array)$value);
                    break;
                case 'brand':
                    $queryBuilder->whereIn('brand', (array)$value);
                    break;
                case 'price_min':
                    $queryBuilder->where('price', '>=', $value);
                    break;
                case 'price_max':
                    $queryBuilder->where('price', '<=', $value);
                    break;
            }
        }

        $results = $queryBuilder->paginate($perPage, ['*'], 'page', $page);

        return [
            'products' => $results->items(),
            'total' => $results->total(),
            'query' => $query,
            'filters' => $filters,
            'took' => 0,
            'fallback' => true
        ];
    }

    protected function fallbackDatabaseSuggestions($query, $limit)
    {
        return Product::active()
            ->where('name', 'like', "%{$query}%")
            ->limit($limit)
            ->pluck('name')
            ->map(function ($name) {
                return ['text' => $name, 'type' => 'product'];
            })
            ->toArray();
    }
}