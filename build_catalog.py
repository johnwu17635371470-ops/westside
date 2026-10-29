"""Build crawlable catalog pages from public, non-confidential product data."""
import json,html,re
from pathlib import Path
ROOT=Path(__file__).parent
D=ROOT/'dist'
all_items=json.loads((ROOT/'data/catalog.json').read_text(encoding='utf-8'))
items=[p for p in all_items if p.get('image')]
for p in all_items:
 if not p.get('image'):
  stale=D/'products'/f'{p["id"]}.html'
  if stale.exists():stale.unlink()
origin=json.loads((ROOT/'site.config.json').read_text(encoding='utf-8'))['origin'].rstrip('/')
e=lambda s:html.escape(str(s),quote=True)
def price(p):return f"${p['referencePrice']:.2f} USD reference" if p['referencePrice'] is not None else 'Reference price unavailable'
def visual(p,prefix=''):
 if p.get('image'):return f'<img class="catalog-image" src="{prefix}{e(p["image"])}" alt="{e(p["name"])} — AI-styled image based on linked product photo" loading="lazy" width="1024" height="1024">'
 return '<div class="catalog-image image-pending"><span>PRODUCT PHOTO<br>AWAITING VERIFICATION</span></div>'
def note(p):return f'<p class="source-note">Amazon reference recorded {e(p["sourceDate"])}; converted from JPY at ¥150 = US$1. Not a FUBAO selling price or live quote. <a href="{e(p["sourceUrl"])}" target="_blank" rel="noopener noreferrer">View source listing ↗</a></p>'
def card(p):
 return f'<article class="product-card" data-category="{e(p["category"])}"><div class="product-visual"><a class="product-open" href="products/{p["id"]}.html" aria-label="View {e(p["name"])}">{visual(p)}</a><button class="product-add" data-add="{p["id"]}" aria-label="Save {e(p["name"])}">+</button></div><div class="product-meta"><div><h3><a href="products/{p["id"]}.html">{e(p["name"])}</a></h3><p>{e(p["category"])} / {e(p["finish"])}</p><span class="price">{price(p)}</span></div></div><button class="quick-view" data-detail="{p["id"]}">Quick view ↗</button></article>'
