<template>
  <button
    type="button"
    :disabled="disabled"
    class="flex items-center gap-2 w-full text-left px-2 py-1.5 text-[13px] rounded transition-colors"
    :class="[
      danger ? 'text-red-600 hover:bg-red-50' : '',
      disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer hover:bg-[var(--bg-hover)]',
    ]"
    :style="!danger ? 'color:var(--fg)' : ''"
    @click="!disabled && $emit('click')"
  >
    <slot />
  </button>
</template>

<script setup>
defineProps({
  disabled: { type: Boolean, default: false },
  danger:   { type: Boolean, default: false },
})
defineEmits(['click'])
</script>
