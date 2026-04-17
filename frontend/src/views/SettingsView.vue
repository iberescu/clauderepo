<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useSettingsStore } from '@/stores/settings'
import LoadingButton from '@/components/LoadingButton.vue'
import ErrorBanner from '@/components/ErrorBanner.vue'

const store = useSettingsStore()
const activeSection = ref('pricing')
const draft = reactive({})
const savedAt = ref(null)

const sectionOrder = ['panel', 'pricing', 'savings', 'branding']

onMounted(async () => {
  await store.load()
  resetDraft()
})

function resetDraft() {
  for (const s of sectionOrder) {
    const values = store.sections?.[s] || {}
    draft[s] = { ...values }
  }
}

const currentDraft = computed({
  get: () => draft[activeSection.value] || {},
  set: (v) => { draft[activeSection.value] = v },
})

async function save() {
  try {
    const values = { ...draft[activeSection.value] }
    // coerce numeric-looking strings back to numbers so server math stays numeric
    for (const k of Object.keys(values)) {
      const raw = values[k]
      if (typeof raw === 'string' && raw.trim() !== '' && !Number.isNaN(Number(raw)) && /^-?\d+(\.\d+)?$/.test(raw.trim())) {
        values[k] = Number(raw)
      }
    }
    await store.save(activeSection.value, values)
    savedAt.value = new Date().toLocaleTimeString()
  } catch { /* store.error */ }
}
</script>

<template>
  <section class="space-y-6">
    <header>
      <h1 class="text-xl font-bold text-slate-900">Settings</h1>
      <p class="text-sm text-slate-500 mt-1">
        Editable defaults persisted to <code class="text-xs">storage/app/config/</code>. Changes affect new calculations immediately.
      </p>
    </header>

    <ErrorBanner :error="store.error" @dismiss="store.error = null" />

    <div class="card">
      <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-3 mb-4">
        <button
          v-for="s in sectionOrder"
          :key="s"
          type="button"
          :class="[
            'px-3 py-1.5 rounded-md text-sm',
            activeSection === s ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100',
          ]"
          @click="activeSection = s"
        >{{ s }}</button>
      </div>

      <div v-if="store.loading && !store.sections" class="text-sm text-slate-400">Loading…</div>

      <form v-else-if="store.sections" class="grid sm:grid-cols-2 gap-4" @submit.prevent="save">
        <div v-for="(val, key) in currentDraft" :key="key">
          <label class="label" :for="`f-${key}`">{{ key }}</label>
          <input
            :id="`f-${key}`"
            v-model="draft[activeSection][key]"
            class="input"
          />
        </div>
        <div class="sm:col-span-2 flex items-center gap-3 pt-2">
          <LoadingButton :loading="store.loading" variant="primary" type="submit">Save {{ activeSection }}</LoadingButton>
          <button type="button" class="btn-secondary" @click="resetDraft">Reset draft</button>
          <span v-if="savedAt" class="text-xs text-emerald-700">Saved at {{ savedAt }}</span>
        </div>
      </form>
    </div>
  </section>
</template>
