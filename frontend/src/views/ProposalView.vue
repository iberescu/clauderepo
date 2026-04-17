<script setup>
import { computed, onMounted, ref } from 'vue'
import { useProjectStore } from '@/stores/project'
import api from '@/services/api'
import LoadingButton from '@/components/LoadingButton.vue'
import ErrorBanner from '@/components/ErrorBanner.vue'
import StatusPill from '@/components/StatusPill.vue'
import ProgressSteps from '@/components/ProgressSteps.vue'

const props = defineProps({ id: { type: String, required: true } })
const store = useProjectStore()

const busy = ref(null)
const htmlContent = ref(null)

const hasProposal = computed(() => store.hasReached('proposal_ready'))
const pdfHref = computed(() => api.proposalPdfUrl(props.id))

async function loadHtml() {
  try {
    const r = await fetch(api.proposalHtmlUrl(props.id))
    if (r.ok) htmlContent.value = await r.text()
  } catch { htmlContent.value = null }
}

onMounted(async () => {
  try {
    await store.loadProject(props.id)
    if (store.hasReached('pricing_ready')) {
      const p = await api.pricing(props.id).catch(() => null)
      if (p) store.pricing = p.pricing || p
    }
    if (hasProposal.value) {
      const s = await store.loadProposal(props.id).catch(() => null)
      await loadHtml()
    }
  } catch { /* store.error */ }
})

async function runStep(key, fn) {
  busy.value = key
  try {
    await fn()
    if (key === 'proposal') await loadHtml()
  } catch { /* store.error */ }
  finally { busy.value = null }
}

function formatMoney(n, currency) {
  if (n == null || isNaN(n)) return '—'
  return `${Number(n).toLocaleString(undefined, { maximumFractionDigits: 0 })} ${currency || 'EUR'}`
}
</script>

<template>
  <section class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-slate-900">Pricing &amp; proposal</h1>
        <p class="text-xs text-slate-500 mt-1">{{ store.selectedCandidate?.formatted_address || id }}</p>
      </div>
      <StatusPill :status="store.status" />
    </header>

    <ProgressSteps :status="store.status" />
    <ErrorBanner :error="store.error" @dismiss="store.error = null" />

    <div class="grid lg:grid-cols-2 gap-6">
      <div class="card space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold text-slate-900">1 · Pricing</h2>
          <LoadingButton
            :loading="busy === 'pricing'"
            :disabled="store.loading"
            variant="secondary"
            @click="runStep('pricing', () => store.runPricing())"
          >
            {{ store.pricing ? 'Re-run' : 'Generate pricing' }}
          </LoadingButton>
        </div>
        <div v-if="store.pricing" class="space-y-2 text-sm">
          <div class="grid grid-cols-2 gap-3">
            <div class="stat">
              <div class="stat-label">System size</div>
              <div class="stat-value">{{ store.pricing.total_panels }} <span class="text-sm text-slate-500 font-medium">× {{ store.pricing.total_kwp?.toFixed(2) }} kWp</span></div>
            </div>
            <div class="stat">
              <div class="stat-label">Total</div>
              <div class="stat-value">{{ formatMoney(store.pricing.total, store.pricing.currency) }}</div>
            </div>
          </div>
          <table class="w-full mt-2 text-sm">
            <thead class="text-xs uppercase text-slate-500">
              <tr>
                <th class="text-left py-1">Line</th>
                <th class="text-right py-1">Qty</th>
                <th class="text-right py-1">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(li, i) in store.pricing.line_items" :key="i" class="border-t border-slate-100">
                <td class="py-1">{{ li.label }}</td>
                <td class="py-1 text-right tabular-nums">{{ Number(li.qty).toFixed(2) }} {{ li.unit }}</td>
                <td class="py-1 text-right tabular-nums">{{ Number(li.subtotal).toFixed(2) }}</td>
              </tr>
            </tbody>
            <tfoot class="text-xs text-slate-500">
              <tr><td colspan="2" class="text-right pt-1">Margin</td><td class="text-right pt-1">{{ Number(store.pricing.margin).toFixed(2) }}</td></tr>
              <tr><td colspan="2" class="text-right">VAT</td><td class="text-right">{{ Number(store.pricing.vat).toFixed(2) }}</td></tr>
              <tr class="text-sm font-semibold text-slate-900"><td colspan="2" class="text-right pt-1">Total</td><td class="text-right pt-1">{{ formatMoney(store.pricing.total, store.pricing.currency) }}</td></tr>
            </tfoot>
          </table>
        </div>
        <div v-else class="text-sm text-slate-400">Not generated yet.</div>
      </div>

      <div class="card space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold text-slate-900">2 · Proposal</h2>
          <LoadingButton
            :loading="busy === 'proposal'"
            :disabled="store.loading"
            variant="primary"
            @click="runStep('proposal', () => store.runProposal())"
          >
            {{ hasProposal ? 'Re-generate' : 'Generate proposal' }}
          </LoadingButton>
        </div>
        <p class="text-sm text-slate-500">Composes savings forecast, branded HTML and a standalone PDF.</p>
        <div v-if="store.proposal" class="grid grid-cols-3 gap-3">
          <div class="stat"><div class="stat-label">Payback</div><div class="stat-value">{{ store.proposal.savings?.payback_years?.toFixed(1) }} <span class="text-sm text-slate-500 font-medium">yrs</span></div></div>
          <div class="stat"><div class="stat-label">Year 1 savings</div><div class="stat-value">{{ formatMoney(store.proposal.savings?.first_year_savings, store.pricing?.currency) }}</div></div>
          <div class="stat"><div class="stat-label">Lifetime savings</div><div class="stat-value">{{ formatMoney(store.proposal.savings?.lifetime_savings, store.pricing?.currency) }}</div></div>
        </div>
        <div v-if="hasProposal" class="flex gap-3 pt-2">
          <a :href="pdfHref" target="_blank" rel="noopener" class="btn-primary">Download PDF</a>
          <a :href="api.proposalHtmlUrl(props.id)" target="_blank" rel="noopener" class="btn-secondary">Open HTML</a>
        </div>
      </div>
    </div>

    <div v-if="htmlContent" class="card p-0 overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 bg-slate-50 border-b border-slate-200 text-sm">
        <span class="font-semibold text-slate-700">Proposal preview</span>
        <a :href="api.proposalHtmlUrl(props.id)" target="_blank" rel="noopener" class="text-brand-700 hover:underline">Open in new tab ↗</a>
      </div>
      <iframe
        :srcdoc="htmlContent"
        class="w-full"
        style="height: 900px; border: 0;"
      />
    </div>

    <div class="flex items-center justify-between">
      <router-link :to="{ name: 'building', params: { id } }" class="btn-ghost">← Back to rooftop</router-link>
      <router-link to="/" class="btn-secondary">Start another project</router-link>
    </div>
  </section>
</template>
