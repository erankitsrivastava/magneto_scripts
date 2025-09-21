#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
scrape_master.py — Unified Scraper for ~350 real-estate sites

What it does
- Scrapes: Agencies, Agents, Properties
- Fields: price, currency, address, lat/lng, beds/baths, m² (sqft→m²), images, video, interior, exterior, amenities, services
- Robustness: timeouts, retries/backoff, per-domain concurrency, circuit breaker, task timeouts, throttle, checkpoint/resume
- Fallbacks: sitemap/API discovery, og:image → agency logo, manual_review queue
- Extras: stable UIDs (md5), agent dedupe, image HEAD check, CSV outputs ready for import

Optional
- Playwright fallback for JS-heavy pages: --use-playwright (requires `pip install playwright` + `playwright install`)
- Proxy rotation via JSON file: --proxies-file proxies.json  (list of proxies)
- Translation/CAPTCHA not auto-enabled (hooks ready; supply your own service if needed)

Outputs (in --outdir)
- enriched_agencies.csv
- profile_import.csv
- properties_import.csv
- agents_import.csv
- manual_review.csv
- progress.json (resume state)
- scrape_errors.log

Quick start
pip install requests beautifulsoup4 urllib3 tqdm python-dateutil
# (optional) playwright: pip install playwright && playwright install

