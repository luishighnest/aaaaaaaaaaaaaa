/* Base URL per tutti i canali */
const STREAM_BASE = "https://www.chilistream.net/live.php?ch=";

let CHANNELS = [
    // ── TIVUSAT ──
    { id: 185, name: "Rai 1", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 186, name: "Rai 2", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 187, name: "Rai 3", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 188, name: "Rai 4", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 189, name: "Rai 5", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 156, name: "Rete 4", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 130, name: "Canale 5", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 146, name: "Italia 1", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 150, name: "La7", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 205, name: "TV8", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 154, name: "Nove", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 128, name: "20 Mediaset", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 129, name: "Twentyseven", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 145, name: "IRIS", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 192, name: "Rai Movie", cat: "digitale_terrestre", icon: "ph-film-strip", code: "" },
    { id: 194, name: "Rai Premium", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 149, name: "La 5", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 155, name: "Real Time", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 141, name: "Food Network", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 140, name: "Focus", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 143, name: "Giallo", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 78, name: "Boing", cat: "kids", icon: "ph-television", code: "" },
    { id: 148, name: "K2", cat: "kids", icon: "ph-television", code: "" },
    { id: 190, name: "Rai Gulp", cat: "kids", icon: "ph-television", code: "" },
    { id: 142, name: "Frisbee", cat: "kids", icon: "ph-television", code: "" },
    { id: 169, name: "DMAX", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 196, name: "Rai Sport", cat: "digitale_terrestre", icon: "ph-soccer-ball", code: "" },
    { id: 203, name: "Sportitalia", cat: "digitale_terrestre", icon: "ph-soccer-ball", code: "" },
    { id: 144, name: "HGTV", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 201, name: "RSI LA 1", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 202, name: "RSI LA 2", cat: "digitale_terrestre", icon: "ph-television", code: "" },
    { id: 32, name: "Rai News 24", cat: "digitale_terrestre", icon: "ph-newspaper", code: "" },

    // ── TIMVISION ──
    { id: 16, name: "DAZN 1", cat: "digitale_terrestre", icon: "ph-play-circle", code: "" },

    // ── SKY ITALIA ──
    { id: 50, name: "Sky Sport 24", cat: "sky_sport", icon: "ph-soccer-ball", code: "" },
    { id: 51, name: "Sky Sport Uno", cat: "sky_sport", icon: "ph-soccer-ball", code: "" },
    { id: 53, name: "Sky Sport Calcio", cat: "sky_sport", icon: "ph-soccer-ball", code: "" },
    { id: 54, name: "Sky Sport Tennis", cat: "sky_sport", icon: "ph-tennis-ball", code: "" },
    { id: 52, name: "Sky Sport F1", cat: "sky_sport", icon: "ph-steering-wheel", code: "" },
    { id: 59, name: "Sky Sport Legend", cat: "sky_sport", icon: "ph-star", code: "" },
    { id: 55, name: "Sky Sport MotoGP", cat: "sky_sport", icon: "ph-motorcycle", code: "" },
    { id: 58, name: "Sky Sport Basket", cat: "sky_sport", icon: "ph-basketball", code: "" },
    { id: 56, name: "Sky Sport Arena", cat: "sky_sport", icon: "ph-trophy", code: "" },
    { id: 57, name: "Sky Sport Max", cat: "sky_sport", icon: "ph-fire", code: "" },
    { id: 60, name: "Sky Sport Mix", cat: "sky_sport", icon: "ph-shuffle", code: "" },
    { id: 61, name: "Sky Sport Golf", cat: "sky_sport", icon: "ph-flag", code: "" },
    { id: 62, name: "Sky Sport 251", cat: "sky_sport", icon: "ph-monitor-play", code: "" },
    { id: 63, name: "Sky Sport 252", cat: "sky_sport", icon: "ph-monitor-play", code: "" },
    { id: 64, name: "Sky Sport 253", cat: "sky_sport", icon: "ph-monitor-play", code: "" },
    { id: 65, name: "Sky Sport 254", cat: "sky_sport", icon: "ph-monitor-play", code: "" },
    { id: 66, name: "Sky Sport 255", cat: "sky_sport", icon: "ph-monitor-play", code: "" },
    { id: 67, name: "Sky Sport 256", cat: "sky_sport", icon: "ph-monitor-play", code: "" },
    { id: 68, name: "Sky Sport 257", cat: "sky_sport", icon: "ph-monitor-play", code: "" },
    { id: 69, name: "Sky Sport 258", cat: "sky_sport", icon: "ph-monitor-play", code: "" },
    { id: 70, name: "Sky Sport 259", cat: "sky_sport", icon: "ph-monitor-play", code: "" },

    { id: 41, name: "Sky Cinema Uno", cat: "sky_cinema", icon: "ph-film-strip", code: "" },
    { id: 56, name: "Sky Cinema Uno +24", cat: "sky_cinema", icon: "ph-film-strip", code: "" },
    { id: 57, name: "Sky Cinema Due", cat: "sky_cinema", icon: "ph-film-strip", code: "" },
    { id: 58, name: "Sky Cinema Due +24", cat: "sky_cinema", icon: "ph-film-strip", code: "" },
    { id: 32, name: "Sky Cinema Collection", cat: "sky_cinema", icon: "ph-film-strip", code: "" },
    { id: 45, name: "Sky Cinema Stories", cat: "sky_cinema", icon: "ph-book", code: "" },
    { id: 46, name: "Sky Cinema Family", cat: "sky_cinema", icon: "ph-baby", code: "" },
    { id: 44, name: "Sky Cinema Action", cat: "sky_cinema", icon: "ph-sword", code: "" },
    { id: 49, name: "Sky Cinema Suspense", cat: "sky_cinema", icon: "ph-eye", code: "" },
    { id: 48, name: "Sky Cinema Romance", cat: "sky_cinema", icon: "ph-heart", code: "" },
    { id: 47, name: "Sky Cinema Drama", cat: "sky_cinema", icon: "ph-masks-theater", code: "" },
    { id: 43, name: "Sky Cinema Comedy", cat: "sky_cinema", icon: "ph-smiley", code: "" },

    { id: 27, name: "Sky Uno", cat: "sky_intrattenimento", icon: "ph-television", code: "" },
    { id: 29, name: "Sky Atlantic", cat: "sky_intrattenimento", icon: "ph-television", code: "" },
    { id: 30, name: "Sky Serie", cat: "sky_intrattenimento", icon: "ph-film-slate", code: "" },
    { id: 31, name: "Sky Investigation", cat: "sky_intrattenimento", icon: "ph-detective", code: "" },
    { id: 34, name: "Sky Crime", cat: "sky_intrattenimento", icon: "ph-fingerprint", code: "" },
    { id: 38, name: "Sky Adventure", cat: "sky_intrattenimento", icon: "ph-mountains", code: "" },
    { id: 39, name: "MTV", cat: "sky_intrattenimento", icon: "ph-music-notes", code: "" },
    { id: 40, name: "Comedy Central", cat: "sky_intrattenimento", icon: "ph-smiley", code: "" },

    { id: 37, name: "Sky Arte", cat: "sky_intrattenimento", icon: "ph-paint-brush", code: "" },
    { id: 33, name: "Sky Documentaries", cat: "sky_intrattenimento", icon: "ph-book-open-text", code: "" },
    { id: 36, name: "Sky Nature", cat: "sky_intrattenimento", icon: "ph-tree", code: "" },
    { id: 78, name: "Discovery Channel", cat: "sky_intrattenimento", icon: "ph-globe", code: "" },
    { id: 35, name: "History Channel", cat: "sky_intrattenimento", icon: "ph-clock-counter-clockwise", code: "" },

    { id: 71, name: "Cartoon Network", cat: "kids", icon: "ph-television", code: "" },
    { id: 73, name: "Dea Kids", cat: "kids", icon: "ph-television", code: "" },
    { id: 74, name: "Nick Jr", cat: "kids", icon: "ph-television", code: "" },
    { id: 75, name: "Boomerang", cat: "kids", icon: "ph-television", code: "" },

    { id: 26, name: "Sky TG 24", cat: "sky_intrattenimento", icon: "ph-newspaper", code: "" }
];

let CATEGORIES = {
    all:                 { label: "Tutti i Canali",         icon: "ph-squares-four",         color: "#FFFF00" },
    digitale_terrestre:  { label: "Digitale Terrestre",     icon: "ph-broadcast",            color: "#E91E63" },
    sky_sport:           { label: "Sky Sport",              icon: "ph-soccer-ball",          color: "#00E676" },
    sky_intrattenimento: { label: "Sky Intrattenimento",    icon: "ph-television",           color: "#00BCD4" },
    sky_cinema:          { label: "Sky Cinema",             icon: "ph-film-strip",           color: "#9C27B0" },
    kids:                { label: "Bambini",                icon: "ph-baby",                 color: "#FF69B4" }
};

function getChannelsByCategory(cat) {
    if (cat === "all")  return CHANNELS;                     
    return CHANNELS.filter(c => c.cat === cat);   
}

function getChannelById(id) {
    return CHANNELS.find(c => c.id === parseInt(id));
}

function getStreamUrl(channel) {
    if (!channel || !channel.code) return "";
    return STREAM_BASE + channel.code;
}


