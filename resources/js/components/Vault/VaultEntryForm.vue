<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
    <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
      <h2 class="mb-4 text-2xl font-bold text-gray-900">
        {{ mode === 'add' ? 'Add New Entry' : 'Edit Entry' }}
      </h2>

      <form @submit.prevent="handleSubmit">
        <!-- Service Name -->
        <div class="mb-4">
          <label for="serviceName" class="mb-2 block text-sm font-medium text-gray-700">
            Service Name <span class="text-red-500">*</span>
          </label>
          <input
            id="serviceName"
            v-model="formData.serviceName"
            type="text"
            class="w-full rounded-md border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            placeholder="e.g., Gmail, Instagram, Company VPN"
            required
          />
        </div>

        <!-- Username -->
        <div class="mb-4">
          <label for="username" class="mb-2 block text-sm font-medium text-gray-700">
            Username / Email <span class="text-red-500">*</span>
          </label>
          <input
            id="username"
            v-model="formData.username"
            type="text"
            class="w-full rounded-md border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            placeholder="username or email"
            required
          />
        </div>

        <!-- Password -->
        <div class="mb-4">
          <label for="password" class="mb-2 block text-sm font-medium text-gray-700">
            Password <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <input
              id="password"
              v-model="formData.password"
              :type="showPassword ? 'text' : 'password'"
              class="w-full rounded-md border border-gray-300 px-4 py-2 pr-20 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder="Enter password"
              required
            />
            <div class="absolute inset-y-0 right-0 flex items-center space-x-1 pr-3">
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="text-gray-400 hover:text-gray-600"
              >
                <svg
                  v-if="!showPassword"
                  class="h-5 w-5"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                  />
                </svg>
                <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                  />
                </svg>
              </button>
              <button
                type="button"
                @click="showPasswordGenerator = !showPasswordGenerator"
                class="text-blue-600 hover:text-blue-800"
                title="Generate password"
              >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                  />
                </svg>
              </button>
            </div>
          </div>

          <!-- Password Strength Indicator -->
          <div v-if="formData.password" class="mt-2">
            <PasswordStrengthIndicator :password="formData.password" />
          </div>

          <!-- Password Generator -->
          <div v-if="showPasswordGenerator" class="mt-2">
            <PasswordGenerator @generated="handlePasswordGenerated" />
          </div>
        </div>

        <!-- Category -->
        <div class="mb-4">
          <label for="category" class="mb-2 block text-sm font-medium text-gray-700">
            Category
          </label>
          <select
            id="category"
            v-model="formData.category"
            class="w-full rounded-md border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          >
            <option value="">Select a category</option>
            <option value="Social Media">Social Media</option>
            <option value="Email">Email</option>
            <option value="Work">Work</option>
            <option value="Banking">Banking</option>
            <option value="Shopping">Shopping</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <!-- Tags -->
        <div class="mb-4">
          <label for="tags" class="mb-2 block text-sm font-medium text-gray-700"> Tags </label>
          <input
            id="tags"
            v-model="tagsInput"
            type="text"
            class="w-full rounded-md border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            placeholder="Comma-separated tags (e.g., personal, important)"
          />
          <p class="mt-1 text-xs text-gray-500">Separate multiple tags with commas</p>
        </div>

        <!-- Notes -->
        <div class="mb-4">
          <label for="notes" class="mb-2 block text-sm font-medium text-gray-700"> Notes </label>
          <textarea
            id="notes"
            v-model="formData.notes"
            rows="3"
            class="w-full rounded-md border border-gray-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            placeholder="Additional notes..."
          ></textarea>
        </div>

        <div v-if="error" class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-600">
          {{ error }}
        </div>

        <div class="flex justify-end space-x-3">
          <button
            type="button"
            @click="$emit('close')"
            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="loading"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {{ loading ? 'Saving...' : mode === 'add' ? 'Add Entry' : 'Update Entry' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useVaultStore } from '@/stores/vaultStore';
import type { VaultEntry } from '@/stores/vaultStore';
import type { VaultEntryData } from '@/services/cryptoService';
import { encryptData } from '@/services/cryptoService';
import PasswordStrengthIndicator from './PasswordStrengthIndicator.vue';
import PasswordGenerator from './PasswordGenerator.vue';
import axios from 'axios';

const props = defineProps<{
  entry?: VaultEntry | null;
  mode: 'add' | 'edit';
}>();

const emit = defineEmits<{
  save: [];
  close: [];
}>();

const vaultStore = useVaultStore();

const formData = ref<VaultEntryData>({
  serviceName: '',
  username: '',
  password: '',
  notes: '',
  category: '',
  tags: [],
});

const tagsInput = ref('');
const showPassword = ref(false);
const showPasswordGenerator = ref(false);
const loading = ref(false);
const error = ref('');

const tagsArray = computed(() => {
  return tagsInput.value
    .split(',')
    .map((tag) => tag.trim())
    .filter((tag) => tag.length > 0);
});

async function handleSubmit() {
  if (!vaultStore.masterPassword) {
    error.value = 'Vault is locked. Please unlock it first.';
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    // Prepare data with tags
    const dataToEncrypt: VaultEntryData = {
      ...formData.value,
      tags: tagsArray.value,
    };

    // Encrypt the data
    const encrypted = await encryptData(dataToEncrypt, vaultStore.masterPassword);

    if (props.mode === 'add') {
      // Create new entry
      const response = await axios.post('/vault', encrypted);
      vaultStore.addEntry(response.data);
    } else if (props.entry) {
      // Update existing entry
      const response = await axios.put(`/vault/${props.entry.id}`, encrypted);
      vaultStore.updateEntry(props.entry.id, response.data);
    }

    emit('save');
  } catch (err) {
    console.error('Failed to save entry:', err);
    error.value = 'Failed to save entry. Please try again.';
  } finally {
    loading.value = false;
  }
}

function handlePasswordGenerated(password: string) {
  formData.value.password = password;
  showPasswordGenerator.value = false;
}

onMounted(async () => {
  if (props.mode === 'edit' && props.entry) {
    // Load existing data
    if (props.entry.decryptedData) {
      formData.value = { ...props.entry.decryptedData };
      tagsInput.value = props.entry.decryptedData.tags?.join(', ') || '';
    } else if (vaultStore.masterPassword) {
      // Decrypt if not already decrypted
      const decrypted = await vaultStore.decryptEntry(props.entry);
      if (decrypted) {
        formData.value = { ...decrypted };
        tagsInput.value = decrypted.tags?.join(', ') || '';
      }
    }
  }
});
</script>
