"""Generate a native, editable Bricks postmeta tree for the local demo."""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'bricks-json'
OUT.mkdir(exist_ok=True)
nodes = []
INK, MUTED, PAPER, ORANGE, LINE = '#202522', '#606760', '#F7F7F0', '#C94725', '#DADDD2'

def space(n):
    return dict(top=f'{n}px', right=f'{n}px', bottom=f'{n}px', left=f'{n}px')

def border(color=LINE, radius=18):
    return dict(width=space(1), style='solid', color={'hex': color}, radius=space(radius))

def add(name, parent=0, settings=None, label=None):
    ident = f'f{len(nodes)+1:05d}'
    node = dict(id=ident, name=name, parent=parent, children=[], settings=settings or {}, label=label or name)
    nodes.append(node)
    if parent:
        next(n for n in nodes if n['id'] == parent)['children'].append(ident)
    return ident

def block(parent, settings=None, label=None):
    return add('block', parent, {'_rowGap': '18px', '_alignItems': 'stretch', **(settings or {})}, label)

def typo(size=16, color=INK, weight=400):
    return {'font-family': 'Arial', 'font-size': f'{size}px', 'font-weight': str(weight), 'line-height': '1.55', 'color': {'hex': color}}

def text(parent, content, size=16, color=MUTED, weight=400, extra=None):
    return add('text-basic', parent, {'text': content, 'tag': 'p', '_typography': typo(size,color,weight), '_margin':space(0), **(extra or {})}, content[:45])

def heading(parent, content, level='h2', size=44, color=INK, extra=None):
    return add('heading', parent, {'text':content, 'tag':level, '_typography':{**typo(size,color,600),'line-height':'1.08','letter-spacing':'-1.7px'}, '_typography:mobile_portrait':{'font-size':f'{min(size,34)}px','letter-spacing':'-1px'}, '_margin':space(0), **(extra or {})}, content)

def button(parent, content, target, primary=True):
    return add('button',parent,{'text':content,'tag':'a','link':{'type':'external','url':target},'_typography':typo(15,'#FFFFFF' if primary else INK,600),'_background':{'color':{'hex':ORANGE if primary else '#FFFFFF'}},'_padding':{'top':'15px','bottom':'15px','left':'24px','right':'24px'},'_border':border(ORANGE if primary else LINE,8),'_width':'fit-content','_background:hover':{'color':{'hex':'#A8391E' if primary else '#ECEFE4'}},'_cssTransition':'background-color 180ms ease'},content)

def section(label,bg=PAPER,padding=88,anchor=None):
    sec=add('section',settings={'_background':{'color':{'hex':bg}},'_padding':{'top':f'{padding}px','bottom':f'{padding}px','left':'28px','right':'28px'},'_padding:mobile_portrait':{'top':f'{min(padding,56)}px','bottom':f'{min(padding,56)}px','left':'20px','right':'20px'},**({'_cssId':anchor} if anchor else {})},label=label)
    return add('container',sec,{'_width':'1160px','_widthMax':'100%','_alignItems':'stretch','_rowGap':'32px'},label+' inner')

def row(parent,gap=20,extra=None):
    return block(parent,{'_direction':'row','_alignItems':'center','_columnGap':f'{gap}px','_flexWrap':'wrap',**(extra or {})})

def grid(parent,columns=3,gap=24):
    return block(parent,{'_display':'grid','_gridTemplateColumns':f'repeat({columns},minmax(0,1fr))','_gridGap':f'{gap}px','_gridTemplateColumns:tablet_portrait':'repeat(2,minmax(0,1fr))','_gridTemplateColumns:mobile_portrait':'minmax(0,1fr)'})

nav=section('Navigation',padding=23,anchor='top')
navrow=row(nav,extra={'_justifyContent':'space-between'})
brand=text(navrow,'forma<span style="color:#C94725">.</span>',28,INK,700,{'_width':'auto','link':{'type':'external','url':'#top'}})
links=row(navrow,26,{'_width':'auto','_display:mobile_portrait':'none'})
for label,target in [('Workspace','#workspace'),('Features','#features'),('Pricing','#pricing')]:
    text(links,label,14,INK,500,{'_width':'auto','link':{'type':'external','url':target}})
button(navrow,'Explore Forma ↗','#workspace',False)

