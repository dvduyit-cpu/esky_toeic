import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
let branding = { software_name: 'E-SKY EXAM' };
const fonts = { system: 'Inter, "Segoe UI", Arial, sans-serif', arial: 'Arial, sans-serif', georgia: 'Georgia, "Times New Roman", serif' };
const escapeTitle = value => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
function applyBranding(next) {
 if (!next) return;
 const previous = branding.software_name;
 branding = next;
 const style = document.documentElement.style;
 for (const [name, value] of Object.entries({ '--menu-color': next.menu_color || '#E0E7EF', '--menu-ink': next.menu_ink || '#20354B', '--menu-muted': next.menu_muted || '#4C6278', '--page-background': next.background_color || '#E5EAF0', '--page-ink': next.background_ink || '#20354B', '--page-muted': next.background_muted || '#4C6278', '--page-surface': next.background_surface || '#F9FAFB', '--page-border': next.background_border || '#889DB2', '--page-scheme': next.background_scheme || 'light', '--theme-color': next.primary_color, '--theme-on-primary': next.on_primary, '--primary': next.primary_color, '--on-primary': next.on_primary, '--accent': next.accent_color, '--app-font': fonts[next.font_family] || fonts.system, '--app-font-size': `${next.font_size}px`, '--corner-radius': `${next.corner_radius}px`, '--font-scale': String(next.font_size / 15) })) style.setProperty(name, value);
 const icon = document.querySelector('link[rel="icon"]');
 if (icon) icon.href = next.logo_url || '/favicon.svg';
 if (previous && document.title.endsWith(' · ' + previous)) document.title = document.title.slice(0, -(previous.length)) + next.software_name;
}
createInertiaApp({
 title: (title, page) => `${title} · ${escapeTitle(page?.props.branding?.software_name || branding.software_name)}`,
 resolve: name => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
 setup({ el, App, props, plugin }) {
  applyBranding(props.initialPage.props.branding);
  router.on('navigate', event => applyBranding(event.detail.page.props.branding));
  router.on('success', event => applyBranding(event.detail.page.props.branding));
  createApp({ render: () => h(App, props) }).use(plugin).mount(el);
 },
 progress: { color: 'var(--primary)' },
});
