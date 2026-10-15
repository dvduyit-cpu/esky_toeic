<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import AppModal from './AppModal.vue';
const props = defineProps({ action: Object });
const emit = defineEmits(['close','done']);
const form = useForm({});
watch(() => props.action, () => form.clearErrors());
function submit() { const action = props.action; form.transform(() => action.data || {}).submit(action.method || 'delete', action.url, { preserveScroll: true, onSuccess: () => { emit('done'); emit('close'); } }); }
</script>
<template><AppModal :open="!!action" :title="action?.title || 'Xác nhận'" :busy="form.processing" @close="emit('close')"><template v-if="action"><p>{{ action.message }}</p><div v-if="Object.keys(form.errors).length" class="notice error-notice" role="alert">{{ Object.values(form.errors).join(' · ') }}</div><div class="modal-actions"><button class="secondary" :disabled="form.processing" @click="emit('close')">Hủy</button><button :class="{danger: !action.method || action.method === 'delete'}" :disabled="form.processing" @click="submit">{{ form.processing ? 'Đang xử lý…' : action.button || 'Xác nhận xóa' }}</button></div></template></AppModal></template>
