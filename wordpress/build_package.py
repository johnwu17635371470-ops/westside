"""Build the independent WordPress migration bundle from reviewed public sources."""
import hashlib,json,re,shutil,zipfile
from pathlib import Path
try:
    from PIL import Image,features
except ImportError as exc:
    raise SystemExit('WordPress packaging requires Pillow with WebP support; no image encoder is installed.') from exc
if not features.check('webp'):
    raise SystemExit('WordPress packaging requires a Pillow build with WebP encoder support.')

BASE=Path(__file__).resolve().parent
ROOT=BASE.parent
DIST=ROOT/'dist'
THEME=BASE/'fubao-noir'
PLUGIN=BASE/'fubao-catalog'
WEBP_QUALITY=88
EDITORIAL=('hero.png','lookbook.png','lookbook-color.png')

def clear_generated(folder):
    """Only reset the two known generated asset directories, never source dist/."""
    permitted=(THEME/'assets/site',PLUGIN/'images')
    if folder not in permitted or folder.is_symlink() or (hasattr(folder,'is_junction') and folder.is_junction()):
        raise ValueError('Refusing to clear an unrecognized or linked asset directory: '+str(folder))
    resolved=folder.resolve()
    if BASE.resolve() not in resolved.parents or DIST.resolve() in (resolved,*resolved.parents):
        raise ValueError('Generated asset directory escaped wordpress/: '+str(resolved))
    if folder.exists():shutil.rmtree(folder)
    folder.mkdir(parents=True,exist_ok=True)

def webp_image(source,target):
    """Encode a derivative without resizing, cropping or regenerating the source."""
    source_hash=hashlib.sha256(source.read_bytes()).hexdigest()
    target.parent.mkdir(parents=True,exist_ok=True)
    with Image.open(source) as image:
        size=image.size
        image.save(target,format='WEBP',quality=WEBP_QUALITY,method=6)
    with Image.open(target) as derivative:
        assert derivative.size==size,(source,target,size,derivative.size)
    assert hashlib.sha256(source.read_bytes()).hexdigest()==source_hash
    return {'source':source.relative_to(DIST).as_posix(),'sourceSha256':source_hash,'derivative':target.name,'derivativeSha256':hashlib.sha256(target.read_bytes()).hexdigest(),'width':size[0],'height':size[1],'format':'WebP','quality':WEBP_QUALITY}

def rewrite_image_paths(text):
    return re.sub(r'(assets/(?:catalog/)?[A-Za-z0-9_-]+)\.png',r'\1.webp',text)

def stylesheet(text):
    # The former eight-item sprite is unused by the WooCommerce product cards.
    text=re.sub(r'background-image\s*:\s*url\([\"\']?assets/products\.png[\"\']?\)\s*;?', '',text)
    return rewrite_image_paths(text)

def main_content(text):
    match=re.search(r'<main\b[^>]*>.*?</main>',text,re.S)
    if not match:raise ValueError('Missing main content')
    return match.group()

def load_html(name):return (DIST/name).read_text(encoding='utf-8')

