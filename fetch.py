"""Recherche Unsplash (photos libres, hors Unsplash+) et vignettes pour choix ; ou téléchargement des photos retenues."""
import json, os, urllib.request, urllib.parse
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
        print('full', key, pid, len(data))
else:
    queries = json.load(open('queries.json'))
    meta = {}
    for key, qs in queries.items():
        os.makedirs(f'out/{key}', exist_ok=True)
        n = 0
        for q in qs:
            url = 'https://unsplash.com/napi/search/photos?' + urllib.parse.urlencode({'query': q, 'per_page': 20})
            try: data = get(url)
            except Exception as e: print('ERR', q, e); continue
            for r in data.get('results', []):
                pid = r['id']
                if r.get('premium') or r.get('plus') or pid in meta or n >= 24: continue
                meta[pid] = {'key': key, 'q': q, 'alt': r.get('alt_description') or r.get('description') or '',
                             'w': r['width'], 'h': r['height'], 'raw': r['urls']['raw'],
                             'by': r['user']['name'], 'page': r['links']['html']}
                try:
                    open(f'out/{key}/{pid}.jpg', 'wb').write(get(r['urls']['raw'] + '&w=420&q=70&fm=jpg', binary=True))
                    n += 1
                except Exception as e: print('ERR img', pid, e)
        print(key, n)
    json.dump(meta, open('out/meta.json', 'w'), ensure_ascii=False, indent=1)
