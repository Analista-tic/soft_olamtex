# Zero-Knowledge Password Manager - System Architecture

## Overview

This document describes the architecture of the zero-knowledge password management system built with Laravel 12 and Vue 3. The system is designed to securely store and manage credentials while ensuring that the server never has access to plaintext passwords or the master password.

## Core Principle: Zero-Knowledge Architecture

### What is Zero-Knowledge?

Zero-knowledge architecture means that the server (backend) stores encrypted data but has no way to decrypt it. All encryption and decryption operations happen exclusively on the client side (in the user's browser).

### Key Properties

1. **Server Blindness**: The server stores only encrypted data and cannot decrypt it
2. **Client-Side Encryption**: All sensitive operations occur in the browser using Web Crypto API
3. **Master Password Privacy**: The master password never leaves the user's browser
4. **No Recovery**: If the master password is lost, data cannot be recovered (by design)

## System Components

### 1. Backend (Laravel 12)

#### Database Schema

```
users
├── id
├── name
├── email
├── password (authentication password - separate from master password)
└── timestamps

vault_entries
├── id
├── user_id (foreign key to users)
├── encrypted_payload (TEXT) - Contains encrypted JSON
├── iv (VARCHAR 32) - Initialization vector for AES-GCM
├── salt (VARCHAR 64) - Salt for PBKDF2 key derivation
└── timestamps

vault_sub_keys
├── id
├── vault_entry_id (foreign key to vault_entries)
├── encrypted_payload (TEXT) - Contains encrypted JSON
├── iv (VARCHAR 32) - Initialization vector
├── salt (VARCHAR 64) - Salt for key derivation
└── timestamps
```

#### Models

- **User**: Standard Laravel user model with authentication
- **VaultEntry**: Stores encrypted vault entries belonging to a user
- **VaultSubKey**: Stores encrypted sub-keys associated with vault entries

#### Controllers

- **VaultEntryController**: RESTful API for vault entries (CRUD operations)
- **VaultSubKeyController**: RESTful API for sub-keys (CRUD operations)

#### API Endpoints

```
POST   /vault              - Create new vault entry
GET    /vault/data         - Get all vault entries for authenticated user
GET    /vault/{id}         - Get specific vault entry
PUT    /vault/{id}         - Update vault entry
DELETE /vault/{id}         - Delete vault entry

POST   /vault/sub-keys     - Create new sub-key
GET    /vault/sub-keys/{id} - Get specific sub-key
PUT    /vault/sub-keys/{id} - Update sub-key
DELETE /vault/sub-keys/{id} - Delete sub-key
```

#### Security Middleware

- **Authentication**: Laravel Sanctum for API authentication
- **Authorization**: Each request verifies that the resource belongs to the authenticated user
- **Rate Limiting**: Protects against brute force attacks
- **CSRF Protection**: Laravel's built-in CSRF protection

### 2. Frontend (Vue 3 + TypeScript)

#### Crypto Service (`cryptoService.ts`)

The core of the zero-knowledge architecture. Implements:

- **Key Derivation**: PBKDF2 with 100,000 iterations
  ```typescript
  masterPassword + salt → PBKDF2 → encryption key
  ```

- **Encryption**: AES-256-GCM
  ```typescript
  plaintext data + encryption key + IV → encrypted payload
  ```

- **Decryption**: AES-256-GCM
  ```typescript
  encrypted payload + encryption key + IV → plaintext data
  ```

- **Password Generation**: Cryptographically secure random passwords
- **Password Strength Calculation**: Analyzes password complexity

#### Vault Store (`vaultStore.ts`)

Pinia store managing:
- Vault lock/unlock state
- Master password (in memory only, never persisted)
- Session timeout (15 minutes of inactivity)
- Vault entries and sub-keys
- Decrypted data (cleared when vault locks)

#### Vue Components

1. **Index.vue**: Main vault dashboard
2. **MasterPasswordPrompt.vue**: Dialog for entering master password
3. **VaultEntryList.vue**: Lists and displays vault entries
4. **VaultEntryForm.vue**: Form for creating/editing entries
5. **PasswordGenerator.vue**: Generates strong random passwords
6. **PasswordStrengthIndicator.vue**: Visual feedback for password strength

## Encryption Flow

### Creating a New Entry

```
1. User enters data in browser
   ↓
2. User submits form
   ↓
3. Browser generates random salt (32 bytes) and IV (16 bytes)
   ↓
4. Browser derives encryption key from master password + salt using PBKDF2
   ↓
5. Browser encrypts data using AES-256-GCM with key + IV
   ↓
6. Browser sends to server: {encrypted_payload, iv, salt}
   ↓
7. Server stores encrypted data in database
```

### Reading an Entry

```
1. Browser requests entry from server
   ↓
2. Server returns: {encrypted_payload, iv, salt}
   ↓
3. Browser derives encryption key from master password + salt using PBKDF2
   ↓
4. Browser decrypts payload using AES-256-GCM with key + IV
   ↓
5. Browser displays plaintext data to user
```

## Security Considerations

### Strengths

1. **Server Compromise**: Even if the server is compromised, encrypted data remains secure
2. **Network Sniffing**: Intercepted data is encrypted and useless without the master password
3. **Database Breach**: Stolen database contains only encrypted payloads
4. **Client-Side Security**: Uses browser's native Web Crypto API (audited, secure)

### Attack Vectors & Mitigations

1. **Keylogger/Malware on Client**
   - Mitigation: This is outside the scope of the application. Users must secure their devices.

2. **Weak Master Password**
   - Mitigation: Password strength validator, minimum requirements, visual feedback

3. **Brute Force Attacks**
   - Mitigation: PBKDF2 with 100,000 iterations makes brute force computationally expensive
   - Mitigation: Rate limiting on authentication endpoints

4. **Session Hijacking**
   - Mitigation: Session timeout after 15 minutes of inactivity
   - Mitigation: Secure cookies, HTTPS only

5. **XSS Attacks**
   - Mitigation: Vue's automatic HTML escaping
   - Mitigation: Content Security Policy headers
   - Mitigation: No eval() or innerHTML usage

### Password Recovery

**There is NO password recovery by design.** This is a fundamental property of zero-knowledge architecture:

- The master password is never sent to the server
- The server has no way to decrypt data
- If the master password is lost, all vault data is permanently inaccessible
- Users must be clearly warned about this during setup

## Technology Stack

### Backend
- **Framework**: Laravel 12
- **Database**: MySQL/PostgreSQL
- **Authentication**: Laravel Sanctum
- **Language**: PHP 8.2+

### Frontend
- **Framework**: Vue 3 (Composition API)
- **Language**: TypeScript
- **State Management**: Pinia
- **Build Tool**: Vite
- **Styling**: Tailwind CSS
- **Rendering**: Inertia.js (SPA-like experience)

### Cryptography
- **Web Crypto API**: Browser-native cryptographic functions
- **Algorithm**: AES-256-GCM for encryption
- **Key Derivation**: PBKDF2 with SHA-256, 100,000 iterations
- **Random Generation**: crypto.getRandomValues() for salts and IVs

## Data Flow Diagram

```
┌─────────────────────┐
│   User's Browser    │
│  (Vue 3 Frontend)   │
│                     │
│  ┌──────────────┐   │
│  │ Master Pwd   │   │ ← Never sent to server
│  │ (in memory)  │   │
│  └──────────────┘   │
│         ↓           │
│  ┌──────────────┐   │
│  │   Crypto     │   │ ← All encryption/decryption here
│  │   Service    │   │
│  └──────────────┘   │
│         ↓           │
│  Encrypted Data     │
└─────────┬───────────┘
          │ HTTPS
          ↓
┌─────────────────────┐
│   Laravel Server    │
│                     │
│  ┌──────────────┐   │
│  │ Controllers  │   │ ← Only handles encrypted data
│  └──────────────┘   │
│         ↓           │
│  ┌──────────────┐   │
│  │   Database   │   │ ← Only stores encrypted data
│  │   (MySQL)    │   │
│  └──────────────┘   │
└─────────────────────┘
```

## Future Enhancements

1. **Multi-User Support**
   - Role-based access control
   - Shared vaults between users
   - Audit logs

2. **Advanced Features**
   - Password expiration warnings
   - Breach detection integration
   - Two-factor authentication for vault unlock
   - Biometric authentication support

3. **Import/Export**
   - Import from other password managers
   - Secure export functionality

4. **Browser Extension**
   - Auto-fill passwords
   - Quick access from browser toolbar

## Compliance & Standards

- **OWASP Top 10**: Addressed through security best practices
- **GDPR**: User data is encrypted, user controls their data
- **SOC 2**: Secure storage and transmission of sensitive data

## Conclusion

This zero-knowledge password manager provides a secure, privacy-focused solution for credential management. By ensuring that all sensitive operations occur client-side, we guarantee that even in the event of a server compromise, user data remains protected.
