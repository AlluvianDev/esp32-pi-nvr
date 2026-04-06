<?php
require_once 'auth.php';
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alluvian Hub | Güvenli Kamera</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .camera-wrapper {
            display: flex; gap: 20px; flex-wrap: wrap; justify-content: center;
        }
        .camera-main   { flex-grow: 1; max-width: 800px; min-width: 0; }
        .cam-controls  {
            width: 100%; max-width: 300px; background: var(--bg-panel);
            padding: 20px; border-radius: 8px; border: 1px solid var(--border);
            max-height: 85vh; overflow-y: auto;
        }
        .cam-controls::-webkit-scrollbar { width: 8px; }
        .cam-controls::-webkit-scrollbar-thumb { background: #444; border-radius: 4px; }

        .camera-container {
            position: relative; width: 100%; background: #000;
            border-radius: 8px; overflow: hidden;
            border: 1px solid var(--border); margin-bottom: 16px;
        }
        .player-title {
            color: #fff; padding: 10px; text-align: center;
            background: #222; border-bottom: 1px solid #444;
            margin: 0; font-size: 14px; letter-spacing: 1px;
        }
        .timestamp-overlay {
            position: absolute; top: 50px; right: 15px;
            background: rgba(0,0,0,.6); color: #fff;
            padding: 5px 10px; border-radius: 4px;
            font-family: monospace; font-size: 14px;
            z-index: 5; pointer-events: none;
            border: 1px solid rgba(255,255,255,.2);
        }
        #streamStatusBar {
            padding: 8px 10px; text-align: center;
            color: var(--text-dim); font-size: 12px; background: #111;
        }

        .control-group { margin-bottom: 15px; }
        .control-group label { display: block; margin-bottom: 5px; color: var(--text-dim); font-size: .9rem; }
        .control-group select,
        .control-group input[type="range"] {
            width: 100%; padding: 8px; border-radius: 4px;
            border: 1px solid #444; background: #222; color: #fff;
        }

        .capture-btn {
            width: 100%; padding: 12px; background: #007bff; color: #fff;
            border: none; border-radius: 4px; cursor: pointer;
            font-size: 16px; font-weight: bold; margin-bottom: 20px;
            transition: background .2s;
        }
        .capture-btn:hover  { background: #0056b3; }
        .capture-btn:active { background: #003f80; }

        .rec-btn {
            width: 100%; padding: 12px; color: #fff;
            border: none; border-radius: 4px; cursor: pointer;
            font-size: 16px; font-weight: bold; margin-bottom: 20px;
            transition: background .2s;
        }
        .rec-btn.recording { background: #c0392b; }
        .rec-btn.recording:hover { background: #a93226; }
        .rec-btn.stopped   { background: #27ae60; }
        .rec-btn.stopped:hover   { background: #1e8449; }

        .explorer-box {
            background: var(--bg-panel); border: 1px solid var(--border);
            border-radius: 8px; overflow: hidden; margin-bottom: 20px;
        }
        .explorer-toolbar {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            padding: 12px 16px; background: #1a1a1a; border-bottom: 1px solid #333;
        }
        .explorer-toolbar h3 { margin: 0; font-size: 14px; letter-spacing: 1px; flex-grow: 1; }
        .explorer-stats { font-size: 12px; color: var(--text-dim); }

        .explorer-filters {
            display: flex; gap: 8px; flex-wrap: wrap;
            padding: 10px 16px; background: #161616; border-bottom: 1px solid #2a2a2a;
            align-items: center;
        }
        .filter-label { font-size: 12px; color: var(--text-dim); margin-right: 4px; }
        .filter-btn {
            padding: 4px 12px; border-radius: 20px; border: 1px solid #444;
            background: transparent; color: #aaa; cursor: pointer; font-size: 12px;
            transition: all .15s;
        }
        .filter-btn.active { background: #007bff; border-color: #007bff; color: #fff; }
        .filter-btn:hover:not(.active) { border-color: #777; color: #ddd; }

        .filter-sep { width: 1px; height: 20px; background: #333; margin: 0 4px; }

        .sort-select {
            padding: 4px 8px; border-radius: 4px; border: 1px solid #444;
            background: #222; color: #aaa; font-size: 12px; cursor: pointer;
        }
        .sort-select:focus { outline: none; border-color: #007bff; }

        #explorerList {
            max-height: 480px; overflow-y: auto;
        }
        #explorerList::-webkit-scrollbar { width: 6px; }
        #explorerList::-webkit-scrollbar-thumb { background: #333; border-radius: 3px; }

        .file-row {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 16px; border-bottom: 1px solid #1e1e1e;
            transition: background .15s; cursor: pointer;
        }
        .file-row:last-child { border-bottom: none; }
        .file-row:hover { background: #1c1c1c; }

        .file-icon { font-size: 20px; flex-shrink: 0; width: 28px; text-align: center; }

        .file-meta { flex-grow: 1; min-width: 0; }
        .file-name {
            font-size: 13px; color: #e0e0e0; white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px;
        }
        .file-sub { font-size: 11px; color: var(--text-dim); }

        .file-actions { display: flex; gap: 6px; flex-shrink: 0; }
        .action-btn {
            padding: 4px 10px; border-radius: 4px; border: none;
            font-size: 12px; cursor: pointer; transition: opacity .15s;
        }
        .action-btn:hover { opacity: .8; }
        .btn-view   { background: #1a73e8; color: #fff; }
        .btn-delete { background: #c0392b; color: #fff; }

        #viewerModal {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.85); z-index: 1000;
            align-items: center; justify-content: center; flex-direction: column;
        }
        #viewerModal.open { display: flex; }
        #viewerModal .modal-inner {
            background: #111; border-radius: 10px; padding: 16px;
            max-width: 90vw; max-height: 90vh; overflow: auto;
            position: relative;
        }
        #viewerModal .modal-close {
            position: absolute; top: 10px; right: 14px;
            background: none; border: none; color: #aaa;
            font-size: 22px; cursor: pointer; line-height: 1;
        }
        #viewerModal .modal-close:hover { color: #fff; }
        #modalTitle { color: #ccc; font-size: 13px; margin-bottom: 10px; text-align: center; }
        #modalImg   { max-width: 100%; border-radius: 6px; display: none; }
        #modalVideo { max-width: 100%; border-radius: 6px; display: none; outline: none; }

        .explorer-empty {
            padding: 40px; text-align: center; color: var(--text-dim); font-size: 14px;
        }
        .spinner {
            display: inline-block; width: 20px; height: 20px;
            border: 2px solid #444; border-top-color: #007bff;
            border-radius: 50%; animation: spin .7s linear infinite;
            vertical-align: middle; margin-right: 8px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        #confirmModal {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.75); z-index: 1100;
            align-items: center; justify-content: center;
        }
        #confirmModal.open { display: flex; }
        .confirm-box {
            background: #1a1a1a; border: 1px solid #333; border-radius: 10px;
            padding: 28px 32px; text-align: center; max-width: 380px;
        }
        .confirm-box p { color: #ccc; margin-bottom: 20px; font-size: 14px; }
        .confirm-box strong { color: #fff; }
        .confirm-actions { display: flex; gap: 12px; justify-content: center; }
        .confirm-actions button {
            padding: 8px 24px; border-radius: 6px; border: none;
            font-size: 14px; cursor: pointer; font-weight: bold;
        }
        .btn-cancel-confirm { background: #333; color: #ccc; }
        .btn-cancel-confirm:hover { background: #444; }
        .btn-confirm-delete { background: #c0392b; color: #fff; }
        .btn-confirm-delete:hover { background: #a93226; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h2 class="header-title">Ev Güvenlik Kamerası</h2>

        <div class="camera-wrapper">

            <div class="camera-main">

                <div class="camera-container">
                    <h3 class="player-title">🔴 CANLI YAYIN</h3>
                    <div id="liveTimestamp" class="timestamp-overlay"></div>
                    <img id="liveStream"
                         src="<?= API_BASE_URL ?>/video_feed"
                         style="width:100%;height:auto;display:block;"
                         alt="Yayın Bekleniyor...">
                    <div id="streamStatusBar">KONTROL EDİLİYOR...</div>
                </div>

                <button class="capture-btn" onclick="saveFrame()">📸 Anlık Kareyi Kaydet</button>
                <button class="rec-btn stopped" id="recToggleBtn" onclick="toggleRecording()">⏺ Kaydı Başlat</button>

                <div class="explorer-box">

                    <div class="explorer-toolbar">
                        <h3>📁 KAYDEDİLEN DOSYALAR</h3>
                        <span class="explorer-stats" id="explorerStats">Yükleniyor…</span>
                        <button class="filter-btn" style="font-size:13px;" onclick="loadExplorer()" title="Yenile">🔄</button>
                    </div>

                    <div class="explorer-filters">
                        <span class="filter-label">Tür:</span>
                        <button class="filter-btn active" data-type="all"  onclick="setFilter(this,'type')">Tümü</button>
                        <button class="filter-btn"        data-type="mp4"  onclick="setFilter(this,'type')">🎬 Video</button>
                        <button class="filter-btn"        data-type="jpg"  onclick="setFilter(this,'type')">🖼️ Fotoğraf</button>

                        <div class="filter-sep"></div>

                        <span class="filter-label">Sırala:</span>
                        <select class="sort-select" id="sortSelect" onchange="loadExplorer()">
                            <option value="date">Tarihe göre</option>
                            <option value="name">İsme göre</option>
                            <option value="size">Boyuta göre</option>
                        </select>
                    </div>

                    <div id="explorerList">
                        <div class="explorer-empty">
                            <span class="spinner"></span> Dosyalar yükleniyor…
                        </div>
                    </div>
                </div>

            </div>

            <div class="cam-controls">
                <h3 style="margin-bottom:15px;border-bottom:1px solid #333;padding-bottom:10px;">Kamera Ayarları</h3>

                <div class="control-group">
                    <label>Çözünürlük (Resolution)</label>
                    <select id="framesize" onchange="sendCamCommand('framesize', this.value)">
                        <option value="13">UXGA (1600×1200)</option>
                        <option value="12">SXGA (1280×1024)</option>
                        <option value="11">HD (1280×720)</option>
                        <option value="10">XGA (1024×768)</option>
                        <option value="9">SVGA (800×600)</option>
                        <option value="8" selected>VGA (640×480)</option>
                        <option value="7">HVGA (480×320)</option>
                        <option value="6">CIF (400×296)</option>
                        <option value="5">QVGA (320×240)</option>
                        <option value="4">240×240</option>
                        <option value="3">HQVGA (240×176)</option>
                        <option value="2">QCIF (176×144)</option>
                        <option value="1">QQVGA (160×120)</option>
                        <option value="0">96×96</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>Kalite (Quality)</label>
                    <input type="range" id="quality" min="4" max="63" value="10" onchange="sendCamCommand('quality', this.value)">
                </div>
                <div class="control-group">
                    <label>Parlaklık (Brightness)</label>
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
                    <label>Özel Efekt (Special Effect)</label>
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
                        <option value="0">Kapalı</option><option value="1" selected>Açık</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>AWB Gain</label>
                    <select id="awb_gain" onchange="sendCamCommand('awb_gain', this.value)">
                        <option value="0">Kapalı</option><option value="1" selected>Açık</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>WB Mode</label>
                    <select id="wb_mode" onchange="sendCamCommand('wb_mode', this.value)">
                        <option value="0">Auto</option><option value="1">Sunny</option>
                        <option value="2">Cloudy</option><option value="3">Office</option>
                        <option value="4">Home</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>AEC Sensor</label>
                    <select id="aec" onchange="sendCamCommand('aec', this.value)">
                        <option value="0">Kapalı</option><option value="1" selected>Açık</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>AEC DSP</label>
                    <select id="aec2" onchange="sendCamCommand('aec2', this.value)">
                        <option value="0">Kapalı</option><option value="1">Açık</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>AE Level</label>
                    <input type="range" id="ae_level" min="-2" max="2" value="0" onchange="sendCamCommand('ae_level', this.value)">
                </div>
                <div class="control-group">
                    <label>AGC</label>
                    <select id="agc" onchange="sendCamCommand('agc', this.value)">
                        <option value="0">Kapalı</option><option value="1" selected>Açık</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>Gain Ceiling</label>
                    <select id="gainceiling" onchange="sendCamCommand('gainceiling', this.value)">
                        <option value="0">2x</option><option value="1">4x</option>
                        <option value="2">8x</option><option value="3">16x</option>
                        <option value="4">32x</option><option value="5">64x</option>
                        <option value="6">128x</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>BPC</label>
                    <select id="bpc" onchange="sendCamCommand('bpc', this.value)">
                        <option value="0">Kapalı</option><option value="1">Açık</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>WPC</label>
                    <select id="wpc" onchange="sendCamCommand('wpc', this.value)">
                        <option value="0">Kapalı</option><option value="1" selected>Açık</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>Raw GMA</label>
                    <select id="raw_gma" onchange="sendCamCommand('raw_gma', this.value)">
                        <option value="0">Kapalı</option><option value="1" selected>Açık</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>Lens Correction</label>
                    <select id="lenc" onchange="sendCamCommand('lenc', this.value)">
                        <option value="0">Kapalı</option><option value="1" selected>Açık</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>Yatay Çevir (H-Mirror)</label>
                    <select id="hmirror" onchange="sendCamCommand('hmirror', this.value)">
                        <option value="0">Kapalı</option><option value="1">Açık</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>Dikey Çevir (V-Flip)</label>
                    <select id="vflip" onchange="sendCamCommand('vflip', this.value)">
                        <option value="0">Kapalı</option><option value="1">Açık</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>DCW (Downsize EN)</label>
                    <select id="dcw" onchange="sendCamCommand('dcw', this.value)">
                        <option value="0">Kapalı</option><option value="1" selected>Açık</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>Color Bar</label>
                    <select id="colorbar" onchange="sendCamCommand('colorbar', this.value)">
                        <option value="0">Kapalı</option><option value="1">Açık</option>
                    </select>
                </div>
                <div class="control-group">
                    <label>Flaş Işığı (LED Intensity)</label>
                    <input type="range" id="led_intensity" min="0" max="255" value="0" onchange="sendCamCommand('led_intensity', this.value)">
                </div>
            </div>

        </div>
    </div>

    <div id="viewerModal">
        <div class="modal-inner">
            <button class="modal-close" onclick="closeViewer()">✕</button>
            <div id="modalTitle"></div>
            <img    id="modalImg"   alt="">
            <video  id="modalVideo" controls playsinline preload="metadata"></video>
            <div class="modal-footer">
                <a id="modalDownload" href="#" download class="action-btn btn-view" style="text-decoration:none;display:inline-block;margin-top:10px;">⬇️ İndir</a>
            </div>
        </div>
    </div>

    <div id="confirmModal">
        <div class="confirm-box">
            <p>Bu dosyayı silmek istediğinize emin misiniz?<br><strong id="confirmFilename"></strong></p>
            <div class="confirm-actions">
                <button class="btn-cancel-confirm" onclick="closeConfirm()">İptal</button>
                <button class="btn-confirm-delete" onclick="confirmDelete()">Sil</button>
            </div>
        </div>
    </div>

    <script>
    const API_BASE = '<?= API_BASE_URL ?>';
    let activeType  = 'all';
    let pendingDelete = null;

    function startClock() {
        setInterval(() => {
            const now = new Date();
            document.getElementById('liveTimestamp').textContent =
                now.toLocaleString('tr-TR', {
                    year: 'numeric', month: '2-digit', day: '2-digit',
                    hour: '2-digit', minute: '2-digit', second: '2-digit',
                    hour12: false
                });
        }, 1000);
    }

    function checkStatus() {
        apiCall('/status', 'GET').then(data => {
            const bar = document.getElementById('streamStatusBar');
            if (data && data.status === 'LIVE') {
                bar.innerHTML = '<span style="color:#22c55e;">● CANLI YAYIN AKTİF</span>';
            } else {
                bar.innerHTML = '<span style="color:#ef4444;">● YAYIN OFFLINE</span>';
            }
        }).catch(() => {});
    }

    function apiCall(endpoint, method = 'GET', body = null) {
        return fetch('api_proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ endpoint, method, body })
        }).then(r => r.json());
    }

    function saveFrame() {
        const btn = document.querySelector('.capture-btn');
        btn.disabled = true;
        btn.textContent = '⏳ Kaydediliyor…';
        apiCall('/save_frame', 'GET').then(data => {
            if (data.success) {
                loadExplorer();
            } else {
                alert('Kayıt başarısız: ' + (data.error || 'Bilinmeyen hata'));
            }
        }).catch(() => alert('Sunucu hatası'))
          .finally(() => {
            btn.disabled = false;
            btn.textContent = '📸 Anlık Kareyi Kaydet';
        });
    }

    function sendCamCommand(variable, value) {
        apiCall('/api/camera_control?var=' + encodeURIComponent(variable) +
                '&val=' + encodeURIComponent(value), 'GET')
            .catch(e => console.error(e));
    }

    function setFilter(btn, group) {
        document.querySelectorAll(`[data-${group}]`).forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        if (group === 'type') activeType = btn.dataset.type;
        loadExplorer();
    }

    function loadExplorer() {
        const sort     = document.getElementById('sortSelect').value;
        const endpoint = `/list_frames?type=${activeType}&sort=${sort}`;
        const list     = document.getElementById('explorerList');
        const stats    = document.getElementById('explorerStats');

        list.innerHTML = '<div class="explorer-empty"><span class="spinner"></span> Yükleniyor…</div>';

        apiCall(endpoint, 'GET').then(data => {
            if (data.error) {
                list.innerHTML = `<div class="explorer-empty" style="color:#e74c3c;">Hata: ${data.error}</div>`;
                return;
            }

            const files = data.recordings || [];
            stats.textContent = `${data.total_files} dosya · ${data.total_size_human}`;

            if (files.length === 0) {
                list.innerHTML = '<div class="explorer-empty">Henüz kayıt yok.</div>';
                return;
            }

            list.innerHTML = files.map(f => `
                <div class="file-row" ondblclick="openViewer('${f.name}','${f.type}')">
                    <div class="file-icon">${f.type === 'video' ? '🎬' : '🖼️'}</div>
                    <div class="file-meta">
                        <div class="file-name" title="${f.name}">${f.name}</div>
                        <div class="file-sub">${f.modified} &nbsp;·&nbsp; ${f.size_human}</div>
                    </div>
                    <div class="file-actions">
                        <button class="action-btn btn-view"   onclick="event.stopPropagation(); openViewer('${f.name}','${f.type}')">Görüntüle</button>
                        <button class="action-btn btn-delete" onclick="event.stopPropagation(); askDelete('${f.name}')">Sil</button>
                    </div>
                </div>
            `).join('');

        }).catch(() => {
            list.innerHTML = '<div class="explorer-empty" style="color:#e74c3c;">Bağlantı hatası.</div>';
        });
    }

    function openViewer(name, type) {
        const url = `${API_BASE}/api/stream/${encodeURIComponent(name)}`;
        document.getElementById('modalTitle').textContent = name;
        const img      = document.getElementById('modalImg');
        const video    = document.getElementById('modalVideo');
        const dlBtn    = document.getElementById('modalDownload');

        img.style.display   = 'none';
        video.style.display = 'none';
        video.pause();
        video.removeAttribute('src');
        video.load();

        dlBtn.href     = url;
        dlBtn.download = name;

        if (type === 'image') {
            img.src            = url;
            img.style.display = 'block';
        } else {
            video.src            = url;
            video.style.display = 'block';
            video.load();
            video.play().catch(() => {});
        }
        document.getElementById('viewerModal').classList.add('open');
    }

    function closeViewer() {
        const video = document.getElementById('modalVideo');
        video.pause();
        video.removeAttribute('src');
        video.load();
        document.getElementById('viewerModal').classList.remove('open');
    }

    function askDelete(name) {
        pendingDelete = name;
        document.getElementById('confirmFilename').textContent = name;
        document.getElementById('confirmModal').classList.add('open');
    }

    function closeConfirm() {
        pendingDelete = null;
        document.getElementById('confirmModal').classList.remove('open');
    }

    function confirmDelete() {
        if (!pendingDelete) return;
        const name = pendingDelete;
        closeConfirm();

        apiCall('/delete_frame', 'DELETE', { filename: name })
            .then(data => {
                if (data.success) {
                    loadExplorer();
                } else {
                    alert('Silme başarısız: ' + (data.error || 'Bilinmeyen hata'));
                }
            })
            .catch(() => alert('Sunucu hatası'));
    }

    document.getElementById('viewerModal').addEventListener('click', function(e) {
        if (e.target === this) closeViewer();
    });
    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeConfirm();
    });

    function checkRecordingStatus() {
        apiCall('/recording_status', 'GET').then(data => {
            if (data && typeof data.recording !== 'undefined') {
                updateRecBtn(data.recording);
            }
        }).catch(() => {});
    }

    function toggleRecording() {
        const btn = document.getElementById('recToggleBtn');
        btn.disabled = true;
        btn.textContent = '⏳ ...';
        apiCall('/toggle_recording', 'POST').then(data => {
            if (data && typeof data.recording !== 'undefined') {
                updateRecBtn(data.recording);
            }
        }).catch(() => alert('Sunucu hatası'))
          .finally(() => { btn.disabled = false; });
    }

    function updateRecBtn(isRecording) {
        const btn = document.getElementById('recToggleBtn');
        if (isRecording) {
            btn.className = 'rec-btn recording';
            btn.textContent = '⏹ Kaydı Durdur';
        } else {
            btn.className = 'rec-btn stopped';
            btn.textContent = '⏺ Kaydı Başlat';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        startClock();
        checkStatus();
        checkRecordingStatus();
        loadExplorer();
        setInterval(checkStatus, 3000);
        setInterval(checkRecordingStatus, 5000);
        setInterval(loadExplorer, 30000);
    });
    </script>
</body>
</html>
