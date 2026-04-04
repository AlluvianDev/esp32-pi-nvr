<?php 
require_once 'auth.php'; 
require_once 'config.php'; 
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alluvian Hub | Guvenli Kamera</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <style>
        .camera-wrapper {
            display: flex; gap: 20px; flex-wrap: wrap; justify-content: center;
        }
        .camera-container { 
            position: relative; width: 100%; max-width: 800px; background: #000; 
            border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        .cam-controls {
            width: 100%; max-width: 300px; background: var(--bg-panel); 
            padding: 20px; border-radius: 8px; border: 1px solid var(--border-color);
            max-height: 85vh; overflow-y: auto;
        }
        .cam-controls::-webkit-scrollbar {
            width: 8px;
        }
        .cam-controls::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 4px;
        }
        .control-group { margin-bottom: 15px; }
        .control-group label { display: block; margin-bottom: 5px; color: var(--text-dim); font-size: 0.9rem; }
        .control-group select, .control-group input[type="range"] {
            width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #444; 
            background: #222; color: #fff;
        }
        .player-title { color: #fff; padding: 10px; text-align: center; background: #222; border-bottom: 1px solid #444; margin: 0; font-size: 14px; letter-spacing: 1px;}
        .timestamp-overlay {
            position: absolute;
            top: 50px;
            right: 15px;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            padding: 5px 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            z-index: 5;
            pointer-events: none;
            border: 1px solid rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <h2 class="header-title">Ev Guvenlik Kamerasi</h2>
        
        <div class="camera-wrapper">
            <div style="flex-grow: 1; max-width: 800px;">
                
                <div class="camera-container" id="liveBox">
                    <h3 class="player-title" id="liveTitle"> CANLI YAYIN</h3>
                    <div id="liveTimestamp" class="timestamp-overlay"></div>
                    <video id="liveStream" autoplay muted playsinline style="width: 100%; height: auto;"></video>
                    <div id="status" style="padding: 10px; text-align: center; color: var(--text-dim); font-size: 12px; background:#111;">Bekleniyor...</div>
                </div>

                <div class="camera-container" id="historyBox">
                    <h3 class="player-title"> GECMIS KAYITLAR (DVR)</h3>
                    <video id="historyStream" controls playsinline style="width: 100%; height: auto;"></video>
                </div>

            </div>

            <div class="cam-controls" id="camControls">
                <h3 style="margin-bottom: 15px; border-bottom: 1px solid #333; padding-bottom: 10px;">Kamera Ayarlari</h3>
                
                <div class="control-group">
                    <label>Cozunurluk (Resolution)</label>
                    <select id="framesize" onchange="sendCamCommand('framesize', this.value)">
                        <option value="13">UXGA(1600x1200)</option>
                        <option value="12">SXGA(1280x1024)</option>
                        <option value="11">HD(1280x720)</option>
                        <option value="10">XGA(1024x768)</option>
                        <option value="9">SVGA(800x600)</option>
                        <option value="8" selected>VGA(640x480)</option>
                        <option value="7">HVGA(480x320)</option>
                        <option value="6">CIF(400x296)</option>
                        <option value="5">QVGA(320x240)</option>
                        <option value="4">240x240</option>
                        <option value="3">HQVGA(240x176)</option>
                        <option value="2">QCIF(176x144)</option>
                        <option value="1">QQVGA(160x120)</option>
                        <option value="0">96x96</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>Kalite (Quality)</label>
                    <input type="range" id="quality" min="4" max="63" value="10" onchange="sendCamCommand('quality', this.value)">
                </div>

                <div class="control-group">
                    <label>Parlaklik (Brightness)</label>
                    <input type="range" id="brightness" min="-2" max="2" value="0" onchange="sendCamCommand('brightness', this.value)">
                </div>

                <div class="control-group">
                    <label>Kontrast (Contrast)</label>
                    <input type="range" id="contrast" min="-2" max="2" value="0" onchange="sendCamCommand('contrast', this.value)">
                </div>

                <div class="control-group">
                    <label>Doygunluk (Saturation)</label>
                    <input type="range" id="saturation" min="-2" max="2" value="0" onchange="sendCamCommand('saturation', this.value)">
                </div>

                <div class="control-group">
                    <label>Ozel Efekt (Special Effect)</label>
                    <select id="special_effect" onchange="sendCamCommand('special_effect', this.value)">
                        <option value="0">No Effect</option>
                        <option value="1">Negative</option>
                        <option value="2">Grayscale</option>
                        <option value="3">Red Tint</option>
                        <option value="4">Green Tint</option>
                        <option value="5">Blue Tint</option>
                        <option value="6">Sepia</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>AWB</label>
                    <select id="awb" onchange="sendCamCommand('awb', this.value)">
                        <option value="0">Kapali</option>
                        <option value="1" selected>Acik</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>AWB Gain</label>
                    <select id="awb_gain" onchange="sendCamCommand('awb_gain', this.value)">
                        <option value="0">Kapali</option>
                        <option value="1" selected>Acik</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>WB Mode</label>
                    <select id="wb_mode" onchange="sendCamCommand('wb_mode', this.value)">
                        <option value="0">Auto</option>
                        <option value="1">Sunny</option>
                        <option value="2">Cloudy</option>
                        <option value="3">Office</option>
                        <option value="4">Home</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>AEC SENSOR</label>
                    <select id="aec" onchange="sendCamCommand('aec', this.value)">
                        <option value="0">Kapali</option>
                        <option value="1" selected>Acik</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>AEC DSP</label>
                    <select id="aec2" onchange="sendCamCommand('aec2', this.value)">
                        <option value="0">Kapali</option>
                        <option value="1">Acik</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>AE Level</label>
                    <input type="range" id="ae_level" min="-2" max="2" value="0" onchange="sendCamCommand('ae_level', this.value)">
                </div>

                <div class="control-group">
                    <label>AGC</label>
                    <select id="agc" onchange="sendCamCommand('agc', this.value)">
                        <option value="0">Kapali</option>
                        <option value="1" selected>Acik</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>Gain Ceiling</label>
                    <select id="gainceiling" onchange="sendCamCommand('gainceiling', this.value)">
                        <option value="0">2x</option>
                        <option value="1">4x</option>
                        <option value="2">8x</option>
                        <option value="3">16x</option>
                        <option value="4">32x</option>
                        <option value="5">64x</option>
                        <option value="6">128x</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>BPC</label>
                    <select id="bpc" onchange="sendCamCommand('bpc', this.value)">
                        <option value="0">Kapali</option>
                        <option value="1">Acik</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>WPC</label>
                    <select id="wpc" onchange="sendCamCommand('wpc', this.value)">
                        <option value="0">Kapali</option>
                        <option value="1" selected>Acik</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>Raw GMA</label>
                    <select id="raw_gma" onchange="sendCamCommand('raw_gma', this.value)">
                        <option value="0">Kapali</option>
                        <option value="1" selected>Acik</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>Lens Correction</label>
                    <select id="lenc" onchange="sendCamCommand('lenc', this.value)">
                        <option value="0">Kapali</option>
                        <option value="1" selected>Acik</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>Yatay Cevir (H-Mirror)</label>
                    <select id="hmirror" onchange="sendCamCommand('hmirror', this.value)">
                        <option value="0">Kapali</option>
                        <option value="1">Acik</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>Dikey Cevir (V-Flip)</label>
                    <select id="vflip" onchange="sendCamCommand('vflip', this.value)">
                        <option value="0">Kapali</option>
                        <option value="1">Acik</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>DCW (Downsize EN)</label>
                    <select id="dcw" onchange="sendCamCommand('dcw', this.value)">
                        <option value="0">Kapali</option>
                        <option value="1" selected>Acik</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>Color Bar</label>
                    <select id="colorbar" onchange="sendCamCommand('colorbar', this.value)">
                        <option value="0">Kapali</option>
                        <option value="1">Acik</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>Flas Isigi (LED Intensity)</label>
                    <input type="range" id="led_intensity" min="0" max="255" value="0" onchange="sendCamCommand('led_intensity', this.value)">
                </div>
            </div>
        </div>
    </div>

    <script>
        const base64Pass = btoa("YOUR_CAMERA_PASSWORD_HERE");

        document.addEventListener('DOMContentLoaded', function() {
            initCamera();
            startClock();
        });

        function startClock() {
            setInterval(() => {
                const now = new Date();
                const options = { 
                    year: 'numeric', month: '2-digit', day: '2-digit', 
                    hour: '2-digit', minute: '2-digit', second: '2-digit',
                    hour12: false
                };
                document.getElementById('liveTimestamp').innerText = now.toLocaleString('tr-TR', options);
            }, 1000);
        }

        function initCamera() {
            try {
                const liveVideo = document.getElementById('liveStream');
                const historyVideo = document.getElementById('historyStream');
                
                const liveUrl = `https://YOUR_DOMAIN_HERE/api/stream/live.m3u8?api_key=YOUR_API_KEY_HERE`;
                const historyUrl = `https://YOUR_DOMAIN_HERE/api/stream/history.m3u8?api_key=YOUR_API_KEY_HERE`;

                if (typeof Hls === 'undefined') {
                    document.getElementById('status').innerText = "HATA: HLS.js kutuphanesi yuklenemedi!";
                    document.getElementById('status').style.color = "red";
                    return;
                }

                if (Hls.isSupported()) {
                    const hlsLive = new Hls({
                        liveSyncDurationCount: 1,
                        liveMaxLatencyDurationCount: 2,
                        maxLiveSyncPlaybackRate: 2.0,
                        lowLatencyMode: true
                    });
                    hlsLive.loadSource(liveUrl);
                    hlsLive.attachMedia(liveVideo);
                    hlsLive.on(Hls.Events.MANIFEST_PARSED, function() {
                        liveVideo.play();
                        document.getElementById('status').innerText = "CANLI YAYIN AKTIF";
                        document.getElementById('status').style.color = "var(--success)";
                    });
                    hlsLive.on(Hls.Events.ERROR, function(event, data) {
                        if (data.fatal) {
                            document.getElementById('status').innerText = "YAYIN BEKLENIYOR... (FFmpeg Hazirlaniyor)";
                            document.getElementById('status').style.color = "orange";
                        }
                    });

                    const hlsHistory = new Hls();
                    hlsHistory.loadSource(historyUrl);
                    hlsHistory.attachMedia(historyVideo);
                } else if (liveVideo.canPlayType('application/vnd.apple.mpegurl')) {
                    liveVideo.src = liveUrl;
                    liveVideo.addEventListener('loadedmetadata', function() { liveVideo.play(); });
                    historyVideo.src = historyUrl;
                }
            } catch (error) {
                console.error(error);
            }
        }

        function sendCamCommand(variable, value) {
            const apiUrl = `https://YOUR_DOMAIN_HERE/api/camera_control?api_key=YOUR_API_KEY_HERE&cam_pass=${base64Pass}&var=${variable}&val=${value}`;
            fetch(apiUrl)
                .then(response => response.json())
                .catch(error => console.error(error));
        }
    </script>
</body>
</html>