hero=section('Hero',padding=62)
hero_grid=grid(hero,2,64)
# Stack hero at tablet instead of squeezing its product illustration.
next(n for n in nodes if n['id']==hero_grid)['settings'].update({'_gridTemplateColumns:tablet_portrait':'minmax(0,1fr)','_alignItemsGrid':'center'})
copy=block(hero_grid,{'_rowGap':'25px'})
text(copy,'LESS NOISE. MORE MOMENTUM.',12,ORANGE,700,{'_typography':{**typo(12,ORANGE,700),'letter-spacing':'1.8px'}})
heading(copy,'A little less busy.<br>A lot more done.','h1',70,extra={'_typography:tablet_portrait':{'font-size':'62px'},'_typography:mobile_portrait':{'font-size':'45px','letter-spacing':'-2px'}})
text(copy,'Give your ideas, projects, and people a place to come together. A calmer workspace for teams who care about what they create.',18,extra={'_widthMax':'470px'})
actions=row(copy,12)
button(actions,'Find your flow ↗','#pricing')
button(actions,'Take a look inside','#workspace',False)
text(copy,'One shared space. Fewer tabs. Room to think.',13)

scene=block(hero_grid,{'_background':{'color':{'hex':'#E3E8D9'}},'_padding':space(26),'_padding:mobile_portrait':space(16),'_border':border('#D8DFCE',24),'_rowGap':'18px','_widthMin':'0','_cssId':'workspace'},'Native product preview')
preview=block(scene,{'_background':{'color':{'hex':'#FFFFFF'}},'_border':border('#DDE1D8',12),'_padding':space(22),'_rowGap':'22px','_boxShadow':{'values':{'offsetX':'0','offsetY':'12','blur':'30','spread':'-15'},'color':{'rgb':'rgba(26,38,25,0.22)'}}},'Project board')
top=row(preview,extra={'_justifyContent':'space-between'})
text(top,'● ● ●',11,'#8A9283',400,{'_width':'auto'})
text(top,'YOUR WEEK, IN FOCUS',10,MUTED,700,{'_width':'auto'})
heading(preview,'Good things in motion.','h3',25)
text(preview,'Studio / Brand launch',12)
tasks=block(preview,{'_rowGap':'10px'})
for icon,title,status,shade in [('✓','Brand direction','Done','#DCEBDD'),('◉','Website exploration','In progress','#F6E8C9'),('○','Launch checklist','Up next','#EDECE7')]:
    task=row(tasks,10,{'_padding':space(12),'_background':{'color':{'hex':'#F8F9F5'}},'_border':border('#ECEEE6',7),'_flexWrap':'nowrap'})
    text(task,icon,17,ORANGE,600,{'_width':'25px','_flexShrink':'0'})
    text(task,title,13,INK,500,{'_flexGrow':'1'})
    text(task,status,10,INK,500,{'_width':'auto','_flexShrink':'0','_padding':space(5),'_background':{'color':{'hex':shade}},'_border':border(shade,5)})
bottom=row(preview,extra={'_justifyContent':'space-between'})
text(bottom,'AL   MK   JO',11,INK,600,{'_width':'auto'})
text(bottom,'A good day to make progress.',11,MUTED,400,{'_width':'auto'})
note=row(scene,extra={'_justifyContent':'space-between'})
text(note,'✦  Built around your rhythm',12,INK,600,{'_width':'auto'})
text(note,'Not the other way around.',11,MUTED,400,{'_width':'auto'})

strap=section('Positioning',padding=29)
text(strap,'FOR SMALL TEAMS WITH BIG IDEAS',11,MUTED,700,{'_typography':{**typo(11,MUTED,700),'letter-spacing':'2px','text-align':'center'}})
roles=row(strap,30,{'_justifyContent':'space-around'})
for role in ['Design studios','Independent makers','Creative teams','Growing businesses']:
    text(roles,role,18,INK,600,{'_width':'auto'})

features=section('Features',bg='#FFFFFF',anchor='features')
intro=block(features,{'_widthMax':'650px'})
text(intro,'EVERYTHING IN ITS PLACE',12,ORANGE,700)
heading(intro,'The work is complex.<br>Your workspace shouldn’t be.')
text(intro,'Keep the important things close, so your team can spend less time searching and more time making.')
cards=grid(features)
for number,title,desc,color in [('01','Make the plan clear','Turn a big idea into small, achievable steps. Keep owners, priorities, and next moves in sight.','#E6EDDF'),('02','Keep the story together','Notes, decisions, and inspiration live beside the work. Context travels with every project.','#F7E6D9'),('03','Find your own rhythm','Move between focused work and team updates with a simple view of what matters today.','#ECE8F4')]:
    card=block(cards,{'_padding':space(28),'_border':border('#E4E7DC',14),'_background':{'color':{'hex':'#FAFBF7'}},'_rowGap':'20px'})
    text(card,number,17,INK,600,{'_width':'48px','_padding':space(10),'_background':{'color':{'hex':color}},'_border':border(color,9),'_typography':{**typo(17,INK,600),'text-align':'center'}})
    heading(card,title,'h3',24)
    text(card,desc,15)

