# Frequently Bought Together - Implementation Guide

## How It Works in Real E-commerce

### 1. Data Collection Phase
The system tracks when customers buy multiple products together in the same order:

```sql
-- When an order is placed with multiple items:
Order #1234: 
  - Book A (PHP Programming)
  - Book B (MySQL Guide)
  - Book C (Laravel Framework)

Order #1235:
  - Book A (PHP Programming)
  - Book B (MySQL Guide)

Order #1236:
  - Book A (PHP Programming)
  - Book C (Laravel Framework)
```

### 2. Database Schema Required

```sql
-- Create a table to track product associations
CREATE TABLE product_associations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    associated_product_id BIGINT NOT NULL,
    frequency INT DEFAULT 1,
    last_purchased_together TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (associated_product_id) REFERENCES products(id),
    UNIQUE KEY unique_association (product_id, associated_product_id),
    INDEX idx_frequency (frequency DESC),
    INDEX idx_product (product_id)
);

-- Track bundle performance
CREATE TABLE bundle_analytics (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    bundle_id VARCHAR(255),
    product_ids JSON,
    views INT DEFAULT 0,
    clicks INT DEFAULT 0,
    purchases INT DEFAULT 0,
    revenue DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 3. Data Processing Algorithm

```php
// When an order is completed, update associations
public function updateProductAssociations($orderId)
{
    $order = Order::with('items.product')->find($orderId);
    $productIds = $order->items->pluck('product_id')->toArray();
    
    // For each pair of products in the order
    foreach ($productIds as $productId) {
        foreach ($productIds as $associatedId) {
            if ($productId !== $associatedId) {
                DB::table('product_associations')
                    ->upsert(
                        [
                            'product_id' => $productId,
                            'associated_product_id' => $associatedId,
                            'frequency' => 1,
                            'last_purchased_together' => now()
                        ],
                        ['product_id', 'associated_product_id'], // unique keys
                        ['frequency' => DB::raw('frequency + 1'), 'last_purchased_together' => now()]
                    );
            }
        }
    }
}
```

### 4. Retrieving Frequently Bought Together

```php
public function getFrequentlyBoughtTogether($productId)
{
    // Get products frequently bought with this product
    $associations = DB::table('product_associations')
        ->where('product_id', $productId)
        ->orderBy('frequency', 'desc')
        ->limit(5)
        ->get();
    
    // Get the associated products
    $associatedProductIds = $associations->pluck('associated_product_id');
    
    $products = Product::whereIn('id', $associatedProductIds)
        ->with(['images', 'category'])
        ->get()
        ->map(function($product) use ($associations) {
            $association = $associations->firstWhere('associated_product_id', $product->id);
            $product->purchase_frequency = $association->frequency;
            $product->confidence_score = $this->calculateConfidence($association);
            return $product;
        });
    
    return $products;
}

private function calculateConfidence($association)
{
    // Calculate confidence score based on:
    // - Frequency of co-purchase
    // - Recency of last purchase together
    // - Overall sales volume
    
    $frequency_score = min($association->frequency / 10, 1) * 0.5; // 50% weight
    $recency_score = $this->getRecencyScore($association->last_purchased_together) * 0.3; // 30% weight
    $volume_score = 0.2; // 20% weight for general popularity
    
    return $frequency_score + $recency_score + $volume_score;
}
```

## Advanced Features

### 1. Machine Learning Approach
```python
# Using collaborative filtering or association rules
from mlxtend.frequent_patterns import apriori, association_rules

# Generate frequent itemsets
frequent_itemsets = apriori(purchase_data, min_support=0.01, use_colnames=True)

# Generate association rules
rules = association_rules(frequent_itemsets, metric="confidence", min_threshold=0.5)

# Find products frequently bought together
def get_recommendations(product_id):
    relevant_rules = rules[rules['antecedents'].apply(lambda x: product_id in x)]
    return relevant_rules.sort_values('lift', ascending=False)['consequents'].head(3)
