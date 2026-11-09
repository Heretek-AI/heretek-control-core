import runpy
from pathlib import Path
call=runpy.run_path(str(Path(__file__).with_name('visual-composer-live.py')))['call']
p=call('get-page',{'post_id':2238}); original=p['published']
p=call('stage-document',{'post_id':2238,'expected_hash':p['content_hash'],'elements':original['elements'],'html':original['html'],'css':original['css']+'\n/* recovery smoke */'})
assert p['published']==original
history=call('list-revisions',{'post_id':2238})['revisions']
p=call('restore-revision',{'post_id':2238,'expected_hash':p['content_hash'],'revision_id':history[0]['id']})
assert p['draft']['css']==original['css'] and p['published']==original
p=call('discard-draft',{'post_id':2238,'expected_hash':p['content_hash'],'confirm':True})
assert p['draft'] is None and p['published']==original
for slug in ['list-pages','list-library','get-design-settings']:
    call(slug)
call('get-page-styles',{'post_id':2238})
print('PASS recovery and discovery; published page unchanged',flush=True)
