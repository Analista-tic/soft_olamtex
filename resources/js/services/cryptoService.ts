/**
 * Zero-Knowledge Encryption Service
 * 
 * This service implements client-side encryption using the Web Crypto API.
 * All encryption and decryption happens in the browser - the server NEVER
 * sees plaintext passwords or the master password.
 * 
 * Architecture:
 * 1. Master password is used only in the browser
 * 2. Master password derives an encryption key using PBKDF2
 * 3. Each vault entry is encrypted with AES-256-GCM
 * 4. Each entry has its own IV (initialization vector) and salt
 */

const PBKDF2_ITERATIONS = 100000; // High iteration count for security
const KEY_LENGTH = 256; // AES-256

export interface EncryptedData {
  encryptedPayload: string; // Base64 encoded
  iv: string; // Hex encoded, 32 chars
  salt: string; // Hex encoded, 64 chars
}

export interface VaultEntryData {
  serviceName: string;
  username: string;
  password: string;
  notes?: string;
  category?: string;
  tags?: string[];
}

export interface SubKeyData {
  name: string;
  value: string;
  notes?: string;
}

/**
 * Converts an ArrayBuffer to a hex string
 */
function bufferToHex(buffer: ArrayBuffer): string {
  return Array.from(new Uint8Array(buffer))
    .map(b => b.toString(16).padStart(2, '0'))
    .join('');
}

/**
 * Converts a hex string to an ArrayBuffer
 */
function hexToBuffer(hex: string): ArrayBuffer {
  const bytes = new Uint8Array(hex.length / 2);
  for (let i = 0; i < hex.length; i += 2) {
    bytes[i / 2] = parseInt(hex.substr(i, 2), 16);
  }
  return bytes.buffer;
}

/**
 * Converts an ArrayBuffer to a base64 string
 */
function bufferToBase64(buffer: ArrayBuffer): string {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  for (let i = 0; i < bytes.byteLength; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return btoa(binary);
}

/**
 * Converts a base64 string to an ArrayBuffer
 */
function base64ToBuffer(base64: string): ArrayBuffer {
  const binary = atob(base64);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) {
    bytes[i] = binary.charCodeAt(i);
  }
  return bytes.buffer;
}

/**
 * Derives a cryptographic key from the master password using PBKDF2
 */
async function deriveKey(
  masterPassword: string,
  salt: ArrayBuffer
): Promise<CryptoKey> {
  const encoder = new TextEncoder();
  const passwordBuffer = encoder.encode(masterPassword);

  // Import the password as a key
  const baseKey = await crypto.subtle.importKey(
    'raw',
    passwordBuffer,
    'PBKDF2',
    false,
    ['deriveBits', 'deriveKey']
  );

  // Derive the encryption key
  const key = await crypto.subtle.deriveKey(
    {
      name: 'PBKDF2',
      salt: salt,
      iterations: PBKDF2_ITERATIONS,
      hash: 'SHA-256',
    },
    baseKey,
    { name: 'AES-GCM', length: KEY_LENGTH },
    false,
    ['encrypt', 'decrypt']
  );

  return key;
}

/**
 * Encrypts data using AES-256-GCM
 */
export async function encryptData(
  data: VaultEntryData | SubKeyData,
  masterPassword: string
): Promise<EncryptedData> {
  // Generate random salt and IV
  const salt = crypto.getRandomValues(new Uint8Array(32)); // 32 bytes = 64 hex chars
  const iv = crypto.getRandomValues(new Uint8Array(16)); // 16 bytes = 32 hex chars

  // Derive key from master password
  const key = await deriveKey(masterPassword, salt);

  // Prepare data for encryption
  const encoder = new TextEncoder();
  const dataString = JSON.stringify(data);
  const dataBuffer = encoder.encode(dataString);

  // Encrypt the data
  const encryptedBuffer = await crypto.subtle.encrypt(
    {
      name: 'AES-GCM',
      iv: iv,
    },
    key,
    dataBuffer
  );

  return {
    encryptedPayload: bufferToBase64(encryptedBuffer),
    iv: bufferToHex(iv),
    salt: bufferToHex(salt),
  };
}

/**
 * Decrypts data using AES-256-GCM
 */
