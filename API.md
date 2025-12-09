# API Documentation - Zero-Knowledge Password Manager

## Overview

This document describes the API endpoints available in the password manager application. All API endpoints require authentication via Laravel Sanctum, and all endpoints return or accept encrypted data only.

## Base URL

```
https://yourdomain.com
```

## Authentication

The application uses Laravel Sanctum for API authentication. Users must be authenticated to access vault endpoints.

### Authentication Flow

1. User logs in with email and authentication password
2. Laravel creates a session
3. Subsequent requests are authenticated via session cookies

## Rate Limiting

All vault endpoints are rate-limited to **60 requests per minute** per user to prevent abuse.

## Endpoints

### Vault Entries

#### List All Entries

```http
GET /vault/data
```

Returns all vault entries belonging to the authenticated user.

**Response:**
```json
[
  {
    "id": 1,
    "user_id": 1,
    "encrypted_payload": "base64_encoded_encrypted_data",
    "iv": "32_character_hex_string",
    "salt": "64_character_hex_string",
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z",
    "sub_keys": []
  }
]
```

**Status Codes:**
- `200 OK` - Success
- `401 Unauthorized` - Not authenticated
- `429 Too Many Requests` - Rate limit exceeded

---

#### Get Single Entry

```http
GET /vault/{id}
```

Returns a specific vault entry.

**Parameters:**
- `id` (path) - Vault entry ID

**Response:**
```json
{
  "id": 1,
  "user_id": 1,
  "encrypted_payload": "base64_encoded_encrypted_data",
  "iv": "32_character_hex_string",
  "salt": "64_character_hex_string",
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z",
  "sub_keys": [
    {
      "id": 1,
      "vault_entry_id": 1,
      "encrypted_payload": "base64_encoded_encrypted_data",
      "iv": "32_character_hex_string",
      "salt": "64_character_hex_string",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    }
  ]
}
```

**Status Codes:**
- `200 OK` - Success
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Entry belongs to another user
- `404 Not Found` - Entry not found

---

#### Create Entry

```http
POST /vault
```

Creates a new vault entry with encrypted data.

**Request Body:**
```json
{
  "encrypted_payload": "base64_encoded_encrypted_data",
  "iv": "32_character_hex_string",
  "salt": "64_character_hex_string"
}
```

**Validation Rules:**
- `encrypted_payload`: Required, string
- `iv`: Required, string, exactly 32 characters (16 bytes as hex)
- `salt`: Required, string, exactly 64 characters (32 bytes as hex)

**Example Encrypted Payload (decrypted structure):**
```json
{
  "serviceName": "Gmail",
  "username": "user@example.com",
  "password": "securePassword123!",
  "notes": "Personal email account",
  "category": "Email",
  "tags": ["personal", "important"]
}
```

