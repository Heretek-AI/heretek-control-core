import runpy
from pathlib import Path
call=runpy.run_path(str(Path(__file__).with_name('visual-composer-live.py')))['call']
p=call('get-page',{'post_id':2238})
d=p['published']
css=d['css']+'''\n.sh-section.vce-row>.vce-row-content{flex-wrap:nowrap}.sh-section.vce-row>.vce-row-content>.vce-col{flex:1 1 0;min-width:0;margin:0}@media(max-width:767px){.sh-section.vce-row>.vce-row-content>.vce-col{flex:1 1 auto;width:100%}}'''
css+='\n.page-id-2238 .entry-header{display:none}'
p=call('stage-document',{'post_id':2238,'expected_hash':p['content_hash'],'elements':d['elements'],'html':d['html'],'css':css})
call('publish-page',{'post_id':2238,'expected_hash':p['content_hash'],'confirm':True})
