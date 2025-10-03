# Multi-Carrier Rate Fetching - Complete Guide

## How Rate Fetching Works

The system fetches rates from multiple carriers **in parallel** for maximum performance. Here's the complete flow:

## 1. Rate Fetching Flow

```
User Request → MultiCarrierShippingService → Parallel API Calls → Aggregated Response
     ↓                    ↓                           ↓                    ↓
Order Details    Check Eligibility         All Carriers Called    Ranked & Sorted
                 Check Cache               Simultaneously         Best Option First
```

## 2. Detailed Implementation for Each Carrier

### **Delhivery**
```php
// API Endpoint: https://track.delhivery.com/api/kinko/v1/invoice/charges/.json

// Request
GET /api/kinko/v1/invoice/charges/.json
Headers:
  Authorization: Token YOUR_API_TOKEN

Parameters:
  md: 'Pre-paid' or 'COD'          // Payment mode
  cgm: 2500                         // Weight in grams
  o_pin: '110001'                   // Origin pincode
  d_pin: '400001'                   // Destination pincode
  v: 1500                          // Order value

// Response
{
  "0": {
    "total_amount": 120,
    "fuel_surcharge": 15,
    "gst_amount": 18,
    "cod_charges": 0,
    "docket_charge": 5
  }
}
```

### **Blue Dart**
```php
// API Endpoint: https://api.bluedart.com/v1/rate

// Request
POST /v1/rate
Headers:
  LicenseKey: YOUR_LICENSE_KEY
  LoginID: YOUR_LOGIN_ID

Body:
{
  "OriginArea": "DEL",
  "DestinationArea": "BOM",
  "Weight": 2.5,
  "PaymentMode": "PPD",         // PPD=Prepaid, COD=Cash on Delivery
  "ProductType": "A"             // A=Air, S=Surface
}

// Response
{
  "BaseRate": 100,
  "FuelSurcharge": 12,
  "ServiceTax": 15,
  "TotalAmount": 127,
  "ExpectedDays": 2
}
```

### **Xpressbees**
```php
// API Endpoint: https://ship.xpressbees.com/api/shipments/charges

// First authenticate to get JWT token
POST /api/users/login
{
  "email": "your_email",
  "password": "your_password"
}

// Then get rates
POST /api/shipments/charges
Headers:
  Authorization: Bearer JWT_TOKEN

Body:
{
  "pickup_pincode": "110001",
  "drop_pincode": "400001",
  "weight": 2.5,
  "payment_type": "prepaid",
  "invoice_value": 1500
}

// Response
{
  "data": {
    "standard_charge": 110,
    "express_charge": 150,
    "cod_charges": 50
  }
}
```

### **DTDC**
```php
// API Endpoint: https://api.dtdc.com/dtdcApi/api/Calculator/PincodeApiCall

// Request
POST /dtdcApi/api/Calculator/PincodeApiCall
Headers:
  api-key: YOUR_API_KEY

Body:
{
  "origin_pincode": "110001",
  "dest_pincode": "400001",
  "weight": 2500,              // In grams
  "payment_mode": "PREPAID",
  "service_type": "EXPRESS"
}

// Response
{
  "status": "success",
  "data": {
    "freight_charge": 95,
    "fuel_surcharge": 10,
    "gst": 14,
    "total": 119,
    "delivery_time": "3-4 days"
  }
}
```

### **FedEx**
```php
// API Endpoint: https://apis.fedex.com/rate/v1/rates/quotes

// Request
POST /rate/v1/rates/quotes
Headers:
  Authorization: Bearer OAUTH_TOKEN
  Content-Type: application/json

Body:
{
  "accountNumber": {
    "value": "YOUR_ACCOUNT"
  },
  "requestedShipment": {
    "shipper": {
      "address": {
        "postalCode": "110001",
        "countryCode": "IN"
      }
    },
    "recipient": {
      "address": {
        "postalCode": "400001",
        "countryCode": "IN"
      }
    },
    "requestedPackageLineItems": [{
      "weight": {
        "value": 2.5,
        "units": "KG"
      }
    }],
    "serviceType": "FEDEX_EXPRESS"
  }
}

// Response
{
  "output": {
    "rateReplyDetails": [{
      "serviceType": "FEDEX_EXPRESS",
      "totalNetCharge": 250,
      "deliveryTimestamp": "2024-01-15T10:00:00"
    }]
  }
}
```

