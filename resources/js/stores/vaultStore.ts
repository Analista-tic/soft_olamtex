/**
 * Vault Store
 * 
 * Manages the state of the password vault, including:
 * - Vault lock/unlock status
 * - Master password (in memory only, never persisted)
 * - Session timeout
 * - Vault entries
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { EncryptedData, VaultEntryData, SubKeyData } from '../services/cryptoService';
import { decryptData } from '../services/cryptoService';

export interface VaultEntry {
  id: number;
  user_id: number;
  encrypted_payload: string;
  iv: string;
  salt: string;
  created_at: string;
  updated_at: string;
  sub_keys?: VaultSubKey[];
  // Decrypted data (only available when vault is unlocked)
  decryptedData?: VaultEntryData;
}

export interface VaultSubKey {
  id: number;
  vault_entry_id: number;
  encrypted_payload: string;
  iv: string;
  salt: string;
  created_at: string;
  updated_at: string;
  // Decrypted data (only available when vault is unlocked)
  decryptedData?: SubKeyData;
}

const SESSION_TIMEOUT = 15 * 60 * 1000; // 15 minutes in milliseconds

export const useVaultStore = defineStore('vault', () => {
  // State
  const isUnlocked = ref(false);
  const masterPassword = ref<string | null>(null);
  const entries = ref<VaultEntry[]>([]);
  const lastActivityTime = ref<number | null>(null);
  const sessionTimeoutId = ref<number | null>(null);

  // Computed
  const hasEntries = computed(() => entries.value.length > 0);
  const unlockedEntries = computed(() => 
    entries.value.filter(entry => entry.decryptedData !== undefined)
  );

  /**
   * Unlocks the vault with the master password
   */
  async function unlock(password: string): Promise<boolean> {
    try {
      // Try to decrypt the first entry to verify the password
      if (entries.value.length > 0) {
        const firstEntry = entries.value[0];
        await decryptData({
          encryptedPayload: firstEntry.encrypted_payload,
          iv: firstEntry.iv,
          salt: firstEntry.salt,
        }, password);
      }

      masterPassword.value = password;
      isUnlocked.value = true;
      updateLastActivity();
      startSessionTimeout();
      return true;
    } catch (error) {
      console.error('Failed to unlock vault:', error);
      return false;
    }
  }

  /**
   * Locks the vault
   */
  function lock() {
    masterPassword.value = null;
    isUnlocked.value = false;
    lastActivityTime.value = null;
    
    // Clear decrypted data from memory
    entries.value.forEach(entry => {
      entry.decryptedData = undefined;
      if (entry.sub_keys) {
        entry.sub_keys.forEach(subKey => {
          subKey.decryptedData = undefined;
        });
      }
    });

    stopSessionTimeout();
  }

  /**
   * Updates last activity time
   */
  function updateLastActivity() {
    lastActivityTime.value = Date.now();
  }

  /**
   * Starts the session timeout timer
   */
  function startSessionTimeout() {
    stopSessionTimeout(); // Clear any existing timeout

    // Check every minute for inactivity
    sessionTimeoutId.value = window.setInterval(() => {
      if (isUnlocked.value && lastActivityTime.value) {
        const timeSinceLastActivity = Date.now() - lastActivityTime.value;
        if (timeSinceLastActivity >= SESSION_TIMEOUT) {
          lock();
          // Optionally show a notification
          console.log('Vault locked due to inactivity');
        }
      }
    }, 60000); // Check every minute
  }

  /**
   * Stops the session timeout timer
   */
  function stopSessionTimeout() {
    if (sessionTimeoutId.value !== null) {
      clearInterval(sessionTimeoutId.value);
      sessionTimeoutId.value = null;
    }
  }

  /**
   * Decrypts a vault entry
   */
  async function decryptEntry(entry: VaultEntry): Promise<VaultEntryData | null> {
    if (!isUnlocked.value || !masterPassword.value) {
      return null;
    }

    try {
      const decrypted = await decryptData<VaultEntryData>({
        encryptedPayload: entry.encrypted_payload,
        iv: entry.iv,
        salt: entry.salt,
      }, masterPassword.value);

      // Store decrypted data in the entry
      entry.decryptedData = decrypted;
      updateLastActivity();
      return decrypted;
    } catch (error) {
      console.error('Failed to decrypt entry:', error);
      return null;
    }
  }

  /**
   * Decrypts a sub-key
   */
  async function decryptSubKey(subKey: VaultSubKey): Promise<SubKeyData | null> {
    if (!isUnlocked.value || !masterPassword.value) {
      return null;
    }

    try {
      const decrypted = await decryptData<SubKeyData>({
        encryptedPayload: subKey.encrypted_payload,
        iv: subKey.iv,
        salt: subKey.salt,
      }, masterPassword.value);

      // Store decrypted data in the sub-key
      subKey.decryptedData = decrypted;
      updateLastActivity();
      return decrypted;
    } catch (error) {
      console.error('Failed to decrypt sub-key:', error);
      return null;
    }
  }

  /**
   * Sets vault entries
   */
  function setEntries(newEntries: VaultEntry[]) {
    entries.value = newEntries;
  }

  /**
   * Adds a new entry
   */
  function addEntry(entry: VaultEntry) {
    entries.value.unshift(entry);
  }

  /**
   * Updates an existing entry
   */
  function updateEntry(id: number, updatedEntry: Partial<VaultEntry>) {
    const index = entries.value.findIndex(e => e.id === id);
    if (index !== -1) {
      entries.value[index] = { ...entries.value[index], ...updatedEntry };
    }
  }

  /**
   * Removes an entry
   */
  function removeEntry(id: number) {
    const index = entries.value.findIndex(e => e.id === id);
    if (index !== -1) {
      entries.value.splice(index, 1);
    }
  }

  /**
   * Adds a sub-key to an entry
   */
  function addSubKey(entryId: number, subKey: VaultSubKey) {
    const entry = entries.value.find(e => e.id === entryId);
    if (entry) {
      if (!entry.sub_keys) {
        entry.sub_keys = [];
      }
      entry.sub_keys.push(subKey);
    }
  }

  /**
   * Updates a sub-key
   */
  function updateSubKey(entryId: number, subKeyId: number, updatedSubKey: Partial<VaultSubKey>) {
    const entry = entries.value.find(e => e.id === entryId);
    if (entry && entry.sub_keys) {
      const index = entry.sub_keys.findIndex(sk => sk.id === subKeyId);
      if (index !== -1) {
        entry.sub_keys[index] = { ...entry.sub_keys[index], ...updatedSubKey };
      }
    }
  }

  /**
   * Removes a sub-key
   */
  function removeSubKey(entryId: number, subKeyId: number) {
    const entry = entries.value.find(e => e.id === entryId);
    if (entry && entry.sub_keys) {
      const index = entry.sub_keys.findIndex(sk => sk.id === subKeyId);
      if (index !== -1) {
        entry.sub_keys.splice(index, 1);
      }
    }
  }

  return {
    // State
    isUnlocked,
    masterPassword,
    entries,
    lastActivityTime,

    // Computed
    hasEntries,
    unlockedEntries,

    // Actions
    unlock,
    lock,
    updateLastActivity,
    decryptEntry,
    decryptSubKey,
    setEntries,
    addEntry,
    updateEntry,
    removeEntry,
    addSubKey,
    updateSubKey,
    removeSubKey,
  };
});
