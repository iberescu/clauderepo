<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useProjectStore } from '@/stores/project'
import api from '@/services/api'
import LoadingButton from '@/components/LoadingButton.vue'
import ErrorBanner from '@/components/ErrorBanner.vue'
import StatusPill from '@/components/StatusPill.vue'
import ProgressSteps from '@/components/ProgressSteps.vue'

const props = defineProps({ id: { type: String, required: true } })
const router = useRouter()
const store = useProjectStore()

const busy = ref(null)
const renderImage = ref(null)
const render3dImage = ref(null)
const solarImagery = ref({ rgb: null, mask: null, flux: null })

const hasAnalysis = computed(() => store.hasReached('analysis_ready'))
const hasLayout = computed(() => store.hasReached('layout_ready'))
const hasRender = computed(() => store.hasReached('render_ready'))

async function refreshRenderImage() {
  if (!hasRender.value) return
  const ts = Date.now()
  renderImage.value = api.renderImageUrl(props.id, 'roof_render.png') + '?ts=' + ts
  render3dImage.value = api.renderImageUrl(props.id, 'roof_render_3d.png') + '?ts=' + ts
}

async function refreshSolarImagery() {
  if (!hasAnalysis.value) return
  try {
    const a = await api.analysis(props.id)
    const imagery = a?.imagery || {}
    const ts = Date.now()
    solarImagery.value = {
      rgb:  imagery.rgb  ? api.solarImageUrl(props.id, 'rgb.png')  + '?ts=' + ts : null,
      mask: imagery.mask ? api.solarImageUrl(props.id, 'mask.png') + '?ts=' + ts : null,
      flux: imagery.flux ? api.solarImageUrl(props.id, 'flux.png') + '?ts=' + ts : null,
    }
  } catch { /* ignore, keep placeholders */ }
}

onMounted(async () => {
  try {
    await store.loadProject(props.id)
    if (hasAnalysis.value) {
      const r = await api.analysis(props.id).catch(() => null)
      if (r) store.analysis = r.analysis || r
    }
    if (hasLayout.value) {
      const l = await api.layout(props.id).catch(() => null)
      if (l) store.layout = l.layout || l
    }
    await refreshSolarImagery()
    await refreshRenderImage()
  } catch { /* store.error */ }
})

async function runStep(key, fn) {
  busy.value = key
  try {
    await fn()
    if (key === 'analysis') await refreshSolarImagery()
    if (key === 'render') await refreshRenderImage()
  } catch { /* store.error */ }
  finally { busy.value = null }
}

function go() {
  router.push({ name: 'proposal', params: { id: props.id } })
}
</script>

