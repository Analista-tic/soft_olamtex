# Zero-Knowledge Password Manager

A secure, self-hosted password management system built with Laravel 12 and Vue 3, featuring **zero-knowledge architecture** where the server never has access to your plaintext passwords or master password.

## 🔐 Key Features

### Security
- **Zero-Knowledge Architecture**: All encryption/decryption happens client-side
- **AES-256-GCM Encryption**: Industry-standard encryption algorithm
- **PBKDF2 Key Derivation**: 100,000 iterations for strong password protection
- **Master Password**: Never transmitted or stored on the server
- **Individual Encryption**: Each vault entry has its own salt and IV
- **Session Timeout**: Auto-lock after 15 minutes of inactivity
- **Rate Limiting**: Protection against brute force attacks
- **Security Headers**: CSP, HSTS, and other security headers configured

### Functionality
- **Vault Entries Management**: Create, read, update, and delete password entries
- **Sub-Keys Support**: Store multiple credentials per service (e.g., API keys, backup accounts)
- **Password Generator**: Generate strong, cryptographically secure passwords
- **Password Strength Indicator**: Visual feedback on password security
- **Search and Filter**: Quickly find entries by service name, username, category, or tags
- **Categories and Tags**: Organize your passwords efficiently
- **Copy to Clipboard**: One-click copy with auto-clear after 60 seconds
- **Responsive Design**: Works on desktop, tablet, and mobile devices

## 🏗️ Architecture

### Tech Stack

**Backend:**
- Laravel 12 (PHP 8.2+)
- MySQL/PostgreSQL
- Laravel Sanctum (Authentication)
- RESTful API

**Frontend:**
- Vue 3 (Composition API)
- TypeScript
- Pinia (State Management)
- Tailwind CSS
- Inertia.js
- Web Crypto API

### Database Schema

```
users
├── Authentication user data

vault_entries
├── id
├── user_id
├── encrypted_payload (TEXT)
├── iv (32 chars hex)
├── salt (64 chars hex)
└── timestamps

vault_sub_keys
├── id
├── vault_entry_id
├── encrypted_payload (TEXT)
├── iv (32 chars hex)
├── salt (64 chars hex)
└── timestamps
```

## 🚀 Quick Start

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js 18.x or higher
- MySQL 8.0+ or PostgreSQL 13+
- Web server (Nginx/Apache)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/password-manager.git
   cd password-manager
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure database**
   
   Edit `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=vault_db
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Build frontend assets**
   ```bash
   npm run build
   ```

7. **Start the server**
   ```bash
   php artisan serve
   ```

Visit `http://localhost:8000` in your browser.

## 📖 Usage

### First Time Setup

1. **Register an Account**
   - Create a user account with email and password (this is your authentication password)

2. **Set Master Password**
   - The first time you access the vault, you'll be prompted to set a master password
   - This master password is used ONLY for encrypting/decrypting your vault
   - **⚠️ IMPORTANT**: This password is never sent to the server and cannot be recovered if lost

### Adding a Password Entry

1. Click "Add Entry" button
2. Fill in the form:
   - Service Name (e.g., "Gmail", "GitHub")
   - Username/Email
   - Password (or generate one)
   - Category (optional)
   - Tags (optional)
   - Notes (optional)
3. Click "Add Entry" to save

### Using Sub-Keys

Sub-keys allow you to store multiple credentials under one service:

**Example:**
- Main Entry: Instagram Business Account
  - Sub-Key: Ads Manager Login
  - Sub-Key: API Key
  - Sub-Key: Backup Email

### Password Generator

1. Click the refresh icon next to password field
2. Adjust length (8-64 characters)
3. Select character types (lowercase, uppercase, numbers, symbols)
4. Click "Generate"
5. Click "Use This Password" to apply it

### Vault Lock/Unlock

- **Lock**: Click "Lock Vault" to secure your data
- **Auto-Lock**: Vault automatically locks after 15 minutes of inactivity
- **Unlock**: Enter your master password to access your vault

## 🔒 Security Considerations

### What the Server Never Knows

1. Your master password
2. Plaintext passwords stored in the vault
3. Plaintext usernames
4. Plaintext notes
5. Any decrypted data

### What the Server Stores

1. Encrypted payloads (useless without master password)
2. Initialization vectors (IVs) - not secret
3. Salts - not secret
4. Your authentication credentials (separate from vault)

