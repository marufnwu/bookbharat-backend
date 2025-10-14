# Carrier Warehouse/Pickup Address Requirements Analysis

## Purpose
Standardize warehouse selection across all carriers based on their API documentation requirements.

## Carrier Requirements Summary

### Type 1: Pre-Registered Warehouse ID Required
These carriers require warehouses to be registered first, then use the registered ID/alias.

#### 1. **BigShip**
- **Requires:** `pickup_location_id` (numeric warehouse ID from their system)
- **Registration:** Yes, via `POST /api/warehouse/add`
- **Get List:** `GET /api/warehouse/get/list`
- **Format:** Numeric ID (e.g., 192676)
- **Usage in Order:** 
  ```json
  {
    "warehouse_detail": {
      "pickup_location_id": 192676,
      "return_location_id": 192676
    }
  }
  ```

#### 2. **Ekart** 
- **Requires:** Registered address alias
- **Registration:** Yes, via address registration API
- **Format:** String alias (e.g., "Bright Academy")
- **Usage:** Referenced by alias in shipment creation

#### 3. **Delhivery**
- **Requires:** Registered client name / warehouse name
- **Registration:** Implicit (managed through Delhivery portal)
- **Format:** String name
- **Usage:** `pickup_location` in order payload

### Type 2: Full Address Required
These carriers accept full pickup address details directly in shipment creation.

#### 4. **Xpressbees**
- **Requires:** Full pickup address in each request
- **Registration:** No separate registration needed
- **Format:** Full address object
- **Usage:**
  ```json
  {
    "pickup_customer_name": "BookBharat",
    "pickup_customer_phone": "9876543210",
    "pickup_address": "123 Main St",
    "pickup_city": "Delhi",
    "pickup_pincode": "110001"
  }
  ```

#### 5. **DTDC**
- **Requires:** Full pickup address
- **Registration:** Optional (can pre-register for faster processing)
- **Format:** Full address object

#### 6. **BlueDart**
- **Requires:** Customer code + full address
- **Registration:** Customer code from BlueDart
- **Format:** Customer code + address details

#### 7. **Shadowfax**
- **Requires:** Full pickup address
- **Registration:** No
- **Format:** Full address object

#### 8. **Shiprocket**
- **Requires:** Full pickup address or pickup location ID
- **Registration:** Can pre-register pickup locations
- **Format:** Either full address or location ID

#### 9. **FedEx**
- **Requires:** Full pickup address
- **Registration:** Account number required
- **Format:** Full address object

#### 10. **Rapidshyp**
- **Requires:** Pickup address or hub code
- **Registration:** Can use registered hub codes
- **Format:** Hub code or full address

## Implementation Strategy

### Database Schema

#### Warehouse Table
```sql
warehouses (
    id,
    name,
    code,
    address_line_1,
    address_line_2,
    city,
    state,
    pincode,
    phone,
    contact_person,
    is_active,
    is_default
)
```

#### Carrier Warehouse Mapping
```sql
carrier_warehouse (
    id,
    carrier_id,
    warehouse_id,
    carrier_warehouse_id,      -- For carriers that use registered IDs
    carrier_warehouse_name,     -- For carriers that use aliases/names
    is_enabled,
    is_registered,              -- Whether warehouse is registered with carrier
    registration_status,        -- pending|registered|failed
    last_synced_at
)
```

### Adapter Interface Extension

Add method to indicate warehouse requirement type:

```php
interface CarrierAdapterInterface {
    /**
     * Get warehouse requirement type for this carrier
     * 
     * @return string 'registered_id'|'registered_alias'|'full_address'
     */
    public function getWarehouseRequirementType(): string;
    
    /**
     * Prepare pickup address based on carrier requirements
     * 
     * @param mixed $warehouseIdentifier Could be ID, alias, or Warehouse model
     * @return array Carrier-specific pickup address format
     */
    public function preparePickupAddress($warehouseIdentifier): array;
}
```

### Admin Panel Flow

```
1. User selects carrier
   ↓
2. Frontend calls: GET /api/admin/shipping/carriers/{carrier}/warehouses
   ↓
3. Backend checks carrier requirement type:
   - If 'registered_id' or 'registered_alias': 
     → Fetch from carrier API (getRegisteredWarehouses)
     → Return carrier-specific IDs/aliases
   - If 'full_address':
     → Return site warehouses from database
     → No carrier-specific mapping needed
   ↓
4. Admin selects warehouse from list
   ↓
5. On shipment creation:
   - If 'registered_id': Pass warehouse_id directly to carrier
   - If 'registered_alias': Pass warehouse alias/name to carrier
   - If 'full_address': Convert warehouse to full address object
```

