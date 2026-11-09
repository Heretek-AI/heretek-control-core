from pathlib import Path
root=Path('F:/laragon/www/msrplugins/wp-content/plugins/visualcomposer')
for p in (root/'public').rglob('*.js'):
    if p.stat().st_size>15000000: continue
    s=p.read_text(encoding='utf-8',errors='replace')
    for needle in ['vcv-data','key:"children"','key:"toJS"','elements:', 'document:data']:
        start=s.find(needle)
        if start>=0:
            print(p.name,needle,s[max(0,start-150):start+1100])
