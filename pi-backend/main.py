import sys
import os
import time
import subprocess
import threading
import requests
import base64
import cv2
from datetime import datetime
from flask import Flask, request, jsonify, send_from_directory, render_template, Response
from flask_cors import CORS
from dotenv import load_dotenv
load_dotenv()
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from routes.system_routes import system_bp
from routes.ai_routes import ai_bp
from routes.media_routes import media_bp
# ─────────────────────────────────────────────
# Configuration
# ─────────────────────────────────────────────
def require_env(var_name):
    value = os.getenv(var_name)
    if not value:
        raise RuntimeError(f"Missing required environment variable: {var_name}")
    return value

CAMERA_PASSWORD = require_env("CAMERA_PASSWORD")
ESP32_IP        = require_env("ESP32_IP")
API_KEY         = require_env("API_KEY")
HLS_DIR         = os.getenv("HLS_DIR", "./recordings")

ESP32_STREAM_URL = f"http://{ESP32_IP}:81/stream"
RECORDING_FPS      = 20
SEGMENT_DURATION_S = 60
RECONNECT_DELAY_S  = 2


BASE_DIR     = os.path.dirname(os.path.abspath(__file__))
TEMPLATE_DIR = os.path.join(BASE_DIR, "public_html", "assets", "templates")
STATIC_DIR   = os.path.join(BASE_DIR, "public_html", "assets")

os.makedirs(HLS_DIR, exist_ok=True)

# ─────────────────────────────────────────────
# Flask app
# ─────────────────────────────────────────────
app = Flask(__name__, template_folder=TEMPLATE_DIR, static_folder=STATIC_DIR)
CORS(app, resources={r"/*": {"origins": "*"}}, allow_headers=["*"])

app.register_blueprint(system_bp, url_prefix='/system')
app.register_blueprint(ai_bp,     url_prefix='/ai')
app.register_blueprint(media_bp,  url_prefix='/media')

# ─────────────────────────────────────────────
# Shared state
# ─────────────────────────────────────────────
_frame_lock    = threading.Lock()
_current_frame = None
_is_receiving  = False
_recording_enabled = False   # Recording is OFF by default

def get_frame():
    with _frame_lock:
        return _current_frame, _is_receiving

def set_frame(frame, receiving: bool):
    global _current_frame, _is_receiving
    with _frame_lock:
        _current_frame = frame
        _is_receiving  = receiving


# ─────────────────────────────────────────────
# Background recording thread
# ─────────────────────────────────────────────
_stop_recording = threading.Event()


def _open_capture() -> cv2.VideoCapture:
    while not _stop_recording.is_set():
        cap = cv2.VideoCapture(ESP32_STREAM_URL)
        cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        if cap.isOpened():
            return cap
        cap.release()
        print(f"[recorder] Could not open stream, retrying in {RECONNECT_DELAY_S}s …")
        time.sleep(RECONNECT_DELAY_S)
    return None


_needs_remux = False   # True when OpenCV fell back to mp4v

def _remux_to_h264(src_path: str):
    """
    Re-encode an mp4v file to H.264 with faststart so browsers can play it.
    Replaces the original file in-place.
    """
    tmp_path = src_path + '.tmp.mp4'
    cmd = [
        'ffmpeg', '-y',
        '-i', src_path,
        '-c:v', 'libx264',
        '-preset', 'veryfast',
        '-crf', '23',
        '-pix_fmt', 'yuv420p',
        '-movflags', '+faststart',
        '-an',
        tmp_path,
    ]
    try:
        result = subprocess.run(
            cmd, capture_output=True, text=True, timeout=120
        )
        if result.returncode == 0 and os.path.isfile(tmp_path):
            os.replace(tmp_path, src_path)
            print(f'[recorder] Remuxed to H.264: {src_path}')
        else:
            print(f'[recorder] ffmpeg failed (rc={result.returncode}): {result.stderr[:300]}')
            if os.path.isfile(tmp_path):
                os.remove(tmp_path)
    except FileNotFoundError:
        print('[recorder] ERROR: ffmpeg not found — install it with: sudo apt install ffmpeg')
    except Exception as e:
        print(f'[recorder] Remux error: {e}')
        if os.path.isfile(tmp_path):
            os.remove(tmp_path)


