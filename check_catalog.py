"""Integrity checks for authored catalog, source links and generated output."""
import json,re
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urlparse,unquote
r=Path(__file__).parent;d=r/'dist'
products=json.loads((r/'data/catalog.json').read_text(encoding='utf-8'))
assert len(products)==len({p['asin'] for p in products})==21
assert sum(p['referencePrice'] is None for p in products)==2
assert len(re.findall('class="product-card"',(d/'index.html').read_text(encoding='utf-8')))==18
assert len(list((d/'products').glob('*.html')))==18
class Check(HTMLParser):
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  for key in ['href','src']:
   v=a.get(key,'');u=urlparse(v)
   if not v or u.scheme or v.startswith('#'):continue
   target=(self.path.parent/unquote(u.path)).resolve()
   assert target.is_file(),(self.path,v)
for f in d.rglob('*.html'):
 s=f.read_text(encoding='utf-8');c=Check();c.path=f;c.feed(s)
 for raw in re.findall(r'<script type="application/ld\+json">(.*?)</script>',s,re.S):json.loads(raw)
for p in products:
 assert p['sourceUrl'].endswith('/'+p['asin'])
 if p['image']:assert (d/p['image']).is_file()
assert 'assets/products.png' not in (d/'app.js').read_text(encoding='utf-8')
print('PASS: 21 source records; 18 displayed products and generated assets; 3 skipped; local links and structured JSON valid.')
import xml.etree.ElementTree as ET
origin=json.loads((r/'site.config.json').read_text(encoding='utf-8'))['origin'].rstrip('/')
urls={node.text for node in ET.parse(d/'sitemap.xml').iter('{http://www.sitemaps.org/schemas/sitemap/0.9}loc')}
titles=[]
for f in d.rglob('*.html'):
 s=f.read_text(encoding='utf-8');rel=f.relative_to(d).as_posix()
 canonical=origin+'/' + ('' if rel=='index.html' else rel)
 assert f'<link rel="canonical" href="{canonical}">' in s,rel
 assert canonical in urls,rel
 title=re.search(r'<title>(.*?)</title>',s);assert title,rel
 titles.append(title.group(1))
 assert re.search(r'<meta name="description" content="[^"]+">',s),rel
assert len(titles)==len(set(titles))==len(urls)==25
for p in products:
 if p['image']:assert 'class="related-guide"' in (d/'products'/f'{p["id"]}.html').read_text(encoding='utf-8')
assert (d/'guides.html').read_text(encoding='utf-8').count('class="guide-card"')==4
print('PASS: 25 unique titled, described, canonical pages in sitemap; 18 product-guide links and four guide cards.')