export async function decryptData<T = VaultEntryData | SubKeyData>(
  encryptedData: EncryptedData,
  masterPassword: string
): Promise<T> {
  // Convert hex strings back to buffers
  const salt = hexToBuffer(encryptedData.salt);
  const iv = hexToBuffer(encryptedData.iv);
  const encryptedBuffer = base64ToBuffer(encryptedData.encryptedPayload);

  // Derive key from master password
  const key = await deriveKey(masterPassword, salt);

  // Decrypt the data
  const decryptedBuffer = await crypto.subtle.decrypt(
    {
      name: 'AES-GCM',
      iv: iv,
    },
    key,
    encryptedBuffer
  );

  // Convert decrypted data back to object
  const decoder = new TextDecoder();
  const dataString = decoder.decode(decryptedBuffer);
  return JSON.parse(dataString) as T;
}

/**
 * Generates a strong random password
 */
export function generatePassword(
  length: number = 20,
  options: {
    lowercase?: boolean;
    uppercase?: boolean;
    numbers?: boolean;
    symbols?: boolean;
  } = {}
): string {
  const {
    lowercase = true,
    uppercase = true,
    numbers = true,
    symbols = true,
  } = options;

  const lowercaseChars = 'abcdefghijklmnopqrstuvwxyz';
  const uppercaseChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  const numberChars = '0123456789';
  const symbolChars = '!@#$%^&*()_+-=[]{}|;:,.<>?';

  let charset = '';
  if (lowercase) charset += lowercaseChars;
  if (uppercase) charset += uppercaseChars;
  if (numbers) charset += numberChars;
  if (symbols) charset += symbolChars;

  if (charset.length === 0) {
    charset = lowercaseChars + uppercaseChars + numberChars; // Default fallback
  }

  const randomValues = new Uint8Array(length);
  crypto.getRandomValues(randomValues);

  let password = '';
  for (let i = 0; i < length; i++) {
    password += charset[randomValues[i] % charset.length];
  }

  return password;
}

/**
 * Calculates password strength (0-100)
 */
export function calculatePasswordStrength(password: string): number {
  let strength = 0;

  // Length score (max 40 points)
  if (password.length >= 8) strength += 10;
  if (password.length >= 12) strength += 10;
  if (password.length >= 16) strength += 10;
  if (password.length >= 20) strength += 10;

  // Character variety (max 40 points)
  if (/[a-z]/.test(password)) strength += 10; // lowercase
  if (/[A-Z]/.test(password)) strength += 10; // uppercase
  if (/[0-9]/.test(password)) strength += 10; // numbers
  if (/[^a-zA-Z0-9]/.test(password)) strength += 10; // symbols

  // Complexity (max 20 points)
  const uniqueChars = new Set(password.split('')).size;
  if (uniqueChars >= password.length * 0.5) strength += 10;
  if (uniqueChars >= password.length * 0.7) strength += 10;

  return Math.min(strength, 100);
}

/**
 * Gets password strength label
 */
export function getPasswordStrengthLabel(strength: number): string {
  if (strength < 30) return 'Weak';
  if (strength < 60) return 'Medium';
  if (strength < 80) return 'Strong';
  return 'Very Strong';
}

/**
 * Gets password strength color
 */
export function getPasswordStrengthColor(strength: number): string {
  if (strength < 30) return 'red';
  if (strength < 60) return 'orange';
  if (strength < 80) return 'yellow';
  return 'green';
}

/**
 * Validates master password format
 */
export function validateMasterPassword(password: string): {
  valid: boolean;
  errors: string[];
} {
  const errors: string[] = [];

  if (password.length < 12) {
    errors.push('Master password must be at least 12 characters long');
  }

  if (!/[a-z]/.test(password)) {
    errors.push('Master password must contain lowercase letters');
  }

  if (!/[A-Z]/.test(password)) {
    errors.push('Master password must contain uppercase letters');
  }

  if (!/[0-9]/.test(password)) {
    errors.push('Master password must contain numbers');
  }

  if (!/[^a-zA-Z0-9]/.test(password)) {
    errors.push('Master password must contain special characters');
  }

  return {
    valid: errors.length === 0,
    errors,
  };
}
