import re
import json
import joblib
import warnings
from pathlib import Path

import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.svm import LinearSVC
from sklearn.calibration import CalibratedClassifierCV
from Sastrawi.Stemmer.StemmerFactory import StemmerFactory
from Sastrawi.StopWordRemover.StopWordRemoverFactory import StopWordRemoverFactory
warnings.filterwarnings("ignore")

BASE_DIR = Path(__file__).resolve().parent
DATASET_ASLI_PATH = BASE_DIR / "dataset_sms_spam_v1.csv"
DATASET_DUMMY_PATH = BASE_DIR / "dummy_sms_1000.csv"
MODEL_PATH = BASE_DIR / "sms_model.pkl"
METRICS_PATH = BASE_DIR / "metrics.json"
LABEL_MAP_PATH = BASE_DIR / "label_map.json"

LABEL_MAP = {
    0: "Normal",
    1: "Penipuan",
    2: "Promo"
}

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
    "diskon"
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
    text = re.sub(r"http\S+|www\.\S+", " LINK ", text)

    # Nominal
    text = re.sub(r"\b\d{1,3}(?:[.,]\d{3})+(?:[.,]\d+)?\b", " NOMINAL ", text)

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


def prepare_dataframe(df: pd.DataFrame) -> pd.DataFrame:
    if "Teks" not in df.columns or "label" not in df.columns:
        raise ValueError("Dataset wajib memiliki kolom 'Teks' dan 'label'.")

    data = df[["Teks", "label"]].copy()
    data = data.dropna(subset=["Teks", "label"]).drop_duplicates()
    print("Preprocessing text...")
    data["clean_text"] = data["Teks"].apply(clean_text)

    print("Preprocessing selesai")
    data["avg_word_length"] = data["clean_text"].apply(avg_word_length)
    return data


def build_pipeline() -> Pipeline:
    preprocessor = ColumnTransformer(
        transformers=[
            ("tfidf", TfidfVectorizer(max_features=5000, ngram_range=(1, 2)), "clean_text"),
            ("avg_len", "passthrough", ["avg_word_length"]),
        ]
    )

    classifier = CalibratedClassifierCV(
        estimator=LinearSVC(dual=False, max_iter=5000, random_state=42),
        method="sigmoid",
        cv=3,
    )

    pipeline = Pipeline([
        ("preprocessor", preprocessor),
        ("classifier", classifier),
    ])
    return pipeline


def main():
    print("Membaca dataset...")
    raw_df_asli = pd.read_csv(DATASET_ASLI_PATH)
    raw_df_dummy = pd.read_csv(DATASET_DUMMY_PATH)

    raw_df = pd.concat([raw_df_asli, raw_df_dummy], ignore_index=True)
    data = prepare_dataframe(raw_df)

    X = data[["clean_text", "avg_word_length"]]
    y = data["label"].astype(int)

    X_train, X_test, y_train, y_test = train_test_split(
        X, y,
        test_size=0.2,
        random_state=42,
        stratify=y
    )

    print("Training model...")
    model = build_pipeline()
    model.fit(X_train, y_train)

    print("Evaluasi model...")
    y_pred = model.predict(X_test)

    acc = accuracy_score(y_test, y_pred)
    report = classification_report(
        y_test,
        y_pred,
        target_names=[LABEL_MAP[i] for i in sorted(LABEL_MAP)],
        output_dict=True,
        zero_division=0
    )
    cm = confusion_matrix(y_test, y_pred).tolist()

    metrics = {
        "accuracy": acc,
        "classification_report": report,
        "confusion_matrix": cm,
        "n_train": int(len(X_train)),
        "n_test": int(len(X_test)),
        "label_distribution": y.value_counts().sort_index().to_dict(),
    }

    joblib.dump(model, MODEL_PATH)
    with open(METRICS_PATH, "w", encoding="utf-8") as f:
        json.dump(metrics, f, indent=2, ensure_ascii=False)

    with open(LABEL_MAP_PATH, "w", encoding="utf-8") as f:
        json.dump(LABEL_MAP, f, indent=2, ensure_ascii=False)

    print(f"Model tersimpan di: {MODEL_PATH}")
    print(f"Metrics tersimpan di: {METRICS_PATH}")
    print(f"Accuracy: {acc:.4f}")
    print("\nClassification report:")
    print(classification_report(
        y_test,
        y_pred,
        target_names=[LABEL_MAP[i] for i in sorted(LABEL_MAP)],
        zero_division=0
    ))


if __name__ == "__main__":
    main()