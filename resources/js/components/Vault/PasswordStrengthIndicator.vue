<template>
  <div>
    <div class="flex items-center justify-between">
      <span class="text-xs font-medium text-gray-700">Password Strength:</span>
      <span
        :class="[
          'text-xs font-medium',
          strengthColor === 'red' && 'text-red-600',
          strengthColor === 'orange' && 'text-orange-600',
          strengthColor === 'yellow' && 'text-yellow-600',
          strengthColor === 'green' && 'text-green-600',
        ]"
      >
        {{ strengthLabel }}
      </span>
    </div>
    <div class="mt-1 h-2 w-full rounded-full bg-gray-200">
      <div
        :class="[
          'h-2 rounded-full transition-all duration-300',
          strengthColor === 'red' && 'bg-red-500',
          strengthColor === 'orange' && 'bg-orange-500',
          strengthColor === 'yellow' && 'bg-yellow-500',
          strengthColor === 'green' && 'bg-green-500',
        ]"
        :style="{ width: `${strength}%` }"
      ></div>
    </div>
    
    <!-- Password Requirements -->
    <div v-if="showRequirements" class="mt-2 space-y-1">
      <div class="flex items-center text-xs">
        <svg
          :class="[
            'mr-1 h-3 w-3',
            hasMinLength ? 'text-green-500' : 'text-gray-400'
          ]"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd"
          />
        </svg>
        <span :class="hasMinLength ? 'text-gray-700' : 'text-gray-500'">
          At least 8 characters
        </span>
      </div>
      <div class="flex items-center text-xs">
        <svg
          :class="[
            'mr-1 h-3 w-3',
            hasLowercase ? 'text-green-500' : 'text-gray-400'
          ]"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd"
          />
        </svg>
        <span :class="hasLowercase ? 'text-gray-700' : 'text-gray-500'">
          Contains lowercase letter
        </span>
      </div>
      <div class="flex items-center text-xs">
        <svg
          :class="[
            'mr-1 h-3 w-3',
            hasUppercase ? 'text-green-500' : 'text-gray-400'
          ]"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd"
          />
        </svg>
        <span :class="hasUppercase ? 'text-gray-700' : 'text-gray-500'">
          Contains uppercase letter
        </span>
      </div>
      <div class="flex items-center text-xs">
        <svg
          :class="[
            'mr-1 h-3 w-3',
            hasNumber ? 'text-green-500' : 'text-gray-400'
          ]"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd"
          />
        </svg>
        <span :class="hasNumber ? 'text-gray-700' : 'text-gray-500'">
          Contains number
        </span>
      </div>
      <div class="flex items-center text-xs">
        <svg
          :class="[
            'mr-1 h-3 w-3',
            hasSymbol ? 'text-green-500' : 'text-gray-400'
          ]"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd"
          />
        </svg>
        <span :class="hasSymbol ? 'text-gray-700' : 'text-gray-500'">
          Contains special character
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import {
  calculatePasswordStrength,
  getPasswordStrengthLabel,
  getPasswordStrengthColor,
} from '@/services/cryptoService';

const props = defineProps<{
  password: string;
  showRequirements?: boolean;
}>();

const strength = computed(() => calculatePasswordStrength(props.password));
const strengthLabel = computed(() => getPasswordStrengthLabel(strength.value));
const strengthColor = computed(() => getPasswordStrengthColor(strength.value));

const hasMinLength = computed(() => props.password.length >= 8);
const hasLowercase = computed(() => /[a-z]/.test(props.password));
const hasUppercase = computed(() => /[A-Z]/.test(props.password));
const hasNumber = computed(() => /[0-9]/.test(props.password));
const hasSymbol = computed(() => /[^a-zA-Z0-9]/.test(props.password));
</script>