def build():
    (THEME/'templates').mkdir(parents=True,exist_ok=True)
    (PLUGIN/'data').mkdir(parents=True,exist_ok=True)
    assets=THEME/'assets/site'
    clear_generated(assets)
    clear_generated(PLUGIN/'images')
    for name in ('styles.css','luxury.css'):
        (assets/name).write_text(stylesheet((DIST/name).read_text(encoding='utf-8')),encoding='utf-8')
    # Only presentation imagery and its font are needed in the theme. Product
    # derivatives are imported into Media Library from the companion plugin.
    derivatives=[]
    for name in EDITORIAL:
        derivatives.append(webp_image(DIST/'assets'/name,assets/'assets'/Path(name).with_suffix('.webp')))
    for name in ('noir-gothic.ttf','noir-gothic-OFL.txt'):
        shutil.copy2(DIST/'assets'/name,assets/'assets'/name)
    pages=[];css=[]
    for name in ('index','sources','guides','ring-guide','necklace-guide','bracelet-guide','earring-guide'):
        text=load_html(name+'.html')
        content=rewrite_image_paths(main_content(text))
        if name=='index':
            content,count=re.subn(r'<div id="products" class="product-grid">.*?</div>(?=\s*<p class="demo-note">)','[fubao_product_grid]',content,flags=re.S)
            assert count==1,'Homepage product-grid boundary changed; review the shortcode replacement.'
        schema=[json.loads(rewrite_image_paths(s)) for s in re.findall(r'<script type="application/ld\+json">(.*?)</script>',text,re.S)]
        title=re.search(r'<title>(.*?)</title>',text,re.S).group(1)
        description=re.search(r'<meta name="description" content="([^"]*)"',text).group(1)
        pages.append({'slug':'home' if name=='index' else name,'path':'/' if name=='index' else '/'+name+'.html','title':'Home' if name=='index' else title.split(' | ')[0],'seoTitle':title,'description':description,'content':content,'schema':schema})
        for style in re.findall(r'<style[^>]*>(.*?)</style>',text,re.S):
            style=stylesheet(style)
            if style not in css:css.append(style)
        if name=='index':(THEME/'templates/home.html').write_text(content,encoding='utf-8')
    (assets/'guide.css').write_text('\n'.join(css),encoding='utf-8')
    products=[]
    fields=('id','asin','name','category','finish','quantityLabel','description','referencePrice','priceCurrency','sourceDate','sourceUrl','image','imageNote')
    for p in json.loads((ROOT/'data/catalog.json').read_text(encoding='utf-8')):
        if not p.get('image'):continue
        item={key:p.get(key) for key in fields}
        item['image']=str(Path(item['image']).with_suffix('.webp')).replace('\\','/')
        # Product template renders the actual editable Woo fields; source fact guides remain separate.
        item['content']=''
        item['guide']={'Rings':'ring-guide','Necklaces':'necklace-guide','Bracelets':'bracelet-guide','Arm cuffs':'bracelet-guide','Earrings':'earring-guide'}[p['category']]
        item['path']='/products/'+p['id']+'.html'
        products.append(item)
        derivatives.append(webp_image(DIST/p['image'],PLUGIN/'images'/f'{p["id"]}.webp'))
    data={'formatVersion':1,'brand':'FUBAO JEWELRY','sourceOrigin':'https://fubaojewelry.com','pages':pages,'products':products}
    (PLUGIN/'data/import.json').write_text(json.dumps(data,ensure_ascii=False,indent=2),encoding='utf-8')
    (PLUGIN/'data/image-derivatives.json').write_text(json.dumps(derivatives,indent=2),encoding='utf-8')
    if (BASE/'woo-defaults.json').is_file():
        shutil.copy2(BASE/'woo-defaults.json',PLUGIN/'data/woo-defaults.json')
    (BASE/'url-map.json').write_text(json.dumps([{'type':'page','slug':p['slug'],'path':p['path']} for p in pages]+[{'type':'product','slug':p['id'],'path':p['path']} for p in products],indent=2),encoding='utf-8')
    assert len(pages)==7 and len(products)==18
    print('Prepared 7 editable pages, 18 WooCommerce research products, 21 original-size WebP derivatives and 25 preserved paths.')

def package():
    out=ROOT.parent/'fubao-wordpress-packages'
    out.mkdir(exist_ok=True)
    for folder in (THEME,PLUGIN):
        target=out/(folder.name+'.zip')
        with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED) as archive:
            for file in sorted(folder.rglob('*')):
                if file.is_file():archive.write(file,folder.name+'/'+file.relative_to(folder).as_posix())
        print(f'Package: {target} ({target.stat().st_size:,} bytes)')

if __name__=='__main__':
    build()
    if (PLUGIN/'fubao-catalog.php').is_file():package()