index=D/'index.html'
s=index.read_text(encoding='utf-8')
s=s.replace('WESTSIDE','FUBAO JEWELRY').replace('Westside','FUBAO JEWELRY').replace('<span>®</span>','')
s=s.replace('BAG <span','SAVED <span').replace('THE BAG.','SAVED PIECES.')
s=s.replace('A little inspiration, saved for later. This is a demo bag.','Your local inspiration list. No purchase or order is created.')
s=s.replace('Subtotal <small>(USD)</small>','Reference total <small>(USD)</small>')
s=s.replace('Sample prices only. No checkout, payment or order will be created.','Historical Amazon reference prices only. Missing prices are excluded and flagged. No checkout or orders.')
s=s.replace('Original AI-generated imagery. Demo shopping bag. No orders or payments.','Editorial AI imagery. Product research collection. No orders or payments.')
s=s.replace('CONCEPT STORE','PRODUCT RESEARCH COLLECTION')
s=s.replace('A little louder. A little bolder.','A little louder. A little bolder.')
s=s.replace('THE FUBAO JEWELRY COLLECTION / VOL. 01','JEWELRY RESEARCH / VOL. 01')
s=s.replace('FUBAO JEWELRY takes its cues','Our visual direction takes its cues')
s=s.replace("It’s the chain you never take off.","Find your own layering rhythm.")
s=s.replace("It's the chain you never take off. The ring that says enough. The details that make your everyday unmistakably yours.","Explore a research collection of rings, necklaces, bracelets, arm cuffs and earrings. Compare shapes and styling ideas, then check the original listing for current details.")
s=s.replace('Three ways to make it yours.<br>The everyday, turned all the way up.','Editorial concept imagery.<br>These looks do not depict the listed products.')
s=re.sub(r'<div class="filters".*?</div><span id="results-count">.*?</span>', '<div class="filters" role="group" aria-label="Filter collection">'+''.join(f'<button data-filter="{c}" aria-pressed="{str(c=="All").lower()}" class="{"active" if c=="All" else ""}">{c}</button>' for c in ['All','Rings','Necklaces','Bracelets','Arm cuffs','Earrings'])+f'</div><span id="results-count" aria-live="polite">{len(items)} PIECES</span>',s,flags=re.S)
s=re.sub(r'<div id="products" class="product-grid">.*?</div><p class="demo-note">.*?</p>', '<div id="products" class="product-grid">'+''.join(map(card,items))+'</div><p class="demo-note">21 research selections, not items offered for sale. Prices are historical Amazon references from October 25, 2026, converted at ¥150 = US$1. Product images are AI-styled only after matching linked source photos. <a href="sources.html">Sources & image notes ↗</a></p>',s,flags=re.S)
s=s.replace('href="styles.css"','href="styles.css"')
s=s.replace('<script src="app.js" defer></script>','<script src="catalog.js" defer></script><script src="app.js" defer></script>') if 'src="catalog.js"' not in s else s
s=re.sub(r'<meta name="description" content="[^"]*">','<meta name="description" content="FUBAO JEWELRY: compare rings, necklaces, earrings and bracelets through a source-linked research collection, reference prices and practical styling guides.">',s)
if '<!-- GEO GUIDE -->' not in s:
 guide='''<!-- GEO GUIDE --><section id="guide" class="section guide"><p class="eyebrow orange-text">04 / FIND YOUR STYLE</p><h2>SMALL DETAILS.<br>CLEAR ANSWERS.</h2><div class="guide-grid"><article><h3>How do I start a ring stack?</h3><p>Choose one statement shape, then add slimmer bands with space between them. Confirm each ring size on the linked listing; an adjustable style is not a guarantee of fit.</p><a href="#collection">Compare rings ↗</a></article><article><h3>How do I layer necklaces?</h3><p>Compare chain lengths before pairing them. A short, flat chain and a longer textured chain can create separation. Check the source for actual lengths and included pieces.</p></article><article><h3>Can I buy these pieces here?</h3><p>No. FUBAO JEWELRY currently presents product research and styling inspiration. Saving a piece creates a list in this browser, not an order. Prices are dated references, not current offers.</p></article><article><h3>Are materials and images verified?</h3><p>Finish descriptions refer to appearance. Composition, plating thickness, allergy suitability and durability require supplier confirmation. AI-styled photos are visual references and may differ in fine detail; check the linked original before choosing.</p></article></div></section>'''
 s=s.replace('<section class="closing">',guide+'<section class="closing">')