<template>
  <section class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-slate-900">Roof analysis &amp; panel layout</h1>
        <p class="text-xs text-slate-500 mt-1">
          {{ store.selectedCandidate?.formatted_address || store.summary?.request?.street }}
        </p>
      </div>
      <StatusPill :status="store.status" />
    </header>

    <ProgressSteps :status="store.status" />
    <ErrorBanner :error="store.error" @dismiss="store.error = null" />

    <div class="grid lg:grid-cols-2 gap-6">
      <!-- Step A: analysis -->
      <div class="card space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold text-slate-900">1 · Building insights</h2>
          <LoadingButton
            :loading="busy === 'analysis'"
            :disabled="store.loading"
            variant="secondary"
            @click="runStep('analysis', () => store.runAnalysis())"
          >
            {{ hasAnalysis ? 'Re-run' : 'Run analysis' }}
          </LoadingButton>
        </div>
        <p class="text-sm text-slate-500">
          Calls the Solar API (or the deterministic fake) to derive roof segments, pitch, azimuth and sunshine hours.
        </p>

        <div v-if="store.analysis" class="grid grid-cols-2 gap-3">
          <div class="stat">
            <div class="stat-label">Usable area</div>
            <div class="stat-value">{{ store.analysis.usable_roof_area_m2?.toFixed(1) }} <span class="text-sm text-slate-500 font-medium">m²</span></div>
          </div>
          <div class="stat">
            <div class="stat-label">Sunshine / year</div>
            <div class="stat-value">{{ Math.round(store.analysis.average_sunshine_hours_per_year) }} <span class="text-sm text-slate-500 font-medium">h</span></div>
          </div>
          <div class="stat">
            <div class="stat-label">Avg. pitch</div>
            <div class="stat-value">{{ store.analysis.average_pitch_deg?.toFixed(1) }}°</div>
          </div>
          <div class="stat">
            <div class="stat-label">Segments</div>
            <div class="stat-value">{{ store.analysis.usable_segments }}</div>
          </div>
        </div>

        <div v-if="solarImagery.rgb || solarImagery.mask || solarImagery.flux" class="grid grid-cols-3 gap-2 pt-2">
          <figure v-if="solarImagery.rgb" class="space-y-1">
            <img :src="solarImagery.rgb" alt="Aerial RGB" class="w-full h-28 object-cover rounded border border-slate-200" />
            <figcaption class="text-[11px] text-slate-500 text-center">Aerial</figcaption>
          </figure>
          <figure v-if="solarImagery.mask" class="space-y-1">
            <img :src="solarImagery.mask" alt="Roof mask" class="w-full h-28 object-cover rounded border border-slate-200" />
            <figcaption class="text-[11px] text-slate-500 text-center">Roof mask</figcaption>
          </figure>
          <figure v-if="solarImagery.flux" class="space-y-1">
            <img :src="solarImagery.flux" alt="Annual flux" class="w-full h-28 object-cover rounded border border-slate-200" />
            <figcaption class="text-[11px] text-slate-500 text-center">Annual flux</figcaption>
          </figure>
        </div>
      </div>

      <!-- Step B: layout -->
      <div class="card space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold text-slate-900">2 · Panel layout</h2>
          <LoadingButton
            :loading="busy === 'layout'"
            :disabled="store.loading || !hasAnalysis"
            variant="secondary"
            @click="runStep('layout', () => store.runLayout())"
          >
            {{ hasLayout ? 'Re-run' : 'Generate layout' }}
          </LoadingButton>
        </div>
        <p class="text-sm text-slate-500">
          Deterministic grid-fit per segment: tries portrait + landscape with configured setbacks and gaps, picks the orientation that maximises panel count then annual kWh.
        </p>

        <div v-if="store.layout" class="grid grid-cols-2 gap-3">
          <div class="stat">
            <div class="stat-label">Panels</div>
            <div class="stat-value">{{ store.layout.total_panels }}</div>
          </div>
          <div class="stat">
            <div class="stat-label">Capacity</div>
            <div class="stat-value">{{ store.layout.total_kwp?.toFixed(2) }} <span class="text-sm text-slate-500 font-medium">kWp</span></div>
          </div>
          <div class="stat col-span-2">
            <div class="stat-label">Annual production</div>
            <div class="stat-value">{{ Math.round(store.layout.annual_kwh).toLocaleString() }} <span class="text-sm text-slate-500 font-medium">kWh</span></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Step C: render -->
    <div class="card space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-slate-900">3 · AI-enhanced render</h2>
        <LoadingButton
          :loading="busy === 'render'"
          :disabled="store.loading || !hasLayout"
          variant="secondary"
          @click="runStep('render', () => store.runRender())"
        >
          {{ hasRender ? 'Re-render' : 'Generate render' }}
        </LoadingButton>
      </div>
      <p class="text-sm text-slate-500">
        Panels are drawn from the exact coordinates above; Gemini enhances realism but cannot move any module. If the AI call fails, the deterministic overlay is preserved.
      </p>

      <div v-if="hasRender" class="grid md:grid-cols-2 gap-4">
        <figure class="space-y-1">
          <div class="rounded-lg overflow-hidden bg-slate-100 border border-slate-200">
            <img :src="renderImage" alt="Top-down render" class="w-full h-auto object-contain max-h-[420px] mx-auto" />
          </div>
          <figcaption class="text-[11px] text-slate-500 text-center">Top-down render</figcaption>
        </figure>
        <figure class="space-y-1">
          <div class="rounded-lg overflow-hidden bg-slate-100 border border-slate-200">
            <img :src="render3dImage" alt="3D aerial render" class="w-full h-auto object-contain max-h-[420px] mx-auto" />
          </div>
          <figcaption class="text-[11px] text-slate-500 text-center">3D aerial render (Gemini)</figcaption>
        </figure>
      </div>
      <div v-else class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-10 text-center text-sm text-slate-400">
        Render not generated yet.
      </div>
    </div>

    <div class="flex items-center justify-between">
      <router-link :to="{ name: 'candidates', params: { id } }" class="btn-ghost">← Back to candidates</router-link>
      <LoadingButton :disabled="!hasRender" variant="primary" @click="go">
        Continue to proposal →
      </LoadingButton>
    </div>
  </section>
</template>
