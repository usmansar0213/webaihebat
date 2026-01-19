import streamlit as st
import pandas as pd
from pathlib import Path

# Path file HTML asli (gunakan raw string untuk Windows path)
html_path = Path(r"C:\integrasi\post\artikel-investasi-ai.html")

# Cek apakah file HTML asli ada
if not html_path.exists():
    st.error(f"File HTML asli tidak ditemukan: {html_path}")
    st.stop()

# Load file HTML asli
with open(html_path, "r", encoding="utf-8") as f:
    html_lines = f.readlines()

# Hardcode struktur kode HTML (template)
data_input = {
    "Nomor Baris": [7, 8, 9, 112, 121, 129, 130, 143, 146, 147, 150, 151, 155, 157, 158, 159, 160, 163, 164, 173, 174, 175, 176, 177, 223, 229, 235, 271, 274],
    "Struktur Code (Template)": [
        "<title>{{user_input}}</title>",
        '<meta name="description" content="{{user_input}}">',
        '<meta name="keywords" content="{{user_input}}">',
        '<h1 class="title">{{user_input}}</h1>',
        '<h4>{{user_input}}</h4>',
        '<div class="tags-section">{{user_input}}</div>',
        '<h4>{{user_input}}</h4>',
        '<p class="lead">{{user_input}}</p>',
        '<h3>{{user_input}}</h3>',
        '<p>{{user_input}}</p>',
        '<p>{{user_input}}</p>',
        '<p>{{user_input}}</p>',
        '<p>{{user_input}}</p>',
        '<h3>{{user_input}}</h3>',
        '<p>{{user_input}}</p>',
        '<p>{{user_input}}</p>',
        '<p>{{user_input}}</p>',
        '<p>{{user_input}}</p>',
        '<p>{{user_input}}</p>',
        '<li>{{user_input}}</li>',
        '<li>{{user_input}}</li>',
        '<li>{{user_input}}</li>',
        '<li>{{user_input}}</li>',
        '<li>{{user_input}}</li>',
        '<li>{{user_input}}</li>',
        '<li>{{user_input}}</li>',
        '<li>{{user_input}}</li>',
        '<li>{{user_input}}</li>',
        '<li>{{user_input}}</li>'
    ],
    "Judul Code": [
        "Judul Halaman (title)", "Deskripsi Halaman (meta description)", "Kata Kunci (meta keywords)",
        "Judul Kategori / Bagian", "Judul Artikel Utama", "Nama Penulis", "Jabatan Penulis", "Paragraf Pembuka (Lead)",
        "Paragraf 1", "Paragraf 2", "Paragraf 3", "Paragraf 4", "Paragraf 5", "Subjudul / Heading Artikel",
        "Paragraf 6", "Paragraf 7", "Paragraf 8", "Catatan Algoritma yang Digunakan", "Pertanyaan Penutup / Ajakan Diskusi",
        "Tips / List 1", "Tips / List 2", "Tips / List 3", "Tips / List 4", "Tips / List 5",
        "Tag / Kategori 1", "Tag / Kategori 2", "Tag / Kategori 3", "Tag / Kategori 4", "Tag / Kategori 5"
    ]
}

# Upload file Excel
uploaded_file = st.file_uploader("📁 Upload file Excel (kolom: Nomor Baris, Artikel Baru)", type=["xlsx"])

if uploaded_file:
    try:
        # Baca data pengganti
        df_upload = pd.read_excel(uploaded_file)
    except Exception as e:
        st.error(f"Gagal membaca file Excel: {e}")
        st.stop()

    # Validasi kolom wajib
    required_cols = ["Nomor Baris", "Artikel Baru"]
    if not all(col in df_upload.columns for col in required_cols):
        st.error(f"File Excel harus mengandung kolom: {required_cols}")
        st.stop()

    # Buat dataframe final: join struktur code dengan data upload (by Nomor Baris)
    df_template = pd.DataFrame(data_input)
    df_final = pd.merge(df_template, df_upload[required_cols], on="Nomor Baris", how="left")

    # Pastikan kolom 'Artikel Baru' terisi (wajib)
    if df_final["Artikel Baru"].isnull().any():
        st.error("❌ Kolom 'Artikel Baru' tidak lengkap di file upload! Periksa kembali.")
    else:
        # Injeksi user_input ke struktur code
        df_final["Kode Final (Hasil HTML)"] = df_final.apply(
            lambda row: row["Struktur Code (Template)"].replace("{{user_input}}", str(row["Artikel Baru"])),
            axis=1
        )

        # Tampilkan tabel hasil penggabungan
        st.subheader("📝 Tabel Hasil Penggabungan")
        st.dataframe(df_final[["Nomor Baris", "Judul Code", "Artikel Baru", "Kode Final (Hasil HTML)"]], use_container_width=True)

        # Ganti baris di HTML asli dengan validasi indeks baris
        html_final_lines = html_lines.copy()
        for _, row in df_final.iterrows():
            try:
                baris_idx = int(row["Nomor Baris"]) - 1  # index Python 0-based
                if 0 <= baris_idx < len(html_final_lines):
                    html_final_lines[baris_idx] = row["Kode Final (Hasil HTML)"] + "\n"
                else:
                    st.warning(f"Nomor baris {row['Nomor Baris']} di luar range file HTML asli.")
            except Exception as e:
                st.warning(f"Gagal mengganti baris {row['Nomor Baris']}: {e}")

        # Gabungkan HTML final
        html_final_str = "".join(html_final_lines)

        # Tampilkan HTML final
        st.subheader("🎉 Hasil HTML Final")
        st.code(html_final_str, language="html")

        # Unduh file HTML final
        st.download_button(
            label="⬇️ Unduh HTML Final",
            data=html_final_str,
            file_name="artikel_baru.html",
            mime="text/html"
        )
else:
    st.info("💡 Silakan upload file Excel untuk memulai proses penggabungan HTML.")