## Implementation Changes Needed

### 1. MultiCarrierShippingService

```php
protected function getPickupAddress($warehouseIdentifier, ShippingCarrier $carrier): array
{
    $adapter = $this->carrierFactory->make($carrier);
    $requirementType = $adapter->getWarehouseRequirementType();
    
    switch ($requirementType) {
        case 'registered_id':
            // Return just the ID, let adapter handle it
            return ['warehouse_id' => $warehouseIdentifier];
            
        case 'registered_alias':
            // Return the alias, let adapter handle it
            return ['warehouse_alias' => $warehouseIdentifier];
            
        case 'full_address':
        default:
            // Convert warehouse to full address
            if (is_numeric($warehouseIdentifier)) {
                $warehouse = Warehouse::find($warehouseIdentifier);
                return $warehouse ? $warehouse->toPickupAddress() : $this->getDefaultPickupAddress();
            }
            return $this->getDefaultPickupAddress();
    }
}
```

### 2. Each Carrier Adapter

Example for BigShip:
```php
public function getWarehouseRequirementType(): string
{
    return 'registered_id';
}

public function preparePickupAddress($warehouseIdentifier): array
{
    // BigShip expects pickup_location_id
    if (is_array($warehouseIdentifier) && isset($warehouseIdentifier['warehouse_id'])) {
        return [
            'pickup_location_id' => $warehouseIdentifier['warehouse_id'],
            'return_location_id' => $warehouseIdentifier['warehouse_id']
        ];
    }
    
    // Fallback: fetch first registered warehouse
    $warehouses = $this->getRegisteredWarehouses();
    return [
        'pickup_location_id' => $warehouses['warehouses'][0]['id'] ?? null,
        'return_location_id' => $warehouses['warehouses'][0]['id'] ?? null
    ];
}
```

Example for Xpressbees:
```php
public function getWarehouseRequirementType(): string
{
    return 'full_address';
}

public function preparePickupAddress($warehouseIdentifier): array
{
    // Xpressbees expects full address
    if (is_array($warehouseIdentifier)) {
        return [
            'pickup_customer_name' => $warehouseIdentifier['name'],
            'pickup_customer_phone' => $warehouseIdentifier['phone'],
            'pickup_address' => $warehouseIdentifier['address_1'],
            'pickup_city' => $warehouseIdentifier['city'],
            'pickup_pincode' => $warehouseIdentifier['pincode'],
            'pickup_state' => $warehouseIdentifier['state']
        ];
    }
    
    throw new \Exception('Full warehouse address required for Xpressbees');
}
```

## Testing Plan

### Test Cases

1. **BigShip - Registered ID**
   - Select warehouse from registered list
   - Verify `pickup_location_id` is sent correctly
   - Confirm shipment uses correct warehouse

2. **Xpressbees - Full Address**
   - Select warehouse from database
   - Verify full address is sent to API
   - Confirm all address fields populated

3. **Mixed Scenario**
   - Create shipments with multiple carriers
   - Verify each uses correct format
   - Check fallback behavior

4. **Warehouse Registration**
   - Register new warehouse with BigShip
   - Verify it appears in dropdown
   - Test immediate usage

## Migration Path

### Phase 1: Add Interface Methods (Non-breaking)
- Add default implementation to base adapter
- Existing code continues to work

### Phase 2: Update Individual Adapters
- Implement per carrier as documented
- Test each carrier independently

### Phase 3: Update Service Layer
- Modify MultiCarrierShippingService
- Add intelligent routing based on requirement type

### Phase 4: Update Admin Panel
- Enhance warehouse selection UI
- Show registration status
- Add sync buttons for registered carriers

## Success Criteria

- ✅ All carriers create shipments with correct warehouse
- ✅ BigShip, Ekart, Delhivery use registered IDs/aliases
- ✅ Other carriers receive full addresses
- ✅ Admin panel shows appropriate warehouse options per carrier
- ✅ No hardcoded warehouse logic in adapters
- ✅ Comprehensive logging for debugging
- ✅ Graceful fallbacks when warehouse not found


