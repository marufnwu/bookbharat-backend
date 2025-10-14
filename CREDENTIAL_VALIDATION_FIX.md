# Credential Validation Fix

## Issue
The credential validation endpoint was failing because it wasn't properly reading credentials from the new `config.credentials` JSON structure.

**Error Message:**
```json
{
    "success": false,
    "message": "Email, Password, and Account ID are required",
    "details": {
        "missing_credentials": {
            "email": true,
            "password": true,
            "account_id": true
        }
    }
}
```

## Root Cause
After implementing JSON-based credential storage in `config.credentials`, three components needed updates but weren't synchronized:

1. **Controller**: `validateCredentials()` was trying to fill credentials directly into carrier model
2. **Carrier Factory**: `prepareConfig()` wasn't reading from `config.credentials`
3. **Adapters**: Expecting credentials in config array but not receiving them

## Files Fixed

### 1. MultiCarrierShippingController.php

**Location**: `app/Http/Controllers/Api/MultiCarrierShippingController.php:804-835`

**What Changed**:
- Updated `validateCredentials()` method to properly populate `config.credentials` before validation
- Reads predefined credential field structure
- Only updates allowed credential fields
- Properly sets the config on the temporary carrier instance

**Code**:
```php
// Get current config and update credentials
$config = is_array($tempCarrier->config) ? $tempCarrier->config : json_decode($tempCarrier->config, true) ?? [];

// Get predefined credential fields for this carrier
$credentialFieldStructure = $config['credential_fields'] ?? [];
$allowedCredentialKeys = array_column($credentialFieldStructure, 'key');

// Update credentials from request
if (!empty($allowedCredentialKeys)) {
    foreach ($allowedCredentialKeys as $fieldKey) {
        if ($request->has($fieldKey)) {
            $config['credentials'][$fieldKey] = $request->input($fieldKey);
        }
    }
}

// Set updated config
$tempCarrier->config = $config;
```

### 2. CarrierFactory.php

**Location**: `app/Services/Shipping/Carriers/CarrierFactory.php:103-127`

**What Changed**:
- Updated `prepareConfig()` method to merge credentials from `config.credentials`
- Reads credentials from database config
- Merges them into the adapter config array
- Applies decryption if needed

**Code**:
```php
// Get credentials from config.credentials
$dbConfig = $carrier->config;
$credentials = $dbConfig['credentials'] ?? [];

$mergedConfig = array_merge($fileConfig, [
    // ... existing fields
]);

// Merge credentials from config.credentials (new structure)
foreach ($credentials as $key => $value) {
    if (!empty($value)) {
        $mergedConfig[$key] = $this->decryptValue($value);
    }
}

return $mergedConfig;
```

## How It Works Now

### Complete Flow

```
1. Admin fills credential form (email, password, account_id)
   ↓
2. Frontend sends POST to /validate-credentials
   {
     "email": "test@xpressbees.com",
     "password": "testpass123",
     "account_id": "XB123456"
   }
   ↓
3. Controller: validateCredentials()
   - Creates temporary carrier clone
   - Reads predefined credential_fields structure
   - Populates config.credentials with request data
   - Passes temp carrier to service
   ↓
4. Service: validateCarrierCredentials()
   - Gets adapter via carrier factory
   ↓
5. Factory: prepareConfig()
   - Reads carrier.config.credentials from database
   - Merges credentials into adapter config array
   ↓
6. Adapter: XpressbeesAdapter constructor
   - Receives config with email, password
   - Calls authenticate()
   - Returns validation result
   ↓
7. Controller returns result to frontend
   {
     "success": true/false,
     "message": "...",
     "details": {...}
   }
```

## Benefits

### ✅ Consistent Architecture
- All credential access goes through `config.credentials`
- No direct column reads for carrier-specific credentials
- Single source of truth

### ✅ Validation Works Correctly
- Reads from predefined structure
- Only validates allowed fields
- Doesn't save during validation (uses temp carrier)

### ✅ Adapter Compatibility
- Adapters receive credentials in expected format
- No adapter code changes needed
- Works with all carriers (Delhivery, Xpressbees, etc.)

### ✅ Security
- Can apply encryption at credential level
- Temporary carrier doesn't persist
- Validation doesn't expose sensitive data

## Testing

### Test Validation Endpoint

**Request**:
```bash
POST /api/v1/admin/shipping/multi-carrier/carriers/3/validate-credentials
Authorization: Bearer {token}
Content-Type: application/json

{
  "email": "test@xpressbees.com",
  "password": "testpass123",
  "account_id": "XB123456"
}
```

**Expected Success Response**:
```json
{
  "success": true,
  "message": "Credentials validated successfully",
  "data": {
    "carrier_id": 3,
    "carrier_name": "Xpressbees",
    "validation_details": {...},
    "response_time": 450
  }
}
```

**Expected Failure Response** (invalid credentials):
```json
{
  "success": false,
  "message": "Authentication failed",
  "details": {
    "error": "Invalid email or password"
  }
}
```

### Test From Admin UI

1. Navigate to `/shipping` → Carriers
2. Click "Edit Credentials" on Xpressbees
3. Fill in:
   - Email: `test@xpressbees.com`
   - Password: `testpass123`
   - Account ID: `XB123456`
4. Click "Validate Credentials"
5. Should see success/failure message
6. If validation succeeds, click "Save Credentials"
7. Credentials are saved to `config.credentials`

## Related Documentation

- `CARRIER_CREDENTIAL_STRUCTURE.md` - Credential field structure system
- `CARRIER_CREDENTIALS_STORAGE.md` - How credentials are stored
- `COURIER_AUTHENTICATION_GUIDE.md` - Authentication methods per carrier
- `CARRIER_MANAGEMENT_SUMMARY.md` - Complete system overview

## Notes

- Validation creates a **temporary carrier** instance - nothing is saved to database
- Only after clicking "Save Credentials" are values persisted
- The "Validate" button tests if credentials work with the carrier's API
- If carrier is inactive, validation will fail with "Carrier is not active"
- Enable the carrier first before testing credentials

