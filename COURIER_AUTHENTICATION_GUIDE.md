# Courier Authentication & Credentials Guide

This document provides a comprehensive overview of authentication methods and required credentials for all supported courier partners.

## Table of Contents
1. [Delhivery Express](#delhivery-express)
2. [Xpressbees](#xpressbees)
3. [BigShip (Multi-Courier Aggregator)](#bigship)
4. [Ekart Logistics](#ekart-logistics)
5. [RapidShyp (Multi-Courier Aggregator)](#rapidshyp)
6. [Summary Table](#summary-table)

---

## Delhivery Express

### Authentication Method
**Token-Based Authentication**

### Required Credentials
1. **API Token** (12-16 character string)
   - Format: `Token XXXXXXXXXXXXXX`
   - Sent in: `Authorization` header
   - Different tokens for Test vs Production

2. **Client Name** (HQ name)
   - Used for account identification

### How to Obtain
Contact Delhivery Business Development (BD) or Customer Service (CS) manager to get:
- Client account name (HQ name)
- Username
- Client ID
- API Token

### Environments
- **Staging/Test**: `https://staging-express.delhivery.com`
- **Production**: `https://track.delhivery.com`

### Authentication Header Example
```
Authorization: Token abc123xyz456789
```

### Security Notes
- Token must be included in ALL API requests
- Different tokens for staging and production
- Tokens should NEVER be shared

---

## Xpressbees

### Authentication Method
**Email/Password Login with Bearer Token**

### Required Credentials
1. **Email** (string)
   - Your Xpressbees account email
   - Example: `abc@mail.com`

2. **Password** (string)
   - Your Xpressbees account password

3. **Account ID** (optional but recommended)
   - Used for tracking and identification

### How to Authenticate
**Step 1**: Login to get token
- **Endpoint**: `POST https://shipment.xpressbees.com/api/users/login`
- **Body**:
  ```json
  {
    "email": "abc@gmail.com",
    "password": "12376767"
  }
  ```
- **Response**: Returns authorization token

**Step 2**: Use token for subsequent requests
- **Header**: `Authorization: Bearer <token>`
- Token obtained from login response

### Environments
- **Base URL**: `https://shipment.xpressbees.com/api`

### Token Lifespan
- Tokens expire after a certain period
- Need to re-authenticate when token expires
- Implement token refresh mechanism

---

## BigShip

### Authentication Method
**3-Factor Login: Username + Password + Access Key → Bearer Token**

### Required Credentials
1. **Username/Email** (string)
   - Your BigShip login email
   - Example: `dummy@gmail.com`

2. **Password** (string)
   - Your BigShip login password
   - Example: `12BUG&*fnuv`

3. **Access Key** (string)
   - Unique 64-character API access key
   - Example: `6e2ac11edab7145109d4cb6cf214c4e1nhj878bbh99f690a1bdc5227632987813`

### How to Authenticate
**Step 1**: Login to generate token
- **Endpoint**: `POST https://api.bigship.in/api/login/user`
- **Body**:
  ```json
  {
    "user_name": "dummy@gmail.com",
    "password": "12BUG&*fnuv",
    "access_key": "6e2ac11edab7145109d4cb6cf214c4e1nhj878bbh99f690a1bdc5227632987813"
  }
  ```
- **Response**:
  ```json
  {
    "data": {
      "token": "eyJhbGciOiJIUzUxMiIsInR5cCI6IkpXVCJ9..."
    },
    "success": true,
    "message": "Token Generated Successfully",
    "responseCode": 200
  }
  ```

**Step 2**: Use token for all API requests
- **Header**: `Authorization: Bearer {token}`

### Token Details
- **Expiration**: 12 hours
- Must re-authenticate after expiration
- Different tokens for different environments

### API Rate Limiting
- **Limit**: 100 requests per minute per IP address
- **Error**: HTTP 429 (Too Many Requests) when exceeded

---

## Ekart Logistics

### Authentication Method
**Client ID + Username/Password → Bearer Token**

### Required Credentials
1. **Client ID** (string)
   - Provided by Ekart during onboarding
   - Used in API endpoint path

2. **Username** (string)
   - Ekart account username

3. **Password** (string)
   - Ekart account password

### How to Authenticate
**Step 1**: Get access token
- **Endpoint**: `POST https://app.elite.ekartlogistics.in/integrations/v2/auth/token/{client_id}`
- **Path Parameter**: `client_id`
- **Body**:
  ```json
  {
    "username": "your_username",
    "password": "your_password"
  }
  ```
- **Response**:
  ```json
  {
    "access_token": "abc123",
    "token_type": "Bearer",
    "expires_in": 86400,
    "scope": "core:all"
  }
  ```

**Step 2**: Use token for API requests
- **Header**: `Authorization: Bearer {access_token}`
- **Format**: `Authorization: {token_type} {access_token}`

### Token Details
- **Expiration**: 24 hours (86400 seconds)
- **Caching**: API returns same token for 24h, `expires_in` decreases with each fetch
- Works with both V1 and V2 APIs

### Base URL
- **Production**: `https://app.elite.ekartlogistics.in`

---

## RapidShyp

### Authentication Method
**API Key Header Authentication**

### Required Credentials
1. **API Key** (string)
   - Long alphanumeric string
   - Example: `e779a4*************8b60ba5f09ecd579fa1f34b64805e`

### How to Obtain
1. Login to RapidShyp Portal
2. Navigate to: **Settings > API > Configure**
3. Generate new API key
4. Save the generated key

### How to Authenticate
**Single-Step**: Include API key in header
- **Header Name**: `rapidshyp-token`
- **Header Value**: Your API key
- **Example**:
  ```
  rapidshyp-token: e779a4*************8b60ba5f09ecd579fa1f34b64805e
  ```

### Base URL
- **Production**: `https://api.rapidshyp.com/rapidshyp/apis/v1`

### Example Request
```bash
curl --location 'https://api.rapidshyp.com/rapidshyp/apis/v1/serviceabilty_check' \
--header 'rapidshyp-token: e779a4*************8b60ba5f09ecd579fa1f34b64805e' \
--header 'Content-Type: application/json' \
--data '{
  "Pickup_pincode": "110068",
  "Delivery_pincode": "110038",
  "cod": true,
  "total_order_value": 2000,
  "weight": 1
}'
```

---

## Summary Table

| Courier | Auth Method | Credentials Required | Token Expiry | Header Format |
|---------|-------------|---------------------|--------------|---------------|
| **Delhivery** | Token-based | • API Token<br>• Client Name | No expiry | `Authorization: Token {token}` |
| **Xpressbees** | Email/Password → Token | • Email<br>• Password<br>• Account ID (optional) | Session-based | `Authorization: Bearer {token}` |
| **BigShip** | 3-Factor Login → Token | • Username/Email<br>• Password<br>• Access Key | 12 hours | `Authorization: Bearer {token}` |
| **Ekart** | Client ID + Creds → Token | • Client ID<br>• Username<br>• Password | 24 hours | `Authorization: Bearer {token}` |
| **RapidShyp** | API Key Header | • API Key | No expiry | `rapidshyp-token: {key}` |

---

## Implementation Recommendations

### 1. **Token Management Strategy**

For services requiring login (Xpressbees, BigShip, Ekart):
- Implement token caching with expiry tracking
- Auto-refresh tokens before expiration
- Store tokens securely (encrypted)
- Handle token refresh on 401 Unauthorized responses

### 2. **Credential Storage**

- **Database Fields Required**:
  - `api_endpoint` - Base URL
  - `api_key` or `api_token` - For static token services
  - `email` - For login-based services
  - `password` - For login-based services (encrypted)
  - `client_id` - For Ekart
  - `client_name` - For Delhivery
  - `access_key` - For BigShip
  - `account_id` - For Xpressbees (optional)

### 3. **Authentication Flow**

```
┌─────────────────┐
│ Static Token    │  Delhivery, RapidShyp
│ (Direct Use)    │  → Use in every request
└─────────────────┘

┌─────────────────┐
│ Login Required  │  Xpressbees, BigShip, Ekart
│ (Token Flow)    │  → Login → Get Token → Cache → Use
└─────────────────┘
```

### 4. **Error Handling**

All services should handle:
- `401 Unauthorized` - Invalid/expired token → Re-authenticate
- `403 Forbidden` - Insufficient permissions
- `429 Too Many Requests` - Rate limit exceeded (BigShip: 100/min)
- `400 Bad Request` - Invalid credentials/parameters

### 5. **Test vs Production**

Most services have separate credentials for:
- **Test/Staging Environment** - For development/testing
- **Production Environment** - For live shipments

Ensure proper environment management in configuration.

---

## Database Schema Recommendation

```sql
-- Shipping Carriers Table
CREATE TABLE shipping_carriers (
    id BIGINT PRIMARY KEY,
    code VARCHAR(50) UNIQUE,
    name VARCHAR(255),
    
    -- Static Token Auth (Delhivery, RapidShyp)
    api_token VARCHAR(255),
    api_key VARCHAR(255),
    client_name VARCHAR(255),
    
    -- Login-based Auth (Xpressbees, BigShip, Ekart)
    email VARCHAR(255),
    password VARCHAR(255), -- Encrypted
    username VARCHAR(255),
    client_id VARCHAR(255),
    access_key VARCHAR(255),
    account_id VARCHAR(255),
    
    -- Token Caching (for login-based)
    cached_token TEXT,
    token_expires_at TIMESTAMP,
    
    -- Common
    api_endpoint VARCHAR(255),
    api_mode ENUM('test', 'live'),
    webhook_url VARCHAR(255),
    is_active BOOLEAN DEFAULT false,
    is_primary BOOLEAN DEFAULT false
);
```

---

## Next Steps

1. ✅ Implement token caching service for login-based carriers
2. ✅ Create authentication middleware per carrier type
3. ✅ Add token refresh mechanism
4. ✅ Implement secure credential encryption
5. ✅ Add admin UI for credential management (DONE)
6. ✅ Test authentication for each carrier
7. ✅ Monitor token expiration and auto-refresh

---

*Last Updated: October 12, 2025*

