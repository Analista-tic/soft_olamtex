# Security Best Practices - Zero-Knowledge Password Manager

## Table of Contents

1. [Overview](#overview)
2. [Cryptographic Implementation](#cryptographic-implementation)
3. [Backend Security](#backend-security)
4. [Frontend Security](#frontend-security)
5. [Deployment Security](#deployment-security)
6. [Operational Security](#operational-security)
7. [Incident Response](#incident-response)
8. [Security Checklist](#security-checklist)

## Overview

This document outlines security best practices for the zero-knowledge password manager. Security is paramount in a password management system, and every component must be carefully configured and maintained.

## Cryptographic Implementation

### Encryption Algorithm: AES-256-GCM

**Why AES-256-GCM?**
- Industry standard, FIPS-approved
- Provides both confidentiality and authenticity
- Protects against tampering and forgery
- Efficient and well-audited implementation in Web Crypto API

**Configuration:**
```typescript
{
  name: 'AES-GCM',
  iv: 16 bytes (128 bits) - randomly generated per encryption
  tagLength: 128 bits (default)
}
```

### Key Derivation: PBKDF2

**Why PBKDF2?**
- Widely supported and standardized
- Configurable iteration count for future-proofing
- Available in Web Crypto API

**Configuration:**
```typescript
{
  name: 'PBKDF2',
  hash: 'SHA-256',
  iterations: 100000,
  salt: 32 bytes (256 bits) - randomly generated per entry
}
```

**Important:** The iteration count should be reviewed annually and increased as computing power grows. Current recommendation: minimum 100,000 iterations.

### Random Number Generation

**Always use cryptographically secure random generation:**
```typescript
// ✅ CORRECT
crypto.getRandomValues(new Uint8Array(32))

// ❌ WRONG - Do not use
Math.random()
```

### Salt and IV Requirements

1. **Salts**: 32 bytes (256 bits)
   - Must be unique per vault entry
   - Stored alongside encrypted data
   - Does not need to be secret

2. **Initialization Vectors (IVs)**: 16 bytes (128 bits)
   - Must be unique for each encryption operation
   - Must NEVER be reused with the same key
   - Stored alongside encrypted data
   - Does not need to be secret

## Backend Security

### Authentication

1. **Separate Passwords**
   - Authentication password (for login) - stored as bcrypt hash
   - Master password (for vault) - NEVER sent to server, NEVER stored

2. **Session Management**
   ```php
   // Configure in config/session.php
   'lifetime' => 120,              // 2 hours
   'expire_on_close' => true,
   'encrypt' => true,
   'http_only' => true,
   'secure' => true,               // HTTPS only
   'same_site' => 'strict',
   ```

3. **Rate Limiting**
   ```php
   // Apply to all vault routes
   Route::middleware(['throttle:60,1'])->group(function () {
       // Vault routes here
   });
   
   // More restrictive for authentication
   Route::middleware(['throttle:5,1'])->group(function () {
       // Auth routes here
   });
   ```

### Authorization

**Always verify ownership:**
```php
// In every controller method
if ($vaultEntry->user_id !== Auth::id()) {
    abort(403, 'Unauthorized');
}
```

### Input Validation

```php
$request->validate([
    'encrypted_payload' => 'required|string',
    'iv' => 'required|string|size:32',    // 16 bytes hex = 32 chars
    'salt' => 'required|string|size:64',  // 32 bytes hex = 64 chars
]);
```

### SQL Injection Protection

- Use Eloquent ORM (parameterized queries)
- Never concatenate user input into queries
- Validate all input data

### CSRF Protection

```php
// Enabled by default in Laravel
// Ensure CSRF middleware is active
protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\VerifyCsrfToken::class,
        // ...
    ],
];
```

## Frontend Security

### Content Security Policy (CSP)

Add to `config/app.php` middleware or web server configuration:

```http
Content-Security-Policy:
    default-src 'self';
    script-src 'self' 'unsafe-inline' 'unsafe-eval';
    style-src 'self' 'unsafe-inline';
    img-src 'self' data: https:;
    font-src 'self' data:;
    connect-src 'self';
    frame-ancestors 'none';
    base-uri 'self';
    form-action 'self';
```

**Note:** Vite development server may require 'unsafe-inline' and 'unsafe-eval'. Remove these in production and use nonces instead.

### XSS Protection

1. **Use Vue's automatic escaping**
   ```vue
   <!-- ✅ CORRECT - Automatically escaped -->
   <div>{{ userData }}</div>
   
   <!-- ❌ WRONG - Unsafe -->
   <div v-html="userData"></div>
   ```

2. **Never use eval() or Function()**

3. **Sanitize any HTML if absolutely necessary**
   ```typescript
   import DOMPurify from 'dompurify';
   const clean = DOMPurify.sanitize(dirty);
   ```

### Memory Management

1. **Clear sensitive data when no longer needed**
   ```typescript
   function lock() {
       masterPassword.value = null;
       // Clear decrypted data
       entries.value.forEach(entry => {
           entry.decryptedData = undefined;
       });
   }
   ```

2. **Session timeout**
   - Automatically lock vault after 15 minutes of inactivity
   - Clear master password from memory
   - Force re-authentication

### Clipboard Security

```typescript
// Auto-clear clipboard after copying passwords
async function copyToClipboard(text: string) {
    await navigator.clipboard.writeText(text);
    
    // Clear clipboard after 60 seconds
    setTimeout(async () => {
        await navigator.clipboard.writeText('');
    }, 60000);
}
```

## Deployment Security

### HTTPS/TLS Configuration

**Mandatory: HTTPS must be enforced for all connections**

1. **Obtain SSL/TLS Certificate**
   ```bash
   # Using Let's Encrypt (free)
   sudo certbot --nginx -d yourdomain.com
   ```

2. **Nginx Configuration**
   ```nginx
   server {
       listen 443 ssl http2;
       server_name yourdomain.com;
       
       ssl_certificate /path/to/cert.pem;
       ssl_certificate_key /path/to/key.pem;
       
       # Strong SSL configuration
       ssl_protocols TLSv1.2 TLSv1.3;
       ssl_ciphers HIGH:!aNULL:!MD5;
       ssl_prefer_server_ciphers on;
       
       # HSTS
       add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
       
       # Security headers
       add_header X-Frame-Options "SAMEORIGIN" always;
       add_header X-Content-Type-Options "nosniff" always;
       add_header X-XSS-Protection "1; mode=block" always;
       add_header Referrer-Policy "no-referrer-when-downgrade" always;
   }
   
   # Redirect HTTP to HTTPS
   server {
       listen 80;
       server_name yourdomain.com;
       return 301 https://$server_name$request_uri;
   }
   ```

3. **Apache Configuration**
   ```apache
   <VirtualHost *:443>
       ServerName yourdomain.com
       
       SSLEngine on
       SSLCertificateFile /path/to/cert.pem
       SSLCertificateKeyFile /path/to/key.pem
       
       # Strong SSL configuration
       SSLProtocol -all +TLSv1.2 +TLSv1.3
       SSLCipherSuite HIGH:!aNULL:!MD5
       SSLHonorCipherOrder on
       
       # HSTS
       Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
       
       # Security headers
       Header always set X-Frame-Options "SAMEORIGIN"
       Header always set X-Content-Type-Options "nosniff"
       Header always set X-XSS-Protection "1; mode=block"
   </VirtualHost>
   
   # Redirect HTTP to HTTPS
   <VirtualHost *:80>
       ServerName yourdomain.com
       Redirect permanent / https://yourdomain.com/
   </VirtualHost>
   ```

### Environment Variables

```bash
# .env - NEVER commit this file to version control

APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:RANDOM_32_CHAR_STRING

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=secure_database_name
DB_USERNAME=limited_privilege_user
DB_PASSWORD=strong_random_password

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true

# Security
SANCTUM_STATEFUL_DOMAINS=yourdomain.com
SESSION_DOMAIN=.yourdomain.com
```

### Database Security

1. **Use separate database user with limited privileges**
   ```sql
   CREATE USER 'vault_user'@'localhost' IDENTIFIED BY 'strong_password';
   GRANT SELECT, INSERT, UPDATE, DELETE ON vault_db.* TO 'vault_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. **Enable encrypted connections**
   ```php
   // config/database.php
   'mysql' => [
       // ...
       'options' => [
           PDO::MYSQL_ATTR_SSL_CA => '/path/to/ca-cert.pem',
       ],
   ],
   ```

3. **Regular backups**
   ```bash
   # Backup script
   mysqldump -u root -p vault_db > backup_$(date +%Y%m%d).sql
   
   # Encrypt backup
   gpg --encrypt --recipient admin@yourdomain.com backup_$(date +%Y%m%d).sql
   ```

### File Permissions

```bash
# Laravel directories
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Prevent web access to sensitive files
chmod 600 .env
chmod 644 config/*.php
```

## Operational Security

### Logging and Monitoring

1. **Log security events**
   ```php
   // Successful vault unlock
   Log::info('Vault unlocked', ['user_id' => Auth::id()]);
   
   // Failed unlock attempt
   Log::warning('Failed vault unlock', ['user_id' => Auth::id()]);
   
   // Entry deletion
   Log::info('Vault entry deleted', [
       'user_id' => Auth::id(),
       'entry_id' => $entry->id
   ]);
   ```

2. **Monitor for suspicious activity**
   - Multiple failed login attempts
   - Unusual access patterns
   - Large data exports

3. **Set up alerts**
   - Email notifications for critical events
   - Integration with monitoring services (e.g., Sentry)

### Regular Security Updates

1. **Keep dependencies updated**
   ```bash
   composer update
   npm update
   ```

2. **Monitor security advisories**
   - Laravel security announcements
   - PHP security updates
   - NPM security advisories

3. **Schedule regular updates**
   - Weekly: Check for security updates
   - Monthly: Apply non-critical updates
   - Immediately: Apply critical security patches

### Penetration Testing

Conduct regular security audits:
1. Automated vulnerability scanning
2. Manual code review
3. Third-party penetration testing (recommended annually)

## Incident Response

### Data Breach Response Plan

1. **Immediate Actions**
   - Isolate affected systems
   - Preserve logs and evidence
   - Notify security team

2. **Assessment**
   - Determine scope of breach
   - Identify compromised data
   - Analyze attack vector

3. **Containment**
   - Patch vulnerabilities
   - Reset affected credentials
   - Update security measures

4. **Notification**
   - Inform affected users
   - Comply with legal requirements
   - Provide remediation guidance

5. **Post-Incident**
   - Conduct root cause analysis
   - Implement preventive measures
   - Update security procedures

### What Users Should Do

**If master password is compromised:**
1. Change master password immediately
2. Re-encrypt all vault entries with new password
3. Change all stored passwords
4. Review account activity

**If authentication password is compromised:**
1. Change authentication password immediately
2. Review recent login activity
3. Enable 2FA if available

## Security Checklist

### Pre-Deployment

- [ ] SSL/TLS certificate installed and configured
- [ ] HTTPS enforced (HTTP redirects to HTTPS)
- [ ] Content Security Policy headers configured
- [ ] HSTS enabled
- [ ] CSRF protection enabled
- [ ] Rate limiting configured
- [ ] Session security configured (secure, httpOnly, sameSite)
- [ ] Database user has minimal required privileges
- [ ] Environment variables properly configured
- [ ] APP_DEBUG=false in production
- [ ] File permissions properly set
- [ ] .env file not in version control

### Post-Deployment

- [ ] Verify HTTPS is working
- [ ] Test CSP headers
- [ ] Test rate limiting
- [ ] Test session timeout
- [ ] Verify encrypted database connections
- [ ] Set up logging and monitoring
- [ ] Configure automated backups
- [ ] Test backup restoration
- [ ] Document incident response procedures
- [ ] Schedule regular security updates

### Ongoing

- [ ] Weekly: Check for security updates
- [ ] Monthly: Review logs for suspicious activity
- [ ] Monthly: Test backups
- [ ] Quarterly: Review and update security policies
- [ ] Annually: Third-party security audit
- [ ] Annually: Update PBKDF2 iteration count if needed

## Conclusion

Security is an ongoing process, not a one-time setup. Regularly review and update security measures to protect against evolving threats. The zero-knowledge architecture provides strong protection for user data, but proper operational security is essential to maintain that protection.

## Contact

For security concerns or to report vulnerabilities, please contact:
- Email: security@yourdomain.com
- Encrypt sensitive communications with PGP key: [Key ID]

**Responsible Disclosure:** We appreciate responsible disclosure of security vulnerabilities. Please allow us 90 days to address issues before public disclosure.
