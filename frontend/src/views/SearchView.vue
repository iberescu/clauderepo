<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProjectStore } from '@/stores/project'
import api from '@/services/api'
import LoadingButton from '@/components/LoadingButton.vue'
import ErrorBanner from '@/components/ErrorBanner.vue'

const router = useRouter()
const store = useProjectStore()

const form = ref({
  street: '',
  city: '',
  country: '',
})

const recent = ref([])
const loadingRecent = ref(false)

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
  if (!form.value.street || !form.value.city || !form.value.country) return
  try {
    const project = await store.createProject(form.value)
    router.push({ name: 'candidates', params: { id: project.project_id } })
  } catch { /* store.error already set */ }
}

function useExample() {
  form.value = { street: 'Rua das Flores', city: 'Lisbon', country: 'Portugal' }
}
</script>

<template>
  <section class="grid lg:grid-cols-5 gap-6">
    <div class="lg:col-span-3">
      <div class="card">
        <h1 class="text-2xl font-bold text-slate-900">Start a rooftop proposal</h1>
        <p class="text-slate-500 text-sm mt-1">Enter a street address. We'll pull building insights, fit panels, and produce a branded offer in one flow.</p>

        <ErrorBanner class="mt-4" :error="store.error" @dismiss="store.error = null" />

        <form class="mt-6 space-y-4" @submit.prevent="submit">
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
          <div class="flex flex-wrap items-center gap-3 pt-2">
            <LoadingButton :loading="store.loading" type="submit" variant="primary">
              Discover candidates →
            </LoadingButton>
            <button type="button" class="btn-secondary" @click="useExample">Use example address</button>
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
