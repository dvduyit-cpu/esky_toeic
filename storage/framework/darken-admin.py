from pathlib import Path
p=Path('resources/js/Layouts/AppLayout.vue');s=p.read_text(encoding='utf-8-sig');widget='<div class="workspace"><span class="workspace-icon"><AppIcon name="book"/></span><div><strong>Không gian quản trị</strong><small>TOEIC · VSTEP · Môn học</small></div></div>';assert widget in s;s=s.replace(widget,'');p.write_text(s,encoding='utf-8')
