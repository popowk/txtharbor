#!/usr/bin/env python3
"""Build an update ZIP with an explicit allowlist. Never bundles user data."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import re

BASE = Path(__file__).resolve().parent.parent
VERSION = (BASE / 'VERSION').read_text(encoding='utf-8').strip()
if not re.fullmatch(r'\d+\.\d+\.\d+', VERSION):
    raise SystemExit('VERSION must be in MAJOR.MINOR.PATCH format')
FILES = [
    '0.php', 'admin.php', 'index.php', 'run.php', 'write.php', 'update.php', 'VERSION',
    'inc/main.js', 'inc/theme.js', 'inc/style.css', 'inc/modern.css', 'inc/theme.css', 'inc/content.php',
    'inc/imcode.html', 'inc/github_update.php',
]
OUT = BASE / 'dist' / 'txtharbor-update.zip'
OUT.parent.mkdir(exist_ok=True)
with ZipFile(OUT, 'w', ZIP_DEFLATED, compresslevel=9) as zf:
    for name in FILES:
        p = BASE / name
        if not p.is_file():
            raise SystemExit(f'Missing source file: {name}')
        zf.write(p, f'txt/{name}')
print(f'Created {OUT} ({OUT.stat().st_size:,} bytes) for v{VERSION}')