def _new_writer(frame) -> tuple:
    global _needs_remux
    h, w = frame.shape[:2]
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    filepath  = os.path.join(HLS_DIR, f"record_{timestamp}.mp4")

    # Try H.264 (browser-compatible) first, fall back to mp4v
    for fourcc_str in ('avc1', 'X264', 'x264', 'mp4v'):
        fourcc = cv2.VideoWriter_fourcc(*fourcc_str)
        writer = cv2.VideoWriter(filepath, fourcc, RECORDING_FPS, (w, h))
        if writer.isOpened():
            if fourcc_str == 'mp4v':
                _needs_remux = True
                print('[recorder] Using mp4v codec (will remux to H.264 after save)')
            else:
                _needs_remux = False
                print(f'[recorder] Codec: {fourcc_str}')
            return writer, filepath
        writer.release()

    raise RuntimeError('No usable video codec found on this system')


def _finish_segment(writer, filepath):
    """Release the writer and remux to H.264 if needed."""
    writer.release()
    print(f"[recorder] Segment saved: {filepath}")
    if _needs_remux:
        # Run remux in a background thread so recording isn't blocked
        threading.Thread(
            target=_remux_to_h264,
            args=(filepath,),
            daemon=True,
        ).start()


def background_recorder():
    frames_per_segment = RECORDING_FPS * SEGMENT_DURATION_S
    frame_interval     = 1.0 / RECORDING_FPS

    while not _stop_recording.is_set():
        cap = _open_capture()
        if cap is None:
            break

        writer   = None
        filepath = None
        frames_written = 0

        try:
            while not _stop_recording.is_set():
                ok, frame = cap.read()

                if not ok:
                    set_frame(None, False)
                    print(f"[recorder] Stream lost. Saving segment and reconnecting …")
                    if writer:
                        _finish_segment(writer, filepath)
                        writer = None
                    break

                set_frame(frame, True)
            if _recording_enabled:
                if writer is None:
                    writer, filepath = _new_writer(frame)
                    frames_written   = 0
                    print(f"[recorder] New segment: {filepath}")

                writer.write(frame)
                frames_written += 1

                if frames_written >= frames_per_segment:
                    _finish_segment(writer, filepath)
                    writer, filepath = _new_writer(frame)
                    frames_written   = 0
            else:
                    # Recording disabled � close any open segment
                    if writer:
                        _finish_segment(writer, filepath)
                        writer = None
                        frames_written = 0
                        print("[recorder] Recording paused.")

            time.sleep(frame_interval)

        finally:
            if writer:
                _finish_segment(writer, filepath)
            cap.release()
            set_frame(None, False)

        if not _stop_recording.is_set():
            print(f"[recorder] Waiting {RECONNECT_DELAY_S}s before reconnect …")
            time.sleep(RECONNECT_DELAY_S)

    print("[recorder] Background recorder stopped.")


# ─────────────────────────────────────────────
# MJPEG relay
# ─────────────────────────────────────────────
def generate_frames():
    while True:
        frame, receiving = get_frame()

        if not receiving or frame is None:
            time.sleep(0.1)
            continue

        ret, buffer = cv2.imencode('.jpg', frame)
        if not ret:
            time.sleep(0.05)
            continue

        frame_bytes = buffer.tobytes()
        yield (b'--frame\r\n'
               b'Content-Type: image/jpeg\r\n\r\n' + frame_bytes + b'\r\n')

        time.sleep(1.0 / RECORDING_FPS)


