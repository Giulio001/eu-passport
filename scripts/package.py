from pathlib import Path
import zipfile, hashlib
root=Path(__file__).resolve().parent.parent
source=root/'giuliomax-product-passport'
import re
version=re.search(r'\* Version:\s*(\S+)',(source/'giuliomax-product-passport.php').read_text()).group(1)
target=root/'dist'/f'giuliomax-product-passport-{version}.zip'
target.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
 for p in sorted(source.rglob('*')):
  if p.is_file():z.write(p,p.relative_to(root))
with zipfile.ZipFile(target) as z:
 assert z.testzip() is None
 assert 'giuliomax-product-passport/giuliomax-product-passport.php' in z.namelist()
 assert not any('/tests/' in n or '/node_modules/' in n for n in z.namelist())
print(target)
print('SHA256',hashlib.sha256(target.read_bytes()).hexdigest())
