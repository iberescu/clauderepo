<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, default: null },
})

const STEPS = [
  { key: 'candidates_ready', label: 'Address' },
  { key: 'candidate_selected', label: 'Building' },
  { key: 'analysis_ready', label: 'Analysis' },
  { key: 'layout_ready', label: 'Layout' },
  { key: 'render_ready', label: 'Render' },
  { key: 'proposal_ready', label: 'Proposal' },
]
const ORDER = [
  'created','searching','candidates_ready','candidate_selected','analyzing',
  'analysis_ready','layout_ready','rendering','render_ready','pricing_ready','proposal_ready','completed',
]

const rank = computed(() => ORDER.indexOf(props.status))

function reached(stepKey) {
  const idx = ORDER.indexOf(stepKey)
  return rank.value >= idx
}
</script>

<template>
  <ol class="flex items-center gap-2 flex-wrap">
    <li
      v-for="(s, i) in STEPS"
      :key="s.key"
      class="flex items-center gap-2"
    >
      <div
        :class="[
          'flex items-center justify-center w-6 h-6 rounded-full text-[11px] font-bold',
          reached(s.key) ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-500',
        ]"
      >{{ i + 1 }}</div>
      <span :class="reached(s.key) ? 'text-slate-800 font-medium' : 'text-slate-400'">
        {{ s.label }}
      </span>
      <span v-if="i < STEPS.length - 1" class="mx-1 text-slate-300">→</span>
    </li>
  </ol>
</template>
