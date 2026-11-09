import runpy
import re
from pathlib import Path
m = runpy.run_path(str(Path(__file__).with_name('blocksy-landing.py')))
invoke = m['invoke']
p = invoke('get-post', {'post_id': 2235})
s = re.sub(r'<!-- wp:blocksy/content-block (.*?) /-->', r'<!-- wp:blocksy/content-block \1 --><div>Blocksy: Content Block Filter</div><!-- /wp:blocksy/content-block -->', p['content'])
old = m['h']('Less noise.<br>More room to live.')
new = m['block']('heading', {'style': {'typography': {'fontSize': 'clamp(32px,4vw,64px)', 'lineHeight': '1.1'}}}, '<h2 class="wp-block-heading" style="font-size:clamp(32px,4vw,64px);line-height:1.1">Less noise.<br>More room to live.</h2>')
s = s.replace(old, new)
print(invoke('update-post', {'post_id': 2235, 'content': s}))
call = m['m']['call']
c = call('blocksy-content-read', 'get-content-block', {'post_id': 2233})
c = call('blocksy-content-write', 'unpublish-content-block', {'post_id': 2233, 'expected_hash': c['content_hash'], 'confirm': True})
old = m['h']('A considered space starts with a conversation.')
new = m['block']('heading', {'style': {'color': {'text': '#ffffff'}}}, '<h2 class="wp-block-heading has-text-color" style="color:#ffffff">A considered space starts with a conversation.</h2>')
c = call('blocksy-content-write', 'update-content-block', {'post_id': 2233, 'expected_hash': c['content_hash'], 'content': c['content'].replace(old, new)})
call('blocksy-content-write', 'publish-content-block', {'post_id': 2233, 'expected_hash': c['content_hash'], 'confirm': True})
