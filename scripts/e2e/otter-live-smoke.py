"""MCP contract checks on an isolated test-site draft. Leaves settings unchanged."""
import json,runpy
from pathlib import Path
call=runpy.run_path(str(Path(__file__).with_name('otter-landing.py')))['call']
if __name__=='__main__':
    context=call('get-context'); assert context['site_url']=='https://elementor-mcp.test'
    blocks=call('list-blocks'); assert blocks['count']>=40
    heading=call('get-block-schema',{'name':'themeisle-blocks/advanced-heading'})['example_markup']
    hours=call('get-block-schema',{'name':'themeisle-blocks/business-hours'})['example_markup']
    features=call('get-features'); assert 'otterConditions' in features['extension_attributes']
    patterns=call('list-patterns'); assert patterns['count']>0
    pattern=call('get-pattern',{'name':patterns['patterns'][0]['name']}); assert pattern['content']
    settings=call('get-settings'); call('update-settings',{'expected_hash':'0'*64,'settings':{}},True)
    call('update-settings',{'expected_hash':settings['settings_hash'],'settings':settings['settings']})
    assert call('get-settings')==settings
    p=call('create-page',{'title':'Otter integration smoke fixture (draft)'})
    pid=p['post_id']
    def write(tool,extra=None,error=False):
        p=call('get-post-blocks',{'post_id':pid})
        return call(tool,{'post_id':pid,'expected_hash':p['content_hash'],**(extra or {})},error)
    write('add-block',{'markup':heading})
    call('add-block',{'post_id':pid,'expected_hash':p['content_hash'],'markup':hours},True)
    write('add-block',{'markup':heading},True) # duplicate IDs rejected
    write('add-block',{'markup':hours})
    write('move-block',{'path':[1],'position':{'mode':'prepend'}})
    write('update-block',{'path':[1],'markup':heading.replace('Your heading','Updated native heading')})
    write('remove-block',{'path':[0]})
    write('move-block',{'path':[0],'position':{'mode':'inside','path':[0]}},True)
    write('publish-page',{'confirm':False},True)
    write('set-page-blocks',{'markup':heading,'confirm':False},True)
    write('rebuild-page-styles')
    assert call('get-page-styles',{'post_id':pid})['css']
    write('insert-pattern',{'name':pattern['name']})
    p=call('get-post-blocks',{'post_id':pid}); assert p['status']=='draft'
    Path(__file__).with_name('otter-smoke-state.json').write_text(json.dumps({'post_id':pid,'pattern':pattern['name']}))
    print(json.dumps({'passed':True,'draft_fixture':pid,'blocks':blocks['count'],'patterns':patterns['count'],'settings_unchanged':True}))
