<script setup>
import { computed } from 'vue';

const props = defineProps({
  /** @type {{ t: string, v: number }[]} */
  points: { type: Array, default: () => [] },
  /** Optional horizontal guideline (e.g. typical high) in same units as v */
  guide: { type: Number, default: null },
  stroke: { type: String, default: '#111' },
  height: { type: Number, default: 72 },
});

const width = 200;

const path = computed(() => {
  const pts = props.points;
  if (!pts.length) return '';
  const values = pts.map((p) => p.v);
  let min = Math.min(...values);
  let max = Math.max(...values);
  if (props.guide != null) {
    min = Math.min(min, props.guide);
    max = Math.max(max, props.guide);
  }
  const pad = max === min ? 0.5 : (max - min) * 0.08;
  min -= pad;
  max += pad;
  const span = max - min || 1;
  return pts
    .map((p, i) => {
      const x = pts.length === 1 ? width / 2 : (i / (pts.length - 1)) * width;
      const y = props.height - ((p.v - min) / span) * (props.height - 8) - 4;
      return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
    })
    .join(' ');
});

const guideY = computed(() => {
  if (props.guide == null || !props.points.length) return null;
  const values = props.points.map((p) => p.v);
  let min = Math.min(...values, props.guide);
  let max = Math.max(...values, props.guide);
  const pad = max === min ? 0.5 : (max - min) * 0.08;
  min -= pad;
  max += pad;
  const span = max - min || 1;
  return props.height - ((props.guide - min) / span) * (props.height - 8) - 4;
});
</script>

<template>
  <div class="spark" :style="{ height: height + 'px' }" aria-hidden="true">
    <svg :viewBox="`0 0 ${width} ${height}`" preserveAspectRatio="none">
      <line
        v-if="guideY != null"
        :x1="0"
        :x2="width"
        :y1="guideY"
        :y2="guideY"
        stroke="#8a5a00"
        stroke-dasharray="4 3"
        stroke-width="1"
      />
      <path v-if="path" :d="path" fill="none" :stroke="stroke" stroke-width="2" />
    </svg>
  </div>
</template>
