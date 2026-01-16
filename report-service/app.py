import os
import jwt
from flask import Flask, request, jsonify
from database import ReportDB

app = Flask(__name__)
db = ReportDB()

# Ambil Secret dari ENV, pastikan sama dengan service lain
JWT_SECRET = os.getenv('JWT_SECRET', 'rahasia_kasir_123_paling_aman_dan_sangat_panjang_sekali')

def verify_token(token):
    if not token: return None
    try:
        pure_token = token.replace("Bearer ", "").strip()
        return jwt.decode(pure_token, JWT_SECRET, algorithms=["HS256"])
    except:
        return None

# Health Check Endpoint
@app.route('/')
def health_check():
    return jsonify({
        "status": "API Ready",
        "service": "Report Service (Python Flask)",
        "database": "Redis Connected"
    })
# Endpoint untuk menerima data dari Java (Internal)
@app.route('/api/reports/sync', methods=['POST'])
def sync():
    data = request.json
    try:
        db.sync_transaction(data)
        return jsonify({"status": "success", "message": "Data synced to Redis"}), 200
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500

# Endpoint untuk Dashboard Kasir (Frontend/Postman)
@app.route('/api/reports/dashboard', methods=['GET'])
def dashboard():
    token_data = verify_token(request.headers.get('Authorization'))
    if not token_data:
        return jsonify({"error": "Token tidak valid atau hilang"}), 401
    
    nama_toko = token_data.get('nama_toko')
    data = db.get_dashboard_summary(nama_toko)
    return jsonify(data)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)