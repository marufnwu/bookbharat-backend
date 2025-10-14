# Carrier Credential Structure Management

## Overview
Each shipping carrier has a **predefined credential structure** that is set during seeding. Admin users can **only update credential VALUES**, not add/remove credential fields. This ensures consistency and prevents configuration errors.

## How It Works

### 1. Credential Structure Definition (Backend)

Each carrier's credential structure is defined in the **ShippingCarrierSeeder**:

```php
// database/seeders/ShippingCarrierSeeder.php

private function getCredentialFieldsStructure(string $carrierCode): array
{
    $structures = [
        'XPRESSBEES' => [
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'description' => 'Registered email address'],
            ['key' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'description' => 'Account password'],
            ['key' => 'account_id', 'label' => 'Account ID', 'type' => 'text', 'required' => false, 'description' => 'Optional account identifier'],
        ],
        'DELHIVERY' => [
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'description' => 'Delhivery API Key'],
            ['key' => 'client_name', 'label' => 'Client Name', 'type' => 'text', 'required' => true, 'description' => 'Registered client name'],
        ],
        // ... more carriers
    ];
}
```

### 2. Storage in Database

The structure is stored in the `shipping_carriers` table's `config` JSON column:

```json
{
  "credential_fields": [
    {
      "key": "email",
      "label": "Email",
      "type": "email",
      "required": true,
      "description": "Registered email address"
    },
    {
      "key": "password",
      "label": "Password",
      "type": "password",
      "required": true,
      "description": "Account password"
    }
  ],
  "credentials": {
    "email": "user@example.com",
    "password": "secretpassword"
  }
}
```

### 3. Field Structure Properties

Each credential field has these properties:

| Property | Type | Description | Example |
|----------|------|-------------|---------|
| `key` | string | Field identifier (used as form field name) | `"email"`, `"api_key"` |
| `label` | string | Human-readable label for the field | `"Email"`, `"API Key"` |
| `type` | string | Input type (text, email, password, etc.) | `"password"`, `"email"` |
| `required` | boolean | Whether the field is mandatory | `true`, `false` |
| `description` | string | Help text explaining the field | `"Registered email address"` |

### 4. Admin Can Only Update Values

When admin updates credentials via the UI:

**Backend Controller Logic** (`MultiCarrierShippingController::updateCarrierConfig()`):

```php
// Get predefined credential fields for this carrier
$credentialFieldStructure = $config['credential_fields'] ?? [];
$allowedCredentialKeys = array_column($credentialFieldStructure, 'key');

// Update only the ALLOWED credential fields
if (!empty($allowedCredentialKeys)) {
    foreach ($allowedCredentialKeys as $fieldKey) {
        if ($request->has($fieldKey)) {
            $config['credentials'][$fieldKey] = $request->input($fieldKey);
        }
    }
}
```

**What This Means:**
- ✅ Admin can update `email` value
- ✅ Admin can update `password` value
- ❌ Admin CANNOT add a new `api_token` field
- ❌ Admin CANNOT remove the `email` field
- ❌ Admin CANNOT change field types or labels

### 5. Frontend Dynamic Form Generation

The admin UI reads `credential_fields` from the carrier and dynamically generates the form:

**Frontend Code** (`CarrierConfiguration.tsx`):

```typescript
const getCredentialFields = (carrier: CarrierConfig) => {
  const baseFields = [
    { key: 'api_endpoint', label: 'API Endpoint', type: 'text', required: true },
  ];

  // Use credential_fields from backend (predefined structure)
  const credentialFields = carrier.credential_fields || [];
  
  return [...baseFields, ...credentialFields];
};
```

This means:
- Form fields are **automatically generated** based on backend structure
- No need to hardcode different forms for different carriers
- Adding a new carrier just requires updating the seeder, no frontend changes

## Supported Carriers & Their Credential Structures

### Delhivery
```json
{
  "credential_fields": [
    {"key": "api_key", "label": "API Key", "type": "password", "required": true},
    {"key": "client_name", "label": "Client Name", "type": "text", "required": true}
  ]
}
```

### BlueDart
```json
{
  "credential_fields": [
    {"key": "license_key", "label": "License Key", "type": "password", "required": true},
    {"key": "login_id", "label": "Login ID", "type": "text", "required": true}
  ]
}
```

### Xpressbees
```json
{
  "credential_fields": [
    {"key": "email", "label": "Email", "type": "email", "required": true},
    {"key": "password", "label": "Password", "type": "password", "required": true},
    {"key": "account_id", "label": "Account ID", "type": "text", "required": false}
  ]
}
```

### DTDC
```json
{
  "credential_fields": [
    {"key": "access_token", "label": "Access Token", "type": "password", "required": true},
    {"key": "customer_code", "label": "Customer Code", "type": "text", "required": true}
  ]
}
```

### Ecom Express
```json
{
  "credential_fields": [
    {"key": "username", "label": "Username", "type": "text", "required": true},
    {"key": "password", "label": "Password", "type": "password", "required": true}
  ]
}
```

### Shadowfax
```json
{
  "credential_fields": [
    {"key": "api_token", "label": "API Token", "type": "password", "required": true}
  ]
}
```

### Ekart
```json
{
  "credential_fields": [
    {"key": "client_id", "label": "Client ID", "type": "text", "required": true},
    {"key": "access_key", "label": "Access Key", "type": "password", "required": true}
  ]
}
```

### Shiprocket
```json
{
  "credential_fields": [
    {"key": "email", "label": "Email", "type": "email", "required": true},
    {"key": "password", "label": "Password", "type": "password", "required": true}
  ]
}
```

