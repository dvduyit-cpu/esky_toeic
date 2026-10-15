<script setup>
import { ref, watch, nextTick, useId } from 'vue';
const props = defineProps({ open: Boolean, title: String, busy: Boolean, wide: Boolean });
const emit = defineEmits(['close']);
const dialog = ref(null); const titleId = useId();
watch(() => props.open, async open => { await nextTick(); if (!dialog.value) return; if (open && !dialog.value.open) dialog.value.showModal(); else if (!open && dialog.value.open) dialog.value.close(); }, { immediate: true, flush: 'post' });
function close() { if (!props.busy) emit('close'); }
</script>
<template><Teleport to="body"><dialog ref="dialog" class="app-modal" :class="{ wide }" @cancel.prevent="close" @click="event => { if(event.target === dialog) close(); }" @close="() => { if(open) close(); }" :aria-labelledby="titleId"><header class="modal-header"><h2 :id="titleId">{{ title }}</h2><button type="button" class="icon-button" aria-label="Đóng hộp thoại" :disabled="busy" @click="close">✕</button></header><div class="modal-body"><slot /></div></dialog></Teleport></template>
