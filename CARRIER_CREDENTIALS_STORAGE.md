# Carrier Credentials Storage Architecture

## Overview
This document explains how carrier credentials are stored and managed in the multi-carrier shipping system.

## Storage Strategy

### Database Storage
All carrier-specific credentials are stored in the `shipping_carriers` table's `config` JSON column under a `credentials` key. This provides maximum flexibility as different carriers require different authentication fields.

```json
{
  "credentials": {
    "api_key": "...",
    "api_secret": "...",
    "email": "...",
    "password": "...",
    "client_id": "...",
    "account_id": "...",
    // ... any other carrier-specific fields
  },
  "features": [...],
  "services": [...],
  // ... other config data
}
```

### Backward Compatibility
For backward compatibility and common use cases, three credential fields remain as direct columns:
- `api_key`
- `api_secret`
- `client_name`

When these fields are updated, they are stored in BOTH the direct column AND the `config.credentials` object.

## API Layer

### Saving Credentials (Backend → Database)
When the admin updates carrier credentials via `PUT /api/admin/carriers/{id}`:

1. All credential fields from the request are stored in `config.credentials`
2. Common fields (`api_key`, `api_secret`, `client_name`) are also saved to their direct columns
3. The `config` JSON is saved to the database

**Code Location**: `app/Http/Controllers/Api/MultiCarrierShippingController.php::updateCarrierConfig()`

```php
// Store ALL credentials in config JSON for flexibility
$credentialFields = [
    'api_key', 'api_secret', 'api_token', 'client_name',
    'email', 'password', 'username', 'client_id', 'access_key', 'account_id',
    'license_key', 'login_id', 'access_token', 'customer_code'
];

foreach ($credentialFields as $field) {
    if ($request->has($field)) {
        $config['credentials'][$field] = $request->input($field);
    }
}

$carrier->config = $config;
$carrier->save();
```

### Retrieving Credentials (Database → API → Frontend)
When the admin fetches carriers via `GET /api/admin/carriers`:

1. Carriers are loaded from database
2. The `config.credentials` object is read
3. All credentials are **flattened** to the carrier root level for easy frontend access
4. Response includes credentials at root level (e.g., `carrier.email`, `carrier.password`)

**Code Location**: `app/Http/Controllers/Api/MultiCarrierShippingController.php::getCarriers()`

```php
// Flatten credentials from config.credentials to carrier root level for frontend
if (isset($config['credentials']) && is_array($config['credentials'])) {
    foreach ($config['credentials'] as $key => $value) {
        $carrier->{$key} = $value;
    }
}
```

## Model Layer

### ShippingCarrier Model Helpers
The `ShippingCarrier` model provides helper methods for working with credentials:

**Location**: `app/Models/ShippingCarrier.php`

#### `getCredential(string $key, $default = null)`
Get a single credential value, checking both `config.credentials` and direct columns.

```php
// Usage
$apiKey = $carrier->getCredential('api_key');
$email = $carrier->getCredential('email');
```

#### `getCredentials(): array`
Get all credentials as an array, merging `config.credentials` with direct columns.

```php
// Usage
$allCredentials = $carrier->getCredentials();
```

#### `getCredentialsForDisplay(): array`
Get credentials with sensitive values masked (shows first 4 and last 4 characters only).

```php
// Usage
$maskedCredentials = $carrier->getCredentialsForDisplay();
// Result: { "api_key": "abcd...xyz9" }
```

## Frontend Integration

### How the Admin UI Works
The admin UI (`bookbharat-admin/src/pages/Shipping/CarrierConfiguration.tsx`) works with credentials at the carrier root level:

1. **Reading Credentials**: Form reads from `carrier.email`, `carrier.password`, etc.
2. **Editing Credentials**: Form sends credentials as flat object
3. **Updating Cache**: After save, the full carrier object (with flattened credentials) is cached

```typescript
// Form initialization
const initialData: Record<string, string> = {};
fields.forEach(field => {
  initialData[field.key] = carrier[field.key] || ''; // Reads from root level
});

// Save mutation
updateCredentialsMutation.mutate({
  carrierId: carrier.id,
  credentials: formData // Sends flat object
});
```

## Benefits of This Approach

1. **Flexibility**: Can support any carrier with any authentication method
2. **Type Safety**: Each carrier can have its own credential schema
3. **Security**: All credentials in one encrypted JSON column (can add column-level encryption)
4. **Backward Compatible**: Existing code using direct columns still works
5. **Frontend Simplicity**: Admin UI works with flat structure, no nested access needed
6. **Maintainability**: Adding new carriers doesn't require database migrations

## Security Considerations

### Current Implementation
- Credentials are stored in plain JSON
- API responses include full credential values
- Direct database column values are accessible

### Recommended Production Enhancements
1. Enable Laravel's encryption for the `config` column
2. Encrypt direct credential columns (`api_key`, `api_secret`)
3. Add API response transformers to mask sensitive data
4. Implement audit logging for credential access
5. Add rate limiting for credential validation endpoints

### Future Enhancement
Consider implementing a dedicated `carrier_credentials` table with column-level encryption:

```sql
CREATE TABLE carrier_credentials (
    id BIGINT PRIMARY KEY,
    carrier_id BIGINT REFERENCES shipping_carriers(id),
    credential_key VARCHAR(255),
    credential_value TEXT ENCRYPTED,
    is_test_mode BOOLEAN,
    last_validated_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## Carrier Service Integration

When carrier services need to authenticate, they should use the model helper methods:

```php
// In a carrier service class
class XpressbeesService {
    public function authenticate(ShippingCarrier $carrier) {
        $email = $carrier->getCredential('email');
        $password = $carrier->getCredential('password');
        $accountId = $carrier->getCredential('account_id');
        
        // Use credentials for API call
        return $this->login($email, $password, $accountId);
    }
}
```

## Testing

When testing carriers in the admin UI:
1. Click "Edit Credentials" for a carrier
2. Fill in the required credential fields
3. Click "Save" to store in database
4. Click "Test Connection" to validate credentials
5. Reopen "Edit Credentials" to verify values persisted

The update modal should now correctly show the saved credential values.

