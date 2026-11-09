"""Kirki landing-page and staging regression test. All document writes use MCP."""
import importlib.util
import json
import sys
from pathlib import Path

spec = importlib.util.spec_from_file_location('client', Path(__file__).with_name('oxygen-mcp-client.py'))
client = importlib.util.module_from_spec(spec)
spec.loader.exec_module(client)
state_path = Path(__file__).with_name('.kirki-state.json')
state = json.loads(state_path.read_text(encoding='utf-8')) if state_path.exists() else {}


def call(slug, args=None, error=False):
    result = client.rpc_tool('emcp-tools-call-tool', {'name': 'emcp-tools/kirki-' + slug, 'arguments': args or {}})
    if error:
        assert result.get('isError'), result
        print('PASS rejected ' + slug, flush=True)
        return result
    assert not result.get('isError'), result
    data = result.get('structuredContent') or json.loads(result['content'][0]['text'])
    print('PASS ' + slug, flush=True)
    return data


def remember(page):
    state['page_id'] = page['post_id']
    state_path.write_text(json.dumps(state, ensure_ascii=False, indent=2), encoding='utf-8')
    return page


def build():
    assert call('get-context')['site_url'] == 'https://elementor-mcp.test'
    for slug in ['list-elements', 'get-design-system', 'list-library', 'list-collections']:
        data = call(slug)
        if slug == 'get-design-system':
            print(json.dumps({'viewports': data['viewports']}), flush=True)
    starters = {name: call('get-element-schema', {'type': name})['starter'] for name in ['section', 'div', 'heading', 'paragraph', 'button', 'text']}
    page = call('get-page', {'post_id': state['page_id']}) if state.get('page_id') else remember(call('create-page', {'title': 'STILLFORM — Kirki Builder Integration Test'}))
    page = call('set-page-canvas', {'post_id': page['post_id'], 'expected_hash': page['content_hash'], 'full_canvas': True})
    blocks = {'root': {'id': 'root', 'name': 'root', 'title': 'Body', 'accept': '*', 'children': [], 'styleIds': []}}
    styles = {}

    def style(key, css, mobile=None):
        variant = {'md': css}
        if mobile:
            variant['tablet'] = mobile
            variant['mobile'] = mobile
        styles[key] = {'id': key, 'name': 'sf-' + key, 'type': 'class', 'variant': variant}
        return key

    def add(identity, kind, parent, text=None, classes=(), tag=None, href=None):
        node = json.loads(json.dumps(starters[kind]))
        node.update(id=identity, parentId=parent, styleIds=list(classes))
        if text is not None and kind != 'button':
            node['properties']['contents'] = [text]
        if tag:
            node['properties']['tag'] = tag
        if href:
            node['properties']['attributes'] = {'href': href}
        blocks[identity] = node
        blocks[parent]['children'].append(identity)
        if kind == 'button' and text is not None:
            node['template_mounted'] = True
            add(identity + '-text', 'text', identity, text)
        return identity

    style('page', 'background:#f4f1e9;color:#24352c;font-family:Arial,sans-serif;line-height:1.6;')
    style('wrap', 'width:100%;max-width:1220px;margin:0 auto;padding:0 48px;box-sizing:border-box;', 'padding:0 22px;')
    style('nav', 'display:flex;justify-content:space-between;align-items:center;padding-top:28px;padding-bottom:28px;border-bottom:1px solid #cbd0c5;gap:18px;')
    style('logo', 'font-size:23px;font-weight:800;letter-spacing:-1px;margin:0;')
    style('eyebrow', 'font-size:11px;font-weight:700;letter-spacing:2.2px;text-transform:uppercase;margin:0;color:#566750;')
    style('navlink', 'color:#24352c;text-decoration:none;font-size:13px;font-weight:700;')
    style('hero', 'display:grid;grid-template-columns:1.3fr 1fr;gap:70px;padding:94px 0 88px;align-items:center;', 'grid-template-columns:1fr;gap:36px;padding:58px 0;')
    style('h1', 'font-family:Georgia,serif;font-size:86px;font-weight:400;line-height:1.02;letter-spacing:-4px;margin:22px 0 26px;', 'font-size:54px;letter-spacing:-2px;')
    style('lead', 'font-size:17px;line-height:1.75;color:#52604f;max-width:450px;margin:0 0 30px;')
    style('button', 'display:inline-block;width:max-content;background:#263d2d;color:#fff;padding:15px 25px;border-radius:3px;text-decoration:none;font-size:13px;font-weight:700;')
    style('art', 'min-height:440px;background:#ccd5b7;border-radius:180px 180px 4px 4px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:18px;padding:36px;box-sizing:border-box;', 'min-height:330px;')
    style('orb', 'width:180px;height:180px;border-radius:100%;background:radial-gradient(circle at 28% 22%,#fff8c7,#d1b16e 53%,#687b53 100%);box-shadow:15px 28px 45px #51624040;')
    style('artlabel', 'font-family:Georgia,serif;font-size:24px;font-style:italic;letter-spacing:-0.5px;margin:25px 0 0;')
    style('trust', 'display:flex;flex-wrap:wrap;justify-content:space-between;gap:22px;border-top:1px solid #cbd0c5;border-bottom:1px solid #cbd0c5;padding:28px 0;margin-bottom:86px;')
    style('wordmark', 'font-size:18px;font-weight:700;color:#64745f;margin:0;')
    style('section', 'padding-bottom:85px;')
    style('h2', 'font-family:Georgia,serif;font-size:48px;line-height:1.15;font-weight:400;letter-spacing:-1.5px;max-width:630px;margin:18px 0 38px;', 'font-size:36px;')
    style('cards', 'display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;', 'grid-template-columns:1fr;')
    style('card', 'border:1px solid #c9cfc0;border-radius:5px;padding:32px 27px;box-sizing:border-box;')
    style('number', 'font-family:Georgia,serif;font-size:40px;color:#869476;margin:0 0 32px;')
    style('h3', 'font-size:20px;line-height:1.3;margin:0 0 16px;font-weight:600;')
    style('body', 'font-size:14px;color:#596452;margin:0;line-height:1.8;')
    style('feature', 'background:#263d2d;color:#f4f1e9;border-radius:5px;display:grid;grid-template-columns:1fr 1fr;gap:50px;padding:52px;margin-bottom:85px;', 'grid-template-columns:1fr;padding:30px;gap:24px;')
    style('light', 'color:#d1dabf;')
    style('quote', 'font-family:Georgia,serif;font-size:32px;font-weight:400;line-height:1.4;margin:0 0 25px;', 'font-size:27px;')
    style('cta', 'text-align:center;padding:10px 0 86px;')
    style('center', 'margin-left:auto;margin-right:auto;')
    style('footer', 'display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;padding:28px 0;border-top:1px solid #cbd0c5;')
    styles['theme-chrome'] = {'id': 'theme-chrome', 'type': 'tag', 'name': 'body > .site-header,body > .site-footer', 'variant': {'md': 'display:none;'}}
    add('page', 'div', 'root', classes=['page','theme-chrome'])
    add('wrap', 'div', 'page', classes=['wrap'])
    add('nav', 'section', 'wrap', classes=['nav'], tag='header')
    add('logo', 'text', 'nav', 'stillform®', ['logo'])
    add('nav-cta', 'button', 'nav', 'Let’s make room for better ↗', ['navlink'], href='#contact')
    add('hero', 'section', 'wrap', classes=['hero'])
    add('intro', 'div', 'hero')
    add('eyebrow', 'paragraph', 'intro', 'Independent design studio · Since 2020', ['eyebrow'])
    add('title', 'heading', 'intro', 'Less noise. More meaning.', ['h1'])
    add('lead', 'paragraph', 'intro', 'Thoughtful identities and digital spaces for brands moving the world in a better direction.', ['lead'])
    add('hero-cta', 'button', 'intro', 'Explore our approach ↗', ['button'], href='#approach')
    add('art', 'div', 'hero', classes=['art'])
    add('orb', 'div', 'art', classes=['orb'])
    add('art-label', 'paragraph', 'art', 'A little space to think differently.', ['artlabel'])
    add('art-caption', 'paragraph', 'art', 'Clarity is a beautiful thing.', ['eyebrow'])
    add('trust', 'section', 'wrap', classes=['trust'])
    for i, text in enumerate(['In good company', 'VERDANT', 'common ground', 'Onda', 'FIELD NOTES']):
        add('trust-' + str(i), 'paragraph', 'trust', text, ['eyebrow' if i == 0 else 'wordmark'])
    add('approach', 'section', 'wrap', classes=['section'])
    blocks['approach']['properties']['customAttributes'] = {'id': 'approach'}
    add('approach-label', 'paragraph', 'approach', 'Small team. Considered work.', ['eyebrow'])
    add('approach-title', 'heading', 'approach', 'Good design starts with the right questions.', ['h2'], tag='h2')
    add('cards', 'div', 'approach', classes=['cards'])
    for i, (title, body) in enumerate([
        ('Find your clarity', 'We listen closely, ask better questions, and uncover what makes your brand worth paying attention to.'),
        ('Give it a language', 'Distinctive identities that feel like you. Built with purpose, made to grow, and easy to make your own.'),
        ('Make it feel effortless', 'Digital experiences that balance expression with clarity. Beautiful to explore. Simple to use.')]):
        key = 'service-' + str(i)
        add(key, 'div', 'cards', classes=['card'])
        add(key + '-n', 'paragraph', key, '0' + str(i + 1), ['number'])
        add(key + '-h', 'heading', key, title, ['h3'], tag='h3')
        add(key + '-p', 'paragraph', key, body, ['body'])
    add('feature', 'section', 'wrap', classes=['feature'])
    add('feature-left', 'div', 'feature')
    add('feature-kicker', 'paragraph', 'feature-left', 'A partnership, from the first conversation.', ['eyebrow', 'light'])
    add('feature-title', 'heading', 'feature-left', 'Better together. By design.', ['h2'], tag='h2')
    add('feature-right', 'div', 'feature')
    add('quote', 'paragraph', 'feature-right', '“They helped us find the simple, honest story at the heart of everything we do.”', ['quote'])
    add('quote-credit', 'paragraph', 'feature-right', 'A sample client story · Made for this integration demo', ['body', 'light'])
    add('contact', 'section', 'wrap', classes=['cta'])
    blocks['contact']['properties']['customAttributes'] = {'id': 'contact'}
    add('contact-label', 'paragraph', 'contact', 'Your next chapter starts here', ['eyebrow'])
    add('contact-title', 'heading', 'contact', 'Make something that matters.', ['h2', 'center'], tag='h2')
    add('contact-copy', 'paragraph', 'contact', 'Have a good idea? We’d love to help you give it shape.', ['lead', 'center'])
    add('contact-button', 'button', 'contact', 'Say hello ↗', ['button'], href='mailto:hello@example.com')
    add('footer', 'section', 'wrap', classes=['footer'], tag='footer')
    add('footer-logo', 'paragraph', 'footer', 'stillform®', ['logo'])
    add('footer-note', 'paragraph', 'footer', 'Kirki 6.3.1 · Native EMCP integration test · Fictional studio', ['body'])
    args = {'post_id': page['post_id'], 'expected_hash': page['content_hash'], 'blocks': blocks, 'styles': styles}
    after = call('set-page-content', args)
    assert after['status'] == page['status'] and after['stage_version']
    assert after['blocks'] == blocks
    call('set-page-content', args, error=True)
    bad = json.loads(json.dumps(blocks))
    bad['hero']['children'].append('missing')
    call('set-page-content', {'post_id': page['post_id'], 'expected_hash': after['content_hash'], 'blocks': bad}, error=True)
    published = call('publish-page', {'post_id': page['post_id'], 'expected_hash': after['content_hash']})
    assert published['status'] == 'publish' and not published['stage_version']
    remember(published)
    print(json.dumps({'post_id': published['post_id'], 'url': published['url'], 'editor_url': published['editor_url'], 'elements': len(blocks), 'styles': len(styles)}), flush=True)


