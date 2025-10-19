<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, Searchable, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'compare_price',
        'cost_price',
        'sku',
        'stock_quantity',
        'sales_count',
        'low_stock_threshold',
        'min_stock_level',
        'manage_stock',
        'track_stock',
        'allow_backorder',
        'in_stock',
        'is_active',
        'weight',
        'dimensions',
        'category_id',
        'brand',
        'author',
        'publisher',
        'isbn',
        'language',
        'pages',
        'publication_date',
        'status',
        'rating',
        'is_featured',
        'is_bestseller',
        'is_digital',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'attributes',
        'metadata',
        'seo',
        'tags'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'stock_quantity' => 'integer',
        'sales_count' => 'integer',
        'min_stock_level' => 'integer',
        'low_stock_threshold' => 'integer',
        'pages' => 'integer',
        'rating' => 'decimal:2',
        'manage_stock' => 'boolean',
        'track_stock' => 'boolean',
        'allow_backorder' => 'boolean',
        'in_stock' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_bestseller' => 'boolean',
        'is_digital' => 'boolean',
        'attributes' => 'array',
        'metadata' => 'array',
        'seo' => 'array',
        'tags' => 'array',
        'publication_date' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable();
    }

    // Scout searchable attributes
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'sku' => $this->sku,
            'brand' => $this->brand,
            'author' => $this->author,
            'publisher' => $this->publisher,
            'isbn' => $this->isbn,
            'category' => $this->category->name ?? null,
        ];
    }

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true);
    }

    public function allVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function productAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ProductReturn::class);
    }

    public function productAssociations(): HasMany
    {
        return $this->hasMany(ProductAssociation::class, 'product_id');
    }

    public function associatedWith(): HasMany
    {
        return $this->hasMany(ProductAssociation::class, 'associated_product_id');
    }

    public function bundleVariants(): HasMany
    {
        return $this->hasMany(ProductBundleVariant::class)->orderBy('sort_order');
    }

    public function activeBundleVariants(): HasMany
    {
        return $this->hasMany(ProductBundleVariant::class)->where('is_active', true)->orderBy('sort_order');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // Accessors
    public function getPrimaryImageAttribute()
    {
        $image = $this->images()->where('is_primary', true)->first();
        if ($image) {
            return [
                'id' => $image->id,
                'image_path' => $image->image_path,
                'image_url' => Storage::disk('public')->url($image->image_path),
                'alt_text' => $image->alt_text,
            ];
        }
        return null;
    }


    public function getDiscountPercentageAttribute()
    {
        if ($this->compare_price && $this->compare_price > $this->price) {
            return round((($this->compare_price - $this->price) / $this->compare_price) * 100);
        }
        return 0;
    }

    public function getAverageRatingAttribute()
    {
        return $this->reviews()->where('is_approved', true)->avg('rating') ?? 0;
    }

    public function getTotalReviewsAttribute()
    {
        return $this->reviews()->where('is_approved', true)->count();
    }

    public function getHasVariantsAttribute()
    {
        return $this->variants()->count() > 0;
    }

    public function getLowestVariantPriceAttribute()
    {
        if ($this->has_variants) {
            return $this->variants()->min('price') ?? $this->price;
        }
        return $this->price;
    }

    public function getHighestVariantPriceAttribute()
    {
        if ($this->has_variants) {
            return $this->variants()->max('price') ?? $this->price;
        }
        return $this->price;
    }

    public function getTotalVariantStockAttribute()
    {
        if ($this->has_variants) {
            return $this->variants()->sum('stock_quantity');
        }
        return $this->stock_quantity;
    }

    public function getAvailableStockAttribute()
    {
        return $this->stock_quantity; // In the future, subtract reserved stock
    }

    public function getStockStatusAttribute()
    {
        if ($this->stock_quantity <= 0) {
            return 'out_of_stock';
        } elseif ($this->stock_quantity <= ($this->low_stock_threshold ?? 10)) {
            return 'low_stock';
        }
        return 'in_stock';
    }

    // Mutators
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = \Str::slug($value);
        }
    }

    // For backwards compatibility with existing code that might reference these fields
    public function getTitleAttribute()
    {
        return $this->name;
    }

    public function getAuthorsAttribute()
    {
        if ($this->author) {
            return [['name' => $this->author]];
        }
        return [];
    }
}
