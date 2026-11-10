"""Recherche Unsplash (photos libres, hors Unsplash+) et vignettes pour choix ; ou téléchargement des photos retenues."""
import json, os, sys, urllib.request, urllib.parse, urllib.error
os.makedirs('out', exist_ok=True)
LOG=open('out/log.txt','w')
def log(*a): print(*a); print(*a, file=LOG, flush=True)
H = {'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',
     'Accept': 'application/json'}

def get(url, binary=False):
    with urllib.request.urlopen(urllib.request.Request(url, headers=H), timeout=60) as r:
        return r.read() if binary else json.load(r)

os.makedirs('out', exist_ok=True)
if os.path.exists('pick.json'):
    meta = json.load(open('out/meta.json'))
    os.makedirs('out/full', exist_ok=True)
    for key, pid in json.load(open('pick.json')).items():
        m = meta[pid]
        sep = '&' if '?' in m['raw'] else '?'
        data = get(m['raw'] + sep + 'w=2000&q=85&fm=jpg&fit=max', binary=True)
        open(f'out/full/{key}__{pid}.jpg', 'wb').write(data)
        try: get(f"https://unsplash.com/napi/photos/{pid}/download")  # compteur de téléchargement Unsplash
        except Exception as e: print('compteur', pid, e)
        log('full', key, pid, len(data))
else:
    queries = json.load(open('queries.json'))
    meta = {}
    for key, qs in queries.items():
        os.makedirs(f'out/{key}', exist_ok=True)
        n = 0
        for q in qs:
            url = 'https://unsplash.com/napi/search/photos?' + urllib.parse.urlencode({'query': q, 'per_page': 20})
            try: data = get(url)
            except urllib.error.HTTPError as e: log('ERR', q, e.code, e.read()[:300]); continue
            except Exception as e: log('ERR', q, repr(e)); continue
            for r in data.get('results', []):
                pid = r['id']
                if r.get('premium') or r.get('plus') or pid in meta or n >= 24: continue
                meta[pid] = {'key': key, 'q': q, 'alt': r.get('alt_description') or r.get('description') or '',
                             'w': r['width'], 'h': r['height'], 'raw': r['urls']['raw'],
                             'by': r['user']['name'], 'page': r['links']['html']}
                try:
                    open(f'out/{key}/{pid}.jpg', 'wb').write(get(r['urls']['raw'] + '&w=420&q=70&fm=jpg', binary=True))
                    n += 1
                except Exception as e: log('ERR img', pid, repr(e))
        if n < 8:
            for q in qs:
                url = 'https://api.openverse.org/v1/images/?' + urllib.parse.urlencode({'q': q, 'license': 'cc0,pdm', 'page_size': 20, 'aspect_ratio': 'wide'})
                try: data = get(url)
                except urllib.error.HTTPError as e: log('ERR ov', q, e.code, e.read()[:200]); continue
                except Exception as e: log('ERR ov', q, repr(e)); continue
                for r in data.get('results', []):
                    pid = 'ov_' + r['id'][:12]
                    if pid in meta or n >= 24: continue
                    meta[pid] = {'key': key, 'q': q, 'alt': r.get('title') or '', 'w': r.get('width'), 'h': r.get('height'),
                                 'raw': r['url'], 'by': r.get('creator') or '', 'page': r.get('foreign_landing_url') or '',
                                 'license': r.get('license'), 'src': r.get('source'), 'thumb': r.get('thumbnail')}
                    try:
                        open(f'out/{key}/{pid}.jpg', 'wb').write(get(r.get('thumbnail') or r['url'], binary=True)); n += 1
                    except Exception as e: log('ERR ovimg', pid, repr(e))
        log(key, n)
    json.dump(meta, open('out/meta.json', 'w'), ensure_ascii=False, indent=1)
