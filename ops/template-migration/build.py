from pathlib import Path
import json,tinycss2,re
base=Path('ops/template-migration');(base/'sources').mkdir(exist_ok=True)
for name in ['srd20','dragonbane','for_the_quest']:
 source=(base/'sources'/f'{name}.css').read_text(encoding='utf-8-sig')
 parsed=tinycss2.parse_stylesheet(source,skip_comments=True,skip_whitespace=True)
 variables={};rules=[]
 for weight,r in enumerate(parsed):
  if r.type=='at-rule':
   assert r.lower_at_keyword=='font-face',r
   selectors=['@font-face']
  elif r.type=='qualified-rule':
   # Split only top-level commas; functions and attribute selectors remain intact.
   parts=[[]]
   for token in r.prelude:
    if token.type=='literal' and token.value==',':parts.append([])
    else:parts[-1].append(token)
   selectors=[tinycss2.serialize(p).strip() for p in parts]
   if selectors==[':root']:
    for d in tinycss2.parse_declaration_list(r.content,skip_comments=True,skip_whitespace=True):
     assert d.type=='declaration'
     variables[d.name.lower()]=tinycss2.serialize(d.value).strip()
    continue
   selectors=[s.replace('.plantilla','main').replace('.a5main','main.a5').replace(':root','main') for s in selectors]
   selectors=[re.sub(r'^\.(a[45])(?=[ >])',r'main.\1',s) for s in selectors]
   selectors=[s.replace('#important ', '') for s in selectors]
   selectors=[s if re.match(r'^main(?:\b|[.#:> ])',s) else 'main '+s for s in selectors]
  else:raise Exception(r)
  declarations=[]
  for d in tinycss2.parse_declaration_list(r.content,skip_comments=True,skip_whitespace=True):
   if d.type=='error':raise Exception((name,d))
   value=tinycss2.serialize(d.value).strip()+(' !important' if d.important else '')
   # CSS variables are case sensitive; the existing BD contract normalises names.
   value=re.sub(r'--[\w-]+',lambda m:m.group().lower(),value)
   declarations.append([d.name.lower(),value])
  for s in selectors:
   assert len(s)<=255,(name,s,len(s))
   rules.append({'selector':s,'peso':weight,'declarations':declarations})
 variables={k:re.sub(r'--[\w-]+',lambda m:m.group().lower(),v) for k,v in variables.items()}
 if name=='srd20':variables['--hb_color_accent']='var(--color-bg-accent)'
 # Standard controls describe the historical typography instead of defaults from a blank template.
 if name=='srd20':
  fam=['MrEaves']*4+['WalterTurncoat']*2; sizes=['34px','26px','22px','16px','13px','11px'];body=('BookInsanity','13px','#000000'); colors=['#58180D']*4+['#000000']*2
 elif name=='dragonbane':
  fam=['Colus']*5+['WalterTurncoat'];sizes=['64px','24px','16px','15px','19px','12px'];body=('Crimson Pro Regular','15px','#333333');colors=['#EE3D3C','#00604D','#996633','#333333','#ffffff','#333333']
 else:
  fam=['Medieval Sharp','Minion Pro','Minion Pro','Minion Pro','Medieval Sharp','Medieval Sharp'];sizes=['22pt','16pt','18pt','14pt','15pt','xxx-large'];body=('Minion Pro','14pt','#333333');colors=['#48000c','#ffffff','#48000c','#333333','#ffffff','#000000']
 controls={}
 # The legacy h3 underline is a 2px border across the whole heading block.
 if name in ['srd20','dragonbane']:controls['--h3-decoration']='2px'
 for rule in rules:
  if rule['selector']=='main h3':
   rule['declarations']=[(k,v) for k,v in rule['declarations'] if k!='border-bottom']
 for i in range(1,7):
  controls.update({f'--h{i}-family':fam[i-1],f'--h{i}-size':sizes[i-1],f'--h{i}-color':colors[i-1]})
 controls.update({'--body-family':body[0],'--body-size':body[1],'--body-color':body[2]})
 for niv in ['body','small']+[f'h{i}' for i in range(1,7)]:
  controls[f'--{niv}-variant']='small-caps' if name=='dragonbane' and niv.startswith('h') and niv!='h6' else 'normal'
  controls[f'--{niv}-weight']='800' if name=='srd20' and niv in ['h1','h2','h3','h4'] else '400'
  controls[f'--{niv}-align']='center' if (name=='dragonbane' and niv=='h1') or (name=='for_the_quest' and niv in ['h1','h5','h6']) else 'left'
  controls[f'--{niv}-style']='italic' if name=='for_the_quest' and niv in ['h1','h5','h6'] else 'normal'
  controls[f'--{niv}-transform']='uppercase' if (name=='dragonbane' and niv=='h4') or (name=='for_the_quest' and niv in ['h2','h3']) else 'none'
 background={'srd20':'/img/editor/srd20/72/parchment_bg.jpg','dragonbane':'/img/editor/dragonbane/96/pergamino.webp','for_the_quest':'/img/editor/for_the_quest/96/pergamino.webp'}[name]
 controls['--background-image']=controls['--background-image-even']=f'url("{background}")'
 controls['--footer-imagen']='url("/img/editor/srd20/72/footer3.png")' if name=='srd20' else 'none'
 controls['--footer-height']='43px' if name=='srd20' else '0px'
 controls['--footer-page-x']='55px' if name=='srd20' else '120px'
 controls['--footer-page-y']='16px' if name=='srd20' else '0px'
 controls['--list-style-checkbox']='"❑ "'
 if name=='dragonbane':
  controls.update({'--section-padding-top':'40px','--section-padding-left':'40px','--section-padding-right':'40px','--section-padding-bottom':'0px','--p-gap':'10px'})
 elif name=='srd20':
  controls.update({'--header-padding-top':'49px','--header-padding-left':'49px','--header-padding-right':'49px','--header-padding-bottom':'0px','--section-padding-top':'0px','--section-padding-left':'49px','--section-padding-right':'49px','--section-padding-bottom':'0px','--p-gap':'10px'})
 # Measured base values feed the same controls used by ordinary BD templates.
 measured=json.loads((base/(name+'-computed.json')).read_text(encoding='utf-8'))
 def color(v):
  m=re.match(r'rgb\((\d+), (\d+), (\d+)\)',v)
  return '#'+''.join(f'{int(c):02x}' for c in m.groups()) if m else v
 typography={'body':'article','h1':'section h1','h2':'h2','h3':'h3','h4':'h4','h5':'h5','h6':'h6','small':'small','dropcap':'header > p'}
 props={'family':'fontFamily','size':'fontSize','color':'color','weight':'fontWeight','style':'fontStyle','variant':'fontVariant','align':'textAlign','transform':'textTransform','margin-top':'marginTop'}
 for niv,sel in typography.items():
  for suffix,prop in props.items():
   v=measured[sel][prop]
   if prop=='color':v=color(v)
   if prop=='fontFamily':v=v.strip('"')
   if prop=='textAlign' and v=='start':v='left'
   if prop=='fontWeight' and v=='400':v='normal'
   controls[f'--{niv}-{suffix}']=v
 for block,sel in {'header':'header','section':'section','footer':'footer','p':'p+p','list':'ul','blockquote':'blockquote','table':'table'}.items():
  for prop in ['padding','margin']:
   for side in ['top','right','bottom','left']:
    value=measured[sel][prop+side.capitalize()]
    # Automatic centering is a contextual rule, not a fixed physical margin.
    if prop=='margin' and side in ['right','left'] and block in ['footer','blockquote']:value='0px'
    controls[f'--{block}-{prop}-{side}']=value
 for side in ['top','right','bottom','left']:
  controls['--dropcap-margin-'+side]=measured['header > p']['margin'+side.capitalize()]
 controls['--p-gap']=measured['p+p']['marginTop']
 controls['--table-td-padding-x']=measured['td']['paddingLeft']
 controls['--table-td-padding-y']=measured['td']['paddingBottom']
 controls['--table-th-border-bottom-width']='2px'
 controls['--table-th-border-bottom-color']=color(measured['th']['borderBottomColor'])
 controls['--table-col1-border-right-width']=measured['td']['borderRightWidth']
 controls['--table-col1-border-right-color']=color(measured['td']['borderRightColor'])
 controls['--blockquote-border-left-width']=measured['blockquote']['borderLeftWidth']
 controls['--blockquote-border-left-color']=color(measured['blockquote']['borderLeftColor'])
 controls['--a-color']={'srd20':'#8b0000','dragonbane':'#8b0000','for_the_quest':'#48000c'}[name]
 stripe=measured['tbody tr:nth-child(even)']['backgroundColor']
 m=re.match(r'rgba\((\d+), (\d+), (\d+), ([\d.]+)\)',stripe)
 if m:
  controls['--table-zebra-color']='#'+''.join(f'{int(c):02x}' for c in m.groups()[:3])
  controls['--table-zebra-opacity']=str(round(float(m.group(4))*100))
 controls['--small-size']=str(round(float(measured['small']['fontSize'][:-2])/float(measured['article']['fontSize'][:-2])*100,2))+'%'
 controls['--componentes-capitular-selector']=next(r['selector'] for r in rules if any(k=='font-size' and ('first-letter' in r['selector']) for k,v in r['declarations']))
 # Missing faces used by decorations, kept in BD along with the original faces.
 if name=='dragonbane':rules.insert(0,{'selector':'@font-face','peso':-2,'declarations':[['font-family','WalterTurncoat'],['src','url("/fonts/walter_turncoat_regular.woff2")']]})
 if name=='for_the_quest':rules.insert(0,{'selector':'@font-face','peso':-2,'declarations':[['font-family','Colus'],['src','url("/fonts/colus/regular.woff2")']]})
 for block, rule in enumerate(rules):
  rule['selector'] += f' /* bloque {block} */'
  assert len(rule['selector'])<=255
 (base/f'{name}.json').write_text(json.dumps({'nombre':name,'variables':variables,'ajustes':controls,'reglas':rules},ensure_ascii=False,indent=2),encoding='utf-8')
 print(name,len(rules),sum(len(r['declarations']) for r in rules))
