"""Native-element landing page and lifecycle smoke, authored exclusively through MCP."""
import json, runpy, copy
from pathlib import Path
base=Path(__file__).parent
call=runpy.run_path(str(base/'visual-composer-live.py'))['call']
schemas=json.loads((base/'visual-composer-live-schemas.json').read_text())
elements={}
def node(tag,id,parent=False,order=0,**attrs):
    d={k:copy.deepcopy(v.get('value')) for k,v in schemas[tag].items() if v.get('type')!='group' and 'value' in v}
    d.update(id=id,tag=tag,parent=parent,order=order,**attrs); elements[id]=d
    return d
def text(id,parent,html,order=0):
    node('textBlock',id,parent,order,output=html)
    return f'<div class="vce-text-block"><div class="vce-text-block-wrapper vce" id="el-{id}">{html}</div></div>'
def button(id,parent,label,href,order=1):
    node('basicButton',id,parent,order,buttonText=label,buttonUrl={'url':href,'title':label,'targetBlank':False,'relNofollow':False},background='#283e35',color='#ffffff',customClass='sh-button')
    return f'<div class="vce-button--style-basic-container sh-button"><div class="vce-button--style-basic-wrapper vce" id="el-{id}"><a class="vce-button vce-button--style-basic" href="{href}">{label}</a></div></div>'
def image(id,parent,attachment,file,alt,order=0):
    url='https://elementor-mcp.test/wp-content/uploads/2026/09/'+file
    node('singleImage',id,parent,order,image={'id':attachment,'full':url,'url':url,'alt':alt,'title':alt},size='full',customClass='sh-image')
    return f'<div class="vce-single-image-container sh-image"><div class="vce vce-single-image-wrapper" id="el-{id}"><figure><img src="{url}" alt="{alt}"/></figure></div></div>'
def row(id,contents,cls=''):
    node('row',id,False,len([x for x in elements.values() if x['tag']=='row']),customClass='sh-section '+cls,layout={'all':['1/'+str(len(contents))]*len(contents)},rowWidth='auto')
    rendered=[]
    for i,fn in enumerate(contents):
        col=id+'c'+str(i); node('column',col,id,i,size={'all':'1/'+str(len(contents)),'defaultSize':'1/'+str(len(contents))})
        rendered.append(f'<div class="vce-col" id="el-{col}"><div class="vce-col-inner"><div class="vce-col-content">{fn(col)}</div></div></div>')
    return f'<div class="vce-row-container"><div class="vce-row sh-section {cls}" id="el-{id}"><div class="vce-row-content">'+''.join(rendered)+'</div></div></div>'