if __name__ == '__main__':
    if len(sys.argv) > 1 and sys.argv[1] == 'read':
        print(json.dumps(call('get-page', {'post_id': state['page_id']}), ensure_ascii=True))
    elif len(sys.argv) > 1 and sys.argv[1] == 'regression':
        page = call('get-page', {'post_id': state['page_id']})
        baseline = json.loads(json.dumps(page))
        version = next(v['version'] for v in page['versions'] if v['publish'])
        def edit(slug, **args):
            global page
            page = call(slug, {'post_id': page['post_id'], 'expected_hash': page['content_hash'], **args})
            return page
        edit('update-element', element_id='title', properties={'contents': ['Temporary version test']})
        assert page['stage_version'] and page['status'] == baseline['status']
        edit('publish-page')
        edit('restore-version', version=version)
        assert page['blocks'] == baseline['blocks']
        edit('publish-page')
        assert page['blocks'] == baseline['blocks']
        starter = call('get-element-schema', {'type': 'paragraph'})['starter']
        starter['id'] = 'regression-temp'
        edit('add-element', parent_id='wrap', element=starter)
        assert 'regression-temp' in page['blocks']
        edit('set-page-content', blocks=baseline['blocks'])
        edit('set-page-styles', styles=baseline['styles'])
        edit('publish-page')
        assert page['blocks'] == baseline['blocks'] and page['styles'] == baseline['styles']
        call('restore-version', {'post_id': page['post_id'], 'expected_hash': page['content_hash'], 'version': 999999}, error=True)
        call('get-page-settings', {'post_id': page['post_id']})
        print('PASS native version restoration and incremental editing', flush=True)
    else:
        build()
