"""Beaver landing-page and staging regression test. All document writes use MCP."""
import importlib.util
import json
import sys
from pathlib import Path

spec = importlib.util.spec_from_file_location('client', Path(__file__).with_name('oxygen-mcp-client.py'))
client = importlib.util.module_from_spec(spec)
spec.loader.exec_module(client)
state_path = Path(__file__).with_name('.beaver-state.json')
state = json.loads(state_path.read_text(encoding='utf-8')) if state_path.exists() else {}


def call(slug, args=None, error=False):
    result = client.rpc_tool('emcp-tools-call-tool', {'name': 'emcp-tools/beaver-' + slug, 'arguments': args or {}})
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
    if '--resume' not in sys.argv:
        assert call('get-context')['site_url']=='https://elementor-mcp.test'
        for slug in ['list-modules','list-pages','list-library','get-design-system']: call(slug)
        schemas={t:call('get-module-schema',{'type':t}) for t in ['heading','rich-text','button','photo']}
        for t in ['row','column']: call('get-node-schema',{'type':t})
    page=call('get-page',{'post_id':state['page_id']}) if state.get('page_id') else remember(call('create-page',{'title':'TIDE & TIMBER — Beaver Builder Integration Test'}))
    def edit(slug,**args):
        nonlocal page
        page=call(slug,dict(post_id=page['post_id'],expected_hash=page['content_hash'],**args));return page
    nodes={}
    def node(id,type,parent,settings):
        pos=sum(1 for n in nodes.values() if n['parent']==parent)
        nodes[id]={'node':id,'type':type,'parent':parent,'position':pos,'settings':settings}
        return id
    def row(id,cols=(100,),bg='f5f1e9',pad=70):
        node(id,'row',None,{'class':'tt-section '+id,'width':'full','content_width':'fixed','max_content_width':1160,'max_content_width_unit':'px','bg_type':'color','bg_color':bg,'padding_top':pad,'padding_bottom':pad,'padding_left':24,'padding_right':24,'padding_top_responsive':38,'padding_bottom_responsive':38,'padding_left_responsive':20,'padding_right_responsive':20})
        node(id+'group','column-group',id,'')
        result=[]
        for i,width in enumerate(cols):
            cid=id+'col'+str(i);node(cid,'column',id+'group',{'size':width,'padding_left':16,'padding_right':16,'padding_left_responsive':0,'padding_right_responsive':0});result.append(cid)
        return result
    def module(id,kind,parent,**settings):
        common={'type':kind,'margin_top':0,'margin_right':0,'margin_bottom':20,'margin_left':0,'class':id}
        common.update(settings);node(id,'module',parent,common)
    def text(id,parent,html):module(id,'rich-text',parent,text=html,color='343932',typography={'font_family':'Arial','font_size':{'length':'16','unit':'px'},'line_height':{'length':'1.7','unit':''}})
    def heading(id,parent,label,tag='h2',size=52):module(id,'heading',parent,heading=label,tag=tag,color='28342b',typography={'font_family':'Georgia','font_weight':'400','font_size':{'length':str(size),'unit':'px'},'line_height':{'length':'1.08','unit':''}},typography_responsive={'font_size':{'length':'42' if tag=='h1' else '34','unit':'px'}})
    def button(id,parent,label,link):module(id,'button',parent,text=label,link=link,bg_color='a6442c',bg_hover_color='80321f',text_color='ffffff',text_hover_color='ffffff',padding_top=17,padding_bottom=17,padding_left=26,padding_right=26,typography={'font_size':{'length':'14','unit':'px'}})
    def photo(id,parent,attachment):module(id,'photo',parent,photo_source='library',photo=attachment,photo_size='full',align='center',crop='',margin_bottom=0)
    a,b=row('ttnav',(50,50),pad=26)
    text('brand',a,'<p class="tt-brand">TIDE &amp; TIMBER</p><p class="tt-kicker">ARCHITECTURE / INTERIORS</p>')
    text('navigation',b,'<p class="tt-nav"><a href="#selected-work">Selected work</a> &nbsp; <a href="#our-approach">Our approach</a> &nbsp; <a href="#get-in-touch">Get in touch ↗</a></p>')
    a,b=row('tthero',(50,50),pad=64)
    text('eyebrow',a,'<p class="tt-kicker">THOUGHTFUL SPACES. EVERYDAY JOY.</p>')
    heading('heroheading',a,'A little closer to the life you imagined.','h1',72)
    text('herointro',a,'<p class="tt-intro">Warm materials. Natural light. Room to breathe. We design homes that bring the things you love into focus.</p>')
    button('herobutton',a,'Discover our work ↗','#selected-work')
    text('heronote',a,'<p class="tt-small">Independent by choice. Personal by design.</p>')
    photo('heroimage',b,1875)
    a,b=row('ttapproach',(35,65),bg='e6e9de')
    nodes['ttapproach']['settings']['id']='our-approach'
    text('approachlabel',a,'<p class="tt-kicker">01 / THE WAY WE SEE IT</p>')
    heading('approachheading',b,'A home should feel like you.')
    text('approachcopy',b,"""<p class="tt-intro">Good design isn't only about how a space looks. It's the morning light across your kitchen, the seat everyone reaches for, and a quiet corner at the end of the day.</p><p>We listen closely, think carefully, and make every detail count.</p>""")
    a,=row('ttworkheading',pad=60);nodes['ttworkheading']['settings']['id']='selected-work'
    text('worklabel',a,'<p class="tt-kicker">02 / SELECTED WORK</p>');heading('worktitle',a,'Made for living.')
    a,b=row('ttwork',(50,50),pad=0)
    photo('workphotoone',a,1873);text('workcaptionone',a,'<p class="tt-project">The Courtyard House</p><p class="tt-small">Architecture · A quieter kind of city living</p>')
    photo('workphototwo',b,1874);text('workcaptiontwo',b,'<p class="tt-project">The Soft Modern</p><p class="tt-small">Interiors · Natural textures, lasting comfort</p>')
    a,b,c=row('ttservices',(33.33,33.33,33.34))
    for parent,num,label,copy in [(a,'01','Architecture','From a new beginning to a considered extension. Spaces shaped around your site and your story.'),(b,'02','Interiors','Material palettes, thoughtful planning, and the finishing details that make a place feel complete.'),(c,'03','A shared process',"Clear conversations and close collaboration. You'll know where we're going, every step of the way.")]:
        text('service'+num,parent,'<p class="tt-kicker">'+num+'</p>');heading('serviceheading'+num,parent,label,size=30);text('servicecopy'+num,parent,'<p>'+copy+'</p>')
    a,=row('ttcontact',bg='e6e9de',pad=80);nodes['ttcontact']['settings']['id']='get-in-touch'
    text('contactlabel',a,'<p class="tt-kicker">LET’S START WITH A CONVERSATION</p>');heading('contactheading',a,'What does home mean to you?',size=60)
    text('contactcopy',a,'<p>Tell us what you have in mind. We’ll explore what’s possible, together.</p>');button('contactbutton',a,'Tell us about your project ↗','mailto:hello@example.com')
    a,b=row('ttfooter',(50,50),pad=28)
    text('footerbrand',a,'<p class="tt-brand">TIDE &amp; TIMBER</p>');text('footernote',b,'<p class="tt-small tt-nav">A demonstration studio · Beaver Builder integration test<br />Built with native rows, columns and modules.</p>')
    published_before=page['published_nodes']
    edit('set-page-layout',nodes=nodes)
    assert page['published_nodes']==published_before
    call('set-page-layout',{'post_id':page['post_id'],'expected_hash':'0'*64,'nodes':nodes},error=True)
    broken=json.loads(json.dumps(nodes));broken['heroheading']['parent']='missing'
    call('set-page-layout',{'post_id':page['post_id'],'expected_hash':page['content_hash'],'nodes':broken},error=True)
    css="""body{background:#f5f1e9}.tt-section a{text-decoration:none}.tt-section .fl-heading{letter-spacing:-.045em}.tt-brand{font-size:23px!important;letter-spacing:-.045em;font-weight:700;margin:0!important}.tt-kicker{font-size:10px!important;letter-spacing:.17em;font-weight:700}.tt-nav{text-align:right;font-size:12px!important}.tt-nav a{color:#343932}.tt-small{font-size:12px!important;color:#62695f}.tt-intro{font-size:18px}.ttnav{border-bottom:1px solid #d3d6c9}.tthero .fl-col:first-child{padding-right:35px}.tthero .fl-col:first-child>.fl-col-content{padding-top:26px}.heroimage img{width:100%;height:600px;object-fit:cover}.ttworkheading .fl-row-content-wrap{padding-bottom:20px}.ttwork img{width:100%;height:350px;object-fit:cover}.tt-project{font-size:20px!important;margin-top:18px!important;margin-bottom:2px!important}.ttcontact{text-align:center}.ttcontact .fl-heading{max-width:750px;margin:auto}.ttcontact .fl-button-wrap{text-align:center}.ttcontact .fl-rich-text{max-width:680px;margin:auto}.ttservices{border-bottom:1px solid #d3d6c9}.tt-section .fl-button{border-radius:0!important}.tt-section .fl-button:hover{transform:translateY(-1px)}
@media(max-width:767px){.tt-nav{text-align:left}.tthero .fl-col:first-child{padding-right:0}.tthero .fl-col:first-child>.fl-col-content{padding-top:0}.heroimage img{height:410px}.ttwork img{height:300px}.ttservices .fl-col{margin-bottom:25px}.ttcontact .fl-heading{font-size:38px}}
"""
    pid=str(page['post_id']);css+=' body.page-id-'+pid+' #site-header,body.page-id-'+pid+' #site-footer,body.page-id-'+pid+' .page-header{display:none!important} body.page-id-'+pid+' #content{max-width:none;width:100%;padding:0}'
    edit('set-page-styles',css=css)
    assert call('get-page-styles',{'post_id':page['post_id']})['draft_css']==css
    edit('add-node',node={'node':'temporarytest','type':'module','parent':'ttfootercol0','position':9,'settings':{'type':'heading','heading':'Temporary verification'}})
    edit('update-node',node_id='temporarytest',settings={'heading':'Updated verification'})
    edit('move-node',node_id='temporarytest',parent_id='ttfootercol1',position=9)
    edit('remove-node',node_id='temporarytest')
    edit('update-node',node_id='heroheading',settings={'heading':'Recovery verification'})
    revisions=call('list-revisions',{'post_id':page['post_id']})['revisions']
    edit('restore-revision',revision_id=revisions[-1]['revision_id'])
    assert page['nodes']['heroheading']['settings']['heading']=='A little closer to the life you imagined.'
    edit('publish-page')
    assert page['status']=='publish' and page['published_nodes']==page['nodes']
    asset=Path('F:/laragon/www/elementor-mcp/wp-content/uploads/bb-plugin/cache/'+str(page['post_id'])+'-layout.css').read_text(encoding='utf-8')
    assert 'heroheading' in asset and 'tt-kicker' in asset and 'Georgia' in asset
    assert "isn't" in page['nodes']['approachcopy']['settings']['text']
    published=page['published_nodes']
    edit('update-node',node_id='heroheading',settings={'heading':'Draft isolation verification'})
    assert page['published_nodes']==published
    r=call('list-revisions',{'post_id':page['post_id']})['revisions'][-1]
    edit('restore-revision',revision_id=r['revision_id'])
    remember(page);print(json.dumps({'page_id':page['post_id'],'url':page['url'],'nodes':len(page['nodes'])}),flush=True)

if __name__=='__main__': build()