html=row('shhero',[
    lambda c:text('shintro',c,'<p class="sh-eyebrow">STILL HOUSE / INTERIORS WITH INTENTION</p><h1>A little quieter.<br>A little more you.</h1><p class="sh-lead">Rooms that make space for the way you live. Natural materials, thoughtful details, and a feeling of home.</p>')+button('shdiscover',c,'Explore our spaces','#el-shwork'),
    lambda c:image('shheroimage',c,1875,'photo-1762544968153-b9b47435fefd.jpg','A warm living room with sculptural furniture')
], 'sh-hero')
html+=row('shabout',[
    lambda c:text('shabouttitle',c,'<p class="sh-eyebrow">OUR POINT OF VIEW</p><h2>Designed to be lived in.</h2>'),
    lambda c:text('shaboutcopy',c,'<p class="sh-lead">A home is more than a collection of beautiful things.</p><p>It is the light at breakfast. The chair you always choose. The room that brings everyone together. We start there, then work carefully through every detail.</p>')
],'sh-light')
html+=row('shwork',[
    lambda c:image('shworkimage1',c,1873,'photo-1756706718604-ef4af3970e33.jpg','Contemporary home with a generous connection to the outdoors')+text('shworkcopy1',c,'<p class="sh-eyebrow">01 / ARCHITECTURE</p><h3>Open to the everyday.</h3><p>A considered connection between home and landscape.</p>',1),
    lambda c:image('shworkimage2',c,1874,'photo-1693578616322-c8abe6c7393d.jpg','Calm interior with natural finishes')+text('shworkcopy2',c,'<p class="sh-eyebrow">02 / INTERIORS</p><h3>Room for a slower pace.</h3><p>Honest textures and a warm, uncomplicated palette.</p>',1)
])
html+=row('shprocess',[
    lambda c:text('shprocess1',c,'<p class="sh-eyebrow">01 / LISTEN</p><h3>Start with your story.</h3><p>Your routines, the things you treasure, and what you want your space to feel like.</p>'),
    lambda c:text('shprocess2',c,'<p class="sh-eyebrow">02 / EXPLORE</p><h3>Find the right balance.</h3><p>We bring layout, light, and materials together into a clear direction.</p>'),
    lambda c:text('shprocess3',c,'<p class="sh-eyebrow">03 / REFINE</p><h3>Make every detail count.</h3><p>A practical plan, thoughtful choices, and a space that feels like it belongs to you.</p>')
],'sh-light')
html+=row('shquestions',[lambda c:text('shfaq',c,'<p class="sh-eyebrow">BEFORE WE BEGIN</p><h2>A few good questions.</h2><h3>Can we keep the pieces we love?</h3><p>Yes. Familiar objects give a home its character. We build around the pieces that matter to you.</p><h3>Do you take on smaller projects?</h3><p>We can start with one room, a layout study, or a complete interior. The scope follows your needs.</p><h3>What happens first?</h3><p>A conversation about your priorities, your space, and your timeline.</p>')])
html+=row('shcontact',[lambda c:text('shcontactcopy',c,'<p class="sh-eyebrow">LET\'S MAKE SOMETHING FEEL LIKE HOME</p><h2>Your next chapter starts here.</h2><p>Explore the work and imagine the possibilities for your own space.</p>')+button('shback',c,'See the selected spaces','#el-shwork')],'sh-dark')
css='''
.sh-section{background:#f1eee5;padding:64px 32px;color:#29382f}.sh-section .vce-row-content{display:flex;gap:40px;align-items:center}.sh-section .vce-col{flex:1;min-width:0;width:auto}.sh-section h1{font-size:clamp(36px,4vw,64px);line-height:1.04;letter-spacing:-.035em;color:inherit;margin:20px 0 28px}.sh-section h2{font-size:clamp(28px,3vw,44px);line-height:1.15;color:inherit}.sh-section h3{font-size:24px;line-height:1.25;color:inherit}.sh-section p{line-height:1.75}.sh-eyebrow{font-size:11px;letter-spacing:.16em;font-weight:700}.sh-lead{font-size:20px}.sh-section .sh-image img{width:100%;height:380px;object-fit:cover}.sh-section figure{margin:0}.sh-section .sh-button a{display:inline-block;background:#283e35;color:white;padding:15px 24px;text-decoration:none;font-weight:600;border:1px solid currentColor;border-radius:0}.sh-section .sh-button a:focus-visible{outline:3px solid #c37935;outline-offset:4px}.sh-light{background:#fff}.sh-dark{background:#283e35;color:#fff}.sh-dark .sh-button a{background:#f1eee5;color:#283e35}#el-shprocess .vce-row-content{align-items:flex-start}#el-shquestions h3{margin-top:32px}#el-shcontact{padding:72px 32px}@media(max-width:767px){.sh-section{padding:36px 20px}.sh-section .vce-row-content{display:flex;flex-direction:column;gap:28px}.sh-section .vce-col{width:100%;flex:auto}.sh-section .sh-image img{height:280px}.sh-section h1{font-size:40px}.sh-lead{font-size:18px}#el-shcontact{padding:44px 20px}}
'''
if __name__=='__main__':
    p=call('get-page',{'post_id':2238})
    old=p['published']
    p=call('stage-document',{'post_id':2238,'expected_hash':p['content_hash'],'elements':elements,'html':html,'css':css})
    assert p['published']==old
    call('stage-document',{'post_id':2238,'expected_hash':'0'*64,'elements':elements,'html':html,'css':css},True)
    call('publish-page',{'post_id':2238,'expected_hash':p['content_hash'],'confirm':False},True)
    p=call('publish-page',{'post_id':2238,'expected_hash':p['content_hash'],'confirm':True})
    assert len(p['published']['elements'])==len(elements) and p['draft'] is None
    (base/'visual-composer-live-state.json').write_text(json.dumps(p),encoding='utf-8')
    print(json.dumps({'post_id':p['post_id'],'url':p['permalink'],'editor_url':p['editor_url'],'elements':len(elements)}),flush=True)