**Response:**
```json
{
  "id": 1,
  "user_id": 1,
  "encrypted_payload": "base64_encoded_encrypted_data",
  "iv": "32_character_hex_string",
  "salt": "64_character_hex_string",
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

**Status Codes:**
- `201 Created` - Success
- `401 Unauthorized` - Not authenticated
- `422 Unprocessable Entity` - Validation failed

---

#### Update Entry

```http
PUT /vault/{id}
```

Updates an existing vault entry with new encrypted data.

**Parameters:**
- `id` (path) - Vault entry ID

**Request Body:**
```json
{
  "encrypted_payload": "base64_encoded_encrypted_data",
  "iv": "32_character_hex_string",
  "salt": "64_character_hex_string"
}
```

**Response:**
```json
{
  "id": 1,
  "user_id": 1,
  "encrypted_payload": "base64_encoded_encrypted_data",
  "iv": "32_character_hex_string",
  "salt": "64_character_hex_string",
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T12:00:00.000000Z"
}
```

**Status Codes:**
- `200 OK` - Success
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Entry belongs to another user
- `404 Not Found` - Entry not found
- `422 Unprocessable Entity` - Validation failed

---

#### Delete Entry

```http
DELETE /vault/{id}
```

Deletes a vault entry and all associated sub-keys.

**Parameters:**
- `id` (path) - Vault entry ID

**Response:**
```
No content
```

**Status Codes:**
- `204 No Content` - Success
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Entry belongs to another user
- `404 Not Found` - Entry not found

---

### Sub-Keys

#### Create Sub-Key

```http
POST /vault/sub-keys
```

Creates a new sub-key for a vault entry.

**Request Body:**
```json
{
  "vault_entry_id": 1,
  "encrypted_payload": "base64_encoded_encrypted_data",
  "iv": "32_character_hex_string",
  "salt": "64_character_hex_string"
}
```

**Validation Rules:**
- `vault_entry_id`: Required, must exist, must belong to authenticated user
- `encrypted_payload`: Required, string
- `iv`: Required, string, exactly 32 characters
- `salt`: Required, string, exactly 64 characters

**Example Encrypted Payload (decrypted structure):**
```json
{
  "name": "API Key",
  "value": "sk_live_abc123xyz789",
  "notes": "Production API key"
}
```

**Response:**
```json
{
  "id": 1,
  "vault_entry_id": 1,
  "encrypted_payload": "base64_encoded_encrypted_data",
  "iv": "32_character_hex_string",
  "salt": "64_character_hex_string",
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

**Status Codes:**
- `201 Created` - Success
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Vault entry belongs to another user
- `422 Unprocessable Entity` - Validation failed

---

#### Get Sub-Key

```http
GET /vault/sub-keys/{id}
```

Returns a specific sub-key.

**Parameters:**
- `id` (path) - Sub-key ID

**Response:**
```json
{
  "id": 1,
  "vault_entry_id": 1,
  "encrypted_payload": "base64_encoded_encrypted_data",
  "iv": "32_character_hex_string",
  "salt": "64_character_hex_string",
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

**Status Codes:**
- `200 OK` - Success
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Sub-key belongs to another user
- `404 Not Found` - Sub-key not found

---

#### Update Sub-Key

```http
PUT /vault/sub-keys/{id}
```

Updates an existing sub-key with new encrypted data.

**Parameters:**
- `id` (path) - Sub-key ID

**Request Body:**
```json
{
  "encrypted_payload": "base64_encoded_encrypted_data",
  "iv": "32_character_hex_string",
  "salt": "64_character_hex_string"
}
```

**Response:**
```json
{
  "id": 1,
  "vault_entry_id": 1,
  "encrypted_payload": "base64_encoded_encrypted_data",
  "iv": "32_character_hex_string",
  "salt": "64_character_hex_string",
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T12:00:00.000000Z"
}
```

**Status Codes:**
- `200 OK` - Success
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Sub-key belongs to another user
- `404 Not Found` - Sub-key not found
- `422 Unprocessable Entity` - Validation failed

---

#### Delete Sub-Key

```http
DELETE /vault/sub-keys/{id}
```

Deletes a sub-key.

**Parameters:**
- `id` (path) - Sub-key ID

**Response:**
```
No content
```

**Status Codes:**
- `204 No Content` - Success
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Sub-key belongs to another user
- `404 Not Found` - Sub-key not found

---

## Encryption Details

### Client-Side Encryption Flow

All encryption happens in the browser before data is sent to the server:

1. **Key Derivation**
   ```
   Master Password + Salt → PBKDF2 (100,000 iterations) → Encryption Key
   ```

2. **Encryption**
   ```
   Plaintext Data + Encryption Key + IV → AES-256-GCM → Encrypted Data
   ```

3. **Storage**
   ```
   {
     encrypted_payload: base64(encrypted_data),
     iv: hex(initialization_vector),
     salt: hex(salt)
   }
   ```

### Decryption Flow

1. **Retrieve encrypted data from server**
2. **Derive key from master password + salt**
3. **Decrypt using AES-256-GCM with key + IV**
4. **Parse JSON to get plaintext data**

### Data Structures

#### Vault Entry (Decrypted)
```typescript
interface VaultEntryData {
  serviceName: string;      // e.g., "Gmail"
  username: string;          // e.g., "user@example.com"
  password: string;          // The actual password
  notes?: string;            // Optional notes
  category?: string;         // e.g., "Email", "Social Media"
  tags?: string[];           // e.g., ["personal", "work"]
}
```

#### Sub-Key (Decrypted)
```typescript
interface SubKeyData {
  name: string;              // e.g., "API Key"
  value: string;             // The actual value
  notes?: string;            // Optional notes
}
```

## Error Responses

All error responses follow this format:

```json
{
  "message": "Error description",
  "errors": {
    "field_name": [
      "Validation error message"
    ]
  }
}
```

### Common Error Codes

- `400 Bad Request` - Invalid request format
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation failed
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - Server error

## Security Considerations

### HTTPS Only

All API requests MUST be made over HTTPS. HTTP requests will be rejected or redirected.

### CSRF Protection

All POST, PUT, PATCH, and DELETE requests must include a valid CSRF token. This is handled automatically by Laravel when using forms or Axios with proper configuration.

### Rate Limiting

Endpoints are rate-limited to prevent abuse:
- Vault endpoints: 60 requests/minute
- Authentication endpoints: 5 requests/minute

### Authorization

Every request to vault endpoints checks:
1. User is authenticated
2. User owns the requested resource
3. Resource exists

If any check fails, a 403 or 404 response is returned.

## Example Usage

### JavaScript Example (Using Fetch)

```javascript
// Create a new vault entry
async function createVaultEntry(encryptedData) {
  const response = await fetch('/vault', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    credentials: 'same-origin',
    body: JSON.stringify(encryptedData)
  });

  if (!response.ok) {
    throw new Error('Failed to create entry');
  }

  return await response.json();
}

// Get all vault entries
async function getVaultEntries() {
  const response = await fetch('/vault/data', {
    credentials: 'same-origin'
  });

  if (!response.ok) {
    throw new Error('Failed to fetch entries');
  }

  return await response.json();
}
```

### cURL Example

```bash
# Create entry
curl -X POST https://yourdomain.com/vault \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: your-csrf-token" \
  -d '{
    "encrypted_payload": "base64_encrypted_data",
    "iv": "0123456789abcdef0123456789abcdef",
    "salt": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"
  }' \
  --cookie "laravel_session=your-session-cookie"

# Get all entries
curl https://yourdomain.com/vault/data \
  --cookie "laravel_session=your-session-cookie"

# Delete entry
curl -X DELETE https://yourdomain.com/vault/1 \
  -H "X-CSRF-TOKEN: your-csrf-token" \
  --cookie "laravel_session=your-session-cookie"
```

## Versioning

Current API version: **v1** (implicit)

Future versions will be versioned explicitly in the URL:
- `/api/v2/vault`

## Support

For API questions or issues:
- Check the documentation files (ARCHITECTURE.md, SECURITY.md)
- Open an issue on GitHub
- Contact: support@yourdomain.com

## Changelog

### v1.0.0 (Initial Release)
- Vault entries CRUD endpoints
- Sub-keys CRUD endpoints
- Rate limiting
- CSRF protection
- Authorization checks
