from pathlib import Path
for root in ['app','bootstrap','routes','database','tests']:
    for p in Path(root).rglob('*.php'):
        b=p.read_bytes()
        if b.startswith(b'\xef\xbb\xbf'):p.write_bytes(b[3:])
