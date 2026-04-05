(outdated, will be updated soon)
# ESP32-Pi-NVR: Edge Security Camera & DVR System

> An end-to-end, self-hosted IoT security camera and Network Video Recorder (NVR) pipeline. Captures an MJPEG stream from an ESP32-CAM, transcodes it in real-time on a Raspberry Pi 5 via FFmpeg hardware acceleration into ultra-low latency HLS, and serves it through a secure PHP web dashboard.

---

## Table of Contents

- [System Architecture](#system-architecture)
- [Key Features](#key-features)
- [Hardware Requirements](#hardware-requirements)
- [Wiring Diagram](#wiring-diagram)
- [Deployment Guide](#deployment-guide)
  - [1. ESP32-CAM Setup](#1-esp32-cam-setup)
  - [2. Raspberry Pi 5 Backend](#2-raspberry-pi-5-backend)
  - [3. Frontend Web Server](#3-frontend-web-server)

---

## System Architecture

The pipeline is designed for high performance, low latency, and secure remote access.

```
┌──────────────┐     MJPEG      ┌─────────────────────────────────────────┐     HTTPS      ┌─────────────┐
│  ESP32-CAM   │ ─────────────► │           Raspberry Pi 5                │ ─────────────► │  Web Browser│
│              │                │                                         │                │  (hls.js)   │
│ · MJPEG Stream│                │  ┌─────────┐   ┌────────┐  ┌────────┐  │                └─────────────┘
│ · Control API│ ◄────────────── │  │  Flask  │   │ FFmpeg │  │  PHP   │  │
└──────────────┘   REST cmds     │  │   API   │   │  HLS   │  │  UI    │  │
                                 │  └─────────┘   └────┬───┘  └────────┘  │
                                 │                      │                  │
                                 │               ┌──────▼──────┐          │
                                 │               │ 1TB Ext. HDD│          │
                                 │               │ (DVR Storage)│         │
                                 └───────────────┴─────────────┴──────────┘
                                                        │
                                               Cloudflare Zero Trust
                                               (No port forwarding)
```

| Component | Role |
|---|---|
| **ESP32-CAM** | Captures video; exposes MJPEG stream and control endpoints over LAN |
| **FFmpeg (Pi 5)** | Ingests MJPEG, burns live timestamp, transcodes to H.264 HLS (1-second segments) |
| **Storage (Pi 5)** | Simultaneously writes DVR segments to external 1TB HDD to prevent SD card wear |
| **Flask API (Pi 5)** | Middleware that relays camera control commands and manages the FFmpeg systemd service |
| **PHP Dashboard** | `hls.js`-powered UI with cryptographic session authentication |
| **Cloudflare Tunnel** | Secure external access — no port forwarding required |

---

## Key Features

- **Ultra-Low Latency Live Stream** — Optimized HLS settings (1-second segments) for near real-time viewing
- **Continuous DVR Recording** — Automated segmentation and storage to external HDD
- **Full Hardware Control** — Adjust resolution (UXGA → 96×96), brightness, contrast, AWB, special effects, and LED flash from the web UI
- **Service Management** — Start and stop the backend recording service directly from the dashboard
- **Cryptographic Security** — No plaintext passwords; uses PHP `password_hash()` / `password_verify()` for session authentication
- **Zero-Config Remote Access** — Cloudflare Zero Trust tunnel handles secure external exposure

---

## Hardware Requirements

| Component | Notes |
|---|---|
| Raspberry Pi 5 | With official 27W USB-C Power Supply |
| ESP32-CAM Module (AI-Thinker) | + FTDI programmer for flashing |
| 1TB External HDD | Mounted as NVR storage volume |
| Stable 5V Power Source | Dedicated supply for the ESP32-CAM |

---

## Wiring Diagram

![Wiring Diagram](docs/wiring_diagram.jpg)

### FTDI → ESP32-CAM (Flashing Only)

| FTDI Pin | ESP32-CAM Pin | Notes |
|---|---|---|
| 5V | 5V | |
| GND | GND | |
| U0R | TX | |
| U0T | RX | |
| — | IO0 → GND | **Remove this jumper after flashing** |

---

## Deployment Guide

### 1. ESP32-CAM Setup

1. Open `esp32/esp32_camera.ino` in the Arduino IDE.
2. Update the WiFi credentials (`ssid` and `password`).
3. Wire IO0 to GND, then flash the firmware to the ESP32-CAM.
4. Remove the IO0 jumper and power-cycle the module.

---

### 2. Raspberry Pi 5 Backend

**Install dependencies:**

```bash
sudo apt update
sudo apt install ffmpeg python3-flask python3-requests php-fpm php-cli -y
sudo npm install -g pm2
```

**Configure the FFmpeg systemd service:**

```bash
sudo cp kamera_hls.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable kamera_hls.service
sudo systemctl start kamera_hls.service
```

**Start the Flask middleware API with PM2:**

```bash
pm2 start main.py --name pi-api
pm2 save
pm2 startup
```

**Grant the API permission to toggle the FFmpeg service:**

```bash
echo "alluvian ALL=(ALL) NOPASSWD: /bin/systemctl start kamera_hls.service, /bin/systemctl stop kamera_hls.service" \
  | sudo tee /etc/sudoers.d/kamera_hls
```

---

### 3. Frontend Web Server

1. Copy the contents of the `web/` directory to your web server root:

    ```bash
    sudo cp -r web/* /var/www/html/
    ```

2. Generate a secure bcrypt hash for your dashboard password:

    ```bash
    php -r 'echo password_hash("YOUR_PASSWORD", PASSWORD_DEFAULT) . PHP_EOL;'
    ```

3. Paste the resulting hash into the `$stored_hash` variable inside `auth.php`.

4. Ensure your Nginx or Apache configuration is set to process PHP files (`php-fpm` socket or `mod_php`).

---

## Project Structure

```
esp32-pi-nvr/
├── esp32/
│   └── esp32_camera.ino        # Arduino firmware for the ESP32-CAM
├── pi/
│   ├── main.py                 # Flask middleware API
│   └── kamera_hls.service      # systemd unit for the FFmpeg HLS service
├── web/
│   ├── index.php               # Main dashboard UI
│   ├── auth.php                # Session authentication
│   └── ...
└── docs/
    └── wiring_diagram.jpg
```

---

