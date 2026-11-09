"""Native Oxygen integration regression fixtures; all document writes use MCP."""
import importlib.util
import json
from pathlib import Path
import uuid

spec = importlib.util.spec_from_file_location('client', Path(__file__).with_name('oxygen-mcp-client.py'))
client = importlib.util.module_from_spec(spec)
spec.loader.exec_module(client)
state_path = Path(__file__).with_name('.oxygen-advanced-state.json')
state = json.loads(state_path.read_text()) if state_path.exists() else {}

def call(slug, args=None, error=False):
    result = client.rpc_tool('emcp-tools-oxygen-' + slug, args or {})
    if error:
        assert result.get('isError'), result
        print('PASS rejected ' + slug, flush=True)
        return result
    assert not result.get('isError'), result
    value = result.get('structuredContent')
    if value is None:
        value = json.loads(result['content'][0]['text'])
    print('PASS ' + slug, flush=True)
    return value

def remember(key, value):
    state[key] = value
    state_path.write_text(json.dumps(state, indent=2))
    return value

def doc(key, kind=None):
    if key not in state:
        args = {'title': 'EMCP Oxygen Advanced â€” ' + key}
        if kind:
            args['kind'] = kind
        remember(key, call('create-template' if kind else 'create-page', args)['post_id'])
    return call('get-page', {'post_id': state[key]})

def edit(slug, page, **kwargs):
    return call(slug, {'post_id': page['post_id'], 'expected_hash': page['content_hash'], **kwargs})

if __name__ == '__main__':
    import sys
    phase = sys.argv[1] if len(sys.argv) > 1 else 'components'
    if phase == 'components':
        master = doc('component', 'component')
        if not master['tree']['root']['children']:
            master = edit('add-element', master, type='OxygenElements\\Text', properties={'content': {'content': {'text': 'Default component title'}}})
        node = master['tree']['root']['children'][0]['id']
        master = edit('define-component-property', master, element_id=node, key='heading', path='content.content.text', label='Heading')
        call('get-component-properties', {'component_id': master['post_id']})
        page = doc('page')
        if not page['tree']['root']['children']:
            page = edit('insert-component', page, component_id=master['post_id'], overrides={'heading': 'MCP instance override verified'})
        call('insert-component', {'post_id': master['post_id'], 'expected_hash': master['content_hash'], 'component_id': master['post_id']}, error=True)
        forged = json.loads(json.dumps(page['tree']))
        forged['root']['children'][0]['data']['properties']['content']['content']['block']['targets'][0]['controlPath'] = 'settings.advanced.id'
        call('set-native-document', {'post_id': page['post_id'], 'expected_hash': page['content_hash'], 'tree': forged}, error=True)
        edit('manage-document', page, operation='rename', title='EMCP Oxygen Advanced â€” Component Test')
    elif phase == 'design':
        before = call('get-design-state', {'scope': 'variables'})
        remember('variables_before', before)
        data = json.loads(json.dumps(before['data']))
        data['collections'].append('EMCP Advanced Test')
        data['variables'].append({'id': str(uuid.uuid4()), 'label': 'Test accent', 'cssVariableName': 'emcp-advanced-accent', 'type': 'color', 'value': '#24684b', 'collection': 'EMCP Advanced Test'})
        after = call('save-design-state', {'scope': 'variables', 'data': data, 'expected_hash': before['content_hash']})
        call('save-design-state', {'scope': 'variables', 'data': data, 'expected_hash': before['content_hash']}, error=True)
        restored = call('restore-design-revision', {'revision_id': after['revision_id'], 'expected_hash': after['content_hash']})
        assert restored['data'] == before['data']
        prefs = call('get-design-state', {'scope': 'preferences'})
        data = json.loads(json.dumps(prefs['data']))
        data['uiSettings']['autoScrollEnabled'] = not data['uiSettings']['autoScrollEnabled']
        after = call('save-design-state', {'scope': 'preferences', 'data': data, 'expected_hash': prefs['content_hash']})
        assert call('restore-design-revision', {'revision_id': after['revision_id'], 'expected_hash': after['content_hash']})['data'] == prefs['data']
    elif phase == 'templates':
        template = doc('header', 'header')
        schema = call('get-template-schema')
        remember('template_schema', schema)
        page = doc('page')
        condition = next(x for x in schema['conditions'] if x['slug'] == 'post-dropdown-page')
        rule = {'ruleSlug': condition['slug'], 'operand': condition['operands'][0], 'value': [{'text': page['title'], 'value': str(page['post_id'])}]}
        template = edit('update-template-settings', template, settings={'type': 'page', 'priority': 50, 'disabled': True, 'ruleGroups': [[rule]]})
        assert template['template_settings']['ruleGroups'] == [[rule]]
        trashed = edit('manage-document', template, operation='trash')
        assert trashed['status'] == 'trash'
        assert edit('manage-document', trashed, operation='restore')['status'] != 'trash'
    elif phase == 'advanced':
        page = doc('advanced-page')
        tree = {'root': {'id': 1, 'data': {'type': 'root', 'properties': {}}, 'children': [
            {'id': 100, 'data': {'type': 'OxygenElements\\HtmlCode', 'properties': {'content': {'content': {'html_code': '<p>Native HTML verified</p>'}}}}, 'children': []},
            {'id': 101, 'data': {'type': 'OxygenElements\\PostsLoop', 'properties': {'content': {'repeated_block': {'global_block': state['component']}, 'query': {'query': {'active': 'text', 'text': 'post_type=post&posts_per_page=2', 'custom': {}, 'php': ''}}}}}, 'children': []}
        ]}, '_nextNodeId': 102, 'status': 'exported'}
        page = edit('set-native-document', page, tree=tree)
        remember('advanced-page-state', page)
        revisions = call('list-revisions', {'post_id': page['post_id']})
        assert revisions['revisions']
        bad = json.loads(json.dumps(tree)); bad['root']['children'][1]['data']['properties']['content']['repeated_block']['global_block'] = page['post_id']
        call('set-native-document', {'post_id': page['post_id'], 'expected_hash': page['content_hash'], 'tree': bad}, error=True)
    elif phase == 'library':
        page = doc('page')
        exported = call('export-design-library', {'post_ids': [page['post_id']]})
        remember('export', exported)
        variables = call('get-design-state', {'scope': 'variables'})
        selectors = call('get-design-state', {'scope': 'selectors'})
        args = {'bundle': exported['bundle'], 'bundle_hash': exported['bundle_hash'], 'variables_hash': variables['content_hash'], 'selectors_hash': selectors['content_hash'], 'request_id': str(uuid.uuid4())}
        remember('import_args', args)
        imported = remember('import', call('import-design-library', args))
        assert imported['status'] == 'complete', imported
        assert call('import-design-library', args) == imported
        imported_page = call('get-page', {'post_id': int(imported['post_map'][str(page['post_id'])])})
        assert imported_page['status'] == 'draft'
        source_component = page['tree']['root']['children'][0]['data']['properties']['content']['content']['block']['componentId']
        target_component = imported_page['tree']['root']['children'][0]['data']['properties']['content']['content']['block']['componentId']
        assert target_component == imported['post_map'][str(source_component)] and target_component != source_component
    print('COMPLETE ' + phase, flush=True)