```

### 2. Personalization
```php
public function getPersonalizedBundles($productId, $userId)
{
    $user = User::find($userId);
    
    // Consider user's purchase history
    $userCategories = $user->orders()
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->join('products', 'order_items.product_id', '=', 'products.id')
        ->pluck('products.category_id')
        ->unique();
    
    // Get frequently bought together, weighted by user preferences
    $products = DB::table('product_associations as pa')
        ->join('products as p', 'pa.associated_product_id', '=', 'p.id')
        ->where('pa.product_id', $productId)
        ->when($userCategories->isNotEmpty(), function($query) use ($userCategories) {
            return $query->whereIn('p.category_id', $userCategories)
                        ->orderByRaw('FIELD(p.category_id, ?) DESC', [$userCategories->implode(',')]);
        })
        ->orderBy('pa.frequency', 'desc')
        ->limit(3)
        ->get();
    
    return $products;
}
```

### 3. Dynamic Bundle Pricing
```php
public function calculateBundlePrice($products)
{
    $totalPrice = $products->sum('price');
    $bundleSize = $products->count();
    
    // Dynamic discount based on bundle size and value
    $discount = 0;
    
    if ($bundleSize == 2) {
        $discount = 0.05; // 5% off
    } elseif ($bundleSize == 3) {
        $discount = 0.10; // 10% off
    } elseif ($bundleSize >= 4) {
        $discount = 0.15; // 15% off
    }
    
    // Additional discount for high-value bundles
    if ($totalPrice > 1000) {
        $discount += 0.05;
    }
    
    $bundlePrice = $totalPrice * (1 - $discount);
    
    return [
        'original_price' => $totalPrice,
        'bundle_price' => $bundlePrice,
        'savings' => $totalPrice - $bundlePrice,
        'discount_percentage' => $discount * 100
    ];
}
```

## Implementation Steps

### Step 1: Create Migration
```bash
php artisan make:migration create_product_associations_table
```

### Step 2: Create Model
```php
// app/Models/ProductAssociation.php
class ProductAssociation extends Model
{
    protected $fillable = ['product_id', 'associated_product_id', 'frequency'];
    
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    public function associatedProduct()
    {
        return $this->belongsTo(Product::class, 'associated_product_id');
    }
}
```

### Step 3: Create Event Listener
```php
// app/Listeners/UpdateProductAssociations.php
class UpdateProductAssociations
{
    public function handle(OrderCompleted $event)
    {
        $this->updateAssociations($event->order);
    }
}
```

### Step 4: Create Scheduled Job for Analytics
```php
// app/Console/Commands/AnalyzeProductAssociations.php
class AnalyzeProductAssociations extends Command
{
    protected $signature = 'products:analyze-associations';
    
    public function handle()
    {
        // Analyze recent orders
        // Update association scores
        // Remove old/irrelevant associations
        // Generate reports
    }
}
```

## Frontend Implementation

### Component Example
```jsx
function FrequentlyBoughtTogether({ productId }) {
    const [bundle, setBundle] = useState(null);
    const [selectedItems, setSelectedItems] = useState([]);
    
    useEffect(() => {
        productApi.getFrequentlyBoughtTogether(productId)
            .then(response => {
                setBundle(response.data);
                // Select all items by default
                setSelectedItems(response.data.products.map(p => p.id));
            });
    }, [productId]);
    
    const handleAddBundle = () => {
        const itemsToAdd = [
            { id: productId, quantity: 1 },
            ...selectedItems.map(id => ({ id, quantity: 1 }))
        ];
        
        cartApi.addBundleToCart(itemsToAdd, bundle.bundle_price);
    };
    
    return (
        <div className="frequently-bought-together">
            <h3>Frequently Bought Together</h3>
            <div className="bundle-items">
                {/* Main product + Associated products */}
            </div>
            <div className="bundle-pricing">
                <span>Total: ₹{bundle.bundle_price}</span>
                <span>Save: ₹{bundle.savings}</span>
            </div>
            <button onClick={handleAddBundle}>
                Add Bundle to Cart
            </button>
        </div>
    );
}
```

## Analytics & Optimization

### Track Performance
```sql
-- Query to find best performing bundles
SELECT 
    product_id,
    GROUP_CONCAT(associated_product_id) as bundle,
    SUM(frequency) as total_purchases,
    AVG(frequency) as avg_frequency
FROM product_associations
GROUP BY product_id
ORDER BY total_purchases DESC;

-- Find seasonal patterns
SELECT 
    MONTH(last_purchased_together) as month,
    product_id,
    associated_product_id,
    COUNT(*) as purchases
FROM product_associations
GROUP BY month, product_id, associated_product_id;
```

## Benefits

1. **Increased Average Order Value (AOV)**: Customers buy more items per order
2. **Better User Experience**: Helpful suggestions save time
3. **Inventory Management**: Move related inventory together
4. **Cross-selling**: Introduce customers to complementary products

## Real Examples

- **Amazon**: "Customers who bought this item also bought"
- **Books**: Programming book + Reference guide + Video course
- **Electronics**: Laptop + Mouse + Laptop bag + Antivirus

This is how major e-commerce platforms like Amazon, Flipkart, and others implement their "Frequently Bought Together" features!