<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProjectStore } from '@/stores/project'
import LoadingButton from '@/components/LoadingButton.vue'
import ErrorBanner from '@/components/ErrorBanner.vue'
import StatusPill from '@/components/StatusPill.vue'
import ProgressSteps from '@/components/ProgressSteps.vue'

const props = defineProps({ id: { type: String, required: true } })
const router = useRouter()
const store = useProjectStore()

const activeIndex = ref(null)

onMounted(async () => {
  try {
    await store.loadProject(props.id)
    await store.loadCandidates(props.id)
    if (store.selectedCandidate) activeIndex.value = store.selectedCandidate.index
  } catch { /* store.error */ }
})

async function confirm() {
  if (activeIndex.value == null) return
  try {
    await store.selectCandidate(activeIndex.value)
    router.push({ name: 'building', params: { id: props.id } })
  } catch { /* store.error */ }
}
</script>

<template>
  <section class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-slate-900">Pick the right building</h1>
        <p class="text-xs text-slate-500 font-mono mt-1">{{ id }}</p>
      </div>
      <StatusPill :status="store.status" />
    </header>

    <ProgressSteps :status="store.status" />

    <ErrorBanner :error="store.error" @dismiss="store.error = null" />

    <div class="card">
      <p v-if="store.summary?.normalized_query" class="text-sm text-slate-600">
        Resolved to
        <strong>{{ store.summary.normalized_query.formatted_address }}</strong>
        (confidence {{ (store.summary.normalized_query.confidence * 100).toFixed(0) }} %)
      </p>

      <div v-if="store.candidates.length === 0 && !store.loading" class="mt-6 text-sm text-slate-500">
        No candidates found.
      </div>

      <ul class="mt-6 grid sm:grid-cols-2 gap-3">
        <li v-for="c in store.candidates" :key="c.index">
          <button
            type="button"
            :class="[
              'w-full text-left p-4 rounded-lg border transition',
              activeIndex === c.index
                ? 'border-brand-600 ring-2 ring-brand-200 bg-brand-50'
                : 'border-slate-200 hover:border-brand-300 hover:bg-slate-50',
            ]"
            @click="activeIndex = c.index"
          >
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-slate-500">#{{ String(c.index).padStart(2, '0') }}</span>
              <span
                :class="[
                  'pill',
                  c.solar_supported ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600',
                ]"
              >
                {{ c.solar_supported ? 'solar supported' : 'solar unsupported' }}
              </span>
            </div>
            <div class="mt-2 text-sm font-medium text-slate-900 leading-snug">
              {{ c.formatted_address }}
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-500">
              <div><dt class="inline">Lat</dt>: <dd class="inline font-mono">{{ c.lat.toFixed(5) }}</dd></div>
              <div><dt class="inline">Lng</dt>: <dd class="inline font-mono">{{ c.lng.toFixed(5) }}</dd></div>
              <div><dt class="inline">Confidence</dt>: <dd class="inline">{{ (c.confidence * 100).toFixed(0) }} %</dd></div>
              <div v-if="c.notes"><dt class="inline">Notes</dt>: <dd class="inline">{{ c.notes }}</dd></div>
            </dl>
          </button>
        </li>
      </ul>

      <div class="mt-6 flex items-center justify-between">
        <router-link to="/" class="btn-ghost">← Change address</router-link>
        <LoadingButton
          :loading="store.loading"
          :disabled="activeIndex == null"
          variant="primary"
          @click="confirm"
        >
          Continue to analysis →
        </LoadingButton>
      </div>
    </div>
  </section>
</template>