### **Ekart (Flipkart)**
```php
// API Endpoint: https://api.ekartlogistics.com/v2/shipments/rate

// Request
POST /v2/shipments/rate
Headers:
  Authorization: YOUR_API_KEY
  seller-id: YOUR_SELLER_ID

Body:
{
  "origin_pin": "110001",
  "destination_pin": "400001",
  "weight": 2.5,
  "payment_type": "prepaid",
  "declared_value": 1500
}

// Response
{
  "success": true,
  "rate": {
    "base_price": 85,
    "fuel_charge": 8,
    "gst": 13,
    "total": 106,
    "sla_days": 3
  }
}
```

### **Rapidshyp (Multi-carrier Aggregator)**
```php
// API Endpoint: https://api.rapidshyp.com/v1/getRate

// Request
POST /v1/getRate
Headers:
  api-key: YOUR_API_KEY
  api-secret: YOUR_API_SECRET

Body:
{
  "from_pincode": "110001",
  "to_pincode": "400001",
  "weight": 2.5,
  "cod": false,
  "invoice_value": 1500,
  "length": 30,
  "width": 20,
  "height": 10
}

// Response - Returns multiple carrier options
{
  "status": "success",
  "carriers": [
    {
      "courier_name": "Delhivery",
      "rate": 120,
      "eta": 3
    },
    {
      "courier_name": "Bluedart",
      "rate": 130,
      "eta": 2
    },
    {
      "courier_name": "Xpressbees",
      "rate": 110,
      "eta": 4
    }
  ]
}
```

### **Bigship (Multi-carrier Aggregator)**
```php
// API Endpoint: https://app.bigship.in/api/v1/ratecalculator

// Request
POST /api/v1/ratecalculator
Headers:
  Authorization: Bearer YOUR_TOKEN

Body:
{
  "from_pincode": "110001",
  "to_pincode": "400001",
  "weight": 2500,              // In grams
  "length": 30,
  "width": 20,
  "height": 10,
  "payment_mode": "prepaid",
  "product_value": 1500
}

// Response - Returns all available carriers
{
  "data": {
    "available_couriers": [
      {
        "courier_company_id": "1",
        "courier_name": "Delhivery Surface",
        "freight_charge": 90,
        "cod_charges": 0,
        "fuel_surcharge": 10,
        "others": 5,
        "cgst": 9,
        "sgst": 9,
        "total_charge": 123,
        "expected_delivery_days": 4
      },
      {
        "courier_company_id": "2",
        "courier_name": "Xpressbees",
        "total_charge": 115,
        "expected_delivery_days": 3
      }
    ]
  }
}
```

## 3. Parallel Processing Implementation

```php
// MultiCarrierShippingService.php

protected function fetchRatesFromCarriers(Collection $carriers, array $shipment): Collection
{
    $rates = collect();
    $promises = [];

    // Create async requests for all carriers
    foreach ($carriers as $carrier) {
        $adapter = $this->carrierFactory->make($carrier);

        // Each adapter returns a Guzzle Promise for parallel execution
        $promises[$carrier->id] = $adapter->getRateAsync($shipment);
    }

    // Execute all requests simultaneously
    $responses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

    // Process all responses
    foreach ($responses as $carrierId => $response) {
        if ($response['state'] === 'fulfilled') {
            $carrierRates = $this->parseCarrierResponse(
                $carrierId,
                $response['value']
            );
            $rates = $rates->merge($carrierRates);
        }
    }

    return $rates;
}
```

## 4. How Admin Sees It

When an admin creates a shipment:

1. **Opens Order Details**
2. **Clicks "Create Shipment"**
3. **System automatically**:
   - Calls all 8+ carriers simultaneously
   - Gets rates in ~1-2 seconds (parallel processing)
   - Shows comparison table

4. **Admin sees**:
```
┌─────────────────────────────────────────────────────────────┐
│ Carrier         Service      Price    Days   Features       │
├─────────────────────────────────────────────────────────────┤
│ Xpressbees      Standard     ₹110     4      ⭐⭐⭐⭐        │
│ DTDC            Express      ₹119     3      ⭐⭐⭐          │
│ Delhivery       Surface      ₹120     4      ⭐⭐⭐⭐⭐      │
│ Delhivery       Express      ₹150     2      ⭐⭐⭐⭐⭐ ⚡   │
│ Blue Dart       Premium      ₹127     2      ⭐⭐⭐⭐ ⚡     │
│ FedEx           Express      ₹250     1      ⭐⭐⭐⭐⭐ ⚡⚡  │
└─────────────────────────────────────────────────────────────┘
  ✓ Cheapest  ⚡ Fastest  ⭐ Rating  📦 Recommended
```

