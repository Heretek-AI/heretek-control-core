import json, runpy
from pathlib import Path
m=runpy.run_path(str(Path(__file__).with_name('visual-composer-mcp.py')))
def call(slug,args=None,error=False):
    r=m['rpc_tool']('emcp-tools-call-tool',{'name':'emcp-tools/visual-composer-'+slug,'arguments':args or {}})
    if error:
        assert r.get('isError'),r
        print('PASS rejected '+slug,flush=True)
        return r
    assert not r.get('isError'),r
    d=r.get('structuredContent') or json.loads(r['content'][0]['text'])
    print('PASS '+slug,flush=True)
    return d
if __name__=='__main__':
    print(call('get-context'))
    print(len(call('list-elements')['elements']))
    s={tag:call('get-element-schema',{'tag':tag}) for tag in ['row','column','textBlock','singleImage','basicButton']}
    Path(__file__).with_name('visual-composer-live-schemas.json').write_text(json.dumps(s),encoding='utf-8')
    p=call('create-page',{'title':'Still House: Visual Composer Integration Test'})
    Path(__file__).with_name('visual-composer-live-state.json').write_text(json.dumps(p),encoding='utf-8')
    print(json.dumps(p),flush=True)
