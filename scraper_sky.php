<?php
/**
 * SkyScraper PHP — Versione ottimizzata per InfinityFree (No curl_multi, No timeout)
 * Carica ed estrae la guida TV da guidatv.org per i canali specificati.
 * Utilizza richieste sequenziali con limite di esecuzione a 22s e aggiornamento incrementale.
 */

class SkyScraper {
    private $base_url = "https://guidatv.org";
    private $start_url = "https://guidatv.org/canali";
    private $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';
    private $desc_cache = [];
    private $cache_file;
    private $target_map;

    public function __construct() {
        $this->cache_file = __DIR__ . '/desc_cache.json';
        $this->loadDescCache();

        $this->target_map = [
            "Digitale Terrestre" => [
                ["u" => "rai-1", "n" => "Rai 1"],
                ["u" => "rai-2", "n" => "Rai 2"],
                ["u" => "rai-3", "n" => "Rai 3"],
                ["u" => "rai-4", "n" => "Rai 4"],
                ["u" => "rai-5", "n" => "Rai 5"],
                ["u" => "rete-4", "n" => "Rete 4"],
                ["u" => "canale-5", "n" => "Canale 5"],
                ["u" => "italia-uno", "n" => "Italia 1"],
                ["u" => "la7", "n" => "La7"],
                ["u" => "tv8", "n" => "TV8"],
                ["u" => "nove", "n" => "Nove"],
                ["u" => "canale-20", "n" => "20 Mediaset"],
                ["u" => "mediaset-27", "n" => "Twentyseven"],
                ["u" => "iris", "n" => "IRIS"],
                ["u" => "rai-movie", "n" => "Rai Movie"],
                ["u" => "rai-Premium", "n" => "Rai Premium"],
                ["u" => "la-5", "n" => "La 5"],
                ["u" => "real-time", "n" => "Real Time"],
                ["u" => "food-network", "n" => "Food Network"],
                ["u" => "focus", "n" => "Focus"],
                ["u" => "giallo", "n" => "Giallo"],
                ["u" => "boing", "n" => "Boing"],
                ["u" => "k2", "n" => "K2"],
                ["u" => "rai-gulp", "n" => "Rai Gulp"],
                ["u" => "frisbee", "n" => "Frisbee"],
                ["u" => "dmax", "n" => "DMAX"],
                ["u" => "rai-sport", "n" => "Rai Sport"],
                ["u" => "sportitalia", "n" => "Sportitalia"],
                ["u" => "home-and-garden-tv", "n" => "HGTV"],
                ["u" => "rsi-la1", "n" => "RSI LA 1"],
                ["u" => "rsi-la2", "n" => "RSI LA 2"]
            ],
            "Sport" => [
                ["u" => "zona-dazn", "n" => "DAZN 1"],
                ["u" => "sky-sport-24", "n" => "Sky Sport 24"],
                ["u" => "sky-sport-uno", "n" => "Sky Sport Uno"],
                ["u" => "sky-sport-calcio", "n" => "Sky Sport Calcio"],
                ["u" => "sky-sport-tennis", "n" => "Sky Sport Tennis"],
                ["u" => "sky-sport-f1", "n" => "Sky Sport F1"],
                ["u" => "sky-sport-legend", "n" => "Sky Sport Legend"],
                ["u" => "sky-sport-motogp", "n" => "Sky Sport MotoGP"],
                ["u" => "sky-sport-basket", "n" => "Sky Sport Basket"],
                ["u" => "sky-sport-arena", "n" => "Sky Sport Arena"],
                ["u" => "sky-sport-max", "n" => "Sky Sport Max"],
                ["u" => "sky-sport-mix", "n" => "Sky Sport Mix"],
                ["u" => "sky-sport-golf", "n" => "Sky Sport Golf"],
                ["u" => "sky-sport-hd-1", "n" => "Sky Sport 251"],
                ["u" => "sky-sport-hd-2", "n" => "Sky Sport 252"],
                ["u" => "sky-sport-hd-3", "n" => "Sky Sport 253"],
                ["u" => "sky-sport-hd-4", "n" => "Sky Sport 254"],
                ["u" => "sky-sport-hd-5", "n" => "Sky Sport 255"],
                ["u" => "sky-sport-hd-6", "n" => "Sky Sport 256"],
                ["u" => "sky-sport-hd-7", "n" => "Sky Sport 257"],
                ["u" => "sky-sport-hd-8", "n" => "Sky Sport 258"],
                ["u" => "sky-sport-hd-9", "n" => "Sky Sport 259"]
            ],
            "Cinema" => [
                ["u" => "sky-cinema-uno", "n" => "Sky Cinema Uno"],
                ["u" => "sky-cinema-uno-+24-hd", "n" => "Sky Cinema Uno +24"],
                ["u" => "sky-cinema-due", "n" => "Sky Cinema Due"],
                ["u" => "sky-cinema-due-+24-hd", "n" => "Sky Cinema Due +24"],
                ["u" => "sky-cinema-collection", "n" => "Sky Cinema Collection"],
                ["u" => "sky-cinema-stories", "n" => "Sky Cinema Stories"],
                ["u" => "sky-cinema-family", "n" => "Sky Cinema Family"],
                ["u" => "sky-cinema-action", "n" => "Sky Cinema Action"],
                ["u" => "sky-cinema-suspense", "n" => "Sky Cinema Suspense"],
                ["u" => "sky-cinema-romance", "n" => "Sky Cinema Romance"],
                ["u" => "sky-cinema-drama", "n" => "Sky Cinema Drama"],
                ["u" => "sky-cinema-comedy", "n" => "Sky Cinema Comedy"]
            ],
            "Intrattenimento" => [
                ["u" => "sky-uno", "n" => "Sky Uno"],
                ["u" => "sky-uno-+1-hd", "n" => "Sky Uno +1"],
                ["u" => "sky-atlantic", "n" => "Sky Atlantic"],
                ["u" => "sky-serie", "n" => "Sky Serie"],
                ["u" => "sky-investigation", "n" => "Sky Investigation"],
                ["u" => "sky-crime", "n" => "Sky Crime"],
                ["u" => "sky-adventure", "n" => "Sky Adventure"],
                ["u" => "mtv", "n" => "MTV"],
                ["u" => "comedy-central", "n" => "Comedy Central"]
            ],
            "Documentari" => [
                ["u" => "sky-arte", "n" => "Sky Arte"],
                ["u" => "sky-documentaries", "n" => "Sky Documentaries"],
                ["u" => "sky-nature", "n" => "Sky Nature"],
                ["u" => "discovery-channel", "n" => "Discovery Channel"],
                ["u" => "national-geographic", "n" => "National Geographic"],
                ["u" => "history-channel", "n" => "History Channel"]
            ],
            "Bambini" => [
                ["u" => "cartoon-network", "n" => "Cartoon Network"],
                ["u" => "deakids", "n" => "Dea Kids"],
                ["u" => "nick-junior", "n" => "Nick Jr"],
                ["u" => "boomerang", "n" => "Boomerang"]
            ],
            "News" => [
                ["u" => "sky-tg24", "n" => "Sky TG 24"],
                ["u" => "sky-tg-24-meteo", "n" => "Sky Meteo 24"],
                ["u" => "rai-news-24", "n" => "Rai News 24"]
            ]
        ];
    }

