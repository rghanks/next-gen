from pathlib import Path
import json
import hashlib
import difflib

root = Path(__file__).parent
source = Path('C:/xampp/htdocs/empath')
baseline = {entry['path']: entry['hash'] for entry in json.loads((root / 'baseline.json').read_text(encoding='utf-8-sig'))}
paths = list(baseline) + ['resources/views/components/v2/registration/visit-step.blade.php', 'AGENTS.md',
    'app/Http/Controllers/V2/BillingWorkflowController.php', 'resources/views/components/v2/billing/patient-step.blade.php',
    'public/assets/js/v2/billing-patient.js', 'tests/Feature/BillingWorkflowTest.php']
manifest, patch = [], []
for name in dict.fromkeys(paths):
    target = source / name
    raw = target.read_bytes() if target.exists() else b''
    current_hash = hashlib.sha256(raw).hexdigest().upper() if target.exists() else None
    if name in baseline and baseline[name] != current_hash:
        raise RuntimeError(f'Source changed while staging: {name}')
    staged = root / name
    staged_bytes = staged.read_bytes()
    manifest.append({'path': name, 'originalHash': current_hash, 'stagedHash': hashlib.sha256(staged_bytes).hexdigest().upper()})
    before, after = raw.decode('utf-8').splitlines(keepends=True), staged_bytes.decode('utf-8').splitlines(keepends=True)
    before = [line.rstrip('\r\n') + '\n' for line in before]
    after = [line.rstrip('\r\n') + '\n' for line in after]
    patch.extend(difflib.unified_diff(before, after, fromfile='a/' + name, tofile='b/' + name))
    print(name)
(root / 'install-manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
(root / 'billing-feature.patch').write_text(''.join(patch), encoding='utf-8')
print(f'{len(manifest)} files; review patch: {root / "billing-feature.patch"}')