### Password Recovery

**There is NO password recovery for the master password by design.**

If you forget your master password:
- All vault data becomes permanently inaccessible
- You'll need to delete the vault and start over
- This is a fundamental property of zero-knowledge architecture

**Best Practices:**
- Write down your master password and store it securely (e.g., physical safe)
- Use a strong, memorable passphrase
- Never share your master password
- Regularly backup your password list (separately, securely)

### Security Best Practices

1. **Use HTTPS in Production**
   - Never use this system over HTTP
   - Obtain a free SSL certificate from Let's Encrypt

2. **Strong Master Password**
   - Minimum 12 characters
   - Mix of uppercase, lowercase, numbers, and symbols
   - Avoid common words or patterns

3. **Keep Software Updated**
   - Regularly update dependencies
   - Apply security patches promptly

4. **Secure Your Device**
   - Use antivirus software
   - Keep your OS updated
   - Be aware of keyloggers and malware

5. **Regular Backups**
   - Export your passwords periodically
   - Store backups in a secure location
   - Encrypt backup files

## 📚 Documentation

- [**ARCHITECTURE.md**](ARCHITECTURE.md) - Detailed system architecture and design
- [**SECURITY.md**](SECURITY.md) - Security best practices and guidelines
- [**DEPLOYMENT.md**](DEPLOYMENT.md) - Production deployment instructions

## 🛠️ Development

### Running in Development

```bash
# Start PHP server
php artisan serve

# In another terminal, start Vite
npm run dev
```

### Running Tests

```bash
# Run PHP tests
php artisan test

# Run JavaScript tests (if configured)
npm run test
```

### Code Style

```bash
# Format PHP code
./vendor/bin/pint

# Format JavaScript/Vue code
npm run format
```

## 🔄 API Endpoints

### Vault Entries

```
GET    /vault              - Vault dashboard page
GET    /vault/data         - Get all vault entries
POST   /vault              - Create new entry
GET    /vault/{id}         - Get specific entry
PUT    /vault/{id}         - Update entry
DELETE /vault/{id}         - Delete entry
```

### Sub-Keys

```
POST   /vault/sub-keys          - Create sub-key
GET    /vault/sub-keys/{id}     - Get sub-key
PUT    /vault/sub-keys/{id}     - Update sub-key
DELETE /vault/sub-keys/{id}     - Delete sub-key
```

All endpoints require authentication and return encrypted data only.

## 🌟 Future Enhancements

### Planned Features

- [ ] Multi-user support with role-based access
- [ ] Shared vaults between users
- [ ] Password expiration warnings
- [ ] Breach detection integration (Have I Been Pwned API)
- [ ] Two-factor authentication for vault unlock
- [ ] Biometric authentication support
- [ ] Browser extension for auto-fill
- [ ] Mobile applications (iOS/Android)
- [ ] Import from other password managers
- [ ] Secure password sharing
- [ ] Audit logs
- [ ] Password history tracking

### Scalability

The current implementation is optimized for single-user or small team use. For larger deployments:

- Implement Redis for session storage and caching
- Set up queue workers for background jobs
- Use load balancers for horizontal scaling
- Implement database read replicas
- Add CDN for static assets

## ⚠️ Disclaimers

1. **Use at Your Own Risk**: While this system implements industry-standard security practices, no system is 100% secure.

2. **Backup Your Data**: Always maintain secure backups of your passwords outside this system.

3. **Master Password**: The master password cannot be recovered. Store it securely.

4. **Self-Hosted**: You are responsible for securing your server and maintaining the application.

5. **Not Audited**: This code has not undergone a professional security audit. Consider hiring a security professional for production use.

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

Please follow the existing code style and include appropriate documentation.

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

## 🙏 Acknowledgments

- Laravel team for the excellent framework
- Vue.js team for the reactive framework
- Web Crypto API for browser-native cryptography
- All open-source contributors

## 📞 Support

For issues, questions, or suggestions:

- Open an issue on GitHub
- Check the documentation files
- Review the security guidelines

## 🔐 Security Disclosure

If you discover a security vulnerability, please email security@yourdomain.com. Do not open a public issue.

We appreciate responsible disclosure and will acknowledge your contribution.

---

**Remember**: With zero-knowledge architecture comes great responsibility. Your master password is the key to all your data. Guard it carefully.

Made with ❤️ for security and privacy.
