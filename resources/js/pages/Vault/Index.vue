<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="mx-auto max-w-7xl">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Password Vault</h1>
        <p class="mt-2 text-sm text-gray-600">
          Securely store and manage your passwords with zero-knowledge encryption
        </p>
      </div>

      <!-- Vault Status Bar -->
      <div class="mb-6 rounded-lg bg-white p-4 shadow">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-3">
            <div
              :class="[
                'h-3 w-3 rounded-full',
                vaultStore.isUnlocked ? 'bg-green-500' : 'bg-red-500',
              ]"
            ></div>
            <span class="text-sm font-medium text-gray-700">
              Vault Status:
              <span :class="vaultStore.isUnlocked ? 'text-green-600' : 'text-red-600'">
                {{ vaultStore.isUnlocked ? 'Unlocked' : 'Locked' }}
              </span>
            </span>
          </div>

          <div class="flex items-center space-x-3">
            <button
              v-if="!vaultStore.isUnlocked"
              @click="showUnlockDialog = true"
              class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
              Unlock Vault
            </button>
            <button
              v-if="vaultStore.isUnlocked"
              @click="handleLock"
              class="rounded-md bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
            >
              Lock Vault
            </button>
            <button
              v-if="vaultStore.isUnlocked"
              @click="showAddDialog = true"
              class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
            >
              Add Entry
            </button>
          </div>
        </div>
      </div>

      <!-- Search and Filter -->
      <div v-if="vaultStore.isUnlocked" class="mb-6 rounded-lg bg-white p-4 shadow">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search entries..."
          class="w-full rounded-md border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
      </div>

      <!-- Vault Entries List -->
      <div v-if="vaultStore.isUnlocked && vaultStore.hasEntries">
        <VaultEntryList
          :entries="filteredEntries"
          :search-query="searchQuery"
          @edit="handleEdit"
          @delete="handleDelete"
        />
      </div>

      <!-- Empty State -->
      <div
        v-if="vaultStore.isUnlocked && !vaultStore.hasEntries"
        class="rounded-lg bg-white p-12 text-center shadow"
      >
        <svg
          class="mx-auto h-12 w-12 text-gray-400"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
          />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900">No entries yet</h3>
        <p class="mt-2 text-sm text-gray-500">
          Get started by adding your first password entry
        </p>
        <button
          @click="showAddDialog = true"
          class="mt-6 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          Add First Entry
        </button>
      </div>

      <!-- Locked State -->
      <div
        v-if="!vaultStore.isUnlocked"
        class="rounded-lg bg-white p-12 text-center shadow"
      >
        <svg
          class="mx-auto h-16 w-16 text-gray-400"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
          />
        </svg>
        <h3 class="mt-4 text-xl font-medium text-gray-900">Vault is Locked</h3>
        <p class="mt-2 text-sm text-gray-500">
          Enter your master password to access your vault
        </p>
        <button
          @click="showUnlockDialog = true"
          class="mt-6 rounded-md bg-blue-600 px-6 py-3 text-base font-medium text-white hover:bg-blue-700"
        >
          Unlock Vault
        </button>
      </div>

      <!-- Master Password Dialog -->
      <MasterPasswordPrompt
        v-if="showUnlockDialog"
        @unlock="handleUnlock"
        @close="showUnlockDialog = false"
      />

      <!-- Add/Edit Entry Dialog -->
      <VaultEntryForm
        v-if="showAddDialog || showEditDialog"
        :entry="editingEntry"
        :mode="showEditDialog ? 'edit' : 'add'"
        @save="handleSave"
        @close="closeDialogs"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useVaultStore } from '@/stores/vaultStore';
import type { VaultEntry } from '@/stores/vaultStore';
import VaultEntryList from '@/components/Vault/VaultEntryList.vue';
import VaultEntryForm from '@/components/Vault/VaultEntryForm.vue';
import MasterPasswordPrompt from '@/components/Vault/MasterPasswordPrompt.vue';
import axios from 'axios';

const vaultStore = useVaultStore();

const showUnlockDialog = ref(false);
const showAddDialog = ref(false);
const showEditDialog = ref(false);
const editingEntry = ref<VaultEntry | null>(null);
const searchQuery = ref('');
const loading = ref(false);

const filteredEntries = computed(() => {
  if (!searchQuery.value) return vaultStore.entries;

  const query = searchQuery.value.toLowerCase();
  return vaultStore.entries.filter((entry) => {
    if (!entry.decryptedData) return false;
    return (
      entry.decryptedData.serviceName?.toLowerCase().includes(query) ||
      entry.decryptedData.username?.toLowerCase().includes(query) ||
      entry.decryptedData.category?.toLowerCase().includes(query) ||
      entry.decryptedData.tags?.some((tag) => tag.toLowerCase().includes(query))
    );
  });
});

async function loadVaultEntries() {
  try {
    loading.value = true;
    const response = await axios.get('/vault/data');
    vaultStore.setEntries(response.data);
  } catch (error) {
    console.error('Failed to load vault entries:', error);
  } finally {
    loading.value = false;
  }
}

async function handleUnlock(password: string) {
  const success = await vaultStore.unlock(password);
  if (success) {
    showUnlockDialog.value = false;
    // Decrypt all entries
    for (const entry of vaultStore.entries) {
      await vaultStore.decryptEntry(entry);
      if (entry.sub_keys) {
        for (const subKey of entry.sub_keys) {
          await vaultStore.decryptSubKey(subKey);
        }
      }
    }
  } else {
    // TODO: Replace with toast notification for better UX
    alert('Invalid master password');
  }
}

function handleLock() {
  vaultStore.lock();
}

function handleEdit(entry: VaultEntry) {
  editingEntry.value = entry;
  showEditDialog.value = true;
}

async function handleDelete(entry: VaultEntry) {
  // TODO: Replace with custom modal dialog for better UX
  if (confirm('Are you sure you want to delete this entry?')) {
    try {
      await axios.delete(`/vault/${entry.id}`);
      vaultStore.removeEntry(entry.id);
    } catch (error) {
      console.error('Failed to delete entry:', error);
      // TODO: Replace with toast notification for better UX
      alert('Failed to delete entry');
    }
  }
}

function handleSave() {
  closeDialogs();
  loadVaultEntries();
}

function closeDialogs() {
  showAddDialog.value = false;
  showEditDialog.value = false;
  editingEntry.value = null;
}

onMounted(() => {
  loadVaultEntries();
});
</script>
