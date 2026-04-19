<script setup>
import { computed, ref, watch } from 'vue'
import api from '@/services/api'

const props = defineProps({
  open: { type: Boolean, default: false },
  projectId: { type: String, default: null },
})
const emit = defineEmits(['close'])

const data = ref(null)
const loading = ref(false)
const error = ref(null)

async function load() {
  if (!props.projectId) return
  loading.value = true
  error.value = null
  try {
    data.value = await api.projectLogs(props.projectId)
  } catch (e) {
    error.value = e.message || String(e)
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.open, props.projectId],
  ([open]) => { if (open) load() },
  { immediate: true },
)

function close() { emit('close') }

const providers = computed(() => data.value?.providers || null)
const images = computed(() => data.value?.images || [])
const apiCalls = computed(() => data.value?.api_calls || [])
const progress = computed(() => data.value?.progress || [])
const errors = computed(() => data.value?.errors || [])

function kindBadge(kind) {
  switch (kind) {
    case 'live':          return 'bg-emerald-100 text-emerald-700 border-emerald-200'
    case 'fake':          return 'bg-amber-100 text-amber-700 border-amber-200'
    case 'deterministic': return 'bg-sky-100 text-sky-700 border-sky-200'
    case 'mixed':         return 'bg-violet-100 text-violet-700 border-violet-200'
    default:              return 'bg-slate-100 text-slate-600 border-slate-200'
  }
}

function statusBadge(status) {
  if (status >= 500) return 'bg-rose-100 text-rose-700 border-rose-200'
  if (status >= 400) return 'bg-amber-100 text-amber-700 border-amber-200'
  return 'bg-slate-100 text-slate-700 border-slate-200'
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div v-if="open" class="fixed inset-0 z-40 bg-slate-900/40" @click.self="close">
        <aside
          class="absolute right-0 top-0 h-full w-full sm:w-[640px] bg-white shadow-xl flex flex-col"
          @click.stop
        >
          <header class="flex items-center justify-between p-4 border-b border-slate-200">
            <div>
              <h2 class="font-semibold text-slate-900">API &amp; render logs</h2>
              <p class="text-xs text-slate-500 mt-0.5">
                Project <code>{{ projectId || '—' }}</code>
              </p>
            </div>
            <div class="flex items-center gap-2">
              <button class="btn-ghost text-xs" :disabled="loading" @click="load">
                {{ loading ? 'Loading…' : 'Refresh' }}
              </button>
              <button class="btn-ghost text-xs" aria-label="Close" @click="close">✕</button>
            </div>
          </header>

          <div class="flex-1 overflow-y-auto p-4 space-y-6 text-sm">
            <div v-if="error" class="rounded border border-rose-200 bg-rose-50 p-3 text-rose-700">
              {{ error }}
            </div>

            <section v-if="providers">
              <h3 class="font-semibold text-slate-900 mb-2">Providers</h3>
              <div class="flex flex-wrap gap-2 text-xs mb-2">
                <span
                  class="px-2 py-0.5 rounded-full border"
                  :class="providers.fake_providers_flag ? 'bg-amber-100 text-amber-700 border-amber-200' : 'bg-emerald-100 text-emerald-700 border-emerald-200'"
                >
                  FAKE_PROVIDERS = {{ providers.fake_providers_flag ? 'true' : 'false' }}
                </span>
                <span
                  v-for="(present, key) in providers.keys_present"
                  :key="key"
                  class="px-2 py-0.5 rounded-full border"
                  :class="present ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'"
                >
                  {{ key }}: {{ present ? 'key set' : 'no key' }}
                </span>
              </div>
              <table class="w-full text-xs border border-slate-200 rounded overflow-hidden">
                <thead class="bg-slate-50 text-slate-600">
                  <tr><th class="text-left p-2">Service</th><th class="text-left p-2">Class</th><th class="text-left p-2">Kind</th></tr>
                </thead>
                <tbody>
                  <tr v-for="(info, name) in providers.services" :key="name" class="border-t border-slate-100">
                    <td class="p-2 font-mono">{{ name }}</td>
                    <td class="p-2 font-mono">{{ info.class }}</td>
                    <td class="p-2">
                      <span class="px-2 py-0.5 rounded-full border text-[11px]" :class="kindBadge(info.kind)">
                        {{ info.kind }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </section>

            <section v-if="images.length">
              <h3 class="font-semibold text-slate-900 mb-2">Image artefacts</h3>
              <table class="w-full text-xs border border-slate-200 rounded overflow-hidden">
                <thead class="bg-slate-50 text-slate-600">
                  <tr>
                    <th class="text-left p-2">Path</th>
                    <th class="text-left p-2">Producer</th>
                    <th class="text-left p-2">Kind</th>
                    <th class="text-right p-2">Bytes</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="img in images" :key="img.path" class="border-t border-slate-100">
                    <td class="p-2 font-mono truncate max-w-[260px]">{{ img.path }}</td>
                    <td class="p-2 font-mono">{{ img.producer }}</td>
                    <td class="p-2">
                      <span class="px-2 py-0.5 rounded-full border text-[11px]" :class="kindBadge(img.kind)">
                        {{ img.kind }}
                      </span>
                    </td>
                    <td class="p-2 text-right tabular-nums">{{ img.bytes.toLocaleString() }}</td>
                  </tr>
                </tbody>
              </table>
            </section>

            <section>
              <h3 class="font-semibold text-slate-900 mb-2">API calls ({{ apiCalls.length }})</h3>
              <div v-if="!apiCalls.length" class="text-xs text-slate-400 italic">No API calls logged yet.</div>
              <table v-else class="w-full text-xs border border-slate-200 rounded overflow-hidden">
                <thead class="bg-slate-50 text-slate-600">
                  <tr>
                    <th class="text-left p-2">Timestamp</th>
                    <th class="text-left p-2">Label</th>
                    <th class="text-left p-2">Status</th>
                    <th class="text-right p-2">ms</th>
                    <th class="text-left p-2">Kind</th>
                    <th class="text-left p-2">Note</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in apiCalls" :key="i" class="border-t border-slate-100">
                    <td class="p-2 font-mono whitespace-nowrap">{{ row.timestamp }}</td>
                    <td class="p-2 font-mono">{{ row.label }}</td>
                    <td class="p-2">
                      <span class="px-2 py-0.5 rounded-full border text-[11px]" :class="statusBadge(row.status)">
                        {{ row.status }}
                      </span>
                    </td>
                    <td class="p-2 text-right tabular-nums">{{ row.duration_ms.toFixed(0) }}</td>
                    <td class="p-2">
                      <span class="px-2 py-0.5 rounded-full border text-[11px]" :class="kindBadge(row.kind)">
                        {{ row.kind }}
                      </span>
                    </td>
                    <td class="p-2 font-mono truncate max-w-[200px]">{{ row.note }}</td>
                  </tr>
                </tbody>
              </table>
            </section>

            <section v-if="progress.length">
              <h3 class="font-semibold text-slate-900 mb-2">Progress (tail)</h3>
              <pre class="text-[11px] bg-slate-900 text-slate-100 p-3 rounded max-h-60 overflow-auto">{{ progress.join('\n') }}</pre>
            </section>

            <section v-if="errors.length">
              <h3 class="font-semibold text-rose-700 mb-2">Errors</h3>
              <pre class="text-[11px] bg-rose-950 text-rose-100 p-3 rounded max-h-48 overflow-auto">{{ errors.join('\n') }}</pre>
            </section>
          </div>
        </aside>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
