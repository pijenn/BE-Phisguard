import re
import json
import sys
import argparse
import joblib
import pandas as pd
import random
from pathlib import Path
from datetime import datetime, UTC
from urllib.parse import urlparse
from Sastrawi.Stemmer.StemmerFactory import StemmerFactory
from Sastrawi.StopWordRemover.StopWordRemoverFactory import StopWordRemoverFactory

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "sms_model.pkl"
LABEL_MAP_PATH = BASE_DIR / "label_map.json"

with open(LABEL_MAP_PATH, "r", encoding="utf-8") as f:
    raw_label_map = json.load(f)
LABEL_MAP = {int(k): v for k, v in raw_label_map.items()}

MODEL = joblib.load(MODEL_PATH)

# Stemmer
stemmer_factory = StemmerFactory()
stemmer = stemmer_factory.create_stemmer()

# Stopwords
stop_factory = StopWordRemoverFactory()
STOPWORDS = set(stop_factory.get_stop_words())

# Kata penting phishing jangan dihapus
CUSTOM_KEEP = {
    "klik",
    "akun",
    "otp",
    "verifikasi",
    "hadiah",
    "saldo",
    "promo",
    "transfer",
    "bank",
    "pin",
    "login",
    "gratis",
    "diskon",
    "blokir",
    "klaim",
    "rekening",
    "validasi",
    "tautan",
    "link"
}

SLANG_DICT = {
    "gk": "tidak",
    "ga": "tidak",
    "nggak": "tidak",
    "ngga": "tidak",
    "kalo": "kalau",
    "aja": "saja",
    "udah": "sudah",
    "udh": "sudah",
    "skrg": "sekarang",
    "bgt": "banget",
    "km": "kamu",
    "sy": "saya",
    "dr": "dari",
    "utk": "untuk",
    "dpt": "dapat",
    "lg": "lagi",
    "tp": "tapi",
    "jd": "jadi"
}

SCAM_KEYWORDS = [
    "verifikasi", "otp", "pin", "blokir", "hadiah", "klaim", "rekening",
    "login", "akun", "transfer", "pajak", "validasi", "tautan", "link",
    "cs online", "bea cukai", "alamat salah", "aktivasi ulang", "saldo",
    "kurir", "pengiriman", "dana cepat", "langsung cair"
]

PROMO_KEYWORDS = [
    "promo", "cashback", "diskon", "voucher", "cicilan", "bonus",
    "transaksi berhasil", "potongan", "reward", "gratis admin", "informasi produk"
]

WHITELIST_ROOT_DOMAINS = [
    "cimbniaga.co.id",
    "octoclicks.co.id",
    "cimbocto.co.id",
    "cnaf.co.id",
    "deloitte-halo.com"
]

def normalize_slang(text: str) -> str:
    words = text.split()

    normalized = [
        SLANG_DICT.get(word, word)
        for word in words
    ]

    return " ".join(normalized)


def remove_stopwords(text: str) -> str:
    words = text.split()

    filtered = [
        word for word in words
        if (word not in STOPWORDS) or (word in CUSTOM_KEEP)
    ]

    return " ".join(filtered)


def stem_text(text: str) -> str:
    return stemmer.stem(text)

def clean_text(text: str) -> str:
    if pd.isna(text):
        return ""

    text = str(text).lower()

    # URL
    text = re.sub(
        r"(https?://\S+|www\.\S+|\b[a-zA-Z0-9-]+\.(com|id|ly|net|org|co)(/\S*)?\b)",
        " LINK ",
        text
    )

    # Nominal
    text = re.sub(
        r"\b\d{1,3}(?:[.,]\d{3})+(?:[.,]\d+)?\b",
        " NOMINAL ",
        text
    )
    # Angka
    text = re.sub(r"\d+", " ANGKA ", text)

    # Hapus simbol
    text = re.sub(r"[^a-zA-Z\s]", " ", text)

    # Rapihin spasi
    text = re.sub(r"\s+", " ", text).strip()

    # Slang normalization
    text = normalize_slang(text)

    # Stopword removal
    text = remove_stopwords(text)

    # Stemming
    text = stem_text(text)

    return text