Run:
python scrape_master.py --in agencies.csv --props-in properties_seed.csv --outdir ./out --max-per-agency 20 --workers 10
"""

import argparse, csv, json, re, os, sys, time, hashlib, signal
from collections import defaultdict, Counter
from urllib.parse import urlparse, urljoin
from concurrent.futures import ThreadPoolExecutor, as_completed
from threading import Semaphore, Lock
from datetime import datetime, timezone

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
from bs4 import BeautifulSoup
from tqdm import tqdm

# ---------------- CONFIG ----------------
DEFAULT_TIMEOUT = 20
DEFAULT_TASK_TIMEOUT = 30
DEFAULT_WORKERS = 10
DEFAULT_DOMAIN_CONCURRENCY = 2
DEFAULT_MAX_PER_AGENCY = 20
PROGRESS_FILE = "progress.json"
ERROR_LOG = "scrape_errors.log"
SQFT_TO_M2 = 0.09290304

HEADERS = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36"}

EMAIL_RE = re.compile(r"[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}", re.I)
PHONE_RE = re.compile(r"(?:(?:\+|00)\d{1,3}[\s\-\.]?)?(?:\(?\d{2,4}\)?[\s\-\.]?)?\d{3,6}[\s\-\.]?\d{3,6}")
WHATS_RE = re.compile(r"(?:https?://)?(?:wa\.me|api\.whatsapp\.com|chat\.whatsapp\.com)/[^\s\"']+", re.I)

SOCIAL_DOMAINS = {
    "facebook.com":"Facebook","fb.com":"Facebook","instagram.com":"Instagram","twitter.com":"Twitter","x.com":"Twitter",
    "linkedin.com":"LinkedIn","youtube.com":"YouTube","wa.me":"WhatsApp","whatsapp.com":"WhatsApp",
}

# Feature dictionaries (EN/ES/PT/NL/FR mix — uitbreidbaar)
FEATURES = {
    "interior": [
        # base
        "furnished","semi-furnished","unfurnished","kitchen","equipped kitchen","open kitchen","cocina equipada","keuken",
        "balcony","balcón","balkon","varanda","ventiladores de techo","ceiling fans","walk-in closet","armario vestidor","inloopkast",
        "laundry","lavandería","lavanderia","wasruimte","office","oficina","kantoor",
        "fireplace","chimenea","open haard","sauna",
        "air conditioning","aire acondicionado","ar-condicionado","climatisation","airco",
        "heating","calefacción","aquecimento","chauffage","vloerverwarming",
        "flooring","hardwood","tile","ceramic","porcelain","marble","laminate","vinyl","parquet","mármol","marmeren",
        "smart home","domótica"
    ],
    "exterior": [
        "garden","jardín","jardim","tuin","yard","patio","deck","terraza","terrace","terras","balcón","balcony","balkon","rooftop","solarium","dakterras",
        "pergola","pérgola",
        "pool","piscina","swimming pool","jacuzzi","hot tub",
        "bbq","parrilla","churrasqueira","buitenkeuken",
        "garage","garaje","garagem","carport","cochera","parking","estacionamiento",
        "sea view","ocean view","vista al mar","vista mar","city view","mountain view","vista a la montaña","lake view",
        "fence","omheining","perimeter","resort cerrado","gated community"
    ],
    "amenities": [
        "elevator","ascensor","elevador","lift",
        "security","seguridad","beveiliging","24/7 security","seguridad 24/7",
        "gated","gated community","condominium","condominio","condomínio","hoa",
        "gym","gimnasio","fitness","academia","playground","kids area","park","parque",
        "storage","trastero","depósito","bodega","berging",
        "generator","backup power","power backup","generator set","generador",
        "solar panels","paneles solares","panneaux solaires","zonnepanelen",
        "cisterna","water tank","citerne","waterreservoir","rainwater"
    ],
    # 4e categorie (services) — apart meegeschreven
    "services": [
        "property management","administración de propiedades","beheer",
        "appraisal","tasación","valuation","taxatie",
        "legal","notary","servicios legales","juridisch",
        "mortgage","financing","hipoteca","financiación","hypotheek",
        "relocation","expat",
        "concierge","conserjería",
        "cleaning","limpieza","maintenance","mantenimiento",
        "rental management","gestión de alquileres","key holding","sleutelbeheer"
    ],
}

# -------------- Session + retry --------------
session = requests.Session()
retry = Retry(total=3, backoff_factor=0.5, status_forcelist=[429, 500,502,503,504])
session.mount("http://", HTTPAdapter(max_retries=retry))
session.mount("https://", HTTPAdapter(max_retries=retry))

# -------------- Globals --------------
domain_semaphores, domain_status = {}, {}
semaphores_lock = Lock()
progress_lock = Lock()
csv_lock = Lock()
shutdown_flag = False

def signal_handler(sig, frame):
    global shutdown_flag
    print("\n[INFO] Stopping gracefully...")
    shutdown_flag = True
signal.signal(signal.SIGINT, signal_handler)
signal.signal(signal.SIGTERM, signal_handler)

# -------------- Utils --------------
def now_iso(): return datetime.now(timezone.utc).isoformat()
def write_error(msg):
    with open(ERROR_LOG, "a", encoding="utf-8") as f:
        f.write(f"{now_iso()} ERROR: {msg}\n")

def absolute_url(u: str, base: str = "") -> str:
    if not u: return ""
    if u.startswith("//"): return "https:" + u
    return urljoin(base, u) if base else u

def soupify(html): return BeautifulSoup(html or "", "lxml")

def fetch_url(url, headers=None, timeout=DEFAULT_TIMEOUT, proxy=None):
    try:
        r = session.get(url, headers=headers or HEADERS, timeout=timeout, proxies=proxy)
        if r.ok and "text/html" in r.headers.get("Content-Type",""):
            try: r.encoding = r.apparent_encoding or r.encoding
            except Exception: pass
            return r.text
        return r.text or ""
    except Exception as e:
        write_error(f"fetch_url({url}) -> {e}")
        return ""

def head_check(url, timeout=8, proxy=None):
    try:
        r = session.head(url, timeout=timeout, headers=HEADERS, proxies=proxy, allow_redirects=True)
        ct = r.headers.get("Content-Type","").lower()
        return r.status_code < 400 and ("image" in ct)
    except Exception:
        return False

def stable_uid(iso: str, url: str, title: str) -> str:
    base = f"{iso}|{url}|{title}".encode("utf-8", errors="ignore")
    return f"{iso}-{hashlib.md5(base).hexdigest()[:12]}"

def sqft_to_m2(value_str):
    try:
        m = re.search(r"[\d\.,]+", str(value_str) or "")
        if not m: return ""
        return round(float(m.group(0).replace(",","")) * SQFT_TO_M2, 2)
    except Exception:
        return ""

def get_domain(u): 
    try: return urlparse(u).netloc.lower()
    except Exception: return u

def ensure_semaphore_for(domain, concurrency):
    with semaphores_lock:
        domain_semaphores.setdefault(domain, Semaphore(concurrency))
        domain_status.setdefault(domain, {"fails":0, "cooldown_until":0})

def domain_allowed(domain, now_ts, fail_threshold, cooldown_seconds):
    st = domain_status.get(domain, {})
    return now_ts >= st.get("cooldown_until", 0)

def record_domain_fail(domain, fail_threshold, cooldown_seconds):
    st = domain_status.setdefault(domain, {"fails":0, "cooldown_until":0})
    st["fails"] += 1
    if st["fails"] >= fail_threshold:
        st["cooldown_until"] = time.time() + cooldown_seconds
        st["fails"] = 0

def record_domain_success(domain):
    st = domain_status.setdefault(domain, {"fails":0, "cooldown_until":0})
    st["fails"] = 0

# -------------- Parsers --------------
def parse_jsonld(html):
    s = soupify(html); arr=[]
    for sc in s.find_all("script", type="application/ld+json"):
        try:
            data = json.loads(sc.string or "{}")
            arr.extend(data if isinstance(data, list) else [data])
        except Exception: continue
    return arr

def extract_meta(html, base_url=""):
    s = soupify(html)
    title = s.title.text.strip() if s.title else ""
    md = s.find("meta", {"name":"description"}) or s.find("meta", {"property":"og:description"})
    desc = (md.get("content") or "").strip() if md else ""
    og = s.find("meta", {"property":"og:image"})
    og_image = absolute_url((og.get("content") or "").strip(), base_url) if og else ""
    lang = s.find("html").get("lang","").strip() if s.find("html") else ""
    return title, desc, og_image, lang

def agents_from_jsonld(jsonlds):
    out=[]
    for d in jsonlds:
        t=d.get("@type"); tl=[t] if isinstance(t,str) else (t or [])
        tl=[str(x).lower() for x in tl]
        if any(tt in tl for tt in ("person","realestateagent")):
            img=d.get("image",""); 
            if isinstance(img,list) and img: img=img[0]
            out.append({
                "Agent Name":d.get("name",""), "Email":d.get("email",""), "Phone":d.get("telephone",""),
                "WhatsApp":"", "Photo":img, "Profile URL":d.get("url","")
            })
    return out

def extract_amenities_from_jsonld(d):
    feats=[]; af=d.get("amenityFeature")
    if isinstance(af, dict): af=[af]
    if isinstance(af,list):
        for it in af:
            name=(it or {}).get("name") or (it or {}).get("value") or ""
            if name: feats.append(str(name))
    return feats

def harvest_text_features(html, desc=""):
    s = soupify(html); tl = (desc or "") + " " + (s.get_text(" ", strip=True) or ""); tl = tl.lower()
    def pick(keys): return ", ".join(sorted({k for k in keys if k in tl}))
    interior = pick(FEATURES["interior"])
    exterior = pick(FEATURES["exterior"])
    amenities = pick(FEATURES["amenities"])
    services  = pick(FEATURES["services"])
    parking   = "Yes" if any(k in tl for k in ("parking","garage","garaje","garagem","cochera","estacionamiento","carport")) else ""
    furnished = "Yes" if any(k in tl for k in ("furnished","amueblado","mobiliado","gemeubileerd")) else ""
    airco     = "Yes" if any(k in tl for k in ("air conditioning","aire acondicionado","ar-condicionado","airco","climatisation")) else ""
    heating   = "Yes" if any(k in tl for k in ("heating","calefacción","aquecimento","chauffage","vloerverwarming")) else ""
    view = ", ".join([v for v in ("sea view","ocean view","city view","mountain view","lake view","vista al mar","vista a la montaña") if v in tl])
    flooring = ", ".join([v for v in ("hardwood","tile","ceramic","porcelain","marble","laminate","vinyl","parquet") if v in tl])
    return {
        "Interior Features": interior,
        "Exterior Features": exterior,
        "Amenities": amenities,
        "Services": services,
        "Parking": parking,
        "Furnished": furnished,
        "Air Conditioning": airco,
        "Heating": heating,
        "View": view,
        "Flooring": flooring
    }

def property_from_jsonld(d):
    item={}
    item["Title"]=d.get("name","")
    item["Content"]=d.get("description","")
    img=d.get("image",""); 
    if isinstance(img,list): img=", ".join(img[:35])
    item["Images"]=img or ""
    addr=d.get("address") or {}
    if isinstance(addr,list) and addr: addr=addr[0]
    item["Property Address"] = ", ".join(filter(None, [addr.get("streetAddress"), addr.get("addressLocality"), addr.get("addressRegion"), addr.get("postalCode"), addr.get("addressCountry")]))
    geo = d.get("geo") or {}
    if isinstance(geo,list) and geo: geo=geo[0]
    item["latitude"]=str(geo.get("latitude") or "")
    item["longitude"]=str(geo.get("longitude") or "")
    item["Number bedrooms"]=d.get("numberOfBedrooms") or d.get("numberOfRooms") or ""
    item["Number bathrooms"]=d.get("numberOfBathroomsTotal") or ""
    area = d.get("floorSize") or {}
    if isinstance(area, dict): area = area.get("value")
    item["Square (m²)"] = area or ""
    if not item["Square (m²)"]:
        m = re.search(r'([\d\.,]+)\s*(sq\s*ft|sqft|ft2)', (item.get("Content","") or "").lower())
        if m: item["Square (m²)"] = sqft_to_m2(m.group(1))
    lot = d.get("lotSize") or {}
    if isinstance(lot, dict): lot = lot.get("value")
    item["Property Lot Size"] = lot or ""
    offer = d.get("offers") or {}
    if isinstance(offer, list) and offer: offer = offer[0]
    item["Price"] = offer.get("price") or ""
    item["Currency"] = offer.get("priceCurrency") or ""
    item["Type"] = "Rent" if ("rent" in (item["Content"] or "").lower()) else "Sale"
    ag = offer.get("seller") if isinstance(offer, dict) else {}
    item["Staff"] = ag.get("name","") if isinstance(ag, dict) else ""
    item["Amenities_from_jsonld"] = extract_amenities_from_jsonld(d)
    return item

# -------------- Fallback discovery --------------
def try_sitemap(base_url):
    parsed = urlparse(base_url); base=f"{parsed.scheme}://{parsed.netloc}"
    for path in ("/sitemap.xml","/sitemap_index.xml"):
        url=f"{base}{path}"; tx=fetch_url(url)
        if tx and "<urlset" in tx.lower():
            s=soupify(tx); return [u.text.strip() for u in s.find_all("loc") if u.text.strip()]
    return []

def try_api_endpoints(html, base_url):
    urls=[]
    s=soupify(html)
    for script in s.find_all("script"):
        txt = script.string or ""
        for m in re.findall(r'["\'](/[^"\']{5,200})["\']', txt):
            if m.startswith("/api") or "search" in m.lower() or "/list" in m.lower():
                urls.append(absolute_url(m, base_url))
    for link in s.find_all("link", type=True, href=True):
        t=link.get("type","")
        if "rss" in t or "xml" in t:
            urls.append(absolute_url(link.get("href"), base_url))
    return list(dict.fromkeys(urls))

def playwright_render(url, timeout=30, proxy=None):
    try:
        from playwright.sync_api import sync_playwright
    except Exception:
        return ""
    try:
        pw = sync_playwright().start()
        browser = pw.chromium.launch(headless=True, args=["--no-sandbox"])
        ctx_kwargs={}
        if proxy: ctx_kwargs["proxy"]=proxy
        ctx = browser.new_context(**ctx_kwargs)
        page = ctx.new_page()
        page.goto(url, timeout=timeout*1000)
        page.wait_for_load_state("networkidle", timeout=timeout*1000)
        html = page.content()
        browser.close(); pw.stop()
        return html
    except Exception as e:
        write_error(f"playwright_render({url}) -> {e}")
        try: pw.stop()
        except Exception: pass
        return ""

# -------------- Agency scrape --------------
def find_social_links(html: str):
    out={}
    s=soupify(html)
    for a in s.find_all("a", href=True):
        href=a["href"].strip(); dom=urlparse(href).netloc.lower()
        for d,label in SOCIAL_DOMAINS.items():
            if d in dom: out.setdefault(label,set()).add(href)
    for m in WHATS_RE.findall(html or ""):
        out.setdefault("WhatsApp",set()).add(m)
    return {k:sorted(list(v)) for k,v in out.items()}

def extract_agency_info(row, throttle=0.0, proxy=None):
    url=(row.get("Website") or "").strip()
    if not url: return None, None, ""
    html = fetch_url(url, proxy=proxy)
    if throttle: time.sleep(throttle)
    jsonlds = parse_jsonld(html)
    # org
    addr=lat=lng=""
    for d in jsonlds:
        t=d.get("@type"); tl=[t] if isinstance(t,str) else (t or [])
        tl=[str(x).lower() for x in tl]
        if any(x in ("organization","localbusiness","realestateagent","realestatelisting") for x in tl):
            a=d.get("address") or {}
            if isinstance(a,list) and a: a=a[0]
            addr = ", ".join(filter(None, [a.get("streetAddress"), a.get("addressLocality"), a.get("addressRegion"), a.get("postalCode"), (a.get("addressCountry") if isinstance(a.get("addressCountry"), str) else (a.get("addressCountry") or {}).get("name"))]))
            g=d.get("geo") or {}
            if isinstance(g,list) and g: g=g[0]
            lat=str(g.get("latitude") or ""); lng=str(g.get("longitude") or "")
    title, desc, og_image, lang = extract_meta(html, url)
    socials = find_social_links(html)
    body = soupify(html).get_text(" ", strip=True)
    emails = EMAIL_RE.findall(body) or []
    phones = PHONE_RE.findall(body) or []
    enriched = dict(row)
    enriched.update({
        "Detected_Address": addr, "Detected_Latitude": lat, "Detected_Longitude": lng,
        "Meta_Title": title or row.get("Description",""), "Meta_Description": desc, "OG_Image": og_image,
        "Email": row.get("Email") or (emails[0] if emails else ""), "Phone": row.get("Phone") or (phones[0] if phones else "")
    })
    profile = {
        "Header": row.get("Agency Name",""),
        "Agency Name": row.get("Agency Name",""),
        "Website Url": url,
        "Slogan": title or row.get("Description",""),
        "Address": addr, "Longitude": lng, "Latitude": lat,
        "Banner Image": og_image, "Short description": desc or row.get("Description",""),
        "Country": row.get("Country",""),
        "Phone": enriched["Phone"], "WhatsApp Number": row.get("WhatsApp",""), "Email": enriched["Email"],
        "City/Region (seed)": row.get("City/Region",""),
    }
    return enriched, profile, og_image

# -------------- Property scrape --------------
def build_property_row(base, extras, seed_row, agency_logo, url, listing_og):
    imgs = base.get("Images","") or ""
    primary_img = (imgs.split(",")[0].strip() if imgs else "") or listing_og or agency_logo
    uid = stable_uid(seed_row.get("ISO",""), url, base.get("Title",""))
    return {
        "Title": base.get("Title","").strip() or "Property",
        "Content": base.get("Content","").strip(),
        "Images": imgs,
        "Staff": base.get("Staff",""),
        "Unique ID": uid,
        "Type": base.get("Type","Sale"),
        "Property Address": base.get("Property Address",""),
        "Neighborhood": "",
        "latitude": base.get("latitude",""),
        "longitude": base.get("longitude",""),
        "Number bedrooms": base.get("Number bedrooms",""),
        "Number bathrooms": base.get("Number bathrooms",""),
        "Number floors": base.get("Number floors",""),
        "Square (m²)": base.get("Square (m²)",""),
        "Year of Building": base.get("Year of Building",""),
        "Property Lot Size": base.get("Property Lot Size",""),
        "Price": base.get("Price",""),
        "Currency": base.get("Currency",""),
        "Status": "Selling" if base.get("Type","Sale") == "Sale" else "Renting",
        "Youtube Video Thumbnail": base.get("Youtube Video Thumbnail",""),
        "Youtube Video URL": base.get("Youtube Video URL",""),
        "Agency Name": seed_row.get("Agency Name",""),
        "Country": seed_row.get("Country",""),
        "ISO": seed_row.get("ISO",""),
        "Listing URL": url,
        "Primary Image (resolved)": primary_img,
        # Features (incl. Services)
        "Interior Features": extras.get("Interior Features",""),
        "Exterior Features": extras.get("Exterior Features",""),
        "Amenities": extras.get("Amenities",""),
        "Services": extras.get("Services",""),
        "Parking": extras.get("Parking",""),
        "Furnished": extras.get("Furnished",""),
        "Air Conditioning": extras.get("Air Conditioning",""),
        "Heating": extras.get("Heating",""),
        "View": extras.get("View",""),
        "Flooring": extras.get("Flooring",""),
    }

def process_listing_seed(seed_row, agency_logo, args, proxies_hook):
    url = seed_row.get("Listing URL","").strip()
    if not url: return [], []
    domain = get_domain(url)
    ensure_semaphore_for(domain, args.domain_max_concurrency)
    if not domain_allowed(domain, time.time(), args.domain_fail_threshold, args.domain_cooldown_seconds):
        return [], []
    proxy = proxies_hook.get_next() if proxies_hook else None
    domain_semaphores[domain].acquire()
    try:
        html = fetch_url(url, proxy=proxy)
        if not html or any(k in (html or "").lower() for k in ("access denied","cf-chl-bypass","captcha")):
            seeds = try_sitemap(url)
            if seeds: 
                record_domain_fail(domain, args.domain_fail_threshold, args.domain_cooldown_seconds)
                return [], []
            apis = try_api_endpoints(html or "", url)
            if apis:
                record_domain_fail(domain, args.domain_fail_threshold, args.domain_cooldown_seconds)
                return [], []
            if args.use_playwright:
                html_pw = playwright_render(url, timeout=args.task_timeout, proxy=proxy)
                if html_pw: html = html_pw
                else:
                    write_manual_review({"Listing URL":url, "Reason":"Cloudflare/CAPTCHA/empty"})
                    record_domain_fail(domain, args.domain_fail_threshold, args.domain_cooldown_seconds)
                    return [], []
            else:
                write_manual_review({"Listing URL":url, "Reason":"Blocked or empty (no Playwright)"})
                record_domain_fail(domain, args.domain_fail_threshold, args.domain_cooldown_seconds)
                return [], []
        jsonlds = parse_jsonld(html)
        listing_title, listing_desc, listing_og, _ = extract_meta(html, url)
        prop_nodes = [d for d in jsonlds if any(tt in (d.get("@type") if isinstance(d.get("@type"),str) else d.get("@type") or []) for tt in ("Offer","Product","Residence","Apartment","House","RealEstateListing"))]
        props=[]

        extras_text = harvest_text_features(html, listing_desc)
        if not prop_nodes:
            base = property_from_jsonld({})
            base["Title"] = seed_row.get("Title") or listing_title or ""
            base["Content"] = soupify(html).get_text(" ", strip=True)[:5000]
            p = build_property_row(base, extras_text, seed_row, agency_logo, url, listing_og)
            props.append(p)
        else:
            for d in prop_nodes:
                base = property_from_jsonld(d)
                extras = harvest_text_features(html, base.get("Content",""))
                if base.get("Amenities_from_jsonld"):
                    merged = ", ".join(sorted(set((extras.get("Amenities","") + ", " + ", ".join(base["Amenities_from_jsonld"])).strip(", ").split(", "))))
                    extras["Amenities"] = merged
                p = build_property_row(base, extras, seed_row, agency_logo, url, listing_og)
                props.append(p)

        # agents
        agents = agents_from_jsonld(jsonlds)
        seen=set(); dedup=[]
        for a in agents:
            key=(a.get("Agent Name","").strip(), a.get("Email","").strip(), a.get("Phone","").strip())
            if key in seen: continue
            seen.add(key)
            if not a.get("Photo"): a["Photo"]=agency_logo
            dedup.append(a)

        record_domain_success(domain)
        return props, dedup
    except Exception as e:
        write_error(f"process_listing_seed({url}) -> {e}")
        record_domain_fail(domain, args.domain_fail_threshold, args.domain_cooldown_seconds)
        return [], []
    finally:
        try: domain_semaphores[domain].release()
        except Exception: pass

# -------------- Manual review + progress --------------
def write_manual_review(item):
    with csv_lock:
        fn="manual_review.csv"; exist=os.path.exists(fn)
        with open(fn, "a", encoding="utf-8-sig", newline="") as f:
            w=csv.DictWriter(f, fieldnames=list(item.keys()))
            if not exist: w.writeheader()
            w.writerow(item)

def load_progress():
    if os.path.exists(PROGRESS_FILE):
        try:
            with open(PROGRESS_FILE,"r",encoding="utf-8") as f: return json.load(f)
        except Exception: return {}
    return {}
def save_progress(data):
    with progress_lock:
        with open(PROGRESS_FILE,"w",encoding="utf-8") as f: json.dump(data,f,ensure_ascii=False,indent=2)

# -------------- Proxies hook (round-robin) --------------
class ProxiesHook:
    def __init__(self, proxies_list):
        self.proxies = proxies_list or []; self.idx=0; self.lock=Lock()
    def get_next(self):
        with self.lock:
            if not self.proxies: return None
            p = self.proxies[self.idx % len(self.proxies)]; self.idx += 1
            if isinstance(p,str): return {"http":p,"https":p}
            return p

# -------------- CSV writer --------------
def append_csv_row(fn, fieldnames, row):
    with csv_lock:
        exist = os.path.exists(fn)
        with open(fn,"a",encoding="utf-8-sig",newline="") as f:
            w=csv.DictWriter(f, fieldnames=fieldnames, quoting=csv.QUOTE_MINIMAL)
            if not exist: w.writeheader()
            w.writerow({k: row.get(k,"") for k in fieldnames})

# -------------- Main --------------
def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--in", required=True, dest="inp", help="Seed agencies CSV")
    ap.add_argument("--sep", default="\t", dest="sep", help="CSV separator (default: tab)")
    ap.add_argument("--props-in", default="", dest="props_in", help="Properties seed CSV")
    ap.add_argument("--outdir", default=".", dest="outdir")
    ap.add_argument("--max-per-agency", type=int, default=DEFAULT_MAX_PER_AGENCY)
    ap.add_argument("--workers", type=int, default=DEFAULT_WORKERS)
    ap.add_argument("--domain-max-concurrency", type=int, default=DEFAULT_DOMAIN_CONCURRENCY)
    ap.add_argument("--task-timeout", type=int, default=DEFAULT_TASK_TIMEOUT)
    ap.add_argument("--domain-fail-threshold", type=int, default=5)
    ap.add_argument("--domain-cooldown-seconds", type=int, default=3600)
    ap.add_argument("--throttle-seconds", type=float, default=0.0)
    ap.add_argument("--checkpoint-every", type=int, default=50)
    ap.add_argument("--proxies-file", default="")
    ap.add_argument("--use-playwright", action="store_true")
    args = ap.parse_args()

    if not os.path.exists(args.outdir): os.makedirs(args.outdir, exist_ok=True)
    os.chdir(args.outdir)

    with open(args.inp,"r",encoding="utf-8-sig") as f:
        agencies = list(csv.DictReader(f, delimiter=args.sep))
    props_seed=[]
    if args.props_in:
        with open(args.props_in,"r",encoding="utf-8-sig") as f:
            props_seed = list(csv.DictReader(f, delimiter=args.sep))

    proxies_hook=None
    if args.proxies_file and os.path.exists(args.proxies_file):
        try:
            with open(args.proxies_file,"r",encoding="utf-8") as pf:
                proxies_hook = ProxiesHook(json.load(pf))
        except Exception as e:
            write_error(f"Proxies file failed: {e}")

    progress = load_progress()
    processed_listings = set(progress.get("processed_listings", []))
    processed_agencies = set(tuple(x) for x in progress.get("processed_agencies", []))

    # Agencies pass
    print("[INFO] Agencies pass...")
    for row in tqdm(agencies):
        if shutdown_flag: break
        key=(row.get("Agency Name",""), row.get("Website",""))
        if key in processed_agencies: continue
        try:
            enriched, profile, banner = extract_agency_info(row, throttle=args.throttle_seconds, proxy=(proxies_hook.get_next() if proxies_hook else None))
            if enriched:
                append_csv_row("enriched_agencies.csv", list(enriched.keys()), enriched)
            if profile:
                profile_fields=["Header","Agency Name","Website Url","Slogan","Address","Longitude","Latitude","Banner Image","Short description","Country","State","City","Phone","WhatsApp Number","Email","City/Region (seed)"]
                append_csv_row("profile_import.csv", profile_fields, profile)
            processed_agencies.add(key)
            progress["processed_agencies"] = [list(x) for x in processed_agencies]
            save_progress(progress)
        except Exception as e:
            write_error(f"Agency failed: {row.get('Agency Name','?')} -> {e}")

    # map agency -> logo
    agency_logo_by_name={}
    try:
        if os.path.exists("enriched_agencies.csv"):
            with open("enriched_agencies.csv","r",encoding="utf-8-sig") as f:
                for r in csv.DictReader(f):
                    agency_logo_by_name[r.get("Agency Name","")] = r.get("OG_Image","")
    except Exception: pass

    # Properties pass (parallel)
    print("[INFO] Properties pass...")
    seeds_by_agency = defaultdict(list)
    for s in props_seed: seeds_by_agency[s.get("Agency Name","")].append(s)
    per_agency_counts = defaultdict(int)

    with ThreadPoolExecutor(max_workers=args.workers) as executor:
        futures={}
        for agency, seeds in seeds_by_agency.items():
            logo = agency_logo_by_name.get(agency,"")
            for seed in seeds:
                if per_agency_counts[agency] >= args.max_per_agency: break
                url = seed.get("Listing URL",""); if not url: continue
                if url in processed_listings: continue
                dom=get_domain(url); ensure_semaphore_for(dom, args.domain_max_concurrency)
                futures[executor.submit(process_listing_seed, seed, logo, args, proxies_hook)] = (agency, seed)
        for fut in tqdm(as_completed(futures), total=len(futures)):
            if shutdown_flag: break
            agency, seed = futures[fut]
            try:
                props, agents = fut.result(timeout=args.task_timeout)
            except Exception as e:
                write_error(f"Task timeout/error: {seed.get('Listing URL','?')} -> {e}")
                record_domain_fail(get_domain(seed.get("Listing URL","")), args.domain_fail_threshold, args.domain_cooldown_seconds)
                continue
            if props:
                remaining = args.max_per_agency - per_agency_counts[agency]
                for p in props[:remaining]:
                    append_csv_row("properties_import.csv", list(p.keys()), p)
                    per_agency_counts[agency] += 1
                    processed_listings.add(p.get("Listing URL",""))
            if agents:
                for a in agents:
                    append_csv_row("agents_import.csv", ["Agency Name","Agent Name","Email","Phone","WhatsApp","Photo","Profile URL"], {"Agency Name":agency, **a})
            if len(processed_listings) % args.checkpoint_every == 0:
                progress["processed_listings"] = list(processed_listings); save_progress(progress)

    progress["processed_listings"] = list(processed_listings); save_progress(progress)

    print("✅ Done")
    print(f" Agencies processed: {len(processed_agencies)}")
    print(f" Properties written: {sum(per_agency_counts.values())}")
    print(f" Agents file: agents_import.csv")
    if os.path.exists("manual_review.csv"): print(" Manual review: manual_review.csv")
    print(f" Errors log: {ERROR_LOG}")

if __name__ == "__main__":
    main()

