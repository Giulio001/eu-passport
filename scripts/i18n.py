"""Extract English messages and rebuild standard WordPress POT, PO, MO and Jed JSON."""
from pathlib import Path
import ast
import hashlib
import json
import shutil
import struct
import subprocess

ROOT = Path(__file__).resolve().parent.parent
DOMAIN = 'giuliomax-product-passport'
LANG = ROOT / DOMAIN / 'languages'
LOCALE = 'it_IT'

def read_po(path):
    entries = {}
    msgid = None
    msgstr = ''
    current = None
    fuzzy = False
    def finish():
        if msgid is not None and not fuzzy:
            entries[msgid] = msgstr
    if not path.exists():
        return entries
    for line in path.read_text().splitlines()+['']:
        if line.startswith('#,') and 'fuzzy' in line:
            fuzzy = True
        elif line.startswith('msgid '):
            finish(); msgid = ast.literal_eval(line[6:]); msgstr = ''; current = 'id'
        elif line.startswith('msgstr '):
            msgstr = ast.literal_eval(line[7:]); current = 'str'
        elif line.startswith('"'):
            value = ast.literal_eval(line)
            if current == 'id': msgid += value
            elif current == 'str': msgstr += value
        elif not line:
            finish(); msgid = None; current = None; msgstr = ''; fuzzy = False
    return entries

php = [shutil.which('php')] if shutil.which('php') else [str(ROOT/'node_modules/.bin/php-wasm-cli')]
rows = json.loads(subprocess.check_output(['node','scripts/i18n-extract.cjs'],cwd=ROOT))
rows += json.loads(subprocess.check_output(php+['scripts/i18n-extract.php'],cwd=ROOT))
messages = {}
for row in rows:
    messages.setdefault(row['id'], set()).add(f"{row['file']}:{row['line']}")
translations = read_po(LANG/f'{DOMAIN}-{LOCALE}.po')
version = json.loads((ROOT/'package.json').read_text())['version']
header = f'Project-Id-Version: Giuliomax Product Passport {version}\nReport-Msgid-Bugs-To: https://github.com/Giulio001/eu-passport/issues\nPOT-Creation-Date: 2026-10-30 00:00+0000\nMIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\nX-Domain: {DOMAIN}\n'
it_header = header+'Language: it_IT\nPlural-Forms: nplurals=2; plural=(n != 1);\n'
quote = lambda text: json.dumps(text,ensure_ascii=False)
def write_catalogue(path, translated=False):
    lines=['# Giuliomax Product Passport. GPL-2.0-or-later.','msgid ""','msgstr '+quote(it_header if translated else header),'']
    for msgid in sorted(messages):
        lines += ['#: '+' '.join(sorted(messages[msgid])),'msgid '+quote(msgid),'msgstr '+quote(translations.get(msgid,'') if translated else ''),'']
    path.write_text('\n'.join(lines))
LANG.mkdir(exist_ok=True)
write_catalogue(LANG/f'{DOMAIN}.pot')
write_catalogue(LANG/f'{DOMAIN}-{LOCALE}.po',True)
mo={'':it_header,**{key:translations.get(key,'') for key in messages if translations.get(key)}}
keys=sorted(mo);ids=b'';values=b'';original=[];translated=[]
for key in keys:
    a=key.encode();b=mo[key].encode();original.append((len(a),len(ids)));translated.append((len(b),len(values)));ids+=a+b'\0';values+=b+b'\0'
size=len(keys);start=28+size*16
binary=struct.pack('<7I',0x950412de,0,size,28,28+size*8,0,0)
binary+=b''.join(struct.pack('<2I',length,start+offset) for length,offset in original)
binary+=b''.join(struct.pack('<2I',length,start+len(ids)+offset) for length,offset in translated)
(LANG/f'{DOMAIN}-{LOCALE}.mo').write_bytes(binary+ids+values)
for old in LANG.glob(f'{DOMAIN}-{LOCALE}-*.json'):old.unlink()
for file in ['assets/admin.js','assets/designer.js','assets/block.js']:
    ids={r['id'] for r in rows if r['file']==file}
    data={'domain':DOMAIN,'locale_data':{'messages':{'':{'domain':DOMAIN,'lang':LOCALE,'plural-forms':'nplurals=2; plural=(n != 1);'},**{key:[translations[key]] for key in sorted(ids) if translations.get(key)}}}}
    (LANG/f'{DOMAIN}-{LOCALE}-{hashlib.md5(file.encode()).hexdigest()}.json').write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
missing=[key for key in messages if not translations.get(key)]
print(json.dumps({'messages':len(messages),'italian_translated':len(messages)-len(missing),'missing':missing},ensure_ascii=False))