def avg_word_length(text: str) -> float:
    words = text.split()
    if not words:
        return 0.0
    return sum(len(w) for w in words) / len(words)


def extract_urls(text: str):
    url_pattern = r"((?:https?://|www\.)?[a-zA-Z0-9-]+\.[a-zA-Z]{2,}(?:/[^\s]*)?)"
    return re.findall(url_pattern, text)


def get_domain(url: str) -> str:
    if not url.startswith("http"):
        url = "http://" + url
    parsed = urlparse(url)
    return parsed.netloc.replace("www.", "").lower()


def is_whitelisted(domain: str) -> bool:
    return any(
        domain == root or domain.endswith("." + root)
        for root in WHITELIST_ROOT_DOMAINS
    )


def analyze_urls(message: str):
    urls = extract_urls(message)

    if not urls:
        return {
            "has_url": False,
            "summary": "Tidak ada URL terdeteksi dalam pesan.",
            "results": []
        }

    results = []
    for url in urls:
        domain = get_domain(url)
        status = "verified" if is_whitelisted(domain) else "unverified"

        results.append({
            "url": url,
            "domain": domain,
            "status": status
        })

    if any(item["status"] == "unverified" for item in results):
        summary = "Terdapat URL yang tidak terverifikasi dalam whitelist resmi."
    elif all(item["status"] == "verified" for item in results):
        summary = "Seluruh URL yang terdeteksi sesuai dengan whitelist resmi."
    else:
        summary = "URL terdeteksi, tetapi status validasi belum dapat dipastikan."

    return {
        "has_url": True,
        "summary": summary,
        "results": results
    }


def build_explanations(original_text: str, predicted_label: int, url_analysis: dict):
    text = original_text.lower()
    reasons = []

    if any(k in text for k in SCAM_KEYWORDS):
        reasons.append("Terdeteksi kata kunci yang umum pada pesan penipuan.")

    if any(k in text for k in PROMO_KEYWORDS):
        reasons.append("Terdeteksi kata kunci promosi atau penawaran resmi.")

    if any(k in text for k in ["segera", "hari ini", "sekarang", "15 menit", "akan diblokir"]):
        reasons.append("Pesan bernada mendesak.")

    if any(k in text for k in ["otp", "pin", "verifikasi", "validasi data"]):
        reasons.append("Pesan meminta verifikasi data sensitif.")

    if url_analysis["has_url"]:
        if any(item["status"] == "unverified" for item in url_analysis["results"]):
            reasons.append("Terdapat URL yang tidak termasuk domain resmi whitelist.")
        elif all(item["status"] == "verified" for item in url_analysis["results"]):
            reasons.append("URL yang terdeteksi sesuai dengan domain resmi whitelist.")

    if not reasons:
        if predicted_label == 0:
            reasons.append("Tidak ditemukan pola kuat penipuan maupun promosi formal.")
        elif predicted_label == 1:
            reasons.append("Struktur pesan mirip pola penipuan pada data pelatihan.")
        else:
            reasons.append("Struktur pesan mirip notifikasi promosi atau penawaran resmi.")

    seen = set()
    unique_reasons = []
    for r in reasons:
        if r not in seen:
            unique_reasons.append(r)
            seen.add(r)
    return unique_reasons[:3]


def adjust_prediction(base_category: str, base_risk: float, url_analysis: dict, message: str):
    text = message.lower()
    adjusted_category = base_category
    adjusted_risk = base_risk

    has_verified = url_analysis["has_url"] and all(
        item["status"] == "verified" for item in url_analysis["results"]
    )
    has_unverified = url_analysis["has_url"] and any(
        item["status"] == "unverified" for item in url_analysis["results"]
    )

    # Jika URL tidak terverifikasi, tingkatkan risiko
    if has_unverified:
        adjusted_risk = min(adjusted_risk + 15, 100)

    # Jika URL resmi/verified, turunkan risiko karena tautannya valid
    if has_verified:
        adjusted_risk = max(adjusted_risk - 25, 0)

        # Jika kategori awal phishing tapi URL resmi dan konteks terlihat informatif/promosi,
        # turunkan menjadi promo/normal agar lebih masuk akal
        promo_like = any(k in text for k in [
            "informasi", "produk", "promo", "cashback", "diskon",
            "layanan", "notifikasi", "resmi", "terima kasih"
        ])

        if base_category == "Penipuan" and promo_like:
            adjusted_category = "Promo"

        elif base_category == "Penipuan" and adjusted_risk < 30:
            adjusted_category = "Normal"

    return adjusted_category, round(adjusted_risk, 2)