workflow=section('How it works',bg=INK,anchor='how-it-works')
text(workflow,'FROM FIRST THOUGHT TO FINISHED WORK',12,'#D9E6CC',700)
heading(workflow,'Less managing.<br>More making.',color='#FFFFFF',size=48)
steps=grid(workflow)
for num,title,desc in [('1','Bring it all in','Start a project. Add your notes, tasks, and the people who make it happen.'),('2','Make space for focus','Choose your priorities and turn the next big thing into a clear next step.'),('3','Move forward together','Share progress, collect feedback, and celebrate the small wins along the way.')]:
    col=block(steps,{'_padding':{'top':'24px','bottom':'12px'},'_border':{'width':{'top':'1px'},'style':'solid','color':{'hex':'#555E53'}}})
    text(col,'0'+num,15,'#DFE9BA',700)
    heading(col,title,'h3',24,'#FFFFFF')
    text(col,desc,15,'#CFD5CA')

pricing=section('Pricing',anchor='pricing')
intro=block(pricing,{'_widthMax':'660px'})
text(intro,'A LITTLE SPACE. A LOT OF POSSIBILITY.',12,ORANGE,700)
heading(intro,'Start small. Grow at your pace.')
text(intro,'Two simple plans for the way you work. Illustrative pricing for this concept demo.',16)
plans=grid(pricing,2)
for label,price,desc,items,popular in [('Solo','$12','A home for your personal projects.',['Unlimited personal projects','Notes and task boards','A focused weekly view'],False),('Together','$29','A shared rhythm for your small team.',['Everything in Solo','Shared projects and feedback','Team views and permissions'],True)]:
    card=block(plans,{'_padding':space(32),'_background':{'color':{'hex':'#FFFFFF' if not popular else '#E8EDDE'}},'_border':border('#CBD4BD' if popular else LINE,16),'_rowGap':'23px'})
    heading(card,label,'h3',25)
    text(card,desc,15)
    heading(card,price+'<span style="font-size:15px;font-weight:400;letter-spacing:0"> / month</span>','h4',49)
    for item in items: text(card,'✓  '+item,15,INK)
    button(card,'Explore '+label+' ↗','#demo',popular)

faq=section('FAQ',bg='#FFFFFF',anchor='faq')
heading(faq,'A few things you might be wondering.',size=39)
faqs=grid(faq,2)
for question,answer in [('Can I use Forma on my own?','Absolutely. The Solo concept is designed for independent work, personal projects, and a little everyday clarity.'),('Is this a real product?','Forma is a fictional product created for this test landing page. The workspace and pricing are illustrative.'),('Can I edit this page?','Yes. The layout, copy, cards, and buttons are native Bricks elements, ready to adjust in the visual builder.'),('Does it work on smaller screens?','The layout adapts from desktop grids to stacked mobile sections, with responsive type and spacing.')]:
    card=block(faqs,{'_padding':{'top':'24px','bottom':'24px'},'_border':{'width':{'top':'1px'},'style':'solid','color':{'hex':LINE}}})
    heading(card,question,'h3',21)
    text(card,answer,15)

cta=section('Final call to action',bg='#E7EDDA',anchor='demo')
box=block(cta,{'_alignItems':'center','_rowGap':'25px'})
text(box,'MAKE ROOM FOR WHAT MATTERS',12,ORANGE,700,{'_typography':{**typo(12,ORANGE,700),'text-align':'center','letter-spacing':'1.5px'}})
heading(box,'Your next good idea<br>deserves a little space.',size=53,extra={'_typography':{**typo(53,INK,600),'line-height':'1.1','letter-spacing':'-2px','text-align':'center'}})
button(box,'Explore the workspace ↑','#workspace')
text(box,'A concept demo. No signup, payment, or personal data required.',12,extra={'_typography':{**typo(12,MUTED),'text-align':'center'}})

footer=section('Footer',padding=30)
foot=row(footer,extra={'_justifyContent':'space-between'})
text(foot,'forma.',25,INK,700,{'_width':'auto'})
text(foot,'Concept landing page · EMCP × Bricks',12,MUTED,400,{'_width':'auto'})
text(foot,'Back to top ↑',13,INK,600,{'_width':'auto','link':{'type':'external','url':'#top'}})

payload={'content':nodes,'source':'bricksCopiedElements','sourceUrl':'https://msrplugins.test','version':'2.3.13','globalClasses':[],'globalElements':[]}
(OUT/'forma-landing.json').write_text(json.dumps(payload,indent=2,ensure_ascii=False),encoding='utf-8')
print(f'Wrote {len(nodes)} native Bricks elements to {OUT / "forma-landing.json"}')
