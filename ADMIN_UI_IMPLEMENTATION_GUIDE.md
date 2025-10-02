# Admin UI Implementation Guide - Frequently Bought Together

**Date:** October 1, 2025
**Status:** 🚧 Implementation Guide

---

## Summary

This guide provides instructions for implementing the admin UI for the Frequently Bought Together system in the React admin panel located at `D:\bookbharat-v2\bookbharat-admin`.

## What's Already Done

### ✅ Backend
- All 30 admin API endpoints created and working
- Controllers: ProductAssociationController, BundleDiscountRuleController, BundleAnalyticsController
- Routes registered in `routes/admin.php`
- Documentation complete

### ✅ Frontend API Layer
- Added `productAssociationsApi` to `src/api/extended.ts` (lines 299-312)
- Added `bundleDiscountRulesApi` to `src/api/extended.ts` (lines 314-327)
- Added `bundleAnalyticsApi` to `src/api/extended.ts` (lines 329-346)

---

## Admin Structure

The admin panel follows these patterns:
- **Framework:** React 18 with TypeScript
- **Router:** React Router v6
- **Data:** React Query (TanStack Query)
- **UI:** Tailwind CSS + Headless UI
- **Icons:** Heroicons
- **Notifications:** React Hot Toast

---

## Files to Create

### 1. Product Associations Page
**Location:** `src/pages/FrequentlyBoughtTogether/ProductAssociations.tsx`

**Features:**
- List all associations with pagination
- Search/filter by product, confidence, frequency
- Create manual associations
- Edit frequency/confidence scores
- Delete single or bulk
- Generate associations button
- Statistics dashboard
- View product-specific associations

**Key Components:**
```tsx
- Table with columns: ID, Product, Associated Product, Frequency, Confidence, Actions
- Filter bar: Search, Min Confidence, Min Frequency
- Action buttons: Generate, Create Manual, Bulk Delete
- Statistics cards: Total, High Confidence, Medium, Low
- Modal for create/edit association
```

### 2. Bundle Discount Rules Page
**Location:** `src/pages/FrequentlyBoughtTogether/BundleDiscountRules.tsx`

**Features:**
- List all discount rules
- Create new rules with form
- Edit existing rules
- Delete rules
- Toggle active/inactive
- Test rule with sample data
- Duplicate rule
- View statistics

**Key Components:**
```tsx
- Table with columns: Name, Type, Discount, Min Products, Category, Customer Tier, Priority, Status, Actions
- Create/Edit form: Name, Type (percentage/fixed), Amount, Min/Max Products, Category, Customer Tier, Priority, Validity Dates
- Test modal: Input sample data, see if rule applies
- Toggle switch for active status
```

### 3. Bundle Analytics Page
**Location:** `src/pages/FrequentlyBoughtTogether/BundleAnalytics.tsx`

**Features:**
- Overall statistics dashboard
- Top performing bundles table
- Funnel analysis chart
- Bundle comparison tool
- Product participation view
- Export data (CSV/JSON)

**Key Components:**
```tsx
- Statistics cards: Total Bundles, Views, Add to Cart, Purchases, Revenue, Conversion Rate
- Top bundles table with metric selector
- Funnel visualization: Views → Cart → Purchase
- Comparison tool: Select multiple bundles, see side-by-side metrics
- Charts: Line charts for trends, Bar charts for comparisons
```

---

## Step-by-Step Implementation

### Step 1: Export APIs in index.ts

Add to `src/api/index.ts`:

```typescript
// Import from extended
import {
  productAssociationsApi,
  bundleDiscountRulesApi,
  bundleAnalyticsApi,
} from './extended';

// Export
export {
  productAssociationsApi,
  bundleDiscountRulesApi,
  bundleAnalyticsApi,
};
```

### Step 2: Add Navigation Items

Update `src/layouts/AdminLayout.tsx`:

```typescript
import {
  SparklesIcon, // For FBT icon
} from '@heroicons/react/24/outline';

const navigation: NavigationItem[] = [
  // ... existing items
  {
    name: 'Frequently Bought Together',
    href: '/frequently-bought-together',
    icon: SparklesIcon,
    children: [
      { name: 'Associations', href: '/frequently-bought-together/associations', icon: SparklesIcon },
      { name: 'Discount Rules', href: '/frequently-bought-together/discount-rules', icon: SparklesIcon },
      { name: 'Analytics', href: '/frequently-bought-together/analytics', icon: SparklesIcon },
    ]
  },
  // ... rest of items
];
```

### Step 3: Add Routes

Update `src/App.tsx`:

```typescript
// Import pages
import ProductAssociations from './pages/FrequentlyBoughtTogether/ProductAssociations';
import BundleDiscountRules from './pages/FrequentlyBoughtTogether/BundleDiscountRules';
import BundleAnalytics from './pages/FrequentlyBoughtTogether/BundleAnalytics';

// Add routes
<Route path="frequently-bought-together">
  <Route path="associations" element={<ProductAssociations />} />
  <Route path="discount-rules" element={<BundleDiscountRules />} />
  <Route path="analytics" element={<BundleAnalytics />} />
</Route>
```

### Step 4: Create Page Directory

```bash
mkdir -p src/pages/FrequentlyBoughtTogether
```

---

## Page Templates

### Product Associations Page Template

