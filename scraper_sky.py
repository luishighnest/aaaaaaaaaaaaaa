import requests
from bs4 import BeautifulSoup
import json
from datetime import datetime
import time
from concurrent.futures import ThreadPoolExecutor
import logging
import re
import threading

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

class SkyScraper:
    def __init__(self):
        self.base_url = "https://guidatv.org"
        self.start_url = "https://guidatv.org/canali"
        self.headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        }
        self.session = requests.Session()
        self.session.headers.update(self.headers)
        self.cache_lock = threading.Lock()
        self.desc_cache = {} 
        # Pre-compiled patterns for title cleaning
        self._pat_date_long  = re.compile(r'\s*\d{1,2}/\d{1,2}/\d{4}')
        self._pat_date_short = re.compile(r'\s*\d{1,2}/\d{1,2}/\d{2}\b')
        self._pat_ep_full    = re.compile(
            r'^(.*?)\s*-\s*(?:Stag\.\s*-?\S+\s+)?Ep\.\s*\d+\s*-\s*(.+)$',
            re.IGNORECASE
        )
        self._pat_stag_full  = re.compile(
            r'^(.*?)\s*-\s*Stag\.\s*-?\S+\s*-\s*(.+)$',
            re.IGNORECASE
        )
        self._pat_ep_trail   = re.compile(r'\s*-?\s*(?:Stag\.\s*-?\S+\s+)?Ep\.\s*\d+\s*$', re.IGNORECASE)
        self._pat_stag_trail = re.compile(r'\s*-?\s*Stag\.\s*-?\S+\s*$', re.IGNORECASE)
        self._pat_json_leak  = re.compile(r'"(?:prima|durata|genre|category|image|episode_number|series_number)"')
        self._pat_live       = re.compile(r'\s+Live\s*$', re.IGNORECASE)
        self._pat_duration   = re.compile(r'\s+\d+\s+(?:min|ore).*', re.IGNORECASE)

        self.target_map = {
            "Digitale Terrestre": [
                {"u": "rai-1", "n": "Rai 1"},
                {"u": "rai-2", "n": "Rai 2"},
                {"u": "rai-3", "n": "Rai 3"},
                {"u": "rai-4", "n": "Rai 4"},
                {"u": "rai-5", "n": "Rai 5"},
                {"u": "rete-4", "n": "Rete 4"},
                {"u": "canale-5", "n": "Canale 5"},
                {"u": "italia-uno", "n": "Italia 1"},
                {"u": "la7", "n": "La7"},
                {"u": "tv8", "n": "TV8"},
                {"u": "nove", "n": "Nove"},
                {"u": "canale-20", "n": "20 Mediaset"},
                {"u": "mediaset-27", "n": "Twentyseven"},
                {"u": "iris", "n": "IRIS"},
                {"u": "rai-movie", "n": "Rai Movie"},
                {"u": "rai-Premium", "n": "Rai Premium"},
                {"u": "la-5", "n": "La 5"},
                {"u": "real-time", "n": "Real Time"},
                {"u": "food-network", "n": "Food Network"},
                {"u": "focus", "n": "Focus"},
                {"u": "giallo", "n": "Giallo"},
                {"u": "boing", "n": "Boing"},
                {"u": "k2", "n": "K2"},
                {"u": "rai-gulp", "n": "Rai Gulp"},
                {"u": "frisbee", "n": "Frisbee"},
                {"u": "dmax", "n": "DMAX"},
                {"u": "rai-sport", "n": "Rai Sport"},
                {"u": "sportitalia", "n": "Sportitalia"},
                {"u": "home-and-garden-tv", "n": "HGTV"},
                {"u": "rsi-la1", "n": "RSI LA 1"},
                {"u": "rsi-la2", "n": "RSI LA 2"}
            ],
            "Sport": [
                {"u": "zona-dazn", "n": "DAZN 1"},
                {"u": "sky-sport-24", "n": "Sky Sport 24"},
                {"u": "sky-sport-uno", "n": "Sky Sport Uno"},
                {"u": "sky-sport-calcio", "n": "Sky Sport Calcio"},
                {"u": "sky-sport-tennis", "n": "Sky Sport Tennis"},
                {"u": "sky-sport-f1", "n": "Sky Sport F1"},
                {"u": "sky-sport-legend", "n": "Sky Sport Legend"},
                {"u": "sky-sport-motogp", "n": "Sky Sport MotoGP"},
                {"u": "sky-sport-basket", "n": "Sky Sport Basket"},
                {"u": "sky-sport-arena", "n": "Sky Sport Arena"},
                {"u": "sky-sport-max", "n": "Sky Sport Max"},
                {"u": "sky-sport-mix", "n": "Sky Sport Mix"},
                {"u": "sky-sport-golf", "n": "Sky Sport Golf"},
                {"u": "sky-sport-hd-1", "n": "Sky Sport 251"},
                {"u": "sky-sport-hd-2", "n": "Sky Sport 252"},
                {"u": "sky-sport-hd-3", "n": "Sky Sport 253"},
                {"u": "sky-sport-hd-4", "n": "Sky Sport 254"},
                {"u": "sky-sport-hd-5", "n": "Sky Sport 255"},
                {"u": "sky-sport-hd-6", "n": "Sky Sport 256"},
                {"u": "sky-sport-hd-7", "n": "Sky Sport 257"},
                {"u": "sky-sport-hd-8", "n": "Sky Sport 258"},
                {"u": "sky-sport-hd-9", "n": "Sky Sport 259"}
            ],
            "Cinema": [
                {"u": "sky-cinema-uno", "n": "Sky Cinema Uno"},
                {"u": "sky-cinema-uno-+24-hd", "n": "Sky Cinema Uno +24"},
                {"u": "sky-cinema-due", "n": "Sky Cinema Due"},
                {"u": "sky-cinema-due-+24-hd", "n": "Sky Cinema Due +24"},
                {"u": "sky-cinema-collection", "n": "Sky Cinema Collection"},
                {"u": "sky-cinema-stories", "n": "Sky Cinema Stories"},
                {"u": "sky-cinema-family", "n": "Sky Cinema Family"},
                {"u": "sky-cinema-action", "n": "Sky Cinema Action"},
                {"u": "sky-cinema-suspense", "n": "Sky Cinema Suspense"},
                {"u": "sky-cinema-romance", "n": "Sky Cinema Romance"},
                {"u": "sky-cinema-drama", "n": "Sky Cinema Drama"},
                {"u": "sky-cinema-comedy", "n": "Sky Cinema Comedy"}
            ],
            "Intrattenimento": [
                {"u": "sky-uno", "n": "Sky Uno"},
                {"u": "sky-uno-+1-hd", "n": "Sky Uno +1"},
                {"u": "sky-atlantic", "n": "Sky Atlantic"},
                {"u": "sky-serie", "n": "Sky Serie"},
                {"u": "sky-investigation", "n": "Sky Investigation"},
                {"u": "sky-crime", "n": "Sky Crime"},
                {"u": "sky-adventure", "n": "Sky Adventure"},
                {"u": "mtv", "n": "MTV"},
                {"u": "comedy-central", "n": "Comedy Central"}
            ],
            "Documentari": [
                {"u": "sky-arte", "n": "Sky Arte"},
                {"u": "sky-documentaries", "n": "Sky Documentaries"},
                {"u": "sky-nature", "n": "Sky Nature"},
                {"u": "discovery-channel", "n": "Discovery Channel"},
                {"u": "national-geographic", "n": "National Geographic"},
                {"u": "history-channel", "n": "History Channel"}
            ],
            "Bambini": [
                {"u": "cartoon-network", "n": "Cartoon Network"},
                {"u": "deakids", "n": "Dea Kids"},
                {"u": "nick-junior", "n": "Nick Jr"},
                {"u": "boomerang", "n": "Boomerang"}
            ],
            "News": [
                {"u": "sky-tg24", "n": "Sky TG 24"},
                {"u": "sky-meteo-24", "n": "Sky Meteo 24"},
                {"u": "rai-news-24", "n": "Rai News 24"}
            ]
        }

    def _remove_dates(self, s):
        """Rimuove date nel formato dd/mm/yyyy o dd/mm/yy."""
        s = self._pat_date_long.sub('', s)
        s = self._pat_date_short.sub('', s)
        return s.strip()

    def _titles_overlap(self, a, b):
        """Restituisce True se i due titoli si sovrappongono (uno contiene l'altro)."""
        a_n = re.sub(r'\W+', '', a.lower())
        b_n = re.sub(r'\W+', '', b.lower())
        if not a_n or not b_n:
            return False
        return a_n in b_n or b_n in a_n

    def _clean_title(self, title):
        """
        Pulizia generalizzata del titolo. Regole applicate in ordine:
        1. JSON leak  → tronca al primo campo JSON trapelato
        2. "A - [Stag. Y] Ep. N - B"
           a. Se A e B si sovrappongono (titolo ripetuto) → mantieni la parte più ricca
           b. Altrimenti → "A - B" (elimina il marcatore episodio)
        3. Ep. / Stag. in coda
        4. Date dd/mm/yyyy o dd/mm/yy
        5. Durata ("45 min", "2 ore...")
        6. Tag " Live" finale
        """
        if not title:
            return "N/A"

        # Regola 1: JSON leak
        m_leak = self._pat_json_leak.search(title)
        if m_leak:
            chunk = title[:m_leak.start()].rstrip().rstrip('\\"\',').strip()
            title = chunk if chunk else title

        # Regola 2a: "A - [Stag. Y] Ep. N - B"
        m = self._pat_ep_full.match(title)
        if m:
            prefix = self._remove_dates(m.group(1).strip())
            suffix = self._remove_dates(m.group(2).strip())
            if self._titles_overlap(prefix, suffix):
                title = suffix if len(suffix) >= len(prefix) else prefix
            else:
                title = f"{prefix} - {suffix}" if prefix and suffix else (prefix or suffix)
        else:
            # Regola 2b: "A - Stag. N - B" (stagione senza episodio)
            m2 = self._pat_stag_full.match(title)
            if m2:
                prefix = self._remove_dates(m2.group(1).strip())
                suffix = self._remove_dates(m2.group(2).strip())
                if self._titles_overlap(prefix, suffix):
                    title = suffix if len(suffix) >= len(prefix) else prefix
                else:
                    title = f"{prefix} - {suffix}" if prefix and suffix else (prefix or suffix)
                title = title.strip()
            else:
                # Regola 3: Ep. / Stag. in coda
                title = self._pat_ep_trail.sub('', title).strip()
                title = self._pat_stag_trail.sub('', title).strip()

        # Regola 4: date
        title = self._remove_dates(title)

        # Regola 5: durata
        title = self._pat_duration.sub('', title).strip()

        # Regola 6: Live finale
        title = self._pat_live.sub('', title).strip()

        # Pulizia finale
        title = re.sub(r'\s{2,}', ' ', title)
        title = re.sub(r'\s*-\s*$', '', title).strip()

        return title if title else "Programma"

    def _clean_description(self, desc):
        """Rimuove i puntini di sospensione ('...' o '\u2026') ad inizio descrizione
        e ripulisce artefatti di encoding errato (spazi non-breaking, caratteri spuri)."""
        if not desc:
            return desc
        cleaned = desc.strip()
        cleaned = re.sub(r'^(?:\.{2,}|\u2026)+\s*', '', cleaned)
        cleaned = cleaned.replace('\xa0', ' ')
        cleaned = re.sub(r'[\x80-\x9f\ufffd]', '', cleaned)
        cleaned = re.sub(r' {2,}', ' ', cleaned)
        return cleaned.strip()

    def get_matched_channels(self):
        """Trova gli URL reali sul sito guidatv.org basandosi sulla mappa target."""
        try:
            res = self.session.get(self.start_url, timeout=15)
            res.encoding = 'utf-8'
            soup = BeautifulSoup(res.text, 'html.parser')
            
            all_links = [l for l in soup.find_all('a', href=True) if l['href'].startswith('/canali/')]
            
            matched = []
            for cat, ch_list in self.target_map.items():
                for target in ch_list:
                    target_id = target['u'].replace('-', '').lower()
                    found = False
                    
                    for link in all_links:
                        href = link['href']
                        slug = href.split('/')[-1].replace('-', '').lower()
                        
                        if target_id in slug or slug in target_id:
                            matched.append({
                                "nome": target['n'],
                                "url": self.base_url + href,
                                "categoria": cat
                            })
                            found = True
                            break
                    
                    if not found and target['u'] == "sky-adventure":
                        for link in all_links:
                            if "adventure" in link['href'].lower():
                                matched.append({
                                    "nome": target['n'],
                                    "url": self.base_url + link['href'],
                                    "categoria": cat
                                })
                                break
            return matched
        except Exception as e:
            logger.error(f"Errore nel recupero canali: {e}")
            return []

    def _get_full_description(self, detail_url):
        """Scarica e restituisce la descrizione completa da una pagina di dettaglio (con cache)."""
        if not detail_url:
            return ""
            
        with self.cache_lock:
            if detail_url in self.desc_cache:
                return self.desc_cache[detail_url]

        full_url = self.base_url + detail_url if detail_url.startswith('/') else detail_url
        try:
            res = self.session.get(full_url, timeout=5)
            if res.status_code == 200:
                res.encoding = 'utf-8'  
                soup = BeautifulSoup(res.text, 'html.parser')
                
                script = soup.find('script', id='__NEXT_DATA__')
                if script:
                    try:
                        data = json.loads(script.string)
                        props = data.get('props', {}).get('pageProps', {})

                        prog_obj = props.get('program') or props.get('initialData', {}).get('program', {})
                        desc = prog_obj.get('description') or prog_obj.get('descrizione') or prog_obj.get('plot')
                        if desc:
                            desc_clean = desc.strip()
                            with self.cache_lock:
                                self.desc_cache[detail_url] = desc_clean
                            return desc_clean
                    except Exception:
                        pass
                
                meta_desc = soup.find('meta', property='og:description') or soup.find('meta', name='description')
                if meta_desc and meta_desc.get('content'):
                    desc_clean = meta_desc['content'].strip()
                    with self.cache_lock:
                        self.desc_cache[detail_url] = desc_clean
                    return desc_clean
        except Exception as e:
            logger.debug(f"Impossibile scaricare descrizione completa per {detail_url}: {e}")
            
        return ""

    def _extract_programs(self, soup):
        """Estrae i dati dei programmi preferendo il flusso Next.js App Router (RSC)."""
        from datetime import timezone
        from zoneinfo import ZoneInfo
        
        try:
            scripts = soup.find_all('script')
            all_text = ""
            for s in scripts:
                if s.string and 'self.__next_f.push' in s.string:
                    all_text += s.string
            
            if all_text:

                raw_strings = []
                for m in re.finditer(r'self\.__next_f\.push\(\[\d+,\s*"(.*?)"\]\)', all_text, re.DOTALL):
                    s_val = m.group(1)
                    s_val = s_val.replace('\\\\', '\\').replace('\\n', '\n').replace('\\/', '/')
                    raw_strings.append(s_val)
                
                combined_text = "".join(raw_strings)
                
                program_pattern = re.compile(
                    r'\\*"\s*id\s*\\*"\s*:\s*\\*"\s*(?P<id>[a-zA-Z0-9_\-]+)\s*\\*"\s*,\s*'
                    r'\\*"\s*title\s*\\*"\s*:\s*\\*"\s*(?P<title>.*?)\s*\\*"\s*,\s*'
                    r'\\*"\s*description\s*\\*"\s*:\s*\\*"\s*(?P<desc>.*?)\s*\\*"\s*,\s*.*?'
                    r'\\*"\s*inizio\s*\\*"\s*:\s*\\*"\s*(?P<inizio>.*?)\s*\\*"\s*,\s*'
                    r'\\*"\s*fine\s*\\*"\s*:\s*\\*"\s*(?P<fine>.*?)\s*\\*"',
                    re.DOTALL
                )
                
                raw_programs = []
                rome_tz = ZoneInfo("Europe/Rome")
                
                for m in program_pattern.finditer(combined_text):
                    title_raw = m.group('title')
                    desc_raw = m.group('desc')
                    inizio_raw = m.group('inizio')
                    fine_raw = m.group('fine')
                    
                    def clean_escapes(s):
                        s = re.sub(r'\\+"', '"', s)
                        s = re.sub(r"\\+'", "'", s)
                        s = re.sub(
                            r'\\u([0-9a-fA-F]{4})',
                            lambda m: chr(int(m.group(1), 16)),
                            s
                        )
                        # Sostituisce le sequenze di escape comuni
                rome_tz = ZoneInfo("Europe/Rome")
                now_utc = datetime.now(timezone.utc)
                today_rome = now_utc.astimezone(rome_tz).date()

                # Estrai tutti i chunk RSC grezzi
                chunks = []
                for m in re.finditer(
                    r'self\.__next_f\.push\(\[\d+,\s*"(.*?)"\]\)',
                    all_text, re.DOTALL
                ):
                    chunks.append(m.group(1))

                # Trova il chunk che contiene i programmi del canale ("prog" + "inizio")
                prog_chunk = None
                for raw_val in chunks:
                    if 'prog' in raw_val and 'inizio' in raw_val:
                        prog_chunk = raw_val
                        break

                if prog_chunk is not None:
                    # Decodifica l'escape del chunk (è una stringa JSON-encoded)
                    try:
                        decoded = json.loads('"' + prog_chunk + '"')
                    except Exception:
                        decoded = prog_chunk.replace('\\\\"', '"').replace('\\\\\\\\', '\\')

                    # Cerca il blocco "prog":[...] con bilanciamento parentesi
                    idx = decoded.find('"prog":[')
                    if idx >= 0:
                        start = decoded.index('[', idx)
                        depth = 0
                        end = start
                        for i, c in enumerate(decoded[start:], start):
                            if c == '[':
                                depth += 1
                            elif c == ']':
                                depth -= 1
                                if depth == 0:
                                    end = i + 1
                                    break
                        prog_json_str = decoded[start:end]
                    else:
                        prog_json_str = None

                    if prog_json_str:
                        try:
                            prog_list = json.loads(prog_json_str)
                        except Exception:
                            prog_list = []

                        raw_programs = []
                        for p in prog_list:
                            try:
                                inizio_dt = datetime.fromisoformat(
                                    p['inizio'].replace('Z', '+00:00')
                                )
                                fine_dt = datetime.fromisoformat(
                                    p['fine'].replace('Z', '+00:00')
                                )
                            except Exception:
                                continue

                            raw_programs.append({
                                "inizio_dt": inizio_dt,
                                "fine_dt": fine_dt,
                                "title": p.get('title', ''),
                                "desc": p.get('description', '')
                            })

                        if raw_programs:
                            # Filtra solo i programmi di oggi (fuso orario Roma)
                            today_programs = [
                                p for p in raw_programs
                                if p['inizio_dt'].astimezone(rome_tz).date() == today_rome
                            ]
                            if not today_programs:
                                today_programs = raw_programs

                            # Trova il programma corrente o il prossimo
                            current_index = -1
                            for i, p in enumerate(today_programs):
                                if p['inizio_dt'] <= now_utc < p['fine_dt']:
                                    current_index = i
                                    break
                            if current_index == -1:
                                for i, p in enumerate(today_programs):
                                    if p['inizio_dt'] >= now_utc:
                                        current_index = i
                                        break
                            if current_index == -1:
                                current_index = 0

                            selected = today_programs[current_index:]
                            extracted = []
                            for p in selected:
                                local_start = p['inizio_dt'].astimezone(rome_tz).strftime("%H:%M")
                                extracted.append({
                                    "ora": local_start,
                                    "titolo": self._clean_title(p['title']),
                                    "descrizione": self._clean_description(p['desc'].strip())
                                })
                            if extracted:
                                return extracted

        except Exception as e:
            logger.debug(f"Errore nel parsing del flusso Next.js RSC: {e}")

        # Fallback: __NEXT_DATA__
        script = soup.find('script', id='__NEXT_DATA__')
        if script:
            try:
                from datetime import timezone
                from zoneinfo import ZoneInfo
                rome_tz = ZoneInfo("Europe/Rome")
                now_utc = datetime.now(timezone.utc)
                today_rome = now_utc.astimezone(rome_tz).date()

                data = json.loads(script.string)
                props = data.get('props', {}).get('pageProps', {})
                programs_list = (props.get('initialData', {}).get('channel', {}).get('programs', []) or
                                 props.get('programs', []) or [])

                extracted = []
                for p in programs_list:
                    ora_raw = p.get('startTime') or p.get('ora') or ""
                    # Filtra per data odierna se l'orario contiene la data ISO
                    if 'T' in str(ora_raw):
                        try:
                            start_dt = datetime.fromisoformat(str(ora_raw).replace('Z', '+00:00'))
                            if start_dt.astimezone(rome_tz).date() != today_rome:
                                continue  # salta programmi di altri giorni
                            ora = start_dt.astimezone(rome_tz).strftime("%H:%M")
                        except Exception:
                            ora = str(ora_raw).split('T')[1][:5]
                    else:
                        ora = str(ora_raw)[:5]

                    titolo_raw = p.get('title') or p.get('titolo') or "N/A"
                    desc_raw = (p.get('description') or p.get('descrizione') or p.get('desc') or "").strip()

                    detail_url = p.get('link') or p.get('url') or p.get('href')
                    if not detail_url and p.get('slug'):
                        detail_url = f"/programma/{p.get('slug')}"

                    if (not desc_raw or desc_raw.endswith('...') or desc_raw.endswith('…')) and detail_url:
                        full_desc = self._get_full_description(detail_url)
                        if full_desc:
                            desc_raw = full_desc

                    if ora and titolo_raw:
                        extracted.append({
                            "ora": ora,
                            "titolo": self._clean_title(titolo_raw),
                            "descrizione": self._clean_description(desc_raw)
                        })
                if extracted:
                    return extracted
            except Exception as e:
                logger.debug(f"Errore nel parsing del JSON __NEXT_DATA__: {e}")

        extracted = []
        items = soup.find_all(['div', 'li'], class_=True)
        for item in items:
            text = item.get_text(" ", strip=True)
            if len(text) > 5 and ":" in text[:6]:
                parts = text.split(" ", 1)
                ora_raw = parts[0].strip()
                ora = ora_raw.rstrip('.')
                
                if len(ora) == 5 and ora[2] == ":":
                    raw_title_desc = parts[1].strip() if len(parts) > 1 else "Programma"
                    titolo_raw = raw_title_desc
                    descrizione = ""
                    
                    link_tag = item.find('a', href=True) or (item if item.name == 'a' and item.has_attr('href') else None)
                    detail_url = link_tag['href'] if link_tag else None
                    
                    desc_tag = item.find(lambda tag: tag.name in ['p', 'span', 'div'] and 
                                         any('desc' in str(cls).lower() or 'plot' in str(cls).lower() 
                                             for cls in tag.get('class', [])))
                    if desc_tag:
                        descrizione = desc_tag.get_text(" ", strip=True)
                        titolo_raw = titolo_raw.replace(descrizione, "").strip()
                    
                    if "|" in titolo_raw:
                        left_side, right_side = titolo_raw.split("|", 1)
                        titolo_raw = left_side.strip()
                        if not descrizione:
                            category_and_desc = right_side.split(".", 1)
                            if len(category_and_desc) > 1:
                                descrizione = category_and_desc[1].strip()
                                
                    if (not descrizione or descrizione.endswith('...') or descrizione.endswith('…')) and detail_url:
                        full_desc = self._get_full_description(detail_url)
                        if full_desc:
                            descrizione = full_desc
                    
                    extracted.append({
                        "ora": ora, 
                        "titolo": self._clean_title(titolo_raw),
                        "descrizione": self._clean_description(descrizione.strip())
                    })
        return extracted

    def scrape_channel(self, ch):
        """Scarica e processa un singolo canale."""
        try:
            res = self.session.get(ch['url'], timeout=10)
            res.raise_for_status()
            res.encoding = 'utf-8'
            soup = BeautifulSoup(res.text, 'html.parser')
            programs = self._extract_programs(soup)
            
            seen = set()
            unique_progs = []
            for p in programs:
                key = f"{p['ora']}-{p['titolo']}"
                if key not in seen:
                    unique_progs.append(p)
                    seen.add(key)

            return {
                "canale": ch['nome'],
                "categoria": ch['categoria'],
                "programmi": unique_progs[:12],
                "aggiornato": datetime.now().strftime("%H:%M")
            }
        except Exception as e:
            logger.debug(f"Errore nello scraping del canale {ch['nome']}: {e}")
            return None

    def run(self):
        start_time = time.time()
        channels = self.get_matched_channels()
        
        if not channels:
            logger.warning("Nessun canale trovato. Verifica la connessione o l'URL.")
            return

        logger.info(f"Trovati {len(channels)} canali da scansionare...")

        with ThreadPoolExecutor(max_workers=10) as executor:
            results = list(executor.map(self.scrape_channel, channels))
            
        final_data = [r for r in results if r is not None]
        
        with open('guida_tv_sky.json', 'w', encoding='utf-8') as f:
            json.dump(final_data, f, ensure_ascii=False, indent=4)
            
        duration = round(time.time() - start_time, 1)
        logger.info(f"Fatto! {len(final_data)} canali salvati in {duration}s. Caching descrizioni attivo.")

if __name__ == "__main__":
    scraper = SkyScraper()
    scraper.run()
