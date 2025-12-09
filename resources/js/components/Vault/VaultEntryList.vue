<template>
  <div class="space-y-4">
    <div
      v-for="entry in entries"
      :key="entry.id"
      class="rounded-lg bg-white p-6 shadow transition-shadow hover:shadow-lg"
    >
      <div class="flex items-start justify-between">
        <div class="flex-1">
          <div class="flex items-center space-x-3">
            <h3 class="text-lg font-semibold text-gray-900">
              {{ entry.decryptedData?.serviceName || 'Encrypted Entry' }}
            </h3>
            <span
              v-if="entry.decryptedData?.category"
              class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800"
            >
              {{ entry.decryptedData.category }}
            </span>
          </div>

          <div class="mt-2 space-y-2">
            <div class="flex items-center text-sm text-gray-600">
              <svg
                class="mr-2 h-4 w-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                />
              </svg>
              <span class="font-medium">Username:</span>
              <span class="ml-2">{{ entry.decryptedData?.username }}</span>
              <button
                @click="copyToClipboard(entry.decryptedData?.username)"
                class="ml-2 text-blue-600 hover:text-blue-800"
                title="Copy username"
              >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                  />
                </svg>
              </button>
            </div>

            <div class="flex items-center text-sm text-gray-600">
              <svg
                class="mr-2 h-4 w-4"
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
              <span class="font-medium">Password:</span>
              <span class="ml-2">
                {{ showPassword[entry.id] ? entry.decryptedData?.password : '••••••••' }}
              </span>
              <button
                @click="togglePasswordVisibility(entry.id)"
                class="ml-2 text-blue-600 hover:text-blue-800"
                :title="showPassword[entry.id] ? 'Hide password' : 'Show password'"
              >
                <svg
                  v-if="!showPassword[entry.id]"
                  class="h-4 w-4"
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
                <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                  />
                </svg>
              </button>
              <button
                @click="copyToClipboard(entry.decryptedData?.password)"
                class="ml-2 text-blue-600 hover:text-blue-800"
                title="Copy password"
              >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                  />
                </svg>
              </button>
            </div>

            <div v-if="entry.decryptedData?.notes" class="text-sm text-gray-600">
              <span class="font-medium">Notes:</span>
              <span class="ml-2">{{ entry.decryptedData.notes }}</span>
            </div>

            <div v-if="entry.decryptedData?.tags?.length" class="flex flex-wrap gap-2">
              <span
                v-for="tag in entry.decryptedData.tags"
                :key="tag"
                class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-700"
              >
                {{ tag }}
              </span>
            </div>
          </div>

          <!-- Sub-keys -->
          <div v-if="entry.sub_keys && entry.sub_keys.length > 0" class="mt-4">
            <h4 class="mb-2 text-sm font-medium text-gray-700">Sub-keys:</h4>
            <div class="space-y-2 pl-4">
              <div
                v-for="subKey in entry.sub_keys"
                :key="subKey.id"
                class="rounded-md bg-gray-50 p-3"
              >
                <div class="flex items-center justify-between">
                  <span class="font-medium text-sm text-gray-700">
                    {{ subKey.decryptedData?.name }}
                  </span>
                  <div class="flex items-center space-x-2">
                    <button
                      @click="copyToClipboard(subKey.decryptedData?.value)"
                      class="text-blue-600 hover:text-blue-800"
                      title="Copy value"
                    >
                      <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                        />
                      </svg>
                    </button>
                  </div>
                </div>
                <div v-if="subKey.decryptedData?.notes" class="mt-1 text-xs text-gray-600">
                  {{ subKey.decryptedData.notes }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="ml-4 flex space-x-2">
          <button
            @click="$emit('edit', entry)"
            class="rounded-md p-2 text-blue-600 hover:bg-blue-50"
            title="Edit"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
              />
            </svg>
          </button>
          <button
            @click="$emit('delete', entry)"
            class="rounded-md p-2 text-red-600 hover:bg-red-50"
            title="Delete"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
              />
            </svg>
          </button>
        </div>
      </div>

      <div class="mt-4 border-t border-gray-200 pt-3 text-xs text-gray-500">
        Created: {{ new Date(entry.created_at).toLocaleDateString() }} |
        Updated: {{ new Date(entry.updated_at).toLocaleDateString() }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import type { VaultEntry } from '@/stores/vaultStore';

defineProps<{
  entries: VaultEntry[];
  searchQuery: string;
}>();

const emit = defineEmits<{
  edit: [entry: VaultEntry];
  delete: [entry: VaultEntry];
}>();

const showPassword = ref<Record<number, boolean>>({});

function togglePasswordVisibility(entryId: number) {
  showPassword.value[entryId] = !showPassword.value[entryId];
}

async function copyToClipboard(text: string | undefined) {
  if (!text) return;

  try {
    await navigator.clipboard.writeText(text);
    // Could add a toast notification here
    console.log('Copied to clipboard');
  } catch (err) {
    console.error('Failed to copy:', err);
  }
}
</script>
