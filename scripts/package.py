"""Build the complete runtime ZIP deterministically; Node/test tools are excluded."""
from pathlib import Path
import hashlib
import re
import zipfile

root = Path(__file__).resolve().parent.parent
source = root / 'giuliomax-product-passport'
version = re.search(r'\* Version:\s*(\S+)', (source / 'giuliomax-product-passport.php').read_text()).group(1)
target = root / 'dist' / f'giuliomax-product-passport-{version}.zip'
target.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(source.rglob('*')):
        if path.is_file():
            info = zipfile.ZipInfo(path.relative_to(root).as_posix(), date_time=(1980, 1, 1, 0, 0, 0))
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, path.read_bytes(), compresslevel=9)
with zipfile.ZipFile(target) as archive:
    assert archive.testzip() is None
    assert 'giuliomax-product-passport/giuliomax-product-passport.php' in archive.namelist()
    assert not any('/tests/' in name or '/node_modules/' in name for name in archive.namelist())
digest = hashlib.sha256(target.read_bytes()).hexdigest()
(root / 'dist' / 'SHA256SUMS').write_text(f'{digest}  {target.name}\n')
print(target)
print('SHA256', digest)
