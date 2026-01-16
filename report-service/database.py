import redis
import os
from datetime import datetime

class ReportDB:
    def __init__(self):
        # Mengambil URL Redis dari environment variable
        redis_url = os.getenv('REDIS_URL', 'redis://report-cache:6379/0')
        self.r = redis.from_url(redis_url, decode_responses=True)

    def sync_transaction(self, data):
        nama_toko = data.get('nama_toko')
        total_harga = float(data.get('total_harga', 0))
        metode = data.get('metode_pembayaran', 'Tunai')
        items = data.get('items', [])
        
        today = datetime.now().strftime('%Y-%m-%d')
        
        # 1. Update Statistik Harian
        key_daily = f"report:daily:{nama_toko}:{today}"
        self.r.hincrbyfloat(key_daily, "total_omzet", total_harga)
        self.r.hincrby(key_daily, "jumlah_transaksi", 1)

        # 2. Update Statistik Metode Pembayaran
        key_payment = f"report:payment:{nama_toko}"
        self.r.hincrbyfloat(key_payment, f"total_{metode.lower()}", total_harga)
        self.r.hincrby(key_payment, f"count_{metode.lower()}", 1)

        # 3. Update Produk Terlaris
        key_top = f"report:top_products:{nama_toko}"
        for item in items:
            # Ambil nama produk secara fleksibel (nama_produk atau produk)
            p_name = item.get('nama_produk') or item.get('produk')
            p_qty = int(item.get('qty', 0))
            
            if p_name:
                self.r.zincrby(key_top, p_qty, p_name)

    def get_dashboard_summary(self, nama_toko):
        today = datetime.now().strftime('%Y-%m-%d')
        key_daily = f"report:daily:{nama_toko}:{today}"
        key_payment = f"report:payment:{nama_toko}"
        key_top = f"report:top_products:{nama_toko}"

        # Ambil Omzet & Transaksi
        daily_data = self.r.hgetall(key_daily)
        omzet = float(daily_data.get("total_omzet", 0))
        transaksi = int(daily_data.get("jumlah_transaksi", 0))
        rata_rata = omzet / transaksi if transaksi > 0 else 0

        # Ambil Data Pembayaran
        payment_data = self.r.hgetall(key_payment)

        # Ambil Top 5 Produk Terlaris
        top_items = self.r.zrevrange(key_top, 0, 4, withscores=True)

        return {
            "summary_hari_ini": {
                "total_omzet": omzet,
                "jumlah_transaksi": transaksi,
                "rata_rata_per_transaksi": round(rata_rata, 2)
            },
            "metode_pembayaran": payment_data,
            "produk_terlaris": [{"produk": name, "terjual": int(qty)} for name, qty in top_items]
        }