# ─────────────────────────────────────────────
# Auth middleware
# ─────────────────────────────────────────────
@app.before_request
def require_apikey():
    if request.method == 'OPTIONS':
        return None

    allowed_paths = ['/', '/video_feed', '/status', '/save_frame',
                     '/recording_status', '/toggle_recording']
    if request.path in allowed_paths:
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


# ─────────────────────────────────────────────
# Routes
# ─────────────────────────────────────────────
@app.route('/')
def index():
    files = sorted(
        [f for f in os.listdir(HLS_DIR) if f.endswith(('.jpg', '.mp4'))],
        reverse=True
    )
    return render_template('index.html', recordings=files)


@app.route('/video_feed')
def video_feed():
    return Response(
        generate_frames(),
        mimetype='multipart/x-mixed-replace; boundary=frame'
    )


@app.route('/status')
def status():
    _, receiving = get_frame()
    return jsonify({"status": "LIVE" if receiving else "OFFLINE"})

@app.route('/recording_status')
def recording_status():
    return jsonify({"recording": _recording_enabled})


@app.route('/toggle_recording', methods=['GET', 'POST'])
def toggle_recording():
    global _recording_enabled
    _recording_enabled = not _recording_enabled
    state = "STARTED" if _recording_enabled else "STOPPED"
    print(f"[main] Recording {state} by user.")
    return jsonify({"recording": _recording_enabled, "message": f"Recording {state}"})


@app.route('/save_frame')
def save_frame():
    frame, receiving = get_frame()

    if not receiving:
        return jsonify({"success": False, "error": "Stream is not active"})

    if frame is None:
        return jsonify({"success": False, "error": "No frame in memory"})

    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    filename  = f"record_{timestamp}.jpg"
    filepath  = os.path.join(HLS_DIR, filename)

    try:
        if cv2.imwrite(filepath, frame):
            return jsonify({"success": True, "file": filename})
        else:
            return jsonify({"success": False, "error": "Permission denied writing to HLS_DIR"})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)})


