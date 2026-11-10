"""Vignettes (ids.json) puis photos retenues (pick.json), depuis le CDN d'Unsplash (photos gratuites, licence Unsplash)."""
import json, os, urllib.request
H = {'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/124 Safari/537.36'}
def get(u):
    with urllib.request.urlopen(urllib.request.Request(u, headers=H), timeout=60) as r: return r.read()
os.makedirs('out', exist_ok=True)
log = open('out/log.txt', 'a')
if os.path.exists('pick.json'):
    os.makedirs('out/full', exist_ok=True)
    for key, iid in json.load(open('pick.json')).items():
        try:
            d = get(f'https://images.unsplash.com/{iid}?w=2400&q=88&fm=jpg&fit=max'); open(f'out/full/{key}.jpg', 'wb').write(d)
            print('full', key, iid, len(d), file=log)
        except Exception as e: print('ERR', key, iid, repr(e), file=log)
else:
    for key, ids in json.load(open('ids.json')).items():
        os.makedirs(f'out/c/{key}', exist_ok=True)
        for iid in ids:
            try: open(f'out/c/{key}/{iid}.jpg', 'wb').write(get(f'https://images.unsplash.com/{iid}?w=480&q=70&fm=jpg'))
            except Exception as e: print('ERR', key, iid, repr(e), file=log)
