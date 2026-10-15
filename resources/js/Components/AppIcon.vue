<script setup>
defineProps({ name: String, size: { type: [Number, String], default: 20 } });
const paths = {
 dashboard: 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
 users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 3a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
 book: 'M4 3h6a3 3 0 0 1 3 3v15a4 4 0 0 0-4-2H3V3h1 M13 6a3 3 0 0 1 3-3h5v16h-5a4 4 0 0 0-3 2',
 file: 'M14 2H5v20h14V7z M14 2v6h5 M8 12h8 M8 16h6',
 menu: 'M3 6h18 M3 12h18 M3 18h18',
 home: 'm3 10 9-7 9 7 M5 9v12h14V9 M9 21v-8h6v8',
 arrow: 'M4 12h16 m-6-6 6 6-6 6',
 logout: 'M9 4H4v16h5 M10 12h11 m-5-5 5 5-5 5',
 headphones: 'M3 14v-3a9 9 0 0 1 18 0v3 M3 12h4v8H3z M17 12h4v8h-4z',
 check: 'm5 12 4 4L19 6',
 chevron: 'm6 9 6 6 6-6',
 close: 'm6 6 12 12 M6 18 18 6',
 graduation: 'm2 9 10-5 10 5-10 5L2 9 M6 11v6c4 3 8 3 12 0v-6 M22 9v7',
 shield: 'M12 3 3 6v6c0 5 9 9 9 9s9-4 9-9V6z m-4 9 3 3 5-6',
 clock: 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0 M12 7v5l3 2',
 play: 'm9 5 11 7-11 7z',
 mail: 'M3 5h18v14H3z m0 1 9 7 9-7',
 sparkles: 'm12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5z M20 2v4 M18 4h4',
 settings: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M10 2h4l1 3 3 1 3-1 2 4-2 2v3l2 2-2 4-3-1-3 1-1 3h-4l-1-3-3-1-3 1-2-4 2-2v-3L1 9l2-4 3 1 3-1z',
};
</script>
<template><svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="paths[name] || paths.file" /></svg></template>