def predict_sms(message: str):
    clean = clean_text(message)
    features = pd.DataFrame({
        "clean_text": [clean],
        "avg_word_length": [avg_word_length(clean)],
    })

    pred_idx = int(MODEL.predict(features)[0])
    probs = MODEL.predict_proba(features)[0]
    scam_prob = float(probs[1]) if len(probs) > 1 else 0.0

    base_category = LABEL_MAP[pred_idx]
    base_risk = scam_prob * 100

    url_analysis = analyze_urls(message)
    final_category, final_risk = adjust_prediction(
        base_category=base_category,
        base_risk=base_risk,
        url_analysis=url_analysis,
        message=message
    )

    reasons = build_explanations(message, pred_idx, url_analysis)

    return {
        "kategori": final_category,
        "risk_score": final_risk,
        "confidence": round(float(probs[pred_idx]), 4),
        "probabilities": {
            LABEL_MAP[i]: round(float(probs[i]), 4) for i in range(len(probs))
        },
        "alasan": reasons,
        "url_analysis": url_analysis
    }


def map_label(kategori: str) -> str:
    mapping = {
        "Penipuan": "phishing",
        "Promo": "promo",
        "Normal": "normal"
    }
    return mapping.get(kategori, "unknown")


def get_priority(risk_score: float) -> str:
    if risk_score >= 70:
        return "high"
    elif risk_score >= 40:
        return "medium"
    else:
        return "low"

def generate_ticket():
    now = datetime.now()
    random_num = random.randint(1000, 9999)
    return f"PH-{now.strftime('%Y%m%d')}-{random_num}"

def predict_for_backend(message: str):
    result = predict_sms(message)
    ticket = generate_ticket()
    report_id = random.randint(1, 999999)
    now = datetime.now(UTC).isoformat()

    return {
        "message": "Report submitted successfully",
        "ticket": ticket,
        "ml_result": {
            "report_id": report_id,
            "label": map_label(result["kategori"]),
            "risk_score": int(round(result["risk_score"])),
            "priority": get_priority(result["risk_score"]),
            "reason": result["alasan"][0] if result["alasan"] else "Tidak ada alasan tersedia",
            "updated_at": now,
            "created_at": now,
            "id": report_id
        },
        "url_analysis": result["url_analysis"]
    }


def map_label_for_db(kategori: str) -> str:
    if kategori == "Penipuan":
        return "phishing"
    return "non-phishing"


def predict_for_laravel(message: str):
    result = predict_sms(message)
    reasons = result.get("alasan", [])

    return {
        "label": map_label_for_db(result["kategori"]),
        "risk_score": int(round(result["risk_score"])),
        "priority": get_priority(result["risk_score"]),
        "reason": reasons[0] if reasons else "Tidak ada alasan tersedia",
        "kategori": result["kategori"],
        "url_analysis": result["url_analysis"],
    }


def parse_args():
    parser = argparse.ArgumentParser(description="Predict SMS phishing category")
    parser.add_argument("--message", type=str, help="SMS message to predict")
    parser.add_argument("--output", type=str, choices=["json", "pretty"], default="pretty")
    parser.add_argument("--interactive", action="store_true", help="Run interactive mode")
    return parser.parse_args()


if __name__ == "__main__":
    args = parse_args()

    if args.message:
        prediction = predict_for_laravel(args.message)
        if args.output == "json":
            print(json.dumps(prediction, ensure_ascii=False))
        else:
            print(json.dumps(prediction, indent=2, ensure_ascii=False))
        sys.exit(0)

    if args.interactive or not args.message:
        while True:
            msg = input("Masukkan isi SMS (ketik 'exit' untuk keluar): ")
            if msg.lower() == "exit":
                break

            print("=" * 80)
            print("Pesan:", msg)
            print(json.dumps(predict_for_backend(msg), indent=2, ensure_ascii=False))