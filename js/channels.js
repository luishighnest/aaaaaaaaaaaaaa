const EXT_PLAYER = "chrome-extension://opmeopcambhfimffbomjgemehjkbbmji/pages/player.html#";

let CHANNELS = [
    // ── EUROSPORT ITA ──
    { id: 1,  name: "Eurosport 1",           cat: "eurosport", icon: "ph-bicycle",      code: "https://timlivetu0.cb.ticdn.it/Content/DASH/Live/channel(eurosport1)/manifest.mpd?ck=NjEwYmNkYTExMWM3NGM5N2IwNzkyYjA1OTYzMGExMGI6Yjk4MTc4NTM1Mzg0NTliMzcxZjNmYjU2YTI2N2Q1NWM=" },
    { id: 2,  name: "Eurosport 2",           cat: "eurosport", icon: "ph-bicycle",      code: "INSERISCI_QUI_URL_COMPLETO_EUROSPORT_2" },
    { id: 3,  name: "Eurosport 3",           cat: "eurosport", icon: "ph-bicycle",      code: "INSERISCI_QUI_URL_COMPLETO_EUROSPORT_3" },
    { id: 4,  name: "Eurosport 4",           cat: "eurosport", icon: "ph-bicycle",      code: "INSERISCI_QUI_URL_COMPLETO_EUROSPORT_4" },
    { id: 5,  name: "Eurosport 5",           cat: "eurosport", icon: "ph-bicycle",      code: "INSERISCI_QUI_URL_COMPLETO_EUROSPORT_5" },
    { id: 6,  name: "Eurosport 6",           cat: "eurosport", icon: "ph-bicycle",      code: "INSERISCI_QUI_URL_COMPLETO_EUROSPORT_6" },
    { id: 7,  name: "Eurosport 4K",          cat: "eurosport", icon: "ph-bicycle",      code: "INSERISCI_QUI_URL_COMPLETO_EUROSPORT_4K" },

    // ── SPORT TV / DAZN POR ──
    { id: 8,  name: "Sport TV 1",            cat: "sportvari",   icon: "ph-play-circle", code: "INSERISCI_QUI_URL_COMPLETO_SPORTTV_1" },
    { id: 9,  name: "Sport TV 2",            cat: "sportvari",   icon: "ph-play-circle", code: "INSERISCI_QUI_URL_COMPLETO_SPORTTV_2" },
    { id: 10, name: "Sport TV 3",            cat: "sportvari",   icon: "ph-play-circle", code: "INSERISCI_QUI_URL_COMPLETO_SPORTTV_3" },
    { id: 11, name: "Sport TV 4",            cat: "sportvari",   icon: "ph-play-circle", code: "INSERISCI_QUI_URL_COMPLETO_SPORTTV_4" },
    { id: 12, name: "Sport TV 5",            cat: "sportvari",   icon: "ph-play-circle", code: "INSERISCI_QUI_URL_COMPLETO_SPORTTV_5" },
    { id: 13, name: "Sport TV 6",            cat: "sportvari",   icon: "ph-play-circle", code: "INSERISCI_QUI_URL_COMPLETO_SPORTTV_6" },
    { id: 14, name: "Sport TV 7",            cat: "sportvari",   icon: "ph-play-circle", code: "INSERISCI_QUI_URL_COMPLETO_SPORTTV_7" },
    { id: 15, name: "Benfica TV",            cat: "sportvari",   icon: "ph-play-circle", code: "INSERISCI_QUI_URL_COMPLETO_BENFICA_TV" },
    { id: 16, name: "DAZN 1",                cat: "sportvari",   icon: "ph-play-circle", code: "INSERISCI_QUI_URL_COMPLETO_DAZN_1" },
    { id: 17, name: "DAZN 2",                cat: "sportvari",   icon: "ph-play-circle", code: "INSERISCI_QUI_URL_COMPLETO_DAZN_2" },
    { id: 18, name: "DAZN 3",                cat: "sportvari",   icon: "ph-play-circle", code: "INSERISCI_QUI_URL_COMPLETO_DAZN_3" },
    { id: 19, name: "DAZN 4",                cat: "sportvari",   icon: "ph-play-circle", code: "INSERISCI_QUI_URL_COMPLETO_DAZN_4" },
    { id: 20, name: "DAZN 5",                cat: "sportvari",   icon: "ph-play-circle", code: "INSERISCI_QUI_URL_COMPLETO_DAZN_5" },

    // ── COMO TV ──
    { id: 21, name: "Como TV",               cat: "sportvari",     icon: "ph-television",  code: "INSERISCI_QUI_URL_COMPLETO_COMO_TV" },
    { id: 22, name: "Como TV 2",             cat: "sportvari",     icon: "ph-television",  code: "INSERISCI_QUI_URL_COMPLETO_COMO_TV_2" },
    { id: 23, name: "Como TV 3",             cat: "sportvari",     icon: "ph-television",  code: "INSERISCI_QUI_URL_COMPLETO_COMO_TV_3" },
    { id: 24, name: "Como TV 4",             cat: "sportvari",     icon: "ph-television",  code: "INSERISCI_QUI_URL_COMPLETO_COMO_TV_4" },
    { id: 25, name: "Como TV 5",             cat: "sportvari",     icon: "ph-television",  code: "INSERISCI_QUI_URL_COMPLETO_COMO_TV_5" },


    // ── SKY ITALIA NEWS & ENTERTAINMENT ──
    { id: 26, name: "Sky TG 24", cat: "sky_intrattenimento", icon: "ph-newspaper", code: "NON_TROVATO" },
    { id: 27, name: "Sky Uno", cat: "sky_intrattenimento", icon: "ph-television", code: "NON_TROVATO" },
    { id: 28, name: "Sky Uno Plus", cat: "sky_intrattenimento", icon: "ph-television", code: "NON_TROVATO" },
    { id: 29, name: "Sky Atlantic", cat: "sky_intrattenimento", icon: "ph-television", code: "NON_TROVATO" },
    { id: 30, name: "Sky Serie", cat: "sky_intrattenimento", icon: "ph-film-slate", code: "NON_TROVATO" },
    { id: 31, name: "Sky Investigation", cat: "sky_intrattenimento", icon: "ph-detective", code: "NON_TROVATO" },
    { id: 32, name: "Sky Collection", cat: "sky_intrattenimento", icon: "ph-television", code: "NON_TROVATO" },
    { id: 33, name: "Sky Documentaries", cat: "sky_intrattenimento", icon: "ph-book-open-text", code: "NON_TROVATO" },
    { id: 34, name: "Sky Crime", cat: "sky_intrattenimento", icon: "ph-fingerprint", code: "NON_TROVATO" },
    { id: 35, name: "History Channel", cat: "sky_intrattenimento", icon: "ph-clock-counter-clockwise", code: "NON_TROVATO" },
    { id: 36, name: "Sky Nature", cat: "sky_intrattenimento", icon: "ph-tree", code: "NON_TROVATO" },
    { id: 37, name: "Sky Arte", cat: "sky_intrattenimento", icon: "ph-paint-brush", code: "NON_TROVATO" },
    { id: 38, name: "Sky Adventure", cat: "sky_intrattenimento", icon: "ph-mountains", code: "NON_TROVATO" },
    { id: 39, name: "MTV", cat: "sky_intrattenimento", icon: "ph-music-notes", code: "NON_TROVATO" },
    { id: 40, name: "Comedy Central", cat: "sky_intrattenimento", icon: "ph-smiley", code: "NON_TROVATO" },

    // ── SKY CINEMA ──
    { id: 41, name: "Sky Cinema Uno", cat: "sky_cinema", icon: "ph-film-strip", code: "NON_TROVATO" },
    { id: 42, name: "Sky Cinema Collection", cat: "sky_cinema", icon: "ph-film-strip", code: "NON_TROVATO" },
    { id: 43, name: "Sky Cinema Comedy", cat: "sky_cinema", icon: "ph-smiley", code: "NON_TROVATO" },
    { id: 44, name: "Sky Cinema Action", cat: "sky_cinema", icon: "ph-sword", code: "NON_TROVATO" },
    { id: 45, name: "Sky Cinema Stories", cat: "sky_cinema", icon: "ph-book", code: "NON_TROVATO" },
    { id: 46, name: "Sky Cinema Family", cat: "sky_cinema", icon: "ph-star", code: "NON_TROVATO" },
    { id: 47, name: "Sky Cinema Drama", cat: "sky_cinema", icon: "ph-masks-theater", code: "NON_TROVATO" },
    { id: 48, name: "Sky Cinema Romance", cat: "sky_cinema", icon: "ph-heart", code: "NON_TROVATO" },
    { id: 49, name: "Sky Cinema Suspense", cat: "sky_cinema", icon: "ph-eye", code: "NON_TROVATO" },

    // ── SKY SPORT ──
    { id: 50, name: "Sky Sport 24", cat: "sky_sport", icon: "ph-soccer-ball", code: "https://g004-lin-it-cmaf-prd-ak.pcdn07.cssott02.com/v~a-0-0_e~1780898422_s~932931af-b442-420f-a147-173d9b617d1a_u~c0712fe81439b039492d5b58a626d540e0f19c1da6e63a813cb8c4d6f65e95b309ba4ab0223c0a47120cc0d2625c6b24_l~56_x~ac10f8adc06598e8202b88383215eb14c197e38712165979dec215b89dfa48f1/nowitlin2/Content/CMAF_CTR_H1/Live/channel(skysport24)/master_2hr-all.mpd?t=v2&c3.ri=6a24fae6_Q1ZYMDc_0_QUtBTUFJ_8918712cf251:0&c3.ri=6a24fae6_Q1ZYMDc_0_QUtBTUFJ_8918712cf251:0&ck=MTExODE4YzQzNGI0ODI2M2U2ZmVkMGQ2MmEwYmMwMWE6NmFjOTdkZGMxMzdhZDg4ZjE1MWJkNmEyMTUxZDZiMjk=" },
    { id: 51, name: "Sky Sport Uno", cat: "sky_sport", icon: "ph-soccer-ball", code: "https://g004-lin-it-cmaf-prd-ak.pcdn07.cssott02.com/v~a-0-0_e~1780898423_s~86582164-e054-4ceb-a771-177d8bff50b2_u~c0712fe81439b039492d5b58a626d540e0f19c1da6e63a813cb8c4d6f65e95b309ba4ab0223c0a47120cc0d2625c6b24_l~57_x~cf78bd29243040e0e7127f0db4106ee336edca9af7732329915d9f9bf2b73742/nowitlin2/Content/CMAF_CTR_H1/Live/channel(skysportuno)/master_2hr-all.mpd?t=v2&c3.ri=6a24fae7_Q1ZYMDc_0_QUtBTUFJ_2b7575700542:0&c3.ri=6a24fae7_Q1ZYMDc_0_QUtBTUFJ_2b7575700542:0&ck=MTExODAzYWE0ODU5M2IwNDBkMTE1ZDFmMGM0NTIyOTM6OGM5ZDY1NGIxZjQ4NWNmN2M1MjBhNjQ2NThkMGI0ZWQ=" },
    { id: 52, name: "Sky Sport F1", cat: "sky_sport", icon: "ph-steering-wheel", code: "https://g004-lin-it-cmaf-prd-ak.pcdn07.cssott02.com/v~a-0-0_e~1780898423_s~0cff0e5a-e8b9-45fc-a78a-7b0ba9a1d496_u~c0712fe81439b039492d5b58a626d540e0f19c1da6e63a813cb8c4d6f65e95b309ba4ab0223c0a47120cc0d2625c6b24_l~56_x~d6428bf62721bfd495144de672c3c1740eb629a67944b2797a19e4a87ebfe353/nowitlin2/Content/CMAF_CTR_H1/Live/channel(skysportf1)/master_2hr-all.mpd?t=v2&c3.ri=6a24fae7_Q1ZYMDc_0_QUtBTUFJ_06341a66dbf9:0&c3.ri=6a24fae7_Q1ZYMDc_0_QUtBTUFJ_06341a66dbf9:0&ck=MTExODMwOWEyYWNkYTcyY2ZjM2NiM2NjOWUwNDdhM2I6YTllOTBhZjEwZmFmOTNiM2M5MzViYjBhODA3ZDI4NWE=" },
    { id: 53, name: "Sky Sport Calcio", cat: "sky_sport", icon: "ph-soccer-ball", code: "https://g004-lin-it-cmaf-prd-ak.pcdn07.cssott02.com/v~a-0-0_e~1780898424_s~44b0d8af-ffe9-4948-9634-f548ba05dec8_u~c0712fe81439b039492d5b58a626d540e0f19c1da6e63a813cb8c4d6f65e95b309ba4ab0223c0a47120cc0d2625c6b24_l~60_x~85549896ceb4777f8ca45748515e83bd771d2e9a58fa2367a2ccc05c094740b8/nowitlin2/Content/CMAF_CTR_H1/Live/channel(skysportcalcio)/master_2hr-all.mpd?t=v2&c3.ri=6a24fae8_Q1ZYMDc_0_QUtBTUFJ_e4f1550b082b:0&c3.ri=6a24fae8_Q1ZYMDc_0_QUtBTUFJ_e4f1550b082b:0&ck=MTExODQ2MjM1YzA0Nzg2ZGIxM2Y5NDVjNGYyZDFlYzA6YjM5YWM0MzhjZDE0OWNhODYwOTZlNTg2YjFmMTY2OWI=" },
    { id: 54, name: "Sky Sport Tennis", cat: "sky_sport", icon: "ph-tennis-ball", code: "https://g004-lin-it-cmaf-prd-ak.pcdn07.cssott02.com/v~a-0-0_e~1780898424_s~ecd81197-ac39-4867-b8bb-38ad66f709b5_u~c0712fe81439b039492d5b58a626d540e0f19c1da6e63a813cb8c4d6f65e95b309ba4ab0223c0a47120cc0d2625c6b24_l~60_x~a2e74edc96822a78b8d53853979d8ca5ec413afa7bd216a6806b94ea6ab98a0a/nowitlin2/Content/CMAF_CTR_H1/Live/channel(skysporttennis)/master_2hr-all.mpd?t=v2&c3.ri=6a24fae8_Q1ZYMDc_0_QUtBTUFJ_8edee32be875:0&c3.ri=6a24fae8_Q1ZYMDc_0_QUtBTUFJ_8edee32be875:0&ck=MTExODMxYmIzYzExMGYyMmI0OWIxMWZkODk5YjZiZmU6YTE0NDIyYTM0MzA3ZDU3MDcwOTIyZTE3MjQ4ZGVkOTU=" },
    { id: 55, name: "Sky Sport MotoGP", cat: "sky_sport", icon: "ph-motorcycle", code: "https://g004-lin-it-cmaf-prd-ak.pcdn07.cssott02.com/v~a-0-0_e~1780898425_s~d715e1d0-b622-4e41-91d0-9ffd374030b5_u~c0712fe81439b039492d5b58a626d540e0f19c1da6e63a813cb8c4d6f65e95b309ba4ab0223c0a47120cc0d2625c6b24_l~60_x~f244623db43443627dfc70fec2a71a5379ded384f657aa2e7751b049fe50bb99/nowitlin2/Content/CMAF_CTR_H1/Live/channel(skysportmotogp)/master_2hr-all.mpd?t=v2&c3.ri=6a24fae9_Q1ZYMDc_0_QUtBTUFJ_a5eacdfec0e6:0&c3.ri=6a24fae9_Q1ZYMDc_0_QUtBTUFJ_a5eacdfec0e6:0&ck=MTExODI1MjM1NWYzZjlmNWYwNmNiNWM4YWU3NWZiM2M6MmNjYWNkYTc4YTMxY2FjMjIyODJlZTNmNzFjOGUxMmM=" },
    { id: 56, name: "Sky Sport Arena", cat: "sky_sport", icon: "ph-trophy", code: "https://g004-lin-it-cmaf-prd-ak.pcdn07.cssott02.com/v~a-0-0_e~1780898425_s~51caf39f-0e95-4a25-ac90-2dd94f426624_u~c0712fe81439b039492d5b58a626d540e0f19c1da6e63a813cb8c4d6f65e95b309ba4ab0223c0a47120cc0d2625c6b24_l~59_x~523ccc9c609294da4a9d8a5f41e6185cc0b86e1b0f4b09bc9a291f9276ea0a1c/nowitlin2/Content/CMAF_CTR_H1/Live/channel(skysportarena)/master_2hr-all.mpd?t=v2&c3.ri=6a24fae9_Q1ZYMDc_0_QUtBTUFJ_82e2d2bf7811:0&c3.ri=6a24fae9_Q1ZYMDc_0_QUtBTUFJ_82e2d2bf7811:0&ck=MTExODBmM2M1NWU5MmQ2YzRlY2IxM2VmNjdlMzhkYmQ6YTQ0NWZjY2E1ZmViODUxZDQ0NDAzZDc0MzJkYzA0NDA=" },
    { id: 57, name: "Sky Sport Max", cat: "sky_sport", icon: "ph-fire", code: "https://g004-lin-it-cmaf-prd-ak.pcdn07.cssott02.com/v~a-0-0_e~1780898426_s~38e5355a-544e-4347-afb9-0cd2347278d5_u~c0712fe81439b039492d5b58a626d540e0f19c1da6e63a813cb8c4d6f65e95b309ba4ab0223c0a47120cc0d2625c6b24_l~57_x~13b9d91b4078419b1bb7c8fedeb0b0c278f3fe8f1b8f667d945f658ebf9727be/nowitlin2/Content/CMAF_CTR_H1/Live/channel(skysportmax)/master_2hr-all.mpd?t=v2&c3.ri=6a24faea_Q1ZYMDc_0_QUtBTUFJ_ae3fbe72fae2:0&c3.ri=6a24faea_Q1ZYMDc_0_QUtBTUFJ_ae3fbe72fae2:0&ck=MTExODBhYjRiYmUwYzZlZDUwN2Y2NmYyZGM1NTJjNjk6ZTE0MzhkZTE3MTc2MmFlNWUzZGY0NTI2YWFiY2FhMDQ=" },
    { id: 58, name: "Sky Sport Basket", cat: "sky_sport", icon: "ph-basketball", code: "https://g004-lin-it-cmaf-prd-ak.pcdn07.cssott02.com/v~a-0-0_e~1780898426_s~4f892bbb-f5a1-46d2-ae57-6c37ab2f0e8b_u~c0712fe81439b039492d5b58a626d540e0f19c1da6e63a813cb8c4d6f65e95b309ba4ab0223c0a47120cc0d2625c6b24_l~60_x~654d9966f6dbafadf9a9c28398ed515160b510d15712823999dc59de37347131/nowitlin2/Content/CMAF_CTR_H1/Live/channel(skysportbasket)/master_2hr-all.mpd?t=v2&c3.ri=6a24faea_Q1ZYMDc_0_QUtBTUFJ_2c996deb9437:0&c3.ri=6a24faea_Q1ZYMDc_0_QUtBTUFJ_2c996deb9437:0&ck=MTExODA4ODRhYTk4NDI3YWQyOTY4ZTcwYWNjZGRhYjE6ZTA4ZmFjZTZhZjNmNzhmZjk4ZmNlM2VkMjgzZTEzZDA=" },
    { id: 59, name: "Sky Sport Legend", cat: "sky_sport", icon: "ph-star", code: "https://g004-lin-it-cmaf-prd-ak.pcdn07.cssott02.com/v~a-0-0_e~1780898427_s~d4dd6bc7-1f87-48ba-8d23-eff132bdf625_u~c0712fe81439b039492d5b58a626d540e0f19c1da6e63a813cb8c4d6f65e95b309ba4ab0223c0a47120cc0d2625c6b24_l~60_x~7bf5b8afb96a2d1aa56cb7b8026b067750486ad092043f8fbed6fc2a2a6a4cb6/nowitlin2/Content/CMAF_CTR_H1/Live/channel(skysportlegend)/master_2hr-all.mpd?t=v2&c3.ri=6a24faeb_Q1ZYMDc_0_QUtBTUFJ_e941b10b100a:0&c3.ri=6a24faeb_Q1ZYMDc_0_QUtBTUFJ_e941b10b100a:0&ck=MTExOGY2Yjk2OTc1YjU5NWQ3YjA4MTAwODk4NzQ1OWE6NjIwMWQ1NDFmZmIzOWY0N2Q1MzZlZjgzNjhiZmRjZGY=" },
    { id: 60, name: "Sky Sport Mix", cat: "sky_sport", icon: "ph-shuffle", code: "https://g004-lin-it-cmaf-prd-ak.pcdn07.cssott02.com/v~a-0-0_e~1780898428_s~a68b4102-bb53-4179-9ca3-e94f69ef63d7_u~c0712fe81439b039492d5b58a626d540e0f19c1da6e63a813cb8c4d6f65e95b309ba4ab0223c0a47120cc0d2625c6b24_l~57_x~3c71046e9308b7a821680b94f020d8f633e2da6eda10abca2f64cecbe1c7c8fa/nowitlin2/Content/CMAF_CTR_H1/Live/channel(skysportmix)/master_2hr-all.mpd?t=v2&c3.ri=6a24faec_Q1ZYMDc_0_QUtBTUFJ_7e705b4dbdd2:0&c3.ri=6a24faec_Q1ZYMDc_0_QUtBTUFJ_7e705b4dbdd2:0&ck=MTExOGE0NTg5NTY1MGMxZmVhZDhmZGY3NzgzNjIxNTg6MGZmYmJiNDQ5YzM5MGZhODgxOWVkMzcyMjdlMjdmMGU=" },
    { id: 61, name: "Sky Sport Golf", cat: "sky_sport", icon: "ph-flag", code: "NON_TROVATO" },
    { id: 62, name: "Sky Sport 251", cat: "sky_sport", icon: "ph-monitor-play", code: "NON_TROVATO" },
    { id: 63, name: "Sky Sport 252", cat: "sky_sport", icon: "ph-monitor-play", code: "NON_TROVATO" },
    { id: 64, name: "Sky Sport 253", cat: "sky_sport", icon: "ph-monitor-play", code: "NON_TROVATO" },
    { id: 65, name: "Sky Sport 254", cat: "sky_sport", icon: "ph-monitor-play", code: "NON_TROVATO" },
    { id: 66, name: "Sky Sport 255", cat: "sky_sport", icon: "ph-monitor-play", code: "NON_TROVATO" },
    { id: 67, name: "Sky Sport 256", cat: "sky_sport", icon: "ph-monitor-play", code: "NON_TROVATO" },
    { id: 68, name: "Sky Sport 257", cat: "sky_sport", icon: "ph-monitor-play", code: "NON_TROVATO" },
    { id: 69, name: "Sky Sport 258", cat: "sky_sport", icon: "ph-monitor-play", code: "NON_TROVATO" },
    { id: 70, name: "Sky Sport 259", cat: "sky_sport", icon: "ph-monitor-play", code: "NON_TROVATO" },

    // ── KIDS ──
    { id: 71, name: "Cartoon Network",    cat: "kids", icon: "ph-baby",   code: "INSERISCI_QUI_URL_COMPLETO_CARTOON_NET" },
    { id: 72, name: "Nickelodeon",        cat: "kids", icon: "ph-baby",   code: "INSERISCI_QUI_URL_COMPLETO_NICKELODEON" },
    { id: 73, name: "Dea Kids",           cat: "kids", icon: "ph-baby",   code: "INSERISCI_QUI_URL_COMPLETO_DEAKIDS" },
    { id: 74, name: "Nick Jr",            cat: "kids", icon: "ph-baby",   code: "INSERISCI_QUI_URL_COMPLETO_NICKJR" },
    { id: 75, name: "Boomerang",          cat: "kids", icon: "ph-baby",   code: "INSERISCI_QUI_URL_COMPLETO_BOOMERANG" },
    { id: 76, name: "Cartoon Network",    cat: "kids", icon: "ph-baby",   code: "INSERISCI_QUI_URL_COMPLETO_CARTOON_NET_2" },
    { id: 77, name: "Cartoonito",         cat: "kids", icon: "ph-baby",   code: "INSERISCI_QUI_URL_COMPLETO_CARTOONITO" },
    { id: 78, name: "Boing",              cat: "kids", icon: "ph-baby",   code: "INSERISCI_QUI_URL_COMPLETO_BOING" },

    // ── SPORT VARI ──
    { id: 79, name: "ZDF",                  cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ZDF" },
    { id: 80, name: "TNT Sports 1",         cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_TNTSPORTS1" },
    { id: 81, name: "TNT Sports 2",         cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_TNTSPORTS2" },
    { id: 82, name: "TNT Sports 3",         cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_TNTSPORTS3" },
    { id: 83, name: "TNT Sports 4",         cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_TNTSPORTS4" },
    { id: 84, name: "Arena Sport 1",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ARENASPORT1" },
    { id: 85, name: "Arena Sport 2",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ARENASPORT2" },
    { id: 86, name: "Arena Sport 3",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ARENASPORT3" },
    { id: 87, name: "Arena Sport 4",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ARENASPORT4" },
    { id: 88, name: "Arena Sport 5",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ARENASPORT5" },
    { id: 89, name: "Arena Sport 6",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ARENASPORT6" },
    { id: 90, name: "Arena Sport 7",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ARENASPORT7" },
    { id: 91, name: "Arena Sport 8",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ARENASPORT8" },
    { id: 92, name: "Arena Sport 9",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ARENASPORT9" },
    { id: 93, name: "SportKlub 1",          cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_SPORTKLUB1" },
    { id: 94, name: "SportKlub 2",          cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_SPORTKLUB2" },
    { id: 95, name: "SportKlub 3",          cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_SPORTKLUB3" },
    { id: 96, name: "SportKlub 4",          cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_SPORTKLUB4" },
    { id: 97, name: "SportKlub 5",          cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_SPORTKLUB5" },
    { id: 98, name: "SportKlub 6",          cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_SPORTKLUB6" },
    { id: 99, name: "SportKlub 7",          cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_SPORTKLUB7" },
    { id: 100, name: "SportKlub 8",         cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_SPORTKLUB8" },
    { id: 101, name: "SportKlub 9",         cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_SPORTKLUB9" },
    { id: 102, name: "SportKlub 10",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_SPORTKLUB10" },
    { id: 103, name: "SportKlub 11",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_SPORTKLUB11" },
    { id: 104, name: "SportKlub 12",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_SPORTKLUB12" },
    { id: 105, name: "ESPN 1",              cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ESPN1" },
    { id: 106, name: "ESPN 2",              cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ESPN2" },
    { id: 107, name: "ESPN 3",              cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ESPN3" },
    { id: 108, name: "ESPN 4",              cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ESPN4" },
    { id: 109, name: "ESPN 5",              cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ESPN5" },
    { id: 110, name: "ESPN 6",              cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ESPN6" },
    { id: 111, name: "ESPN 7",              cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_ESPN7" },
    { id: 112, name: "FOX Sports",          cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_FOXSPORTS" },
    { id: 113, name: "FOX Deportes",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_FOXDEPORTES" },
    { id: 114, name: "FS1",                 cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_FS1" },
    { id: 115, name: "FS2",                 cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_FS2" },
    { id: 116, name: "NBC Universo",        cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_NBCUNIVERSO" },
    { id: 117, name: "TSN 1",               cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_TSN1" },
    { id: 118, name: "TSN 2",               cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_TSN2" },
    { id: 119, name: "TSN 3",               cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_TSN3" },
    { id: 120, name: "TSN 4",               cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_TSN4" },
    { id: 121, name: "TSN 5",               cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_TSN5" },
    { id: 122, name: "LBA TV 1",            cat: "lba", icon: "ph-basketball", code: "https://live-d-01-lba-ew.akamaized.net/out/v1/l-prd/ch-01-prd-l-v2/dash-ch-01-prd-l-hd/index.mpd?ck=MWRjOGU0Mzk3ODcwM2UxY2I4M2VjN2JhZWM0YTUyNGY6MTI5NGZhMjUzYTdkZWFlN2VkYjM4NTMyYjY3MTlhY2Q=" },
    { id: 123, name: "LBA TV 2",            cat: "lba", icon: "ph-basketball", code: "INSERISCI_QUI_URL_COMPLETO_LBATV2" },
    { id: 124, name: "LBA TV 3",            cat: "lba", icon: "ph-basketball", code: "INSERISCI_QUI_URL_COMPLETO_LBATV3" },
    { id: 125, name: "LBA TV 4",            cat: "lba", icon: "ph-basketball", code: "INSERISCI_QUI_URL_COMPLETO_LBATV4" },
    { id: 126, name: "LBA TV 5",            cat: "lba", icon: "ph-basketball", code: "INSERISCI_QUI_URL_COMPLETO_LBATV5" },
    { id: 127, name: "SPOTV2",               cat: "sportvari", icon: "ph-soccer-ball", code: "INSERISCI_QUI_URL_COMPLETO_SPOTV2" },

    // ── TIMVISION ──
    { id: 128, name: "20 Mediaset",        cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_MEDIASET20" },
    { id: 129, name: "TwentySeven",      cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_TWENTYSEVEN" },
    { id: 130, name: "Canale 5",           cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_CANALE5" },
    { id: 131, name: "Cine34",             cat: "digitale_terrestre", icon: "ph-film-strip", code: "INSERISCI_QUI_URL_COMPLETO_CINE34" },
    { id: 132, name: "CNN International",  cat: "digitale_terrestre", icon: "ph-globe",      code: "INSERISCI_QUI_URL_COMPLETO_CNN" },
    { id: 133, name: "Discovery",          cat: "digitale_terrestre", icon: "ph-globe",      code: "INSERISCI_QUI_URL_COMPLETO_DISCOVERY" },
    { id: 134, name: "Eurosport 1",        cat: "digitale_terrestre", icon: "ph-bicycle",    code: "INSERISCI_QUI_URL_COMPLETO_TIM_EUROSPORT1" },
    { id: 135, name: "Eurosport 2",        cat: "digitale_terrestre", icon: "ph-bicycle",    code: "INSERISCI_QUI_URL_COMPLETO_TIM_EUROSPORT2" },
    { id: 136, name: "Eurosport 3",        cat: "digitale_terrestre", icon: "ph-bicycle",    code: "INSERISCI_QUI_URL_COMPLETO_TIM_EUROSPORT3" },
    { id: 137, name: "Eurosport 4",        cat: "digitale_terrestre", icon: "ph-bicycle",    code: "INSERISCI_QUI_URL_COMPLETO_TIM_EUROSPORT4" },
    { id: 138, name: "Eurosport 5",        cat: "digitale_terrestre", icon: "ph-bicycle",    code: "INSERISCI_QUI_URL_COMPLETO_TIM_EUROSPORT5" },
    { id: 139, name: "Eurosport 6",        cat: "digitale_terrestre", icon: "ph-bicycle",    code: "INSERISCI_QUI_URL_COMPLETO_TIM_EUROSPORT6" },
    { id: 140, name: "Focus",              cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_FOCUS" },
    { id: 141, name: "Food Network",       cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_FOODNETWORK" },
    { id: 142, name: "Frisbee",            cat: "kids",      icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_FRISBEE" },
    { id: 143, name: "Giallo",             cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_GIALLO" },
    { id: 144, name: "HGTV",               cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_HGTV" },
    { id: 145, name: "Iris",               cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_IRIS" },
    { id: 146, name: "Italia 1",           cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_ITALIA1" },
    { id: 147, name: "Italia 2",           cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_ITALIA2" },
    { id: 148, name: "K2",                 cat: "kids",      icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_K2" },
    { id: 149, name: "La 5",               cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_LA5" },
    { id: 150, name: "LA7",                cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_LA7" },
    { id: 151, name: "LA7d HD",            cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_LA7D" },
    { id: 152, name: "Mediaset Extra",     cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_MEDIASETEXTRA" },
    { id: 153, name: "Motor Trend",        cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_MOTORTREND" },
    { id: 154, name: "Nove",               cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_NOVE" },
    { id: 155, name: "Real Time",          cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_REALTIME" },
    { id: 156, name: "Rete 4",             cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_RETE4" },
    { id: 157, name: "TGCOM24",            cat: "digitale_terrestre", icon: "ph-newspaper",  code: "INSERISCI_QUI_URL_COMPLETO_TGCOM24" },
    { id: 158, name: "Timvision Trailer",  cat: "digitale_terrestre", icon: "ph-play-circle",code: "INSERISCI_QUI_URL_COMPLETO_TIMTRAILER" },
    { id: 159, name: "Top Crime",          cat: "digitale_terrestre", icon: "ph-fingerprint",code: "INSERISCI_QUI_URL_COMPLETO_TOPCRIME" },

    // ── TIVUSAT ──
    { id: 160, name: "Boing HD",           cat: "kids",      icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_BOINGHD" },
    { id: 161, name: "Boing Plus",         cat: "kids",      icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_BOINGPLUS" },
    { id: 162, name: "Boomerang HD",       cat: "kids",      icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_BOOMERANGHD" },
    { id: 163, name: "Canale 5 HD",        cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_CANALE5HD" },
    { id: 164, name: "Cartoon Network HD",cat: "kids",      icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_CARTOONHD" },
    { id: 165, name: "Cartoonito",         cat: "kids",      icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_CARTOONITO_TIVU" },
    { id: 166, name: "Cielo",              cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_CIELO" },
    { id: 167, name: "Cine34",             cat: "digitale_terrestre", icon: "ph-film-strip", code: "INSERISCI_QUI_URL_COMPLETO_CINE34_TIVU" },
    { id: 168, name: "Deejay TV",          cat: "digitale_terrestre", icon: "ph-music-notes",code: "INSERISCI_QUI_URL_COMPLETO_DEEJAYTV" },
    { id: 169, name: "DMAX",               cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_DMAX" },
    { id: 170, name: "Focus",              cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_FOCUS_TIVU" },
    { id: 171, name: "Food Network",       cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_FOOD_TIVU" },
    { id: 172, name: "Frisbee",            cat: "kids",      icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_FRISBEE_TIVU" },
    { id: 173, name: "Giallo",             cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_GIALLO_TIVU" },
    { id: 174, name: "HGTV Italia",        cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_HGTV_TIVU" },
    { id: 175, name: "History",            cat: "digitale_terrestre", icon: "ph-clock-counter-clockwise",code: "INSERISCI_QUI_URL_COMPLETO_HISTORY_TIVU" },
    { id: 176, name: "Iris",               cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_IRIS_TIVU" },
    { id: 177, name: "Italia 1",           cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_ITALIA1_TIVU" },
    { id: 178, name: "Italia 2",           cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_ITALIA2_TIVU" },
    { id: 179, name: "La5",                cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_LA5_TIVU" },
    { id: 180, name: "LA7",                cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_LA7_TIVU" },
    { id: 181, name: "LA7d",               cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_LA7D_TIVU" },
    { id: 182, name: "Mediaset 20",        cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_MEDIASET20_TIVU" },
    { id: 183, name: "Mediaset Extra",     cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_EXTRA_TIVU" },
    { id: 184, name: "Motor Trend",        cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_MOTOR_TIVU" },
    { id: 185, name: "Rai 1",              cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_RAI1" },
    { id: 186, name: "Rai 2",              cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_RAI2" },
    { id: 187, name: "Rai 3",              cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_RAI3" },
    { id: 188, name: "Rai 4",              cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_RAI4" },
    { id: 189, name: "Rai 5",              cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_RAI5" },
    { id: 190, name: "Rai Gulp",           cat: "kids",      icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_RAIGULP" },
    { id: 191, name: "Rai Italia",         cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_RAIITALIA" },
    { id: 192, name: "Rai Movie",          cat: "digitale_terrestre", icon: "ph-film-strip", code: "INSERISCI_QUI_URL_COMPLETO_RAIMOVIE" },
    { id: 193, name: "Rai News ",          cat: "digitale_terrestre", icon: "ph-newspaper",  code: "INSERISCI_QUI_URL_COMPLETO_RAINEWS" },
    { id: 194, name: "Rai Premium",        cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_RAIPREMIUM" },
    { id: 195, name: "Rai Scuola",         cat: "kids",      icon: "ph-student",    code: "INSERISCI_QUI_URL_COMPLETO_RAISCUOLA" },
    { id: 196, name: "Rai Sport",          cat: "digitale_terrestre", icon: "ph-soccer-ball",code: "INSERISCI_QUI_URL_COMPLETO_RAISPORT" },
    { id: 197, name: "Rai Storia",         cat: "digitale_terrestre", icon: "ph-clock-counter-clockwise",code: "INSERISCI_QUI_URL_COMPLETO_RAISTORIA" },
    { id: 198, name: "Rai Yoyo",           cat: "kids",      icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_RAIYOYO" },
    { id: 199, name: "Real Time",          cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_REALTIME_TIVU" },
    { id: 200, name: "Rete 4",             cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_RETE4_TIVU" },
    { id: 201, name: "RSI La1",            cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_RSILA1" },
    { id: 202, name: "RSI La2",            cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_RSILA2" },
    { id: 203, name: "Sportitalia",        cat: "digitale_terrestre", icon: "ph-soccer-ball",code: "INSERISCI_QUI_URL_COMPLETO_SPORTITALIA" },
    { id: 204, name: "Super!",             cat: "kids",      icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_SUPER" },
    { id: 205, name: "TV8",                cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_TV8" },
    { id: 206, name: "TwentySeven",        cat: "digitale_terrestre", icon: "ph-television", code: "INSERISCI_QUI_URL_COMPLETO_TWENTYSEVEN_TIVU" },
    { id: 207, name: "Video Italia",       cat: "digitale_terrestre", icon: "ph-music-notes",code: "INSERISCI_QUI_URL_COMPLETO_VIDEOITALIA" }
];

let CATEGORIES = {
    all:                 { label: "Tutti i Canali",         icon: "ph-squares-four",         color: "#FFFF00" },
    digitale_terrestre:  { label: "Digitale Terrestre",     icon: "ph-broadcast",            color: "#E91E63" },
    sky_sport:           { label: "Sky Sport",              icon: "ph-soccer-ball",          color: "#00E676" },
    sky_intrattenimento: { label: "Sky Intrattenimento",    icon: "ph-television",           color: "#00BCD4" },
    sky_cinema:          { label: "Sky Cinema",             icon: "ph-film-strip",           color: "#9C27B0" },
    eurosport:           { label: "Eurosport",              icon: "ph-bicycle",              color: "#2196F3" },
    lba:                 { label: "LBA TV",                 icon: "ph-basketball",           color: "#f57c00" },
    kids:                { label: "Bambini",                icon: "ph-baby",                 color: "#FF69B4" },
    sportvari:           { label: "Sport Vari e Altro",     icon: "ph-play-circle",          color: "#FF9800" }
};

// Mappa per velocizzare la ricerca (Performance O(1))
const CHANNELS_MAP = new Map(CHANNELS.map(c => [c.id, c]));

function getChannelsByCategory(cat) {
    if (cat === "all")  return CHANNELS;                     
    if (cat === "favorites") {
        const favs = window.favorites || [];
        return favs.map(id => CHANNELS_MAP.get(id)).filter(Boolean);
    }
    return CHANNELS.filter(c => c.cat === cat);   
}

function getChannelById(id) {
    return CHANNELS_MAP.get(id) || null;
}

/**
 * Adesso la funzione concatena semplicemente la stringa EXT_PLAYER con l'URL statico e unico del canale
 */
function getStreamUrl(channel) {
    if (!channel || !channel.code) return "";
    return EXT_PLAYER + channel.code;
}