schema={'@context':'https://schema.org','@type':'CollectionPage','name':'FUBAO JEWELRY research collection','url':origin+'/','description':'Source-linked jewelry research with historical reference prices; not a retail offer.','mainEntity':{'@type':'ItemList','numberOfItems':len(items),'itemListElement':[{'@type':'ListItem','position':i+1,'url':origin+'/products/'+p['id']+'.html','name':p['name']} for i,p in enumerate(items)]}}
s=re.sub(r'<script type="application/ld\+json">.*?</script>','',s,flags=re.S)
schema={'@context':'https://schema.org','@graph':[schema,{'@type':'WebSite','@id':origin+'/#website','url':origin+'/','name':'FUBAO JEWELRY','publisher':{'@id':origin+'/#organization'}},{'@type':'Organization','@id':origin+'/#organization','name':'FUBAO JEWELRY','url':origin+'/'}]}
s=re.sub(r'<link rel="canonical"[^>]*>','',s)
s=s.replace('</head>',f'<link rel="canonical" href="{origin}/"></head>')
s=s.replace('</head>','<script type="application/ld+json">'+json.dumps(schema).replace('<','\\u003c')+'</script></head>')
s=s.replace('21 research selections','18 research selections')
index.write_text(s,encoding='utf-8')
(D/'catalog.js').write_text('window.catalog='+json.dumps(items,ensure_ascii=False).replace('<','\\u003c')+';',encoding='utf-8')
(D/'products').mkdir(exist_ok=True)
for p in items:
 schema={'@context':'https://schema.org','@type':'Product','name':p['name'],'category':p['category'],'description':p['description'],'url':origin+'/products/'+p['id']+'.html','isBasedOn':p['sourceUrl'],'identifier':{'@type':'PropertyValue','propertyID':'ASIN','value':p['asin']}}
 schema.pop('isBasedOn',None)
 schema['subjectOf']={'@type':'WebPage','url':p['sourceUrl']}
 if p.get('image'):schema['image']=origin+'/'+p['image']
 page=f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{e(p['name'])} | FUBAO JEWELRY</title><meta name="description" content="{e(p['description'])}"><link rel="stylesheet" href="../styles.css"><script type="application/ld+json">{json.dumps(schema).replace('<', chr(92)+'u003c')}</script></head><body><header class="header"><a class="wordmark" href="../index.html">FUBAO JEWELRY</a><a href="../index.html#collection">← Collection</a></header><main class="section"><p class="eyebrow">RESEARCH SELECTION / {e(p['category'])}</p><div class="detail-grid">{visual(p,'../')}<article class="detail-copy"><h1 class="product-title">{e(p['name'])}</h1><p class="detail-price">{price(p)}</p><p>{e(p['description'])}</p><dl><dt>Appearance</dt><dd>{e(p['finish'])}</dd><dt>Set information</dt><dd>{e(p['quantityLabel'])}</dd><dt>Source identifier</dt><dd>{p['asin']}</dd></dl>{note(p)}<p>Research selection, not a FUBAO offer. Verify current variants, sizing, included pieces and material specifications with the original seller.</p><p>Images, where available, are AI-styled from the linked source photo. Fine details require comparison with the original.</p><a class="button orange" href="../index.html#collection">BACK TO COLLECTION ↗</a></article></div></main><footer><a href="../sources.html">Sources & image notes ↗</a></footer></body></html>'''
 page=page.replace('</head>',f'<link rel="canonical" href="{origin}/products/{p["id"]}.html"></head>')
 (D/'products'/f'{p["id"]}.html').write_text(page,encoding='utf-8')
(D/'sources.html').write_text('''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sources & image notes | FUBAO JEWELRY</title><link rel="stylesheet" href="styles.css"></head><body><header class="header"><a class="wordmark" href="index.html">FUBAO JEWELRY</a></header><main class="section sources"><h1 class="product-title">Sources & image notes</h1><p>This collection contains 21 distinct product references extracted from the supplied Amazon jewelry research workbook, across its opportunity and shortlist sheets. It is a curated research selection, not an independently verified Amazon Top 100 ranking.</p><p>Source snapshot: October 25, 2026. Reference prices were converted from Japanese yen using ¥150 = US$1. Two records have no price. References may differ from current prices, variants or availability. No reference price constitutes a FUBAO JEWELRY retail offer.</p><p>Each product page links to its original Amazon listing by ASIN. Product imagery is generated only from matched photos on those links, never from images embedded in the workbook. AI styling can alter fine detail; original listings remain the visual reference. Unverified photos are explicitly marked pending.</p><p>Homepage hero and lookbook imagery are editorial concepts, not photographs of catalog products. FUBAO JEWELRY is the site identity, not a claim to manufacture or own the referenced designs.</p><p>Material composition, dimensions, allergy suitability, durability and supplier availability require separate confirmation. Internal cost analysis and profit estimates are not published.</p><p>The saved list stays in your browser. This site takes no payments and creates no orders.</p><a class="button orange" href="index.html#collection">EXPLORE COLLECTION ↗</a></main></body></html>''',encoding='utf-8')
(D/'sitemap.xml').write_text('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'+''.join('<url><loc>'+origin+'/'+path+'</loc></url>' for path in ['','sources.html']+['products/'+p['id']+'.html' for p in items])+'</urlset>',encoding='utf-8')
(D/'robots.txt').write_text('User-agent: *\nAllow: /\nSitemap: '+origin+'/sitemap.xml\n',encoding='utf-8')
source_page=D/'sources.html'
source_text=source_page.read_text(encoding='utf-8').replace('This collection contains 21 distinct product references extracted','The source dataset contains 21 distinct product references extracted').replace('Two records have no price.','18 selections with matched source images are displayed; 3 without verified source photos are skipped. One displayed selection has no price.').replace('Unverified photos are explicitly marked pending.','Selections without verified photos are omitted from the displayed collection.')
source_page.write_text(source_text.replace('</head>',f'<link rel="canonical" href="{origin}/sources.html"></head>'),encoding='utf-8')
print(f'Built {len(items)} product pages. Images: {sum(bool(p.get("image")) for p in items)}. Domain fubaojewelry.com remains pending hosting connection.')
