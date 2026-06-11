"""
media_uploader.py
=================
Scansiona i canali Sky Italia su pepperstream.xyz,
poi carica i nuovi URL sul sito via FTP + trigger HTTP.

Dipendenze: pip install selenium requests
"""

from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import time
import os
import subprocess
import re
import json
import ftplib
import requests

# ============================================================
# CONFIGURAZIONE SCANSIONE
ATTESA_STREAM = 5
OUTPUT_FILE   = "canali_completi_js.txt"

# CONFIGURAZIONE FTP (InfinityFree)
FTP_HOST  = "ftpupload.net"
FTP_USER  = "if0_42142234"
FTP_PASS  = "Alecssito01"
FTP_PATH  = "/htdocs"

# CONFIGURAZIONE TRIGGER (dopo upload FTP)
SERVER_URL  = "https://pzeo.infy.click/update_channels.php"
SECRET_KEY  = "SKY_UPDATE_2026_SECRET"
AUTO_UPLOAD = True   # False = solo scansione locale
# ============================================================

MAPPATURA_ID_HTML = {
    26: ("Sky TG24",               "Sky Italia"),
    27: ("Sky Uno",                "Sky Italia"),
    28: ("Sky Uno Plus",           "Sky Italia"),
    29: ("Sky Atlantic",           "Sky Italia"),
    30: ("Sky Serie",              "Sky Italia"),
    31: ("Sky Investigation",      "Sky Italia"),
    32: ("Sky Collection",         "Sky Italia"),
    33: ("Sky Documentaries",      "Sky Italia"),
    34: ("Sky Crime",              "Sky Italia"),
    35: ("History",                "Sky Italia"),
    36: ("Sky Nature",             "Sky Italia"),
    37: ("Sky Arte",               "Sky Italia"),
    38: ("Sky Adventure",          "Sky Italia"),
    39: ("MTV",                    "Sky Italia"),
    40: ("Comedy Central",         "Sky Italia"),
    41: ("Sky Cinema Uno",         "Sky Italia"),
    42: ("Sky Cinema Collection",  "Sky Italia"),
    43: ("Sky Cinema Comedy",      "Sky Italia"),
    44: ("Sky Cinema Action",      "Sky Italia"),
    45: ("Sky Cinema Stories",     "Sky Italia"),
    46: ("Sky Cinema Illumination","Sky Italia"),
    47: ("Sky Cinema Drama",       "Sky Italia"),
    48: ("Sky Cinema Romance",     "Sky Italia"),
    49: ("Sky Cinema Suspense",    "Sky Italia"),
    50: ("Sky Sport 24",           "Sky Italia"),
    51: ("Sky Sport Uno",          "Sky Italia"),
    52: ("Sky Sport F1",           "Sky Italia"),
    53: ("Sky Sport Calcio",       "Sky Italia"),
    54: ("Sky Sport Tennis",       "Sky Italia"),
    55: ("Sky Sport MotoGP",       "Sky Italia"),
    56: ("Sky Sport Arena",        "Sky Italia"),
    57: ("Sky Sport Max",          "Sky Italia"),
    58: ("Sky Sport Basket",       "Sky Italia"),
    59: ("Sky Sport Legend",       "Sky Italia"),
    60: ("Sky Sport Mix",          "Sky Italia"),
    61: ("Sky Sport Golf",         "Sky Italia"),
    62: ("Sky Sport 251",          "Sky Italia"),
    63: ("Sky Sport 252",          "Sky Italia"),
    64: ("Sky Sport 253",          "Sky Italia"),
    65: ("Sky Sport 254",          "Sky Italia"),
    66: ("Sky Sport 255",          "Sky Italia"),
    67: ("Sky Sport 256",          "Sky Italia"),
    68: ("Sky Sport 257",          "Sky Italia"),
    69: ("Sky Sport 258",          "Sky Italia"),
    70: ("Sky Sport 259",          "Sky Italia"),
}

# ── Chrome setup ──────────────────────────────────────────────────────────────
PROFILE_DIR  = os.path.abspath("chrome_profile")
SETUP_MARKER = os.path.join(PROFILE_DIR, "setup_done.txt")

options = Options()
options.add_argument("--log-level=3")
options.add_experimental_option('excludeSwitches', ['enable-logging'])
options.add_argument(f"--user-data-dir={PROFILE_DIR}")