    private function loadDescCache() {
        if (file_exists($this->cache_file)) {
            $data = json_decode(file_get_contents($this->cache_file), true);
            if (is_array($data)) {
                $yesterday = time() - 86400;
                foreach ($data as $url => $entry) {
                    if (isset($entry['ts']) && $entry['ts'] > $yesterday) {
                        $this->desc_cache[$url] = $entry;
                    }
                }
            }
        }
    }


    private function removeDates($s) {
        $s = preg_replace('/\s*\d{1,2}\/\d{1,2}\/\d{4}/u', '', $s);
        $s = preg_replace('/\s*\d{1,2}\/\d{1,2}\/\d{2}\b/u', '', $s);
        return trim($s);
    }

    private function titlesOverlap($a, $b) {
        $a_n = preg_replace('/\W+/u', '', mb_strtolower($a));
        $b_n = preg_replace('/\W+/u', '', mb_strtolower($b));
        if (!$a_n || !$b_n) {
            return false;
        }
        return (strpos($b_n, $a_n) !== false) || (strpos($a_n, $b_n) !== false);
    }

    private function cleanTitle($title) {
        if (!$title) {
            return "N/A";
        }

        // Regola 1: JSON leak
        if (preg_match('/"(?:prima|durata|genre|category|image|episode_number|series_number)"/ui', $title, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1];
            $chunk = substr($title, 0, $pos);
            $chunk = rtrim($chunk);
            $chunk = rtrim($chunk, '\\"\',');
            $chunk = trim($chunk);
            $title = $chunk ? $chunk : $title;
        }

        // Regola 2a: "A - [Stag. Y] Ep. N - B"
        if (preg_match('/^(.*?)\s*-\s*(?:Stag\.\s*-?\S+\s+)?Ep\.\s*\d+\s*-\s*(.+)$/ui', $title, $m)) {
            $prefix = $this->removeDates(trim($m[1]));
            $suffix = $this->removeDates(trim($m[2]));
            if ($this->titlesOverlap($prefix, $suffix)) {
                $title = (mb_strlen($suffix) >= mb_strlen($prefix)) ? $suffix : $prefix;
            } else {
                $title = ($prefix && $suffix) ? "$prefix - $suffix" : ($prefix ? $prefix : $suffix);
            }
        } else {
            // Regola 2b: "A - Stag. N - B"
            if (preg_match('/^(.*?)\s*-\s*Stag\.\s*-?\S+\s*-\s*(.+)$/ui', $title, $m2)) {
                $prefix = $this->removeDates(trim($m2[1]));
                $suffix = $this->removeDates(trim($m2[2]));
                if ($this->titlesOverlap($prefix, $suffix)) {
                    $title = (mb_strlen($suffix) >= mb_strlen($prefix)) ? $suffix : $prefix;
                } else {
                    $title = ($prefix && $suffix) ? "$prefix - $suffix" : ($prefix ? $prefix : $suffix);
                }
                $title = trim($title);
            } else {
                // Regola 3: Ep. / Stag. in coda
                $title = preg_replace('/\s*-?\s*(?:Stag\.\s*-?\S+\s+)?Ep\.\s*\d+\s*$/ui', '', $title);
                $title = preg_replace('/\s*-?\s*Stag\.\s*-?\S+\s*$/ui', '', $title);
                $title = trim($title);
            }
        }

        // Regola 4: date
        $title = $this->removeDates($title);

        // Regola 5: durata
        $title = preg_replace('/\s+\d+\s+(?:min|ore).*/ui', '', $title);
        $title = trim($title);

        // Regola 6: Live finale
        $title = preg_replace('/\s+Live\s*$/ui', '', $title);
        $title = trim($title);

        // Pulizia finale
        $title = preg_replace('/\s{2,}/u', ' ', $title);
        $title = preg_replace('/\s*-\s*$/u', '', $title);
        $title = trim($title);

        return $title ? $title : "Programma";
    }

