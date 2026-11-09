"""Exercise the configured local test server over MCP stdio, with fresh WP state."""
import json
import pathlib
import queue
import subprocess
import threading

ROOT = pathlib.Path(__file__).resolve().parents[2]


def rpc_tool(name, arguments=None):
    if name.startswith('emcp-tools-oxygen-'):
        arguments = {'name': name.replace('emcp-tools-', 'emcp-tools/', 1), 'arguments': arguments or {}}
        name = 'emcp-tools-call-tool'
    config = json.loads((ROOT / '.mcp.json').read_text())['mcpServers']['elementor-mcp-test']
    assert '--path=F:/laragon/www/elementor-mcp' in config['args']
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
            'clientInfo': {'name': 'emcp-oxygen-verification', 'version': '1.0'}}})
        receive(1)
        send({'jsonrpc': '2.0', 'method': 'notifications/initialized'})
        send({'jsonrpc': '2.0', 'id': 2, 'method': 'tools/list' if name == 'list' else 'tools/call', 'params': {} if name == 'list' else {
            'name': name, 'arguments': arguments or {}}})
        return receive(2)
    finally:
        proc.terminate()
        proc.wait(timeout=10)


if __name__ == '__main__':
    import sys
    result = rpc_tool(sys.argv[1], json.loads(sys.argv[2]) if len(sys.argv) > 2 else {})
    print(json.dumps(result, ensure_ascii=True))