if not os.path.exists(SETUP_MARKER):
    print("\n" + "="*80)
    print(" PRIMO AVVIO: installa l'estensione VideoPlayer nel browser che si aprirà.")
    print("="*80 + "\n")
    driver = webdriver.Chrome(options=options)
    driver.get("https://chromewebstore.google.com/detail/videoplayer-mpdm3u8iptvep/opmeopcambhfimffbomjgemehjkbbmji")
    input("Installa l'estensione, poi premi [INVIO] qui...")
    os.makedirs(PROFILE_DIR, exist_ok=True)
    with open(SETUP_MARKER, "w") as f:
        f.write("setup completato")
    print("Setup completato!")
else:
    print("Chiusura di eventuali processi Chrome aperti...")
    subprocess.run(["taskkill", "/F", "/IM", "chrome.exe"], capture_output=True)
    time.sleep(2)
    driver = webdriver.Chrome(options=options)


def estrai_mpd(driver, scheda_media, urls_gia_visti, start_index=0):
    driver.switch_to.window(scheda_media)
    time.sleep(1.5)
    urls_nuovi = []
    try:
        player_items = driver.find_elements(By.CSS_SELECTOR, "#player-list .tree-item")
        nuovo_start = len(player_items)
        for item in player_items[start_index:]:
            try:
                header = item.find_element(By.CSS_SELECTOR, ".tree-item-header.selectable-button")
                driver.execute_script("arguments[0].click();", header)
                time.sleep(0.6)
                righe = driver.find_elements(By.CSS_SELECTOR, "#player-property-table tbody tr")
                for riga in righe:
                    try:
                        prop = riga.find_element(By.TAG_NAME, "td").text.strip()
                        if prop == "kFrameUrl":
                            valore = riga.find_elements(By.TAG_NAME, "td")[1].text.strip().strip('"')
                            if "#" in valore and ".mpd" in valore:
                                url = valore.split("#", 1)[1]
                                if url not in urls_gia_visti:
                                    urls_gia_visti.add(url)
                                    if url not in urls_nuovi:
                                        urls_nuovi.append(url)
                                break
                    except Exception:
                        continue
            except Exception:
                continue
    except Exception:
        nuovo_start = start_index
    return urls_nuovi, nuovo_start


def upload_via_ftp(risultati):
    """
    1. Crea sky_update.json con i nuovi URL
    2. Lo carica su FTP in /htdocs
    3. Chiama update_channels.php via GET per applicare le modifiche
    """
    # Costruisci payload
    payload_channels = []
    for ch_id, chiave_html in MAPPATURA_ID_HTML.items():
        urls = risultati.get(chiave_html, [])
        if urls:
            payload_channels.append({"id": ch_id, "code": urls[0]})

    if not payload_channels:
        print("\n[UPLOAD] Nessun canale da caricare.")
        return

    # Salva JSON locale temporaneo
    json_data = json.dumps({"channels": payload_channels}, ensure_ascii=False, indent=2)
    local_json = "sky_update.json"
    with open(local_json, "w", encoding="utf-8") as f:
        f.write(json_data)
    print(f"\n[FTP] sky_update.json creato con {len(payload_channels)} canali")

    # Upload FTP
    print(f"[FTP] Connessione a {FTP_HOST} ...")
    try:
        ftp = ftplib.FTP(FTP_HOST)
        ftp.login(FTP_USER, FTP_PASS)
        ftp.cwd(FTP_PATH)
        with open(local_json, "rb") as f:
            ftp.storbinary("STOR sky_update.json", f)
        ftp.quit()
        print("[FTP] ✓ sky_update.json caricato sul server")
    except ftplib.all_errors as e:
        print(f"[FTP] ✗ Errore FTP: {e}")
        return

    # Trigger HTTP GET per applicare le modifiche
    trigger_url = f"{SERVER_URL}?key={SECRET_KEY}"
    print(f"[HTTP] Trigger aggiornamento: {trigger_url}")
    try:
        resp = requests.get(trigger_url, timeout=30,
                            headers={"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"})
        # InfinityFree a volte risponde con challenge JS, proviamo a parsare
        text = resp.text.strip()
        if text.startswith("{"):
            data = resp.json()
            if data.get("ok"):
                print(f"[HTTP] ✓ Aggiornati {data['count']} canali su channels.js")
                if data.get("not_found"):
                    print(f"[HTTP] ⚠ ID non trovati: {data['not_found']}")
                print(f"[HTTP] Backup: {data.get('backup', '?')}")
            else:
                print(f"[HTTP] ✗ Errore: {data.get('error')}")
        else:
            # InfinityFree sta bloccando con challenge JS
            # Il file è già sul server, aggiornalo manualmente aprendo:
            print(f"[HTTP] ⚠ InfinityFree ha bloccato la richiesta automatica.")
            print(f"[HTTP] Apri questo URL nel browser per completare l'aggiornamento:")
            print(f"       {trigger_url}")
    except Exception as e:
        print(f"[HTTP] ✗ Errore: {e}")
        print(f"[HTTP] Apri manualmente nel browser:")
        print(f"       {trigger_url}")

    # Pulisci file locale temporaneo
    if os.path.exists(local_json):
        os.remove(local_json)


