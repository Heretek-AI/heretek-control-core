"""Build a native Otter Free/Pro landing page through fresh MCP calls on the test site."""
import json, runpy
from pathlib import Path
from html import escape
rpc=runpy.run_path(str(Path(__file__).with_name('otter-mcp.py')))['rpc_tool']
def call(slug,args=None,error=False):
    r=rpc('emcp-tools-call-tool',{'name':'emcp-tools/otter-'+slug,'arguments':args or {}})
    if error:
        assert r.get('isError'),r
        return r
    assert not r.get('isError'),r
    return r.get('structuredContent') or json.loads(r['content'][0]['text'])
def block(name,attrs,html):
    return '<!-- wp:'+name+' '+json.dumps(attrs,separators=(',',':'))+' -->'+html+'<!-- /wp:'+name+' -->'
def heading(text,ident,tag='h2',size=44):
    return block('themeisle-blocks/advanced-heading',{'id':ident,'tag':tag,'fontSize':size,'fontSizeMobile':min(size,34)},f'<{tag} id="{ident}" class="wp-block-themeisle-blocks-advanced-heading {ident}">{text}</{tag}>')
def para(text,cls=''):
    a={'className':cls} if cls else {}
    return block('paragraph',a,'<p'+(' class="'+cls+'"' if cls else '')+'>'+text+'</p>')
def col(content,ident):
    return block('themeisle-blocks/advanced-column',{'id':ident,'columnsHTMLTag':'div'},f'<div id="{ident}" class="wp-block-themeisle-blocks-advanced-column">{content}</div>')
def section(contents,ident,cls=''):
    n=len(contents);layout='equal'
    attrs={'id':ident,'columns':n,'layout':layout,'layoutTablet':'equal','layoutMobile':'collapsedRows','verticalAlign':'center','columnsHTMLTag':'section','className':'fw-section '+cls}
    classes=f'wp-block-themeisle-blocks-advanced-columns has-{n}-columns has-desktop-equal-layout has-tablet-equal-layout has-mobile-collapsedRows-layout has-vertical-center fw-section '+cls
    html=f'<section id="{ident}" class="{classes}"><div class="wp-block-themeisle-blocks-advanced-columns-overlay"></div><div class="innerblocks-wrap">'+''.join(col(c,ident+'-col'+str(i)) for i,c in enumerate(contents))+'</div></section>'
    return block('themeisle-blocks/advanced-columns',attrs,html)
def photo(file,alt):
    url='https://elementor-mcp.test/wp-content/uploads/2026/07/'+file+'.jpg'
    return block('image',{'sizeSlug':'full','linkDestination':'none'},f'<figure class="wp-block-image size-full"><img src="{url}" alt="{alt}"/></figure>')
def buttons():
    button=block('button',{},'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#fw-visit">Find your morning spot <span aria-hidden="true">↗</span></a></div>')
    return block('buttons',{},'<div class="wp-block-buttons">'+button+'</div>')