### BigShip
```json
{
  "credential_fields": [
    {"key": "api_key", "label": "API Key", "type": "password", "required": true},
    {"key": "api_secret", "label": "API Secret", "type": "password", "required": true}
  ]
}
```

### RapidShyp
```json
{
  "credential_fields": [
    {"key": "api_key", "label": "API Key", "type": "password", "required": true}
  ]
}
```

## Adding a New Carrier

To add a new carrier with its own credential structure:

### Step 1: Update Config File
Add carrier to `config/shipping-carriers.php`:

```php
'mycarrier' => [
    'enabled' => env('MYCARRIER_ENABLED', false),
    'code' => 'MYCARRIER',
    'name' => 'MyCarrier',
    'display_name' => 'MyCarrier Logistics',
    'test' => [
        'api_endpoint' => 'https://api.mycarrier.com/test',
        'username' => env('MYCARRIER_TEST_USERNAME', ''),
        'api_token' => env('MYCARRIER_TEST_TOKEN', ''),
    ],
    'live' => [
        'api_endpoint' => 'https://api.mycarrier.com/v1',
        'username' => env('MYCARRIER_LIVE_USERNAME', ''),
        'api_token' => env('MYCARRIER_LIVE_TOKEN', ''),
    ],
    // ... other config
],
```

### Step 2: Update Seeder
Add credential structure to `ShippingCarrierSeeder::getCredentialFieldsStructure()`:

```php
'MYCARRIER' => [
    ['key' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => true, 'description' => 'MyCarrier account username'],
    ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password', 'required' => true, 'description' => 'MyCarrier API authentication token'],
],
```

### Step 3: Run Seeder
```bash
php artisan db:seed --class=ShippingCarrierSeeder
```

That's it! No frontend changes needed. The admin UI will automatically show the correct form fields.

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        Database (config JSON)                    │
├─────────────────────────────────────────────────────────────────┤
│ {                                                               │
│   "credential_fields": [                                        │
│     {"key": "email", "label": "Email", ...}  ← STRUCTURE        │
│   ],                                                            │
│   "credentials": {                                              │
│     "email": "user@example.com"  ← VALUES (Admin can update)   │
│   }                                                             │
│ }                                                               │
└─────────────────────────────────────────────────────────────────┘
                              ↓
                     ┌────────────────┐
                     │  API Response  │
                     └────────────────┘
                              ↓
                 ┌─────────────────────────┐
                 │  Flatten for Frontend   │
                 └─────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                     Frontend (Admin UI)                          │
├─────────────────────────────────────────────────────────────────┤
│ carrier = {                                                     │
│   id: 3,                                                        │
│   name: "Xpressbees",                                           │
│   credential_fields: [                    ← Structure for form  │
│     {"key": "email", "label": "Email", ...}                     │
│   ],                                                            │
│   email: "user@example.com",              ← Current values      │
│   password: "***"                                               │
│ }                                                               │
│                                                                 │
│ Form renders dynamically based on credential_fields             │
│ Admin can only edit VALUES (email, password)                    │
│ Admin CANNOT add/remove fields                                  │
└─────────────────────────────────────────────────────────────────┘
                              ↓
                    ┌──────────────────┐
                    │  Update Request  │
                    │  {               │
                    │   email: "new",  │
                    │   password: "pw" │
                    │  }               │
                    └──────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                   Backend Validation                             │
├─────────────────────────────────────────────────────────────────┤
│ 1. Load carrier's credential_fields structure                   │
│ 2. Extract allowed keys: ["email", "password"]                  │
│ 3. Only update values for ALLOWED keys                          │
│ 4. Ignore any extra fields sent by frontend                     │
│ 5. Save to config.credentials                                   │
└─────────────────────────────────────────────────────────────────┘
```

## Security Benefits

1. **Prevents Configuration Errors**: Admin cannot accidentally add wrong fields
2. **Maintains Data Consistency**: All carriers follow their predefined structure
3. **Easy Validation**: Backend knows exactly what fields to expect
4. **Type Safety**: Field types are enforced (email, password, text)
5. **Required Field Enforcement**: Backend can validate required fields

## Troubleshooting

### Issue: Form not showing credential fields
**Solution**: Run the seeder to populate `credential_fields`:
```bash
php artisan db:seed --class=ShippingCarrierSeeder
```

### Issue: Admin can't save new field
**Expected Behavior**: If the field is not in `credential_fields`, it will be ignored. Update the seeder to add the field.

### Issue: Credential values not persisting
**Check**: Ensure the field key matches exactly between seeder definition and form input.

### Issue: Wrong form fields showing
**Solution**: Clear the React Query cache or refresh the page to fetch the latest carrier data.

## Best Practices

1. **Never hardcode credential fields in frontend**: Always read from `carrier.credential_fields`
2. **Add new carriers via seeder**: This ensures structure is defined correctly
3. **Use descriptive field descriptions**: Help text guides admins on what to enter
4. **Mark sensitive fields as password type**: Ensures they're masked in UI
5. **Set required fields correctly**: Prevents incomplete configurations
6. **Document authentication flow**: See `COURIER_AUTHENTICATION_GUIDE.md` for each carrier's auth method

## Related Documentation

- `COURIER_AUTHENTICATION_GUIDE.md` - Detailed authentication flows for each carrier
- `CARRIER_CREDENTIALS_STORAGE.md` - How credentials are stored in database
- `config/shipping-carriers.php` - System configuration for all carriers
- `database/seeders/ShippingCarrierSeeder.php` - Seeder that defines credential structures

