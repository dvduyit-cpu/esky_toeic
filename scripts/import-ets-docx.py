"""Extract this supplied ETS test without executing any document content."""
import json
import re
import shutil
import zipfile
from pathlib import Path
from xml.etree import ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'storage/app/imports/ets-2026-test-1'
MEDIA = ROOT / 'storage/app/public/exam-media/ets-2026-test-1'
OUT.mkdir(parents=True, exist_ok=True)
MEDIA.mkdir(parents=True, exist_ok=True)
NS = {'w': 'http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'a': 'http://schemas.openxmlformats.org/drawingml/2006/main', 'r': 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'}
questions, groups = [], {}

def group_for(number):
    if number <= 6: return (number, number)
    if number <= 31: return None
    if number <= 70:
        first = 32 + (number - 32) // 3 * 3
        return (first, first + 2)
    if number <= 100:
        first = 71 + (number - 71) // 3 * 3
        return (first, first + 2)
    return None

def part_for(n):
    return next(p for end, p in [(6,1),(31,2),(70,3),(100,4),(130,5),(146,6),(200,7)] if n <= end)

for kind, filename in [('listening', 'ETS_TOEIC_2026_TEST_1_Listening_1.docx'), ('reading', 'ETS_TOEIC_2026_TEST_1_Reading (1).docx')]:
    source = Path('C:/Users/dvduy/Downloads/TOEIC') / filename
    shutil.copy2(source, OUT / filename)
    with zipfile.ZipFile(source) as archive:
        rels = {r.attrib['Id']: r.attrib['Target'] for r in ET.fromstring(archive.read('word/_rels/document.xml.rels'))}
        root = ET.fromstring(archive.read('word/document.xml'))
        rows = []
        for p in root.findall('.//w:p', NS):
            text = ''.join(t.text or '' for t in p.findall('.//w:t', NS)).strip()
            images = []
            for b in p.findall('.//a:blip', NS):
                target = rels.get(b.attrib.get('{'+NS['r']+'}embed'))
                if target:
                    asset = MEDIA / (kind + '-' + Path(target).name)
                    asset.write_bytes(archive.read('word/' + target))
                    images.append(asset.name)
            rows.append((text, images))

    number = 1 if kind == 'listening' else 101
    pending, pending_images, passage = [], [], []
    current_group = None
    begun = False
    for text, images in rows:
        if re.match(r'^PART\s+[1-7]', text):
            begun = True
            pending = []
            continue
        if not begun or text.startswith(('Directions:', 'Example Question:', 'Statement (C)')):
            continue
        heading = re.match(r'^Questions\s+(\d+)[–-](\d+)', text)
        if heading:
            if kind == 'reading':
                current_group = (int(heading[1]), int(heading[2]))
                groups[current_group] = {'part': part_for(number), 'title': text, 'passage': [], 'images': []}
                passage = []
            pending = []
            continue
        correct = re.fullmatch(r'Đáp án đúng là\s*([ABCD])\s*', text)
        if not correct:
            if text: pending.append(text)
            if images:
                key = group_for(number) if kind == 'listening' else current_group
                assert key, (number, images)
                group = groups.setdefault(key, {'part': part_for(number), 'title': f'Questions {key[0]}–{key[1]}', 'passage': [], 'images': []})
                group['images'].extend(images)
            continue

        part = part_for(number)
        key = group_for(number) if kind == 'listening' else current_group
        if key:
            group = groups.setdefault(key, {'part': part, 'title': f'Questions {key[0]}–{key[1]}', 'passage': [], 'images': []})
        if kind == 'reading':
            starts = [i for i, line in enumerate(pending) if re.match(rf'^{number}\.', line)]
            assert len(starts) == 1, (number, pending)
            start = starts[0]
            if key: groups[key]['passage'].extend(pending[:start])
            body = '\n'.join(pending[start:])
            body = re.sub(rf'^{number}\.\s*', '', body)
            # Part 6's supplied DOCX omits the (A) label on its first choice.
            if part == 6 and '(A)' not in body:
                body = '(A) ' + body
        elif number <= 31:
            body = '\n'.join(pending)
            body = re.sub(r'^Question\s+\d+[.:]\s*', '', body)
        else:
            start = next(i for i, line in enumerate(pending) if '(A)' in line)
            if key: groups[key]['passage'].extend(pending[:start])
            body = '\n'.join(pending[start:])
            body = re.sub(r'^\d+\.\s*', '', body)
        if number <= 31:
            content = 'Listen and choose the correct answer.'
            options = {letter: f'({letter})' for letter in ('ABC' if part == 2 else 'ABCD')}
        else:
            pieces = re.split(r'\(([ABCD])\)', body)
            content = pieces[0].strip() or f'Choose the best answer for blank [{number}].'
            options = {pieces[i]: pieces[i+1].strip() for i in range(1, len(pieces), 2)}
            assert list(options) == list('ABCD') and all(options.values()), (number, body)
        assert correct[1] in options
        questions.append({'number': number, 'part': part, 'content': content, 'options': options, 'correct_option': correct[1], 'group': f'{key[0]}-{key[1]}' if key else None})
        pending = []
        number += 1
    assert number == (101 if kind == 'listening' else 201), number

result_groups = {}
for key, group in groups.items():
    images = list(dict.fromkeys(group.pop('images')))
    path = None
    if images:
        # Stack all source pages; do not omit second/third texts in Part 7.
        target = MEDIA / f'group-{key[0]}-{key[1]}.png'
        path = 'exam-media/ets-2026-test-1/' + target.name
    result_groups[f'{key[0]}-{key[1]}'] = {**group, 'passage': '\n'.join(group['passage']).strip(), 'media_path': path, 'source_images': images}
assert [q['number'] for q in questions] == list(range(1,201))
payload = {'title': 'ETS TOEIC 2026 - Test 1', 'questions': questions, 'groups': result_groups, 'missing_audio': True}
(OUT / 'parsed.json').write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding='utf-8')
print(json.dumps({'questions':len(questions), 'groups':len(groups), 'parts':{p:sum(q['part']==p for q in questions) for p in range(1,8)}, 'groups_with_images':sum(bool(g['media_path']) for g in result_groups.values()), 'missing_audio': True}))
