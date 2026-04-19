<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProjectStore } from '@/stores/project'
import api from '@/services/api'
import LoadingButton from '@/components/LoadingButton.vue'
import ErrorBanner from '@/components/ErrorBanner.vue'

const router = useRouter()
const store = useProjectStore()

const mode = ref('address') // 'address' | 'coords'

const form = ref({
  street: '',
  city: '',
  country: '',
  lat: '',
  lng: '',
})

const recent = ref([])
const loadingRecent = ref(false)

const canSubmit = computed(() => {
  if (mode.value === 'address') {
    return !!(form.value.street && form.value.city && form.value.country)
  }
  const lat = parseFloat(form.value.lat)
  const lng = parseFloat(form.value.lng)
  return Number.isFinite(lat) && Number.isFinite(lng)
    && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180
})

async function loadRecent() {
  loadingRecent.value = true
  try {
    const ids = await api.listProjects()
    recent.value = (ids || []).slice(0, 8)
  } catch {
    recent.value = []
  } finally {
    loadingRecent.value = false
  }
}

onMounted(() => {
  store.reset()
  loadRecent()
})

async function submit() {
  if (!canSubmit.value) return
  const payload = mode.value === 'address'
    ? { street: form.value.street, city: form.value.city, country: form.value.country }
    : { lat: parseFloat(form.value.lat), lng: parseFloat(form.value.lng) }
  try {
    const project = await store.createProject(payload)
    router.push({ name: 'candidates', params: { id: project.project_id } })
  } catch { /* store.error already set */ }
}

function useExample() {
  if (mode.value === 'address') {
    form.value.street = 'Rua das Flores'
    form.value.city = 'Lisbon'
    form.value.country = 'Portugal'
  } else {
    form.value.lat = '38.711046'
    form.value.lng = '-9.139968'
  }
}
</script>

<template>
  <section class="grid lg:grid-cols-5 gap-6">
    <div class="lg:col-span-3">
      <div class="card">
        <h1 class="text-2xl font-bold text-slate-900">Start a rooftop proposal</h1>
        <p class="text-slate-500 text-sm mt-1">Enter a street address or drop in raw coordinates. We'll pull building insights, fit panels, and produce a branded offer in one flow.</p>

        <ErrorBanner class="mt-4" :error="store.error" @dismiss="store.error = null" />

        <div class="mt-5 inline-flex rounded-lg border border-slate-200 p-1 bg-slate-50 text-sm">
          <button
            type="button"
            class="px-3 py-1 rounded-md transition"
            :class="mode === 'address' ? 'bg-white shadow text-slate-900 font-medium' : 'text-slate-500 hover:text-slate-700'"
            @click="mode = 'address'"
          >Address</button>
          <button
            type="button"
            class="px-3 py-1 rounded-md transition"
            :class="mode === 'coords' ? 'bg-white shadow text-slate-900 font-medium' : 'text-slate-500 hover:text-slate-700'"
            @click="mode = 'coords'"
          >Coordinates</button>
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
          <template v-if="mode === 'address'">
            <div>
              <label class="label" for="street">Street</label>
              <input id="street" v-model="form.street" class="input" placeholder="e.g. Rua das Flores 12" required />
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="label" for="city">City</label>
                <input id="city" v-model="form.city" class="input" placeholder="Lisbon" required />
              </div>
              <div>
                <label class="label" for="country">Country</label>
                <input id="country" v-model="form.country" class="input" placeholder="Portugal" required />
              </div>
            </div>
          </template>
          <template v-else>
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="label" for="lat">Latitude</label>
                <input id="lat" v-model="form.lat" class="input" type="number" step="any" min="-90" max="90" placeholder="38.711046" required />
              </div>
              <div>
                <label class="label" for="lng">Longitude</label>
                <input id="lng" v-model="form.lng" class="input" type="number" step="any" min="-180" max="180" placeholder="-9.139968" required />
              </div>
            </div>
            <p class="text-xs text-slate-500">Decimal degrees (WGS84). Tip: right-click a spot in Google Maps to copy coordinates.</p>
          </template>
          <div class="flex flex-wrap items-center gap-3 pt-2">
            <LoadingButton :loading="store.loading" :disabled="!canSubmit" type="submit" variant="primary">
              Discover candidates →
            </LoadingButton>
            <button type="button" class="btn-secondary" @click="useExample">
              Use example {{ mode === 'address' ? 'address' : 'coordinates' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <aside class="lg:col-span-2">
      <div class="card">
        <h2 class="font-semibold text-slate-900">Recent projects</h2>
        <p class="text-xs text-slate-500 mt-1">Jump back into an in-flight proposal.</p>
        <div v-if="loadingRecent" class="mt-4 text-sm text-slate-400">Loading…</div>
        <ul v-else-if="recent.length" class="mt-4 divide-y divide-slate-100">
          <li v-for="id in recent" :key="id">
            <router-link
              :to="{ name: 'candidates', params: { id } }"
              class="flex items-center justify-between py-2 text-sm hover:text-brand-700"
            >
              <span class="font-mono truncate">{{ id }}</span>
              <span class="text-slate-400">→</span>
            </router-link>
          </li>
        </ul>
        <p v-else class="mt-4 text-sm text-slate-400">No projects yet.</p>
      </div>
    </aside>
  </section>
</template>