```typescript
import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { productAssociationsApi } from '../../api';
import { Table, Button, Badge, LoadingSpinner } from '../../components';
import { useNotificationStore } from '../../store/notificationStore';

const ProductAssociations: React.FC = () => {
  const [filters, setFilters] = useState({
    page: 1,
    per_page: 20,
    search: '',
    min_confidence: '',
    min_frequency: '',
  });

  const queryClient = useQueryClient();
  const { showSuccess, showError } = useNotificationStore();

  // Fetch associations
  const { data, isLoading } = useQuery({
    queryKey: ['product-associations', filters],
    queryFn: () => productAssociationsApi.getAssociations(filters),
  });

  // Fetch statistics
  const { data: stats } = useQuery({
    queryKey: ['product-associations-stats'],
    queryFn: productAssociationsApi.getStatistics,
  });

  // Delete mutation
  const deleteMutation = useMutation({
    mutationFn: productAssociationsApi.deleteAssociation,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['product-associations'] });
      showSuccess('Association deleted successfully');
    },
    onError: () => showError('Failed to delete association'),
  });

  // Generate mutation
  const generateMutation = useMutation({
    mutationFn: productAssociationsApi.generateAssociations,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['product-associations'] });
      showSuccess('Associations generation started');
    },
  });

  return (
    <div className="space-y-6">
      {/* Statistics Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <StatCard title="Total" value={stats?.statistics?.total_associations} />
        <StatCard title="High Confidence" value={stats?.statistics?.high_confidence} />
        <StatCard title="Medium Confidence" value={stats?.statistics?.medium_confidence} />
        <StatCard title="Average Confidence" value={stats?.statistics?.average_confidence?.toFixed(2)} />
      </div>

      {/* Actions Bar */}
      <div className="flex justify-between items-center">
        <div className="flex gap-2">
          <Button onClick={() => generateMutation.mutate({ months: 6, async: true })}>
            Generate Associations
          </Button>
        </div>
        <div className="flex gap-2">
          <Input
            placeholder="Search..."
            value={filters.search}
            onChange={(e) => setFilters({ ...filters, search: e.target.value })}
          />
        </div>
      </div>

      {/* Table */}
      {isLoading ? (
        <LoadingSpinner />
      ) : (
        <Table
          data={data?.associations?.data || []}
          columns={[
            { key: 'id', title: 'ID' },
            { key: 'product.name', title: 'Product' },
            { key: 'associated_product.name', title: 'Associated Product' },
            { key: 'frequency', title: 'Frequency' },
            {
              key: 'confidence_score',
              title: 'Confidence',
              render: (value) => <Badge>{(value * 100).toFixed(0)}%</Badge>
            },
            {
              key: 'actions',
              title: 'Actions',
              render: (_, record) => (
                <div className="flex gap-2">
                  <Button size="sm" onClick={() => deleteMutation.mutate(record.id)}>
                    Delete
                  </Button>
                </div>
              )
            }
          ]}
          pagination={{
            current: filters.page,
            pageSize: filters.per_page,
            total: data?.associations?.total,
            onChange: (page) => setFilters({ ...filters, page }),
          }}
        />
      )}
    </div>
  );
};
```

---

## Quick Start Commands

```bash
# Navigate to admin directory
cd D:/bookbharat-v2/bookbharat-admin

# Install dependencies (if needed)
npm install

# Start development server
npm start

# Build for production
npm run build
```

---

## Testing Checklist

### Product Associations
- [ ] View list of associations
- [ ] Filter by confidence/frequency
- [ ] Search by product name
- [ ] Create manual association
- [ ] Edit association scores
- [ ] Delete single association
- [ ] Bulk delete associations
- [ ] Generate associations from orders
- [ ] View statistics dashboard

### Discount Rules
- [ ] View list of rules
- [ ] Create percentage discount rule
- [ ] Create fixed amount rule
- [ ] Edit existing rule
- [ ] Delete rule
- [ ] Toggle active/inactive
- [ ] Test rule with sample data
- [ ] Duplicate rule
- [ ] Filter by category/tier

### Analytics
- [ ] View overall statistics
- [ ] See top bundles by metric
- [ ] View funnel analysis
- [ ] Compare multiple bundles
- [ ] Export data as CSV
- [ ] Export data as JSON
- [ ] View product participation

---

## API Endpoints Reference

### Product Associations
```
GET    /admin/product-associations
POST   /admin/product-associations
PUT    /admin/product-associations/{id}
DELETE /admin/product-associations/{id}
POST   /admin/product-associations/generate
GET    /admin/product-associations/statistics
```

### Bundle Discount Rules
```
GET    /admin/bundle-discount-rules
POST   /admin/bundle-discount-rules
PUT    /admin/bundle-discount-rules/{id}
DELETE /admin/bundle-discount-rules/{id}
POST   /admin/bundle-discount-rules/{id}/toggle-active
POST   /admin/bundle-discount-rules/{id}/test
GET    /admin/bundle-discount-rules/statistics
```

### Bundle Analytics
```
GET    /admin/bundle-analytics
GET    /admin/bundle-analytics/statistics
GET    /admin/bundle-analytics/top-bundles
GET    /admin/bundle-analytics/funnel
GET    /admin/bundle-analytics/export
```

---

## Notes

- All API methods are already implemented in `src/api/extended.ts`
- Follow existing patterns from ProductList.tsx
- Use React Query for data fetching
- Use React Hot Toast for notifications
- Use existing UI components (Table, Button, Badge, Input)
- Implement proper loading states
- Add error handling
- Follow TypeScript best practices

---

**Status:** Ready for UI implementation
**Backend:** ✅ Complete
**Frontend API:** ✅ Complete
**Frontend UI:** 🚧 Needs implementation

