<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useProjectStore } from '@/stores/project'
import LogsDrawer from '@/components/LogsDrawer.vue'

const store = useProjectStore()
const { id: projectId } = storeToRefs(store)
const logsOpen = ref(false)
</script>

<template>
  <header class="bg-white border-b border-slate-200 shadow-sm">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
      <RouterLink to="/" class="flex items-center gap-3">
        <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-brand-700 text-white">
          <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="10" r="4" fill="#f59f2a" stroke="none" />
            <path d="M4 20h16" stroke="#fff" />
            <path d="M6 20l2-4M18 20l-2-4" stroke="#fff" />
          </svg>
        </span>
        <div>
          <div class="font-bold text-slate-900 leading-tight">Solar Proposal</div>
          <div class="text-xs text-slate-500 leading-tight">Rooftop photovoltaic pricing</div>
        </div>
      </RouterLink>
      <nav class="flex items-center gap-1 text-sm">
        <button
          v-if="projectId"
          type="button"
          class="px-3 py-1.5 rounded-md text-slate-600 hover:text-brand-700 hover:bg-brand-50 flex items-center gap-1.5"
          @click="logsOpen = true"
        >
          <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 6h16M4 12h16M4 18h10" stroke-linecap="round" />
          </svg>
          View logs
        </button>
        <RouterLink
          to="/"
          active-class="bg-brand-50 text-brand-700"
          exact-active-class="bg-brand-50 text-brand-700"
          class="px-3 py-1.5 rounded-md text-slate-600 hover:text-brand-700 hover:bg-brand-50"
        >New project</RouterLink>
        <RouterLink
          to="/settings"
          active-class="bg-brand-50 text-brand-700"
          class="px-3 py-1.5 rounded-md text-slate-600 hover:text-brand-700 hover:bg-brand-50"
        >Settings</RouterLink>
      </nav>
    </div>
    <LogsDrawer :open="logsOpen" :project-id="projectId" @close="logsOpen = false" />
  </header>
</template>
