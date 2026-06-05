import json
import re

with open('c:/Users/manue/Desktop/guidaepg-main/js/channels.js', 'r', encoding='utf-8') as f:
    content = f.read()

# Replace categories
cat_str = '''let CATEGORIES = {
    all:                 { label: "Tutti i Canali",         icon: "ph-squares-four",         color: "#FFFF00" },
    eurosport:           { label: "Eurosport",              icon: "ph-bicycle",              color: "#2196F3" },
    lba:                 { label: "LBA TV",                 icon: "ph-basketball",           color: "#f57c00" },
    sky_sport:           { label: "Sky Sport",              icon: "ph-soccer-ball",          color: "#00E676" },
    sky_intrattenimento: { label: "Sky Intrattenimento",    icon: "ph-television",           color: "#00BCD4" },
    sky_cinema:          { label: "Sky Cinema",             icon: "ph-film-strip",           color: "#9C27B0" },
    digitale_terrestre:  { label: "Digitale Terrestre",     icon: "ph-broadcast",            color: "#E91E63" },
    kids:                { label: "Bambini",                icon: "ph-baby",                 color: "#FF69B4" },
    sportvari:           { label: "Sport Vari e Altro",     icon: "ph-play-circle",          color: "#FF9800" }
};'''
content = re.sub(r'let CATEGORIES = \{.*?\};', cat_str, content, flags=re.DOTALL)

def replace_cat(match):
    name = match.group(1)
    cat = match.group(2)
    name_lower = name.lower()
    
    new_cat = cat
    if cat in ['timvision', 'tivusat']:
        new_cat = 'digitale_terrestre'
    elif 'eurosport' in name_lower:
        new_cat = 'eurosport'
    elif 'lba' in name_lower:
        new_cat = 'lba'
    elif cat == 'sky' or 'sky' in name_lower:
        if 'sport' in name_lower:
            new_cat = 'sky_sport'
        elif 'cinema' in name_lower:
            new_cat = 'sky_cinema'
        else:
            new_cat = 'sky_intrattenimento'
    elif cat in ['sporttv', 'como', 'dazn', 'sportvari']:
        new_cat = 'sportvari'
    elif cat == 'kids' or cat == 'bambini':
        new_cat = 'kids'
    
    return f'name: "{name}",           cat: "{new_cat}",'

content = re.sub(r'name:\s*"([^"]+)",\s*cat:\s*"([^"]+)",', replace_cat, content)

# Fix alignment
content = re.sub(r'name: "(.*?)",           cat: "(.*?)",', lambda m: f'name: "{m.group(1)}",'.ljust(35) + f'cat: "{m.group(2)}",', content)

with open('c:/Users/manue/Desktop/guidaepg-main/js/channels.js', 'w', encoding='utf-8') as f:
    f.write(content)
