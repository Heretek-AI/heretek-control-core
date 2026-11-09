"""Read bundled vendor source strings without executing any JavaScript."""
import json, re
from pathlib import Path
root=Path('F:/laragon/www/msrplugins/wp-content/plugins/visualcomposer/elements')
for tag in ['row','column','textBlock','singleImage','basicButton']:
    s=(root/tag/'public/dist/element.bundle.js').read_text(encoding='utf-8')
    parts=[json.loads(x) for x in re.findall(r'eval\(("(?:[^"\\]|\\.)*")\)',s)]
    selected=[p for p in parts if f'./{tag}/' in p and ('settings.json' in p or 'component.js' in p)]
    Path(__file__).with_name('visual-composer-'+tag+'.txt').write_text('\n'.join(selected),encoding='utf-8')
    print(tag,[(len(p),p[-100:]) for p in selected])
