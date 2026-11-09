"""WPBakery landing-page and staging regression test. All document writes use MCP."""
import importlib.util
import json
import sys
from pathlib import Path

spec = importlib.util.spec_from_file_location('client', Path(__file__).with_name('oxygen-mcp-client.py'))
client = importlib.util.module_from_spec(spec)
spec.loader.exec_module(client)
state_path = Path(__file__).with_name('.wpbakery-state.json')
state = json.loads(state_path.read_text(encoding='utf-8')) if state_path.exists() else {}


def call(slug, args=None, error=False):
    result = client.rpc_tool('emcp-tools-call-tool', {'name': 'emcp-tools/wpbakery-' + slug, 'arguments': args or {}})
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
    for slug in ['list-elements','get-settings','list-templates','list-pages']:
        call(slug)
    for kind in ['vc_row','vc_column','vc_custom_heading','vc_column_text','vc_btn','vc_single_image']:
        call('get-element-schema',{'type':kind})
    page=call('get-page',{'post_id':state['page_id']}) if state.get('page_id') else remember(call('create-page',{'title':'FIELDWORK — WPBakery Integration Test'}))
    def edit(slug, **args):
        nonlocal page
        page=call(slug,dict(post_id=page['post_id'],expected_hash=page['content_hash'],**args))
        return page
    def text(html): return '[vc_column_text]'+html+'[/vc_column_text]'
    def heading(label,tag='h2',cls=''): return '[vc_custom_heading text="'+label+'" font_container="tag:'+tag+'|text_align:left" use_theme_fonts="yes" el_class="'+cls+'"]'
    def button(label,href): return '[vc_btn title="'+label+'" style="custom" custom_background="#263d30" custom_text="#ffffff" shape="square" link="url:'+href+'"]'
    def col(content,width='1/1',cls=''): return '[vc_column width="'+width+'" el_class="'+cls+'"]'+content+'[/vc_column]'
    def row(content,cls='',id=''): return '[vc_row el_class="fw-section '+cls+'" el_id="'+id+'"]'+content+'[/vc_row]'
    def photo(id,cls=''): return '[vc_single_image image="'+str(id)+'" img_size="full" el_class="'+cls+'"]'
    content=row(col(text('<p class="fw-brand">FIELDWORK</p><p class="fw-tagline">ARCHITECTURE &amp; INTERIORS</p>'),'1/2')+col(text('<p class="fw-nav"><a href="#work">Selected work</a> &nbsp; <a href="#studio">Our studio</a> &nbsp; <a href="#contact">Let’s talk ↗</a></p>'),'1/2'),'fw-navrow')
    content+=row(col(text('<p class="fw-eyebrow">INDEPENDENT DESIGN STUDIO · EST. 2014</p>')+heading('Good spaces.<br />Better living.','h1')+text('<p class="fw-intro">Considered architecture for the way you live. We create warm, enduring places that feel distinctly yours.</p>')+button('Explore our work ↗','%23work')+text('<p class="fw-caption">Architecture · Interior design · Everyday life</p>'),'1/2','fw-hero-copy')+col(photo(1875),'1/2'),'fw-hero')
    content+=row(col(text('<p class="fw-eyebrow">OUR APPROACH</p>'),'1/3')+col(heading('Built around you.<br />Grounded in place.')+text('<p class="fw-intro">We start by listening. To your routines, your ambitions, and the character of a place. Then we turn those conversations into spaces that work beautifully, every day.</p>'),'2/3'),'fw-about','studio')
    content+=row(col(text('<p class="fw-eyebrow">01 / SELECTED WORK</p>')+heading('A few places we call home.')),'fw-work-heading','work')
    content+=row(col(photo(1873)+text('<p class="fw-project"><strong>The Garden House</strong><span>Residential · 2025</span></p>'),'1/2')+col(photo(1874)+text('<p class="fw-project"><strong>A Quiet Interior</strong><span>Interior design · 2024</span></p>'),'1/2'),'fw-projects')
    content+=row(col(text('<p class="fw-eyebrow">02 / HOW WE WORK</p>')+heading('From first thought<br />to finishing touch.'),'1/2')+col(text('<div class="fw-step"><span>01</span><h3>Listen &amp; discover</h3><p>A shared brief, a clear direction, and room to explore.</p></div><div class="fw-step"><span>02</span><h3>Design &amp; refine</h3><p>Thoughtful plans, honest materials, and details that matter.</p></div><div class="fw-step"><span>03</span><h3>Build &amp; bring to life</h3><p>Careful coordination from the drawing board to your front door.</p></div>'),'1/2'),'fw-process')
    content+=row(col(text('<p class="fw-eyebrow">LET’S MAKE ROOM FOR SOMETHING GOOD</p>')+heading('Your next chapter<br />starts with a conversation.')+button('Tell us about your project ↗','mailto%3Ahello%40example.com')+text('<p class="fw-caption">A demonstration studio. Built with native WPBakery elements.</p>')),'fw-contact','contact')
    content+=row(col(text('<p class="fw-brand">FIELDWORK</p><p class="fw-caption">Spaces for a life well lived.</p>'),'1/2')+col(text('<p class="fw-nav">WPBakery integration test · 2026<br />Architecture &amp; interiors</p>'),'1/2'),'fw-footer')
    # Heading attributes cannot contain HTML. Native headings render plain multiline text via CSS.
    content=content.replace('Good spaces.<br />Better living.','Good spaces. Better living.').replace('Built around you.<br />Grounded in place.','Built around you. Grounded in place.').replace('From first thought<br />to finishing touch.','From first thought to finishing touch.').replace('Your next chapter<br />starts with a conversation.','Your next chapter starts with a conversation.')
    before=page['content_hash']
    edit('set-page-content',content=content)
    call('set-page-content',dict(post_id=page['post_id'],expected_hash="0"*64,content=content),error=True)
    call('set-page-content',dict(post_id=page['post_id'],expected_hash=page['content_hash'],content='[vc_row][unknown][/vc_row]'),error=True)
    css="""
body{background:#f6f3ec!important;color:#273d31} .fw-section{max-width:1200px;margin:0 auto!important;padding:60px 24px;font-family:Arial,sans-serif;color:#273d31} .fw-section p{line-height:1.7}.fw-section h1,.fw-section h2{font-family:Georgia,serif!important;font-weight:400!important;letter-spacing:-.045em;line-height:1.08!important;color:#273d31!important}.fw-section h1{font-size:clamp(48px,6vw,82px)!important;margin:24px 0!important;max-width:530px}.fw-section h2{font-size:clamp(36px,4vw,56px)!important;margin:12px 0 28px!important}.fw-eyebrow{font-size:11px;letter-spacing:.18em;font-weight:700}.fw-intro{font-size:17px;max-width:530px}.fw-caption{font-size:12px;color:#536154;margin-top:22px}.fw-brand{font-size:24px;letter-spacing:-.04em;font-weight:700;margin:0}.fw-tagline{display:block;font-size:9px;letter-spacing:.15em;font-weight:400}.fw-nav{text-align:right;font-size:12px}.fw-nav a{color:#273d31;text-decoration:none}.fw-navrow{padding-top:32px;padding-bottom:26px;border-bottom:1px solid #d5d8cc}.fw-hero{padding-top:64px;padding-bottom:70px}.fw-hero .vc_single_image-wrapper,.fw-hero .vc_single_image-wrapper img{width:100%}.fw-hero img{height:560px;object-fit:cover}.fw-hero-copy{padding-right:50px!important}.fw-hero-copy>.vc_column-inner{padding-top:34px}.fw-section .vc_btn3{font-size:13px!important;padding:18px 25px!important;letter-spacing:.025em}.fw-about{border-top:1px solid #d5d8cc;border-bottom:1px solid #d5d8cc;padding-top:75px;padding-bottom:75px}.fw-work-heading{padding-bottom:0}.fw-projects{padding-top:10px}.fw-projects img{width:100%;height:350px;object-fit:cover}.fw-project{font-size:13px}.fw-project strong{display:block}.fw-project span{display:block}.fw-project span{font-size:11px;color:#536154}.fw-process{border-top:1px solid #d5d8cc}.fw-step{border-bottom:1px solid #d5d8cc;padding:0 0 24px;margin:0 0 26px}.fw-step span{font-size:11px;color:#536154}.fw-step h3{font-size:22px!important;color:#273d31!important;margin:8px 0}.fw-step p{font-size:14px}.fw-contact{background:#e6ebdf;text-align:center;padding-top:70px;padding-bottom:70px;margin-top:20px!important}.fw-contact h2{max-width:760px;margin-left:auto!important;margin-right:auto!important;text-align:center!important}.fw-footer{padding-top:34px;padding-bottom:34px}.fw-section img{max-width:100%}
@media(max-width:767px){.fw-section{padding:38px 20px}.fw-section .vc_column_container>.vc_column-inner{padding-left:0;padding-right:0}.fw-nav{text-align:left}.fw-navrow{padding-top:20px;padding-bottom:10px}.fw-hero-copy{padding-right:0!important}.fw-hero-copy>.vc_column-inner{padding-top:0}.fw-hero img{height:390px}.fw-hero h1{font-size:54px!important}.fw-projects img{height:280px}.fw-project{flex-direction:column}.fw-projects .wpb_column{margin-bottom:25px}.fw-process h2{margin-bottom:40px!important}.fw-contact{margin-left:10px!important;margin-right:10px!important}.fw-section h2{font-size:38px!important}}
"""
    # Scope theme chrome adjustments to this test page only.
    css+=' body.page-id-'+str(page['post_id'])+' #site-header,body.page-id-'+str(page['post_id'])+' #site-footer,body.page-id-'+str(page['post_id'])+' .page-header{display:none!important} body.page-id-'+str(page['post_id'])+' #content{max-width:none;width:100%;padding:0}'
    edit('set-page-styles',css=css)
    assert call('get-page-styles',{'post_id':page['post_id']})['css']==css
    edit('replace-fragment',find='Spaces for a life well lived.',replacement='Spaces for a thoughtful life.')
    revisions=call('list-revisions',{'post_id':page['post_id']})['revisions']
    edit('restore-revision',revision_id=revisions[-1]['revision_id'])
    assert 'Spaces for a life well lived.' in page['content']
    edit('publish-page')
    remember(page)
    print(json.dumps({'page_id':page['post_id'],'url':page['url']}),flush=True)

if __name__=='__main__': build()
