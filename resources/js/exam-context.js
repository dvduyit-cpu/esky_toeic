import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
export function useExamContext() {
 const page = usePage();
 const context = computed(() => page.props.context);
 const base = computed(() => context.value.admin_base);
 const candidateBase = computed(() => context.value.candidate_base);
 return { page, context, base, candidateBase };
}