## 5. Configuration Needed

### Step 1: Add Carriers to Database
```sql
INSERT INTO shipping_carriers (code, name, display_name, api_endpoint, api_key, api_secret, api_mode, is_active) VALUES
('delhivery', 'Delhivery', 'Delhivery Express', 'https://staging-express.delhivery.com', 'test_key', NULL, 'test', 1),
('bluedart', 'Blue Dart', 'Blue Dart Express', 'https://api.bluedart.com', 'license_key', 'login_id', 'test', 1),
('xpressbees', 'Xpressbees', 'Xpressbees', 'https://shipuat.xpressbees.com/api', 'email', 'password', 'test', 1),
('dtdc', 'DTDC', 'DTDC Express', 'https://api.dtdc.com', 'api_key', NULL, 'test', 1),
('fedex', 'FedEx', 'FedEx Express', 'https://apis-sandbox.fedex.com', 'client_id', 'client_secret', 'test', 1),
('ekart', 'Ekart', 'Ekart Logistics', 'https://api.ekartlogistics.com', 'api_key', 'seller_id', 'test', 1),
('rapidshyp', 'Rapidshyp', 'Rapidshyp', 'https://api.rapidshyp.com', 'api_key', 'api_secret', 'test', 1),
('bigship', 'Bigship', 'Bigship', 'https://app.bigship.in', 'token', NULL, 'test', 1);
```

### Step 2: Add Services for Each Carrier
```sql
-- Delhivery Services
INSERT INTO carrier_services (carrier_id, service_code, service_name, mode, min_delivery_hours, max_delivery_hours) VALUES
(1, 'SURFACE', 'Surface Express', 'surface', 72, 96),
(1, 'EXPRESS', 'Air Express', 'air', 48, 72);

-- Similar for other carriers...
```

### Step 3: Configure Rate Cards (Optional)
```sql
-- If you want to cache/override carrier rates
INSERT INTO carrier_rate_cards (carrier_service_id, zone_code, weight_min, weight_max, base_rate) VALUES
(1, 'A', 0, 0.5, 50),
(1, 'A', 0.5, 1, 60),
(1, 'B', 0, 0.5, 70);
```

## 6. Rate Caching Strategy

```php
// Rates are cached for 5 minutes to reduce API calls
$cacheKey = md5("rates_{$pickup}_{$delivery}_{$weight}_{$payment}");

// Check cache first
if ($cached = Cache::get($cacheKey)) {
    return $cached;
}

// Fetch fresh rates
$rates = $this->fetchFromCarriers();

// Cache for 5 minutes
Cache::put($cacheKey, $rates, 300);
```

## 7. Error Handling

Each carrier adapter handles errors gracefully:

```php
try {
    $response = $this->callCarrierAPI();
    return $this->parseResponse($response);
} catch (\Exception $e) {
    Log::error("Carrier {$this->name} failed", ['error' => $e->getMessage()]);
    return [];  // Return empty array, other carriers will still work
}
```

## 8. Benefits of This Approach

1. **Speed**: All carriers called simultaneously (1-2 seconds total)
2. **Reliability**: If one carrier fails, others still work
3. **Flexibility**: Easy to add/remove carriers
4. **Cost Optimization**: Always shows cheapest option
5. **No Vendor Lock-in**: Can switch carriers anytime
6. **Scalable**: Can handle 100+ carriers

## 9. Testing the System

```bash
# Test rate fetching
curl -X POST http://localhost:8000/api/shipping/rates/compare \
  -H "Content-Type: application/json" \
  -d '{
    "pickup_pincode": "110001",
    "delivery_pincode": "400001",
    "weight": 2.5,
    "order_value": 1500,
    "payment_mode": "prepaid"
  }'
```

## 10. Production Considerations

1. **API Rate Limits**: Each carrier has limits (usually 100-1000 requests/min)
2. **Timeout Settings**: Set reasonable timeouts (3-5 seconds per carrier)
3. **Fallback Options**: Always have 2-3 backup carriers
4. **Monitoring**: Track API response times and success rates
5. **Cost Tracking**: Log actual vs quoted prices for reconciliation

This system ensures you always get the best rates from multiple carriers with minimal latency!