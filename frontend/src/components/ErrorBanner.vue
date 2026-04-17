<script setup>
defineProps({ error: { type: [String, Error, null], default: null } })
defineEmits(['dismiss'])
</script>

<template>
  <div
    v-if="error"
    class="mb-4 flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"
  >
    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="12" cy="12" r="10" />
      <path d="M12 8v4M12 16h.01" stroke-linecap="round" />
    </svg>
    <div class="flex-1">{{ typeof error === 'string' ? error : error.message }}</div>
    <button class="text-rose-600 hover:text-rose-800" @click="$emit('dismiss')">&times;</button>
  </div>
</template>