if __name__=='__main__':
    state=Path(__file__).with_name('otter-live-state.json')
    if state.exists(): p=call('get-post-blocks',{'post_id':json.loads(state.read_text())['post_id']})
    else:
        p=call('create-page',{'title':'Fieldwork Coffee: Otter Free and Pro Test','slug':'fieldwork-otter-test'})
        state.write_text(json.dumps(p,indent=2))
    pid=p['post_id']
    css=f'''.page-id-{pid} .entry-header{{display:none}} .page-id-{pid} .site-content{{background:#f3efe5}} .page-id-{pid} .entry-content{{color:#282a22}} .page-id-{pid} .ast-container{{max-width:1400px}} .page-id-{pid} .entry-content h1,.page-id-{pid} .entry-content h2,.page-id-{pid} .entry-content h3{{font-family:Georgia,serif;line-height:1.07;letter-spacing:-.04em;color:inherit;margin:0 0 24px}} .fw-section{{padding:70px 38px;margin:0!important}} .fw-section>.innerblocks-wrap{{gap:48px!important}} .fw-section .wp-block-themeisle-blocks-advanced-column{{min-width:0;flex:1!important}} .fw-section p{{font-size:18px;line-height:1.7}} .fw-kicker{{font:600 12px/1.5 Arial,sans-serif!important;letter-spacing:.17em;text-transform:uppercase;margin-bottom:32px}} .fw-hero{{padding-top:50px;padding-bottom:80px}} .fw-hero img{{height:550px;object-fit:cover;width:100%}} .fw-section figure{{margin:0}} .fw-section .wp-block-button__link{{background:#b44225;color:#fff;border-radius:0;padding:18px 24px;font-size:15px}} .fw-note{{font-size:13px!important;margin-top:26px;opacity:.8}} .fw-intro{{border-top:1px solid #c7c7b4;border-bottom:1px solid #c7c7b4}} .fw-menu{{background:#e7e8d9}} .fw-menu h3{{font-size:27px}} .fw-menu img{{height:250px;object-fit:cover;width:100%;margin-bottom:28px}} .fw-visit{{background:#293f32;color:#f3efe5}} .fw-visit .otter-business-hour__title{{background:transparent!important;text-align:left!important;font-family:Georgia,serif;font-size:28px}} .fw-visit .otter-business-hour__container{{border:0!important;background:transparent!important;color:inherit}} .fw-visit .wp-block-themeisle-blocks-business-hours-item{{background:transparent!important;color:inherit;border-bottom:1px solid #647364;padding:16px 0}} .fw-faq details{{border-bottom:1px solid #c7c7b4;padding:22px 0}} .fw-faq summary{{cursor:pointer;font-size:20px}} .fw-footer{{padding-top:26px;padding-bottom:26px;background:#d8a457}} @media(max-width:767px){{.fw-section{{padding:40px 22px}}.fw-section>.innerblocks-wrap{{gap:28px!important}}.fw-hero img{{height:360px}}.fw-section .wp-block-themeisle-blocks-advanced-column{{width:100%!important;flex-basis:100%!important}}.fw-menu img{{height:280px}}}}'''
    hero=section([para('Fieldwork / Coffee & company','fw-kicker')+heading('Good coffee.<br>A slower start.','fw-title','h1',72)+para('A neighborhood coffee house for early risers, long conversations, and the pleasure of a cup made well.')+buttons()+para('Small-batch coffee. Something warm from the oven.','fw-note'),photo('photo-1707320484980-bbc47c71da0c','A freshly made caramel latte')],'fw-hero','fw-hero')
    # Otter custom CSS belongs to a native attribute, not a Custom HTML block.
    header=json.loads(hero.split(' ',2)[2].split(' -->',1)[0]);header.update({'hasCustomCSS':True,'customCSS':css})
    hero='<!-- wp:themeisle-blocks/advanced-columns '+json.dumps(header,separators=(',',':'))+' -->'+hero.split(' -->',1)[1]
    intro=section([para('01 / The daily ritual','fw-kicker')+heading('Come for a cup.<br>Stay for a while.','fw-about'),para('We keep our menu small and our standards high. Thoughtful sourcing, careful brewing, and a friendly face across the counter.')+para('Pull up a chair, bring a book, or catch up with someone you have been meaning to see.')],'fw-about-section','fw-intro')
    menu=section([photo('photo-1461023058943-07fcbe16d735','Cold brew coffee over ice')+heading('The slow brew','fw-menu1','h3',30)+para('Cold-steeped, chocolatey, and easygoing. Served over plenty of ice.'),photo('photo-1638202518327-956c496a5240','A handcrafted cappuccino')+heading('The house favorite','fw-menu2','h3',30)+para('A balanced espresso, textured milk, and just the right amount of sweetness.'),photo('photo-1769138885103-7d6e2c02fae7','Freshly baked pastries')+heading('Fresh from the oven','fw-menu3','h3',30)+para('Flaky pastries and seasonal bakes. Best enjoyed before your second cup.')],'fw-menu','fw-menu')
    hours=''
    for label,time in [('Monday to Friday','07:30 to 18:00'),('Saturday','08:00 to 18:00'),('Sunday','08:00 to 16:00')]:
        hours+=block('themeisle-blocks/business-hours-item',{'label':label,'time':time},'<div class="wp-block-themeisle-blocks-business-hours-item"><div class="otter-business-hour-item__label"><span>'+label+'</span></div><div class="otter-business-hour-item__time"><span>'+time+'</span></div></div>')
    hours=block('themeisle-blocks/business-hours',{'id':'fw-hours','title':'Opening hours','titleFontSize':28,'itemsFontSize':17},'<div id="fw-hours" class="wp-block-themeisle-blocks-business-hours"><div class="otter-business-hour__container"><div class="otter-business-hour__title"><span>Opening hours</span></div><div class="otter-business-hour__content">'+hours+'</div></div></div>')
    visit=section([para('02 / Make yourself at home','fw-kicker')+heading('Your next good<br>morning starts here.','fw-visit-title')+para('An unhurried corner of the neighborhood. Walk in, find a seat, and let us take care of the coffee.')+para('This is a demonstration cafe, built to test Otter Free and Pro.','fw-note'),hours],'fw-visit','fw-visit')
    faq=''
    for q,answer in [('Do you have dairy-free options?','Yes. Oat and almond milk are available for all of our espresso drinks.'),('Can I bring my laptop?','Of course. Settle in with a coffee, and please share the larger tables during busy hours.'),('Is this a real cafe?','Fieldwork is a demonstration landing page for testing native Otter blocks. No reservations or orders are collected.')]:
        faq+=block('themeisle-blocks/accordion-item',{'title':q,'tag':'h3'},'<details class="wp-block-themeisle-blocks-accordion-item"><summary class="wp-block-themeisle-blocks-accordion-item__title"><h3>'+q+'</h3></summary><div class="wp-block-themeisle-blocks-accordion-item__content">'+para(answer)+'</div></details>')
    faq=block('themeisle-blocks/accordion',{'id':'fw-questions','alwaysOpen':True,'FAQSchema':False},'<div id="fw-questions" class="wp-block-themeisle-blocks-accordion" data-has-schema="false">'+faq+'</div>')
    content=hero+intro+menu+section([heading('A few things<br>before you visit.','fw-faq-title'),faq],'fw-faq','fw-faq')+visit+section([para('FIELDWORK / Made for the everyday.','fw-kicker'),para('Otter Free + Pro · Native Gutenberg blocks','fw-note')],'fw-footer','fw-footer')
    saved=call('set-page-blocks',{'post_id':pid,'expected_hash':p['content_hash'],'markup':content,'confirm':True})
    assert saved['styles']['css'],saved
    p=saved['page']; published=call('publish-page',{'post_id':pid,'expected_hash':p['content_hash'],'confirm':True})
    state.write_text(json.dumps(published['page'],indent=2))
    print(json.dumps({'post_id':pid,'url':published['page']['permalink'],'css_bytes':len(published['styles']['css'])}))
