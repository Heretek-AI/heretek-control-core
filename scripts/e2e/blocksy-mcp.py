from pathlib import Path
"""Exercise the configured local test server over MCP stdio, with fresh WP state."""
import json
import pathlib
import queue
import subprocess
import threading

ROOT = pathlib.Path(__file__).resolve().parents[2]


def rpc_tool(name, arguments=None):
    config = json.loads((ROOT / '.mcp.json').read_text(encoding='utf-8'))['mcpServers']['elementor-mcp-test']
    assert '--path=F:/laragon/www/elementor-mcp' in config['args']
    config['args'].append('--require=' + str(Path(__file__).with_name('blocksy-source-test.php')))
    proc = subprocess.Popen([config['command'], *config['args']], stdin=subprocess.PIPE,
                            stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
                            encoding='utf-8', errors='replace', cwd=ROOT)
    responses = queue.Queue()
    def read():
        for line in proc.stdout:
            try:
                responses.put(json.loads(line))
            except json.JSONDecodeError:
                pass
    threading.Thread(target=read, daemon=True).start()
    def send(value):
        proc.stdin.write(json.dumps(value, separators=(',', ':')) + '\n')
        proc.stdin.flush()
    def receive(identity):
        while True:
            item = responses.get(timeout=90)
            if item.get('id') == identity:
                if 'error' in item:
                    raise RuntimeError(item['error'])
                return item['result']
    try:
        send({'jsonrpc': '2.0', 'id': 1, 'method': 'initialize', 'params': {
            'protocolVersion': '2024-11-05', 'capabilities': {},
            'clientInfo': {'name': 'emcp-blocksy-verification', 'version': '1.0'}}})
        receive(1)
        send({'jsonrpc': '2.0', 'method': 'notifications/initialized'})
        send({'jsonrpc': '2.0', 'id': 2, 'method': 'tools/list' if name == 'list' else 'tools/call', 'params': {} if name == 'list' else {
            'name': name, 'arguments': arguments or {}}})
        return receive(2)
    finally:
        proc.terminate()
        proc.wait(timeout=10)



def call(tool, operation, args=None, error=False):
    result=rpc_tool('emcp-tools-call-tool',{'name':'emcp-tools/'+tool,'arguments':{'operation':operation,'arguments':args or {}}})
    if error:
        assert result.get('isError'), result
        print('PASS rejected '+operation,flush=True)
        return result
    assert not result.get('isError'), result
    data=result.get('structuredContent') or json.loads(result['content'][0]['text'])
    print('PASS '+operation,flush=True)
    return data

def verify():
    assert call('blocksy-theme-read','get-context')['theme']=='blocksy'
    call('blocksy-theme-read','get-settings')
    call('blocksy-theme-read','get-design-settings')
    context=call('blocksy-content-read','get-context')
    assert 'header' in context['template_types'] and 'footer' in context['template_types']
    call('blocksy-content-read','list-hooks')
    call('blocksy-content-read','list-content-blocks')
    page=call('blocksy-content-write','create-content-block',{'title':'EMCP Blocksy Content Block compatibility test','template_type':'hook'})
    def edit(operation, error=False, **args):
        nonlocal page
        result=call('blocksy-content-write',operation,dict(post_id=page['post_id'],expected_hash=page['content_hash'],**args),error)
        if not error: page=result
        return result
    assert page['status']=='draft' and page['settings']['is_hook_enabled']=='no'
    edit('update-content-block',content="<!-- wp:paragraph --><p>Blocksy's native Content Blocks through MCP.</p><!-- /wp:paragraph -->",settings={'priority':20})
    assert "Blocksy's" in page['content'] and page['settings']['priority']==20
    call('blocksy-content-write','update-content-block',{'post_id':page['post_id'],'expected_hash':'bad','title':'stale'},True)
    edit('update-content-block',error=True,settings={'has_inline_code_editor':'yes'})
    edit('publish-content-block',error=True)
    edit('publish-content-block',confirm=True)
    assert page['status']=='publish' and page['settings']['is_hook_enabled']=='no'
    edit('update-content-block',error=True,title='published edit refused')
    edit('unpublish-content-block',confirm=True)
    assert page['status']=='draft'
    print(json.dumps({'fixture_id':page['post_id'],'status':page['status'],'enabled':page['settings']['is_hook_enabled']}),flush=True)

if __name__=='__main__': verify()