@app.route('/list_frames')
def list_frames():
    """
    Rich file-explorer endpoint.
    Query params:
      ?type=jpg|mp4|all   (default: all)
      ?sort=date|name|size (default: date, newest first)
    Returns:
      { total_files, total_size_human, recordings: [ { name, type,
        size_bytes, size_human, modified_ts, modified, url }, … ] }
    """
    def _human(b: int) -> str:
        for unit in ('B', 'KB', 'MB', 'GB'):
            if b < 1024:
                return f"{b:.1f} {unit}"
            b /= 1024
        return f"{b:.1f} TB"

    filter_type = request.args.get('type', 'all').lower()   # jpg | mp4 | all
    sort_by     = request.args.get('sort', 'date').lower()  # date | name | size

    try:
        entries = []
        for f in os.listdir(HLS_DIR):
            ext = os.path.splitext(f)[1].lower().lstrip('.')
            if ext not in ('jpg', 'mp4'):
                continue
            if filter_type != 'all' and ext != filter_type:
                continue
            full    = os.path.join(HLS_DIR, f)
            st      = os.stat(full)
            mod_ts  = st.st_mtime
            entries.append({
                "name":        f,
                "type":        "video" if ext == 'mp4' else "image",
                "size_bytes":  st.st_size,
                "size_human":  _human(st.st_size),
                "modified_ts": mod_ts,
                "modified":    datetime.fromtimestamp(mod_ts).strftime("%d.%m.%Y %H:%M:%S"),
                "url":         f"/api/stream/{f}",
            })

        key_map  = {"date": "modified_ts", "name": "name", "size": "size_bytes"}
        sort_key = key_map.get(sort_by, "modified_ts")
        entries.sort(key=lambda x: x[sort_key], reverse=(sort_by != "name"))

        total_bytes = sum(e["size_bytes"] for e in entries)
        return jsonify({
            "total_files":      len(entries),
            "total_size_human": _human(total_bytes),
            "recordings":       entries,
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/delete_frame', methods=['DELETE'])
def delete_frame():
    """
    Delete a single recording file.
    Body: { "filename": "record_20260405_120000.mp4" }
    Requires X-API-Key header (enforced by before_request).
    """
    data     = request.get_json(silent=True) or {}
    filename = data.get("filename", "").strip()

    if not filename:
        return jsonify({"success": False, "error": "Missing filename"}), 400

    # Prevent path traversal
    if os.sep in filename or filename.startswith('.'):
        return jsonify({"success": False, "error": "Invalid filename"}), 400

    if not filename.endswith(('.jpg', '.mp4')):
        return jsonify({"success": False, "error": "Invalid file type"}), 400

    filepath = os.path.join(HLS_DIR, filename)
    if not os.path.isfile(filepath):
        return jsonify({"success": False, "error": "File not found"}), 404

    try:
        os.remove(filepath)
        return jsonify({"success": True, "deleted": filename})
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500


@app.route('/api/stream/<path:filename>')
def serve_stream(filename):
    response = send_from_directory(HLS_DIR, filename)
    response.headers['Cache-Control'] = 'no-cache, no-store, must-revalidate'
    response.headers['Pragma']        = 'no-cache'
    response.headers['Expires']       = '0'
    response.headers['Access-Control-Allow-Origin'] = '*'
    return response


@app.route('/api/camera_control')
def camera_control():
    if request.args.get('api_key') != API_KEY:
        return jsonify({"error": "Unauthorized"}), 401

    encoded_pass = request.args.get('cam_pass')
    try:
        decoded_pass = base64.b64decode(encoded_pass).decode('utf-8')
    except Exception:
        return jsonify({"error": "Invalid Format"}), 400

    if decoded_pass != CAMERA_PASSWORD:
        return jsonify({"error": "Unauthorized Camera"}), 403

    var = request.args.get('var')
    val = request.args.get('val')

    if not var or val is None:
        return jsonify({"error": "Missing parameters"}), 400

    url = f"http://{ESP32_IP}/control?var={var}&val={val}"
    try:
        resp = requests.get(url, timeout=3)
        return jsonify({"status": "success", "esp_status": resp.status_code})
    except Exception as e:
        return jsonify({"error": str(e)}), 500


# ─────────────────────────────────────────────
# Entry point
# ─────────────────────────────────────────────
def _remux_existing():
    """Check all existing .mp4 files and remux any that aren't H.264."""
    try:
        files = [f for f in os.listdir(HLS_DIR) if f.endswith('.mp4')]
    except Exception:
        return

    for f in files:
        path = os.path.join(HLS_DIR, f)
        try:
            probe = subprocess.run(
                ['ffprobe', '-v', 'error', '-select_streams', 'v:0',
                 '-show_entries', 'stream=codec_name',
                 '-of', 'csv=p=0', path],
                capture_output=True, text=True, timeout=10,
            )
            codec = probe.stdout.strip()
            if codec and codec != 'h264':
                print(f'[startup] Remuxing {f} (codec={codec}) to H.264 …')
                _remux_to_h264(path)
        except FileNotFoundError:
            print('[startup] ffprobe not found — skipping existing file check')
            return
        except Exception as e:
            print(f'[startup] Error checking {f}: {e}')


if __name__ == '__main__':
    # Convert any existing non-H.264 recordings in the background
    remuxer = threading.Thread(
        target=_remux_existing,
        name="StartupRemuxer",
        daemon=True,
    )
    remuxer.start()
    print("[main] Background remux of existing recordings started.")

    recorder = threading.Thread(
        target=background_recorder,
        name="BackgroundRecorder",
        daemon=True
    )
    recorder.start()
    print("[main] Background recorder started.")

    try:
        app.run(host='0.0.0.0', port=5000, threaded=True)
    finally:
        print("[main] Shutting down recorder …")
        _stop_recording.set()
        recorder.join(timeout=10)
        print("[main] Done.")
