from pathlib import Path
p=Path('resources/js/Pages/Settings.vue');s=p.read_text(encoding='utf-8-sig').replace("function chooseLogo(event){clearPreview();const file=event.target.files?.[0];if(!file)return;", "function chooseLogo(event){const file=event.target.files?.[0];if(!file)return;if(objectUrl.value)URL.revokeObjectURL(objectUrl.value);objectUrl.value=null;");p.write_text(s,encoding='utf-8')
