"""Create the Blocksy demonstration on the test site through fresh MCP calls."""
import json
import runpy
from pathlib import Path

m = runpy.run_path(str(Path(__file__).with_name('blocksy-mcp.py')))
def invoke(name, args):
    r = m['rpc_tool']('emcp-tools-call-tool', {'name': 'emcp-tools/' + name, 'arguments': args})
    assert not r.get('isError'), r
    return r.get('structuredContent') or json.loads(r['content'][0]['text'])

def block(name, attrs, html):
    return '<!-- wp:' + name + ' ' + json.dumps(attrs, separators=(',', ':')) + ' -->' + html + '<!-- /wp:' + name + ' -->\n'
def p(text):
    return block('paragraph', {}, '<p>' + text + '</p>')
def h(text, level=2):
    return block('heading', {'level': level}, f'<h{level} class="wp-block-heading">{text}</h{level}>')
def group(content, color='#f2eee5', ink='#232d28', anchor=None):
    attrs = {'style': {'color': {'background': color, 'text': ink}, 'spacing': {'padding': {'top': '48px', 'right': '24px', 'bottom': '48px', 'left': '24px'}}}, 'layout': {'type': 'constrained'}}
    if anchor: attrs['anchor'] = anchor
    ident = f' id="{anchor}"' if anchor else ''
    return block('group', attrs, f'<div{ident} class="wp-block-group has-text-color has-background" style="color:{ink};background-color:{color};padding-top:48px;padding-right:24px;padding-bottom:48px;padding-left:24px">' + content + '</div>')
def cols(items):
    inner = ''.join(block('column', {}, '<div class="wp-block-column">' + x + '</div>') for x in items)
    return block('columns', {}, '<div class="wp-block-columns">' + inner + '</div>')
def photo(id, filename, alt):
    url = 'https://elementor-mcp.test/wp-content/uploads/2026/09/' + filename
    return block('image', {'id': id, 'sizeSlug': 'full', 'linkDestination': 'none'}, f'<figure class="wp-block-image size-full"><img src="{url}" alt="{alt}" class="wp-image-{id}"/></figure>')
def button(text, href):
    inner = block('button', {}, f'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{href}">{text}</a></div>')
    return block('buttons', {}, '<div class="wp-block-buttons">' + inner + '</div>')

if __name__ == '__main__':
    schema = invoke('get-block-schema', {'name': 'blocksy/content-block'})
    print(json.dumps(schema), flush=True)
    reusable = m['call']('blocksy-content-write', 'create-content-block', {'title': 'Form & Field: project invitation', 'template_type': 'hook'})
    cta = group(p('LET\'S MAKE ROOM FOR WHAT MATTERS') + h('A considered space starts with a conversation.') + p('Tell us about the way you live, the things you love, and the space you have in mind.') + button('Explore our approach', '#approach') + p('Form & Field / Independent interiors studio'), '#233b31', '#ffffff', 'conversation')
    reusable = m['call']('blocksy-content-write', 'update-content-block', {'post_id': reusable['post_id'], 'expected_hash': reusable['content_hash'], 'content': cta, 'settings': {'is_hook_enabled': 'yes', 'conditions': []}})
    reusable = m['call']('blocksy-content-write', 'publish-content-block', {'post_id': reusable['post_id'], 'expected_hash': reusable['content_hash'], 'confirm': True})
    hero = group(p('FORM & FIELD / SPACES FOR EVERYDAY LIVING') + cols([h('Less noise.<br>More room to live.') + p('Thoughtful interiors shaped around real life. Natural materials, useful details, and a quiet sense of belonging.') + button('Discover the collection', '#spaces'), photo(1875, 'photo-1762544968153-b9b47435fefd.jpg', 'A warm contemporary interior with natural materials')]))
    intro = group(cols([h('Good spaces feel like you.'), p('We believe the best rooms are the ones you want to spend time in. We bring a clear point of view and a careful eye to homes with character.') + p('From the first sketch to the last well-placed object, every decision has a purpose.')]), '#ffffff')
    work = group(p('01 / SELECTED SPACES') + h('Built around the everyday.') + cols([photo(1873, 'photo-1756706718604-ef4af3970e33.jpg', 'A contemporary home opening onto its surroundings') + h('An open invitation', 3) + p('Architecture / A home connected to its surroundings'), photo(1874, 'photo-1693578616322-c8abe6c7393d.jpg', 'A carefully composed interior with warm neutral finishes') + h('A quieter rhythm', 3) + p('Interiors / Texture, warmth, and space to pause')]), anchor='spaces')
    approach = group(p('02 / OUR APPROACH') + h('Considered from the beginning.') + cols([h('01. Listen', 3) + p('We start with your routines, your references, and what you want your home to feel like.'), h('02. Shape', 3) + p('Layouts, materials, and light come together in a clear, practical direction.'), h('03. Refine', 3) + p('We work through the details so the finished space feels effortless to use.')]), '#ffffff', anchor='approach')
    faq = group(p('03 / A FEW GOOD QUESTIONS') + h('Before we begin.') + h('What kinds of spaces do you work on?', 3) + p('We focus on residential interiors, from an individual room to a complete home.') + h('Can you work with pieces we already own?', 3) + p('Absolutely. The objects you love give a space its history and character.') + h('Where does a project start?', 3) + p('With a conversation about your space, your priorities, and the practical scope.'))
    embed = block('blocksy/content-block', {'content_block': reusable['post_id']}, '<div>Blocksy: Content Block Filter</div>')
    content = hero + intro + work + approach + faq + embed
    page = invoke('create-post', {'post_type': 'page', 'title': 'Form & Field', 'slug': 'form-field-blocksy-integration-test', 'status': 'publish', 'content': content, 'comment_status': 'closed'})
    result = {'page': page, 'content_block_id': reusable['post_id']}
    Path(__file__).with_name('blocksy-landing-result.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
    print(json.dumps(result), flush=True)