    private function cleanDescription($desc) {
        if (empty($desc)) {
            return "";
        }
        $cleaned = trim($desc);
        
        $res = preg_replace('/^(?:\.{2,}|\x{2026})+\s*/u', '', $cleaned);
        if ($res !== null) {
            $cleaned = $res;
        }
        
        $cleaned = str_replace(["\xc2\xa0", "\xa0"], ' ', $cleaned);
        
        $res = preg_replace('/[\x{0080}-\x{009F}\x{FFFD}]/u', '', $cleaned);
        if ($res !== null) {
            $cleaned = $res;
        }
        
        $res = preg_replace('/ +/', ' ', $cleaned);
        if ($res !== null) {
            $cleaned = $res;
        }
        
        return trim($cleaned);
    }

    private function getFullDescription($detail_url) {
        if (!$detail_url) {
            return "";
        }

        if (isset($this->desc_cache[$detail_url])) {
            return $this->desc_cache[$detail_url]['desc'];
        }

        $full_url = (strpos($detail_url, '/') === 0) ? $this->base_url . $detail_url : $detail_url;

        $ch = curl_init($full_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $html) {
            // __NEXT_DATA__
            if (preg_match('/<script\s+[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/s', $html, $matches)) {
                $data = json_decode($matches[1], true);
                $props = $data['props']['pageProps'] ?? [];
                $prog_obj = $props['program'] ?? $props['initialData']['program'] ?? [];
                $desc = $prog_obj['description'] ?? $prog_obj['descrizione'] ?? $prog_obj['plot'] ?? null;
                if ($desc) {
                    $desc_clean = trim($desc);
                    $this->desc_cache[$detail_url] = [
                        'desc' => $desc_clean,
                        'ts' => time()
                    ];
                    return $desc_clean;
                }
            }

            // meta tags
            if (preg_match('/<meta\s+[^>]*property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches) ||
                preg_match('/<meta\s+[^>]*name=["\']description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $desc_clean = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
                $this->desc_cache[$detail_url] = [
                    'desc' => $desc_clean,
                    'ts' => time()
                ];
                return $desc_clean;
            }
        }

        return "";
    }

    public function getMatchedChannels() {
        $ch = curl_init($this->start_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) {
            return [];
        }

        preg_match_all('/href=["\'](\/canali\/[^"\']+)["\']/i', $html, $matches);
        $all_links = array_unique($matches[1] ?? []);

        $matched = [];
        foreach ($this->target_map as $cat => $ch_list) {
            foreach ($ch_list as $target) {
                $target_id = str_replace('-', '', strtolower($target['u']));
                $found = false;

                // 1. Cerca match esatto
                foreach ($all_links as $href) {
                    $parts = explode('/', $href);
                    $slug = str_replace('-', '', strtolower(end($parts)));
                    if ($slug === $target_id) {
                        $matched[] = [
                            'nome' => $target['n'],
                            'url' => $this->base_url . $href,
                            'categoria' => $cat
                        ];
                        $found = true;
                        break;
                    }
                }

                // 2. Cerca match parziale
                if (!$found) {
                    foreach ($all_links as $href) {
                        $parts = explode('/', $href);
                        $slug = str_replace('-', '', strtolower(end($parts)));

                        if (strpos($slug, $target_id) !== false || strpos($target_id, $slug) !== false) {
                            $matched[] = [
                                'nome' => $target['n'],
                                'url' => $this->base_url . $href,
                                'categoria' => $cat
                            ];
                            $found = true;
                            break;
                        }
                    }
                }

                if (!$found && $target['u'] === "sky-adventure") {
                    foreach ($all_links as $href) {
                        if (strpos(strtolower($href), 'adventure') !== false) {
                            $matched[] = [
                                'nome' => $target['n'],
                                'url' => $this->base_url . $href,
                                'categoria' => $cat
                            ];
                            break;
                        }
                    }
                }
            }
        }

        return $matched;
    }

    private function extractPrograms($html) {
        $rome_tz = new DateTimeZone("Europe/Rome");
        $now = new DateTime("now", $rome_tz);
        $today_rome_str = $now->format("Y-m-d");

        preg_match_all('/<script\b[^>]*>(.*?)<\/script>/s', $html, $script_matches);
        $all_text = "";
        if (!empty($script_matches[1])) {
            foreach ($script_matches[1] as $script_content) {
                if (strpos($script_content, 'self.__next_f.push') !== false) {
                    $all_text .= $script_content;
                }
            }
        }

        if ($all_text) {
            preg_match_all('/self\.__next_f\.push\(\[(\d+),\s*"(.*?)"\]\)/s', $all_text, $chunk_matches);
            $chunks = $chunk_matches[2] ?? []; // gruppo 2 = contenuto stringa (gruppo 1 = indice numerico)

            $prog_chunk = null;
            foreach ($chunks as $raw_val) {
                if (strpos($raw_val, 'prog') !== false && strpos($raw_val, 'inizio') !== false) {
                    $prog_chunk = $raw_val;
                    break;
                }
            }

            if ($prog_chunk !== null) {
                $decoded = json_decode('"' . $prog_chunk . '"');
                if ($decoded === null) {
                    $decoded = str_replace(['\\\\"', '\\\\\\\\'], ['"', '\\'], $prog_chunk);
                }

                $idx = strpos($decoded, '"prog":[');
                if ($idx !== false) {
                    $start = $idx + 7;
                    $depth = 0;
                    $end = $start;
                    $len = strlen($decoded);
                    for ($i = $start; $i < $len; $i++) {
                        $c = $decoded[$i];
                        if ($c === '[') {
                            $depth++;
                        } elseif ($c === ']') {
                            $depth--;
                            if ($depth === 0) {
                                $end = $i + 1;
                                break;
                            }
                        }
                    }
                    $prog_json_str = substr($decoded, $start, $end - $start);

                    $prog_list = json_decode($prog_json_str, true);
                    if (is_array($prog_list)) {
                        $raw_programs = [];
                        foreach ($prog_list as $p) {
                            if (!isset($p['inizio']) || !isset($p['fine'])) {
                                continue;
                            }

                            try {
                                $inizio_dt = new DateTime($p['inizio']);
                                $fine_dt = new DateTime($p['fine']);

                                $raw_programs[] = [
                                    'inizio_dt' => $inizio_dt,
                                    'fine_dt' => $fine_dt,
                                    'title' => $p['title'] ?? '',
                                    'desc' => $p['description'] ?? ''
                                ];
                            } catch (Exception $e) {
                                continue;
                            }
                        }

                        if (!empty($raw_programs)) {
                            // Filtra tutti i programmi di oggi
                            $today_programs = [];
                            foreach ($raw_programs as $p) {
                                $p_inizio = clone $p['inizio_dt'];
                                $p_inizio->setTimezone($rome_tz);
                                if ($p_inizio->format('Y-m-d') === $today_rome_str) {
                                    $today_programs[] = $p;
                                }
                            }

                            if (empty($today_programs)) {
                                $today_programs = $raw_programs;
                            }

                            // Salva TUTTI i programmi del giorno (non solo dal corrente in poi)
                            // Il frontend gestisce autonomamente il contrassegno live/passato/futuro
                            $extracted = [];
                            foreach ($today_programs as $p) {
                                $p_inizio = clone $p['inizio_dt'];
                                $p_inizio->setTimezone($rome_tz);
                                $local_start = $p_inizio->format('H:i');

                                $extracted[] = [
                                    'ora' => $local_start,
                                    'titolo' => $this->cleanTitle($p['title']),
                                    'descrizione' => $this->cleanDescription(trim($p['desc']))
                                ];
                            }

                            if (!empty($extracted)) {
                                return $extracted;
                            }
                        }
                    }
                }
            }
        }

        // Fallback 1: __NEXT_DATA__
        if (preg_match('/<script\s+[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/s', $html, $matches)) {
            try {
                $data = json_decode($matches[1], true);
                $props = $data['props']['pageProps'] ?? [];
                
                // Replichiamo l'operatore 'or' di Python: se initialData/channel/programs è vuoto, usa programs
                $programs_list = [];
                if (!empty($props['initialData']['channel']['programs'])) {
                    $programs_list = $props['initialData']['channel']['programs'];
                } elseif (!empty($props['programs'])) {
                    $programs_list = $props['programs'];
                }

                $extracted = [];
                $count = 0;
                foreach ($programs_list as $p) {
                    $count++;
                    $ora_raw = !empty($p['startTime']) ? $p['startTime'] : (!empty($p['ora']) ? $p['ora'] : "");
                    $ora = "";
                    if (strpos((string)$ora_raw, 'T') !== false) {
                        try {
                            $start_dt = new DateTime((string)$ora_raw);
                            $start_dt->setTimezone($rome_tz);
                            if ($start_dt->format('Y-m-d') !== $today_rome_str) {
                                continue;
                            }
                            $ora = $start_dt->format('H:i');
                        } catch (Exception $e) {
                            $parts = explode('T', (string)$ora_raw);
                            if (isset($parts[1])) {
                                $ora = substr($parts[1], 0, 5);
                            }
                        }
                    } else {
                        $ora = substr((string)$ora_raw, 0, 5);
                    }

                    $titolo_raw = !empty($p['title']) ? $p['title'] : (!empty($p['titolo']) ? $p['titolo'] : "N/A");
                    $desc_raw = trim(!empty($p['description']) ? $p['description'] : (!empty($p['descrizione']) ? $p['descrizione'] : (!empty($p['desc']) ? $p['desc'] : "")));

                    $detail_url = !empty($p['link']) ? $p['link'] : (!empty($p['url']) ? $p['url'] : (!empty($p['href']) ? $p['href'] : null));
                    if (empty($detail_url) && !empty($p['slug'])) {
                        $detail_url = "/programma/" . $p['slug'];
                    }

                    $is_truncated = !$desc_raw || 
                                    (substr($desc_raw, -3) === '...') || 
                                    (mb_substr($desc_raw, -1) === '…');

                    // Scarica la descrizione estesa solo per i primi 2 programmi della giornata (i più rilevanti)
                    if ($is_truncated && $detail_url && $count <= 2) {
                        $full_desc = $this->getFullDescription($detail_url);
                        if ($full_desc) {
                            $desc_raw = $full_desc;
                        }
                    }

                    if ($ora && $titolo_raw) {
                        $extracted[] = [
                            'ora' => $ora,
                            'titolo' => $this->cleanTitle($titolo_raw),
                            'descrizione' => $this->cleanDescription($desc_raw)
                        ];
                    }
                }

                if (!empty($extracted)) {
                    return $extracted;
                }
            } catch (Exception $e) {
                // ignore
            }
        }

        // Fallback 2: regex DOM-like
        preg_match_all('/<(div|li|a)\b[^>]*>(.*?)<\/\1>/si', $html, $matches_tags);
        $extracted = [];
        $count = 0;
        if (!empty($matches_tags[0])) {
            foreach ($matches_tags[0] as $tag_html) {
                $text = trim(html_entity_decode(strip_tags($tag_html), ENT_QUOTES, 'UTF-8'));
                $text = preg_replace('/\s+/', ' ', $text);
                if (strlen($text) > 5 && strpos(substr($text, 0, 6), ':') !== false) {
                    $parts = explode(' ', $text, 2);
                    $ora_raw = trim($parts[0]);
                    $ora = rtrim($ora_raw, '.');

                    if (strlen($ora) === 5 && $ora[2] === ':') {
                        $count++;
                        $titolo_raw = isset($parts[1]) ? trim($parts[1]) : "Programma";
                        $descrizione = "";

                        $detail_url = null;
                        if (preg_match('/href=["\'](.*?)["\']/i', $tag_html, $href_match)) {
                            $detail_url = $href_match[1];
                        }

                        if (strpos($titolo_raw, '|') !== false) {
                            $parts_pipe = explode('|', $titolo_raw, 2);
                            $titolo_raw = trim($parts_pipe[0]);
                            $right_side = trim($parts_pipe[1]);
                            if (!$descrizione) {
                                $cat_desc = explode('.', $right_side, 2);
                                if (count($cat_desc) > 1) {
                                    $descrizione = trim($cat_desc[1]);
                                }
                            }
                        }

                        $is_truncated = !$descrizione || 
                                        (substr($descrizione, -3) === '...') || 
                                        (mb_substr($descrizione, -1) === '…');

                        // Scarica la descrizione estesa solo per i primi 2 programmi (i più rilevanti)
                        if ($is_truncated && $detail_url && $count <= 2) {
                            $full_desc = $this->getFullDescription($detail_url);
                            if ($full_desc) {
                                $descrizione = $full_desc;
                            }
                        }

                        $extracted[] = [
                            'ora' => $ora,
                            'titolo' => $this->cleanTitle($titolo_raw),
                            'descrizione' => $this->cleanDescription($descrizione)
                        ];
                    }
                }
            }
        }

        return $extracted;
    }

    private function saveDescCache() {
        $json_content = json_encode($this->desc_cache, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json_content !== false && !empty($json_content)) {
            file_put_contents($this->cache_file, $json_content);
        }
    }

    public function run() {
        $start_time = microtime(true);
        $max_execution_time = (php_sapi_name() === 'cli') ? 999999 : 28; // Consenti tempo illimitato in CLI (GitHub Actions)

        $channels = $this->getMatchedChannels();
        if (empty($channels)) {
            return false;
        }

        // Carica dati esistenti dal JSON per non sovrascrivere tutto ogni volta
        $existing_data = [];
        $epg_file = __DIR__ . '/guida_tv_sky.json';
        if (file_exists($epg_file)) {
            $json_raw = file_get_contents($epg_file);
            $decoded_existing = json_decode($json_raw, true);
            if (is_array($decoded_existing)) {
                foreach ($decoded_existing as $item) {
                    if (isset($item['canale'])) {
                        $existing_data[$item['canale']] = $item;
                    }
                }
            }
        }

        // Ordina i canali in base all'aggiornamento più vecchio o assente
        usort($channels, function($a, $b) use ($existing_data) {
            $has_a = isset($existing_data[$a['nome']]);
            $has_b = isset($existing_data[$b['nome']]);
            if (!$has_a && $has_b) return -1;
            if ($has_a && !$has_b) return 1;
            if (!$has_a && !$has_b) return 0;
            
            $time_a = $existing_data[$a['nome']]['aggiornato'] ?? '00:00';
            $time_b = $existing_data[$b['nome']]['aggiornato'] ?? '00:00';
            return strcmp($time_a, $time_b);
        });

        $updated_channels = [];
        $channels_scraped_count = 0;

        foreach ($channels as $ch_info) {
            // Se abbiamo superato i 22 secondi, fermati
            if ((microtime(true) - $start_time) > $max_execution_time) {
                break;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $ch_info['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);          // RSC pages sono ~100KB, serve più tempo
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $html = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200 && $html) {
                $programs = $this->extractPrograms($html);
                // Salta canali con un solo programma "Programmazione non disponibile" (nessuna guida reale)
                $is_no_data = (count($programs) === 1 &&
                    mb_strtolower(trim($programs[0]['titolo'] ?? '')) === 'programmazione non disponibile');
                if (!empty($programs) && !$is_no_data) {
                    $seen = [];
                    $unique_progs = [];
                    foreach ($programs as $p) {
                        $key = $p['ora'] . '-' . $p['titolo'];
                        if (!isset($seen[$key])) {
                            $unique_progs[] = $p;
                            $seen[$key] = true;
                        }
                    }

                    $channel_name = $ch_info['nome'];
                    $updated_channels[$channel_name] = [
                        "canale" => $channel_name,
                        "categoria" => $ch_info['categoria'],
                        "programmi" => array_slice($unique_progs, 0, 30),
                        "aggiornato" => date("H:i")
                    ];
                    $channels_scraped_count++;
                }
            }
        }

        // Se non abbiamo scaricato nulla, non aggiorniamo il file
        if ($channels_scraped_count === 0) {
            return false;
        }

        // Unisce i vecchi canali non aggiornati con quelli nuovi
        $final_data_map = $existing_data;
        foreach ($updated_channels as $name => $data) {
            $final_data_map[$name] = $data;
        }

        $final_data = array_values($final_data_map);

        // Scrive il JSON con controllo di sicurezza UTF-8
        $json_content = json_encode($final_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json_content !== false && !empty($json_content)) {
            file_put_contents($epg_file, $json_content);
        } else {
            return false;
        }
        
        $this->saveDescCache();
        return true;
    }
}
