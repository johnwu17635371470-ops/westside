"""Build the independent WordPress migration bundle from reviewed public sources."""
import json,re,shutil,zipfile
from pathlib import Path

BASE=Path(__file__).resolve().parent
ROOT=BASE.parent
DIST=ROOT/'dist'
THEME=BASE/'fubao-noir'
PLUGIN=BASE/'fubao-catalog'

def main_content(text):
    match=re.search(r'<main\b[^>]*>.*?</main>',text,re.S)
    if not match:raise ValueError('Missing main content')
    return match.group()

def load_html(name):return (DIST/name).read_text(encoding='utf-8')

def build():
    (THEME/'templates').mkdir(parents=True,exist_ok=True)
    (PLUGIN/'data').mkdir(parents=True,exist_ok=True)
    (PLUGIN/'images').mkdir(exist_ok=True)
    assets=THEME/'assets/site'
    assets.mkdir(parents=True,exist_ok=True)
    for name in ('styles.css','luxury.css'):
        shutil.copy2(DIST/name,assets/name)
    # Use self-contained copies, including the locally saved original editorial imagery.
    shutil.copytree(DIST/'assets',assets/'assets',dirs_exist_ok=True)
    pages=[];css=[]
    for name in ('index','sources','guides','ring-guide','necklace-guide','bracelet-guide','earring-guide'):
        text=load_html(name+'.html')
        content=main_content(text)
        schema=[json.loads(s) for s in re.findall(r'<script type="application/ld\+json">(.*?)</script>',text,re.S)]
        title=re.search(r'<title>(.*?)</title>',text,re.S).group(1)
        description=re.search(r'<meta name="description" content="([^"]*)"',text).group(1)
        pages.append({'slug':'home' if name=='index' else name,'path':'/' if name=='index' else '/'+name+'.html','title':'Home' if name=='index' else title.split(' | ')[0],'seoTitle':title,'description':description,'content':content,'schema':schema})
        for style in re.findall(r'<style[^>]*>(.*?)</style>',text,re.S):
            if style not in css:css.append(style)
        if name=='index':(THEME/'templates/home.html').write_text(content,encoding='utf-8')
    (assets/'guide.css').write_text('\n'.join(css),encoding='utf-8')
    products=[]
    fields=('id','asin','name','category','finish','quantityLabel','description','referencePrice','priceCurrency','sourceDate','sourceUrl','image','imageNote')
    for p in json.loads((ROOT/'data/catalog.json').read_text(encoding='utf-8')):
        if not p.get('image'):continue
        item={key:p.get(key) for key in fields}
        # Product template renders the actual editable Woo fields; source fact guides remain separate.
        item['content']=''
        item['guide']={'Rings':'ring-guide','Necklaces':'necklace-guide','Bracelets':'bracelet-guide','Arm cuffs':'bracelet-guide','Earrings':'earring-guide'}[p['category']]
        item['path']='/products/'+p['id']+'.html'
        products.append(item)
        shutil.copy2(DIST/p['image'],PLUGIN/'images'/f'{p["id"]}.png')
    data={'formatVersion':1,'brand':'FUBAO JEWELRY','sourceOrigin':'https://fubaojewelry.com','pages':pages,'products':products}
    (PLUGIN/'data/import.json').write_text(json.dumps(data,ensure_ascii=False,indent=2),encoding='utf-8')
    if (BASE/'woo-defaults.json').is_file():
        shutil.copy2(BASE/'woo-defaults.json',PLUGIN/'data/woo-defaults.json')
    (BASE/'url-map.json').write_text(json.dumps([{'type':'page','slug':p['slug'],'path':p['path']} for p in pages]+[{'type':'product','slug':p['id'],'path':p['path']} for p in products],indent=2),encoding='utf-8')
    assert len(pages)==7 and len(products)==18
    print('Prepared 7 editable pages, 18 WooCommerce research products, bundled images and 25 preserved paths.')

def package():
    out=ROOT.parent/'fubao-wordpress-packages'
    out.mkdir(exist_ok=True)
    for folder in (THEME,PLUGIN):
        target=out/(folder.name+'.zip')
        with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED) as archive:
            for file in folder.rglob('*'):
                if file.is_file():archive.write(file,folder.name+'/'+file.relative_to(folder).as_posix())
        print('Package: '+str(target))

if __name__=='__main__':
    build()
    if (PLUGIN/'fubao-catalog.php').is_file():package()
