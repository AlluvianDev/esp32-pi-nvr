import sys
import os
import requests
import base64

sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from flask import Flask, request, jsonify, send_from_directory
from flask_cors import CORS
from routes.system_routes import system_bp
from routes.ai_routes import ai_bp
from routes.media_routes import media_bp
from config import API_KEY

CAMERA_PASSWORD = "camera password when the camera is reached through the website, a layer of protection"
ESP32_IP = "192.168.1.100"
HLS_DIR = "/media/esp32/recordings" #example directory

app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}}, allow_headers=["*"])

app.register_blueprint(system_bp, url_prefix='/system')
app.register_blueprint(ai_bp, url_prefix='/ai')
app.register_blueprint(media_bp, url_prefix='/media')

@app.before_request
def require_apikey():
    if request.method == 'OPTIONS':
        return None
        
    if request.path.startswith('/api/stream/'):
        if request.path.endswith('.m3u8'):
            if request.args.get('api_key') != API_KEY:
                return jsonify({"error": "Unauthorized"}), 401
        return None

    if request.path == '/api/camera_control':
        if request.args.get('api_key') != API_KEY:
            return jsonify({"error": "Unauthorized"}), 401
        return None

    if request.headers.get('X-API-Key') != API_KEY:
        return jsonify({"error": "Unauthorized"}), 401

@app.route('/api/stream/<path:filename>')
def serve_stream(filename):
    response = send_from_directory(HLS_DIR, filename)
    response.headers['Cache-Control'] = 'no-cache, no-store, must-revalidate'
    response.headers['Pragma'] = 'no-cache'
    response.headers['Expires'] = '0'
    response.headers['Access-Control-Allow-Origin'] = '*'
    return response

@app.route('/api/camera_control')
def camera_control():
    if request.args.get('api_key') != API_KEY:
        return jsonify({"error": "Unauthorized"}), 401
    
    encoded_pass = request.args.get('cam_pass')
    try:
        decoded_pass = base64.b64decode(encoded_pass).decode('utf-8')
    except:
        return jsonify({"error": "Invalid Format"}), 400

    if decoded_pass != CAMERA_PASSWORD:
        return jsonify({"error": "Unauthorized Camera"}), 403

    var = request.args.get('var')
    val = request.args.get('val')

    if not var or val is None:
        return jsonify({"error": "Missing parameters"}), 400

    url = f"http://{ESP32_IP}/control?var={var}&val={val}"
    try:
        response = requests.get(url, timeout=3)
        return jsonify({"status": "success", "esp_status": response.status_code})
    except Exception as e:
        return jsonify({"error": str(e)}), 500
        
if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)