# ── Scansione principale ──────────────────────────────────────────────────────
try:
    time.sleep(3)
    while len(driver.window_handles) > 1:
        driver.switch_to.window(driver.window_handles[-1])
        driver.close()
    driver.switch_to.window(driver.window_handles[0])

    print("Apertura Media Internals...")
    driver.get("chrome://media-internals")
    scheda_media = driver.current_window_handle
    time.sleep(2)

    print("Apertura Pepperstream...")
    driver.execute_script("window.open('about:blank', '_blank');")
    driver.switch_to.window(driver.window_handles[-1])
    scheda_pepper = driver.current_window_handle
    driver.maximize_window()
    time.sleep(1)
    driver.get("https://pepperstream.xyz/TV/regia.php")

    print("Tentativo di login...")
    try:
        campo_password = WebDriverWait(driver, 10).until(
            EC.presence_of_element_located(
                (By.XPATH, "//input[@type='password' or @name='password' or @id='password']")
            )
        )
        campo_password.send_keys("CHILI-627443930-95")
        campo_password.send_keys(Keys.RETURN)
        print("Password inviata!")
        time.sleep(5)
    except Exception:
        print("Accesso già attivo.")

    WebDriverWait(driver, 15).until(
        EC.presence_of_element_located((By.CSS_SELECTOR, ".grid-scroll .ch-row"))
    )

    elementi_canali = driver.find_elements(By.CSS_SELECTOR, ".grid-scroll .ch-row")
    canali_disponibili_html = []
    for el in elementi_canali:
        nome_ch = el.get_attribute("data-name")
        cat_ch  = el.get_attribute("data-l")
        if nome_ch and cat_ch and cat_ch.strip().lower() == "sky italia":
            canali_disponibili_html.append((nome_ch, cat_ch))

    print(f"\n✔ Trovati {len(canali_disponibili_html)} canali SKY ITALIA sulla pagina.")

    print("\n" + "="*60)
    print("Scegli la modalità di scansione:")
    print(f"  [T] Scansiona TUTTI i {len(canali_disponibili_html)} canali")
    print("  [N] Scansiona solo alcuni canali (per test)")
    scelta = input("\nScelta (T / N): ").strip().upper()

    if scelta == "T":
        canali_da_scansionare = canali_disponibili_html
    else:
        limite_str = input(f"Quanti canali (1-{len(canali_disponibili_html)})? ")
        limite = int(limite_str) if limite_str.isdigit() else 5
        canali_da_scansionare = canali_disponibili_html[:limite]

    risultati           = {}
    urls_globali_visti  = set()
    media_start_index   = 0
    totale = len(canali_da_scansionare)

    print(f"\nInizio scansione di {totale} canali...\n" + "="*60)

    for i, (nome_ch, cat_ch) in enumerate(canali_da_scansionare, 1):
        print(f"[{i}/{totale}] {nome_ch} ({cat_ch})...")
        driver.switch_to.window(scheda_pepper)
        try:
            xpath_str = f"//div[@data-name='{nome_ch}' and @data-l='{cat_ch}']"
            canale_btn = WebDriverWait(driver, 5).until(
                EC.presence_of_element_located((By.XPATH, xpath_str))
            )
            driver.execute_script("arguments[0].click();", canale_btn)
        except Exception:
            print("    [SKIP] Canale non cliccabile")
            continue

        time.sleep(ATTESA_STREAM)
        mpd_list, media_start_index = estrai_mpd(
            driver, scheda_media, urls_globali_visti, media_start_index
        )
        if mpd_list:
            for url in mpd_list:
                print(f"    ✓ {url}")
            risultati[(nome_ch, cat_ch)] = mpd_list

    canali_con_link = sum(1 for v in risultati.values() if v)
    print(f"\n{'='*60}")
    print(f"Scansione terminata! URL trovati: {canali_con_link}/{totale}")

    # ── Upload via FTP + trigger ──────────────────────────────
    if AUTO_UPLOAD:
        upload_via_ftp(risultati)
    else:
        print("\n[UPLOAD] AUTO_UPLOAD=False, skip upload.")

finally:
    print("\nChiusura del browser...")
    driver.quit()
