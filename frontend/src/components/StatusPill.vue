<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, default: null },
})

const STYLE = {
  created:            { label: 'Created',              cls: 'bg-slate-100 text-slate-700' },
  searching:          { label: 'Searching address',    cls: 'bg-sky-100 text-sky-800' },
  candidates_ready:   { label: 'Candidates ready',     cls: 'bg-sky-100 text-sky-800' },
  candidate_selected: { label: 'Building selected',    cls: 'bg-indigo-100 text-indigo-800' },
  analyzing:          { label: 'Analyzing',            cls: 'bg-amber-100 text-amber-800' },
  analysis_ready:     { label: 'Analysis ready',       cls: 'bg-amber-100 text-amber-800' },
  layout_ready:       { label: 'Layout ready',         cls: 'bg-emerald-100 text-emerald-800' },
  rendering:          { label: 'Rendering',            cls: 'bg-amber-100 text-amber-800' },
  render_ready:       { label: 'Render ready',         cls: 'bg-emerald-100 text-emerald-800' },
  pricing_ready:      { label: 'Pricing ready',        cls: 'bg-emerald-100 text-emerald-800' },
  proposal_ready:     { label: 'Proposal ready',       cls: 'bg-emerald-200 text-emerald-900' },
  completed:          { label: 'Completed',            cls: 'bg-emerald-200 text-emerald-900' },
  failed:             { label: 'Failed',               cls: 'bg-rose-100 text-rose-800' },
}

const meta = computed(() => STYLE[props.status] || { label: props.status || 'unknown', cls: 'bg-slate-100 text-slate-600' })
</script>

<template>
  <span class="pill" :class="meta.cls">
    <span class="w-2 h-2 rounded-full bg-current opacity-60"></span>
    {{ meta.label }}
  </span>
</template>
