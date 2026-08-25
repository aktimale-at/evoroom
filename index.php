<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Психологическая студия</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/selfie_segmentation.js" crossorigin="anonymous"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f0f1a;
            color: #e0e0e0;
            height: 100vh;
            overflow: hidden;
        }

        .room {
            display: flex;
            height: 100vh;
            width: 100vw;
            background: #0f0f1a;
            min-width: 0;
            overflow: hidden;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            width: 280px;
            min-width: 280px;
            height: 100vh;
            background: #161625;
            border-right: 1px solid #2a2a3e;
            flex-shrink: 0;
            overflow: hidden;
            z-index: 10;
            min-height: 0;
        }

        .participants-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
            padding: 8px 12px 8px 12px;
        }

        .host-video-panel {
            flex-shrink: 0;
            padding: 12px 12px 0 12px;
        }

        .host-video-stage {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            border-radius: 14px;
            overflow: hidden;
            background: #0a0a14;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
        }

        .host-video-stage video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            background: transparent;
            position: relative;
            z-index: 1;
        }

        .host-video-stage .host-bg-blur-video {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
            display: none;
            pointer-events: none;
        }

        .host-video-stage .host-processed-canvas {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
            display: none;
            pointer-events: none;
            background: #0a0a14;
        }

        .host-video-stage.has-beauty-bg .host-processed-canvas {
            display: block;
        }

        .host-video-stage.has-beauty-bg #videoSelf,
        .host-video-stage.has-beauty-bg .host-bg-blur-video {
            opacity: 0;
            pointer-events: none;
        }

        .host-video-stage.has-beauty-bg .host-processed-canvas.is-mirrored {
            transform: scaleX(-1);
        }

        .participant-video .host-processed-canvas {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
            display: none;
            pointer-events: none;
        }

        .participant-video.has-beauty-bg .host-processed-canvas {
            display: block;
        }

        .participant-video.has-beauty-bg video {
            opacity: 0;
        }

        .participant-video.has-beauty-bg .host-processed-canvas.is-mirrored {
            transform: scaleX(-1);
        }

        .host-video-stage .host-name-tag {
            position: absolute;
            left: 10px;
            bottom: 10px;
            padding: 0 10px;
            height: 24px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.55);
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            backdrop-filter: blur(6px);
            pointer-events: none;
            z-index: 2;
        }

        .host-video-stage .host-speaking-ring {
            position: absolute;
            inset: 0;
            border-radius: 14px;
            border: 2px solid transparent;
            pointer-events: none;
            z-index: 1;
        }

        .host-video-panel.is-speaking .host-speaking-ring {
            border-color: #4ade80;
        }

        .host-video-stage .host-status-dot {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #4ade80;
            border: 2px solid #0a0a14;
            z-index: 2;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 4px 10px 4px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6a6a7e;
            flex-shrink: 0;
        }

        .participant-count {
            background: #2a2a3e;
            padding: 1px 10px;
            border-radius: 12px;
            font-size: 10px;
            color: #aaa;
        }

        .participants-list {
            flex: 1;
            overflow-y: auto;
            padding-right: 2px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 6px;
        }

        .participants-list::-webkit-scrollbar {
            width: 3px;
        }
        .participants-list::-webkit-scrollbar-thumb {
            background: #3a3a4e;
            border-radius: 4px;
        }

        .participant-item {
            display: block;
            padding: 0;
            border-radius: 12px;
            cursor: pointer;
            transition: box-shadow 0.15s ease, outline-color 0.15s ease;
            background: transparent;
            border: none;
            flex-shrink: 0;
            min-width: 0;
            width: 100%;
            box-sizing: border-box;
            outline: 2px solid transparent;
            outline-offset: 1px;
        }

        .participant-item:hover .participant-video {
            border-color: rgba(255, 255, 255, 0.14);
        }

        .participant-item.active {
            background: transparent;
            outline-color: rgba(74, 108, 247, 0.55);
        }

        .participant-video {
            position: relative;
            flex: none;
            width: 100%;
            max-width: none;
            aspect-ratio: 16 / 9;
            height: auto;
            border-radius: 12px;
            overflow: hidden;
            background: #1a1a2e;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .participant-video video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            background: transparent;
            position: relative;
            z-index: 1;
        }

        .participant-video .host-bg-blur-video {
            display: none !important;
        }

        .participant-video .video-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 600;
            color: #fff;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .participant-video .video-placeholder .animated-bg {
            position: absolute;
            inset: 0;
            border-radius: 12px;
            opacity: 0.85;
        }

        .participant-video .speaking-ring {
            position: absolute;
            inset: 0;
            border-radius: 12px;
            border: 2px solid transparent;
            pointer-events: none;
            z-index: 3;
        }

        .participant-item.speaking .speaking-ring {
            border-color: #4ade80;
        }

        .participant-video .participant-emoji {
            position: absolute;
            top: 6px;
            left: 6px;
            z-index: 4;
            font-size: 13px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(6px);
        }

        .participant-video .participant-name-tag {
            position: absolute;
            left: 8px;
            bottom: 8px;
            z-index: 4;
            max-width: calc(100% - 36px);
            height: 22px;
            font-size: 12px;
            font-weight: 500;
            color: #fff;
            background: rgba(0, 0, 0, 0.6);
            padding: 0 10px;
            border-radius: 20px;
            backdrop-filter: blur(8px);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1;
            display: inline-flex;
            align-items: center;
        }

        .participant-video .status-dot {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid rgba(22, 22, 37, 0.9);
            z-index: 4;
        }

        .status-dot.online {
            background: #4ade80;
        }
        .status-dot.speaking {
            background: #4ade80;
        }
        .status-dot.waiting {
            background: #facc15;
        }
        .status-dot.muted {
            background: #f87171;
        }

        .sidebar-divider {
            border-top: 1px solid #2a2a3e;
            margin: 0 12px;
            flex-shrink: 0;
        }

        .tools-section {
            padding: 10px 12px 8px 12px;
            flex-shrink: 0;
        }

        .tools-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
        }

        .tool-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px 4px 6px 4px;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: #6a6a7e;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .tool-btn .icon {
            font-size: 18px;
            line-height: 1.2;
        }

        .tool-btn .label {
            font-size: 9px;
            margin-top: 2px;
            color: #6a6a7e;
        }

        .tool-btn:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #e0e0e0;
        }
        .tool-btn:hover .label {
            color: #aaa;
        }

        .tool-btn.active {
            background: rgba(74, 108, 247, 0.15);
            color: #4a6cf7;
        }
        .tool-btn.active .label {
            color: #4a6cf7;
        }

        .tool-btn.filter-on {
            color: #f0abfc;
        }

        .tool-btn.filter-on .label {
            color: #f0abfc;
        }

        .sidebar-bottom {
            padding: 10px 12px 14px 12px;
            border-top: 1px solid #2a2a3e;
            flex-shrink: 0;
        }

        .mode-toggle-btn {
            width: 100%;
            padding: 9px 0;
            border: 1px solid #2a2a3e;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.03);
            color: #e0e0e0;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .mode-toggle-btn:hover {
            background: rgba(255, 255, 255, 0.06);
        }

        .mode-toggle-btn.active {
            background: rgba(74, 108, 247, 0.15);
            border-color: #4a6cf7;
            color: #4a6cf7;
        }

        .main-area {
            flex: 1;
            position: relative;
            background: #0f0f1a;
            overflow: hidden;
            min-width: 0;
            min-height: 0;
        }

        .screen {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.5s cubic-bezier(0.4, 0, 0.2, 1),
                transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: transform, opacity;
            min-width: 0;
            min-height: 0;
        }

        .screen.hidden {
            opacity: 0;
            transform: scale(0.96);
            pointer-events: none;
        }

        .screen.visible {
            opacity: 1;
            transform: scale(1);
            pointer-events: auto;
        }

        /* ===== ЗАГОЛОВКИ РАЗДЕЛОВ ===== */
        .screen-header {
            flex-shrink: 0;
            padding: 0 4px 12px;
            z-index: 2;
        }

        .screen-header-dark {
            position: absolute;
            top: 16px;
            left: 16px;
            right: 16px;
            padding: 0;
            pointer-events: none;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            letter-spacing: -0.02em;
            line-height: 1.2;
            margin: 0;
        }

        .section-subtitle {
            font-size: 13px;
            margin: 4px 0 0;
            opacity: 0.65;
            font-weight: 400;
        }

        .screen-header-dark .section-title {
            color: #e0e0e0;
        }

        .screen-header-dark .section-subtitle {
            color: #8a8a9e;
        }

        .screen-header-light {
            padding-right: 220px;
            box-sizing: border-box;
        }

        .screen-header-light .section-title {
            color: #1a1a2e;
        }

        .screen-header-light .section-subtitle {
            color: #6a6a7e;
        }

        .canvas-empty {
            flex: 1;
            min-height: 280px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #9a948c;
            font-size: 14px;
            text-align: center;
            padding: 24px;
        }

        .canvas-empty-icon {
            font-size: 40px;
            opacity: 0.5;
            line-height: 1;
        }

        .screen-video {
            background: #0a0a12;
            flex-direction: column;
            align-items: stretch;
            justify-content: stretch;
            padding: 66px 16px 16px;
            box-sizing: border-box;
        }

        .video-grid {
            display: grid;
            gap: 16px;
            width: 100%;
            flex: 1;
            min-height: 0;
            max-width: none;
            position: relative;
            justify-content: center;
            align-content: center;
            justify-items: stretch;
            align-items: stretch;
            box-sizing: border-box;
        }

        .video-tile {
            background: #1a1a2e;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.06);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            aspect-ratio: 16 / 9;
            width: 100%;
            height: auto;
            min-width: 0;
            min-height: 0;
        }

        .video-tile video {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            background: #0f0f1a;
        }

        .video-tile .avatar-big {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(28px, 5vw, 56px);
            font-weight: 600;
            color: #fff;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.35);
        }

        .video-tile .name-tag {
            position: absolute;
            bottom: 12px;
            left: 16px;
            height: 26px;
            font-size: 13px;
            font-weight: 500;
            line-height: 1;
            color: #fff;
            background: rgba(0, 0, 0, 0.6);
            padding: 0 14px;
            border-radius: 20px;
            backdrop-filter: blur(8px);
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
            max-width: calc(100% - 32px);
            overflow: hidden;
            text-overflow: ellipsis;
            box-sizing: border-box;
        }

        .video-tile .speaking-ring {
            position: absolute;
            inset: -2px;
            border-radius: 18px;
            border: 2px solid transparent;
            transition: border-color 0.3s;
        }

        .video-tile.speaking .speaking-ring {
            border-color: #4ade80;
        }

        .video-tile.active-participant {
            border: 2px solid #4a6cf7;
            box-shadow: 0 0 20px rgba(74, 108, 247, 0.15);
            border-radius: 16px;
        }

        .video-tile .tile-fullscreen-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(0, 0, 0, 0.7);
            border: none;
            color: #fff;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            z-index: 10;
            backdrop-filter: blur(8px);
            transition: all 0.2s;
            font-family: inherit;
            opacity: 0;
            transform: scale(0.9);
        }

        .video-tile:hover .tile-fullscreen-btn {
            opacity: 1;
            transform: scale(1);
        }

        .video-tile .tile-fullscreen-btn:hover {
            background: rgba(0, 0, 0, 0.9);
        }

        /* ===== КНОПКА ВОЗВРАТА К ПЛИТКАМ ===== */
        .exit-fullscreen-btn {
            display: none;
            position: absolute;
            top: 16px;
            right: 360px;
            z-index: 200;
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 12px 16px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 13px;
            backdrop-filter: blur(8px);
            transition: background 0.2s, transform 0.2s;
            font-family: inherit;
            align-items: center;
            gap: 10px;
            line-height: 1;
            pointer-events: auto;
        }

        .exit-fullscreen-btn.visible {
            display: inline-flex;
        }

        .exit-fullscreen-btn:hover {
            background: rgba(0, 0, 0, 0.95);
            transform: scale(1.04);
        }

        .exit-fullscreen-btn .tiles-icon {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3px;
            width: 16px;
            height: 16px;
        }

        .exit-fullscreen-btn .tiles-icon span {
            display: block;
            background: #fff;
            border-radius: 2px;
        }

        /* ===== ТЕСТ: КОЛ-ВО УЧАСТНИКОВ + ЗАПИСЬ ===== */
        .top-right-controls {
            position: absolute;
            top: 16px;
            right: 16px;
            z-index: 210;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            pointer-events: auto;
        }

        .demo-count-control {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(0, 0, 0, 0.72);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
        }

        .demo-count-control label {
            font-size: 11px;
            color: #9a9aae;
            padding-left: 8px;
            white-space: nowrap;
        }

        .demo-count-control input {
            width: 48px;
            height: 32px;
            border-radius: 9px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            outline: none;
        }

        .demo-count-control input:focus {
            border-color: rgba(74, 108, 247, 0.55);
        }

        .demo-count-control button {
            height: 32px;
            padding: 0 12px;
            border-radius: 9px;
            border: none;
            background: rgba(74, 108, 247, 0.85);
            color: #fff;
            font-family: inherit;
            font-size: 12px;
            font-weight: 550;
            cursor: pointer;
            white-space: nowrap;
        }

        .demo-count-control button:hover {
            background: rgba(74, 108, 247, 1);
        }

        .record-controls {
            position: relative;
            top: auto;
            right: auto;
            z-index: 210;
            display: inline-flex;
            align-items: center;
            gap: 0;
            padding: 4px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(0, 0, 0, 0.72);
            backdrop-filter: blur(10px);
            pointer-events: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
        }

        .record-controls.is-active {
            background: rgba(20, 22, 40, 0.9);
            border-color: rgba(255, 255, 255, 0.18);
        }

        .record-controls.is-paused {
            background: rgba(20, 22, 40, 0.9);
            border-color: rgba(255, 255, 255, 0.18);
        }

        .record-start-btn,
        .record-status,
        .record-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            background: transparent;
            color: #fff;
            font-family: inherit;
            font-size: 13px;
            font-weight: 550;
            line-height: 1;
            cursor: pointer;
            transition: background 0.15s, color 0.15s, opacity 0.15s;
        }

        .record-start-btn {
            padding: 8px 12px;
            border-radius: 10px;
        }

        .record-start-btn:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .record-controls.is-active .record-start-btn {
            display: none;
        }

        .record-status {
            display: none;
            padding: 8px 10px 8px 12px;
            cursor: default;
            user-select: none;
        }

        .record-controls.is-active .record-status {
            display: inline-flex;
        }

        .record-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ef4444;
            flex-shrink: 0;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.25);
        }

        .record-controls.is-paused .record-dot {
            opacity: 0.55;
        }

        .record-timer {
            font-variant-numeric: tabular-nums;
            color: #c8c8d8;
            min-width: 42px;
        }

        .record-actions {
            display: none;
            align-items: center;
            gap: 2px;
            padding-right: 2px;
            margin-left: 2px;
            border-left: 1px solid rgba(255, 255, 255, 0.12);
            padding-left: 4px;
        }

        .record-controls.is-active .record-actions {
            display: inline-flex;
        }

        .record-icon-btn {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            color: #fff;
        }

        .record-icon-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .record-icon-btn svg {
            width: 16px;
            height: 16px;
            display: block;
            fill: currentColor;
        }

        .record-icon-btn.record-stop-btn:hover {
            background: rgba(239, 68, 68, 0.22);
            color: #fecaca;
        }

        .record-icon-btn .icon-play {
            display: none;
        }

        .record-controls.is-paused .record-icon-btn .icon-pause {
            display: none;
        }

        .record-controls.is-paused .record-icon-btn .icon-play {
            display: block;
        }

        .video-tile.fullscreen-tile {
            position: absolute !important;
            inset: 0 !important;
            width: 100% !important;
            height: 100% !important;
            border-radius: 0 !important;
            z-index: 100;
            background: #0a0a12;
            border: none !important;
            box-shadow: none !important;
            overflow: hidden;
            margin: 0 !important;
        }

        .video-tile.fullscreen-tile video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            pointer-events: none;
        }

        .video-tile.fullscreen-tile .tile-fullscreen-btn,
        .video-tile.fullscreen-tile .tile-close-btn {
            display: none !important;
        }

        .video-tile.fullscreen-tile .name-tag {
            font-size: 18px;
            padding: 8px 20px;
            bottom: 30px;
            left: 30px;
            z-index: 2;
        }

        .video-tile.fullscreen-tile .speaking-ring {
            display: none;
        }

        /* ===== ХОЛСТ — БЕЛЫЙ ФОН ===== */
        .screen-canvas {
            background: #ffffff !important;
            padding: 0;
            align-items: stretch;
        }

        .canvas-wrapper {
            width: 100%;
            height: 100%;
            max-width: none;
            background: #ffffff !important;
            border-radius: 0;
            box-shadow: none;
            padding: 20px 28px 16px 28px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            margin: 0;
            min-width: 0;
            min-height: 0;
            box-sizing: border-box;
        }

        .canvas-scroll-area {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
            padding: 4px 0;
            background: #ffffff !important;
            display: flex;
            flex-direction: column;
            -webkit-overflow-scrolling: touch;
        }

        .canvas-scroll-area > .figures-grid,
        .canvas-scroll-area > .drawing-area {
            flex: 1;
            min-height: 420px;
        }

        .canvas-scroll-area::-webkit-scrollbar {
            width: 6px;
        }
        .canvas-scroll-area::-webkit-scrollbar-track {
            background: transparent;
        }
        .canvas-scroll-area::-webkit-scrollbar-thumb {
            background: #d4d4d4;
            border-radius: 8px;
        }

        /* ===== КАРТЫ — БЕЛЫЙ ФОН, НЕТ ЗАТЕМНЕНИЯ ===== */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 12px;
            padding: 12px 8px 20px 8px;
            background: #ffffff !important;
            isolation: isolate;
        }

        @media (max-width: 1100px) {
            .cards-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px;
            }
        }

        .card {
            aspect-ratio: 5 / 7;
            background: linear-gradient(145deg, #2a1f3d, #1a1228);
            border-radius: 16px;
            box-shadow: none;
            filter: none;
            cursor: pointer;
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            user-select: none;
            border: 1px solid rgba(255, 255, 255, 0.08);
            perspective: 800px;
            background-color: #2a1f3d;
        }

        .card:hover {
            transform: translateY(-6px) scale(1.03);
            box-shadow: none;
        }

        .cards-grid.is-shuffling .card {
            pointer-events: none;
        }

        .cards-grid.is-shuffling .card:hover {
            transform: none;
        }

        .card.card-shuffle {
            animation: card-shuffle-blur 0.45s ease-in-out both;
        }

        @keyframes card-shuffle-blur {
            0% {
                filter: blur(0);
                opacity: 1;
            }
            50% {
                filter: blur(2.5px);
                opacity: 0.82;
            }
            100% {
                filter: blur(2.5px);
                opacity: 0.75;
            }
        }

        .card.card-deal {
            animation: card-deal-blur 0.4s ease-out both;
        }

        @keyframes card-deal-blur {
            0% {
                filter: blur(2.5px);
                opacity: 0.75;
            }
            100% {
                filter: blur(0);
                opacity: 1;
            }
        }

        .card .card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            transform-style: preserve-3d;
            border-radius: 16px;
        }

        .card.flipped .card-inner {
            transform: rotateY(180deg);
        }

        .card .card-back,
        .card .card-face {
            position: absolute;
            inset: 0;
            backface-visibility: hidden;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .card .card-back {
            background: repeating-linear-gradient(45deg,
                    rgba(255, 255, 255, 0.04) 0px,
                    rgba(255, 255, 255, 0.04) 8px,
                    rgba(255, 255, 255, 0.08) 8px,
                    rgba(255, 255, 255, 0.08) 16px);
            font-size: 28px;
            color: rgba(255, 255, 255, 0.15);
        }

        .card .card-face {
            background: #fffdf9;
            border: 1px solid rgba(0, 0, 0, 0.06);
            transform: rotateY(180deg);
            font-size: 28px;
            font-weight: 500;
            color: #1a1a2e;
            padding: 12px;
            text-align: center;
            line-height: 1.3;
        }

        /* ===== РАССТАНОВКА ===== */
        .figures-grid {
            position: relative;
            width: 100%;
            min-height: 420px;
            height: 100%;
            background:
                radial-gradient(circle at center, rgba(74, 108, 247, 0.04) 0%, transparent 55%),
                #ffffff;
            border: 1px dashed #d8d2cc;
            border-radius: 16px;
            overflow: hidden;
            touch-action: none;
        }

        .figure-item {
            position: absolute;
            width: 96px;
            height: 96px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
            cursor: grab;
            user-select: none;
            text-align: center;
            padding: 8px;
            line-height: 1.2;
            touch-action: none;
            transition: box-shadow 0.15s ease, transform 0.15s ease;
            z-index: 1;
            box-sizing: border-box;
        }

        .figure-item:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.18);
            z-index: 2;
        }

        .figure-item.dragging {
            opacity: 0.9;
            transform: scale(1.08);
            cursor: grabbing;
            z-index: 1000;
            transition: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.28);
            border: 2px solid #4a6cf7;
            pointer-events: none;
        }

        .figure-item .delete-figure {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.65);
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
            font-family: inherit;
            z-index: 2;
            pointer-events: auto;
        }

        .figure-item:hover .delete-figure {
            opacity: 1;
        }

        .figures-hint {
            position: absolute;
            left: 50%;
            bottom: 12px;
            transform: translateX(-50%);
            text-align: center;
            color: #8a847c;
            font-size: 12px;
            padding: 6px 12px;
            pointer-events: none;
            white-space: nowrap;
            z-index: 0;
        }

        /* ===== РИСОВАНИЕ ===== */
        .drawing-area {
            width: 100%;
            height: 100%;
            min-height: 350px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px dashed #d0c8c0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
            color: #b0a89e;
            font-size: 14px;
            margin: 10px 0;
        }

        .drawing-area .icon-big {
            font-size: 48px;
            opacity: 0.4;
        }

        /* ===== ПРЕЗЕНТАЦИЯ PDF ===== */
        .presentation-area {
            width: 100%;
            flex: 1;
            min-height: 420px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.06);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            margin: 4px 0 8px;
            position: relative;
        }

        .canvas-scroll-area > .presentation-area {
            flex: 1;
            min-height: 420px;
        }

        .presentation-dropzone {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            text-align: center;
            padding: 32px 24px;
            border: 2px dashed #d0c8c0;
            border-radius: 12px;
            margin: 4px;
            color: #8a8278;
            background: #fafafa;
            transition: border-color 0.15s ease, background 0.15s ease;
            cursor: default;
        }

        .presentation-dropzone.drag-over {
            border-color: #4a6cf7;
            background: rgba(74, 108, 247, 0.06);
            color: #4a6cf7;
        }

        .presentation-dropzone .icon-big {
            font-size: 52px;
            opacity: 0.45;
            line-height: 1;
        }

        .presentation-dropzone .drop-title {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a2e;
        }

        .presentation-dropzone .drop-hint {
            font-size: 13px;
            color: #a09890;
            max-width: 360px;
            line-height: 1.45;
        }

        .presentation-dropzone .drop-upload-btn {
            margin-top: 6px;
            padding: 10px 22px;
            border: none;
            border-radius: 10px;
            background: #4a6cf7;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.15s ease;
        }

        .presentation-dropzone .drop-upload-btn:hover {
            background: #5a7cf7;
        }

        .presentation-viewer {
            flex: 1;
            display: flex;
            min-height: 0;
            position: relative;
            background: #f0f0f2;
        }

        .presentation-stage {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .presentation-stage-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 12px;
            background: #ffffff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
        }

        .presentation-filename-label {
            font-size: 12px;
            font-weight: 500;
            color: #1a1a2e;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 50%;
        }

        .presentation-stage-actions {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-shrink: 0;
        }

        .presentation-stage-actions button {
            padding: 5px 12px;
            border: none;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.04);
            color: #1a1a2e;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            font-family: inherit;
        }

        .presentation-stage-actions button:hover {
            background: rgba(0, 0, 0, 0.08);
        }

        .presentation-carousel {
            flex: 1;
            display: flex;
            align-items: center;
            min-height: 0;
            gap: 4px;
            padding: 12px 8px;
            touch-action: pan-y;
        }

        .presentation-nav-btn {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            background: #ffffff;
            color: #1a1a2e;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease, transform 0.15s ease;
        }

        .presentation-nav-btn:hover:not(:disabled) {
            background: #4a6cf7;
            color: #fff;
            transform: scale(1.05);
        }

        .presentation-nav-btn:disabled {
            opacity: 0.35;
            cursor: default;
        }

        .presentation-page-wrap {
            flex: 1;
            min-width: 0;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #e8e8ec;
            border-radius: 10px;
            touch-action: none;
            cursor: grab;
            user-select: none;
        }

        .presentation-page-wrap.is-swiping {
            cursor: grabbing;
        }

        .presentation-page-wrap canvas {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12);
            background: #fff;
            border-radius: 2px;
            pointer-events: none;
        }

        .presentation-page-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 8px 12px 12px;
            flex-shrink: 0;
            background: #ffffff;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .presentation-page-bar .page-label {
            font-size: 13px;
            font-weight: 500;
            color: #1a1a2e;
            min-width: 72px;
            text-align: center;
        }

        .presentation-page-dots {
            display: flex;
            gap: 6px;
            align-items: center;
            max-width: 200px;
            overflow: hidden;
        }

        .presentation-page-dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #d0d0d8;
            flex-shrink: 0;
        }

        .presentation-page-dots span.active {
            background: #4a6cf7;
            width: 8px;
            height: 8px;
        }

        .presentation-sidebar {
            width: 160px;
            min-width: 0;
            flex-shrink: 0;
            background: #ffffff;
            border-left: 1px solid rgba(0, 0, 0, 0.06);
            display: flex;
            flex-direction: column;
            min-height: 0;
            transition: width 0.2s ease, opacity 0.2s ease, border 0.2s ease;
        }

        .presentation-sidebar.hidden {
            width: 0;
            min-width: 0;
            opacity: 0;
            border-left: none;
            overflow: hidden;
            pointer-events: none;
        }

        .presentation-sidebar-header {
            padding: 10px 12px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #8a8278;
            font-weight: 600;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
        }

        .presentation-thumbs {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            touch-action: pan-y;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }

        .presentation-thumbs::-webkit-scrollbar {
            width: 4px;
        }
        .presentation-thumbs::-webkit-scrollbar-thumb {
            background: #d4d4d4;
            border-radius: 4px;
        }

        .presentation-thumb {
            border: 2px solid transparent;
            border-radius: 8px;
            padding: 6px;
            background: #f5f5f7;
            cursor: pointer;
            transition: border-color 0.15s ease, background 0.15s ease;
            text-align: center;
            width: 100%;
            box-sizing: border-box;
            overflow: visible;
            outline: none;
            -webkit-tap-highlight-color: transparent;
        }

        .presentation-thumb:hover {
            background: #eeeef2;
        }

        .presentation-thumb:focus,
        .presentation-thumb:focus-visible {
            outline: none;
            box-shadow: none;
        }

        .presentation-thumb.active {
            border-color: #4a6cf7;
            background: rgba(74, 108, 247, 0.08);
        }

        .presentation-thumb.active:hover {
            background: rgba(74, 108, 247, 0.12);
        }

        .presentation-thumb-frame {
            width: 100%;
            aspect-ratio: var(--thumb-aspect, 3 / 4);
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e8e8ec;
            border-radius: 4px;
            overflow: hidden;
        }

        .presentation-thumb canvas {
            max-width: 100%;
            max-height: 100%;
            width: auto !important;
            height: auto !important;
            display: block;
            border-radius: 2px;
            background: #fff;
            object-fit: contain;
        }

        .presentation-thumb .thumb-num {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            color: #6a6a7e;
            font-weight: 500;
        }

        .presentation-loading {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.8);
            color: #1a1a2e;
            font-size: 14px;
            z-index: 5;
        }

        /* ===== ПАНЕЛЬ УПРАВЛЕНИЯ ===== */
        .canvas-toolbar {
            display: flex;
            gap: 10px;
            justify-content: center;
            padding-top: 16px;
            flex-shrink: 0;
            border-top: 1px solid rgba(0, 0, 0, 0.04);
            margin-top: 4px;
            flex-wrap: wrap;
            min-height: 52px;
            align-items: center;
            background: #ffffff;
        }

        .canvas-toolbar .toolbar-group {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .canvas-toolbar button {
            padding: 6px 16px;
            border: none;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.04);
            color: #1a1a2e;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: inherit;
            white-space: nowrap;
        }

        .canvas-toolbar button:hover {
            background: rgba(0, 0, 0, 0.08);
        }

        .canvas-toolbar button.primary {
            background: #4a6cf7;
            color: #fff;
        }

        .canvas-toolbar button.primary:hover {
            background: #5a7cf7;
        }

        .canvas-toolbar .toolbar-divider {
            width: 1px;
            height: 28px;
            background: rgba(0, 0, 0, 0.08);
        }

        .canvas-toolbar .toolbar-label {
            font-size: 12px;
            color: #6a6a7e;
            font-weight: 500;
            margin-right: 4px;
        }

        /* ===== ВИДЕО-PiP ===== */
        .video-pip {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 200px;
            height: 130px;
            min-width: 120px;
            min-height: 80px;
            max-width: 80%;
            max-height: 70%;
            border-radius: 14px;
            background: #0a0a12;
            border: 2px solid rgba(74, 108, 247, 0.4);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: grab;
            touch-action: none;
            user-select: none;
        }

        .video-pip.dragging {
            cursor: grabbing;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.55);
            border-color: rgba(74, 108, 247, 0.7);
            z-index: 6;
        }

        .video-pip.resizing {
            cursor: nesw-resize;
            z-index: 6;
        }

        .video-pip .pip-resize-handle {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 22px;
            height: 22px;
            cursor: nesw-resize;
            z-index: 4;
        }

        .video-pip .pip-resize-handle::before {
            content: '';
            position: absolute;
            left: 5px;
            bottom: 5px;
            width: 10px;
            height: 10px;
            border-left: 2px solid rgba(255, 255, 255, 0.55);
            border-bottom: 2px solid rgba(255, 255, 255, 0.55);
            border-radius: 1px;
        }

        .video-pip:hover .pip-resize-handle::before {
            border-color: rgba(255, 255, 255, 0.9);
        }

        .video-pip video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            pointer-events: none;
        }

        .video-pip .pip-placeholder {
            font-size: 32px;
            color: #4a4a5e;
            text-align: center;
            line-height: 1.4;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            pointer-events: none;
        }

        .video-pip .pip-placeholder .pip-emoji {
            font-size: 40px;
        }

        .video-pip .pip-placeholder .pip-name {
            font-size: 13px;
            color: #aaa;
            font-weight: 500;
        }

        .video-pip .pip-name-tag {
            position: absolute;
            bottom: 8px;
            left: 12px;
            font-size: 11px;
            font-weight: 500;
            background: rgba(0, 0, 0, 0.6);
            padding: 2px 10px;
            border-radius: 12px;
            backdrop-filter: blur(8px);
            pointer-events: none;
            z-index: 2;
        }

        .video-pip .pip-expand-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.6);
            border: none;
            color: #fff;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            z-index: 3;
            backdrop-filter: blur(8px);
            font-family: inherit;
            transition: opacity 0.2s;
            opacity: 0;
            transform: scale(0.9);
        }

        .video-pip:hover .pip-expand-btn {
            opacity: 0.8;
            transform: scale(1);
        }

        .video-pip .pip-expand-btn:hover {
            opacity: 1;
            background: rgba(0, 0, 0, 0.8);
        }

        .video-pip.hidden {
            opacity: 0;
            transform: scale(0.9);
            pointer-events: none;
        }

        .video-pip.expanded {
            position: absolute;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            height: 100% !important;
            border-radius: 0;
            border: none;
            z-index: 100;
            box-shadow: none;
            cursor: default;
            max-width: none;
            max-height: none;
            transition: none;
        }

        .video-pip.expanded .pip-resize-handle {
            display: none;
        }

        .video-pip.expanded .pip-expand-btn {
            opacity: 1;
            transform: scale(1);
            top: 20px;
            right: 20px;
            padding: 8px 16px;
            font-size: 14px;
        }

        .video-pip.expanded .pip-name-tag {
            font-size: 16px;
            padding: 6px 18px;
            bottom: 20px;
            left: 20px;
        }

        .pip-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.4s ease;
        }

        .pip-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .chat-overlay {
            position: absolute;
            bottom: 80px;
            right: 20px;
            width: 320px;
            max-height: 400px;
            background: rgba(22, 22, 37, 0.95);
            backdrop-filter: blur(16px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 20;
        }

        .chat-overlay.active {
            display: flex;
        }

        .beauty-overlay {
            position: absolute;
            bottom: 80px;
            right: 20px;
            width: 360px;
            max-height: none;
            background: rgba(22, 22, 37, 0.96);
            backdrop-filter: blur(16px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 21;
        }

        .beauty-overlay.active {
            display: flex;
        }

        .beauty-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            flex-shrink: 0;
        }

        .beauty-header .title {
            font-weight: 600;
            font-size: 14px;
        }

        .beauty-header .close-btn {
            background: none;
            border: none;
            color: #6a6a7e;
            font-size: 18px;
            cursor: pointer;
        }

        .beauty-body {
            flex: 0 0 auto;
            padding: 14px 16px;
            overflow: visible;
        }

        .beauty-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .beauty-section-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #8a8a9e;
            font-weight: 600;
            margin: 4px 0 8px;
        }

        .beauty-section-label:not(:first-child) {
            margin-top: 16px;
        }

        .beauty-bg-grid {
            grid-template-columns: 1fr 1fr;
        }

        .beauty-btn.beauty-bg-btn {
            min-height: 64px;
            background-size: cover;
            background-position: center;
            border-color: rgba(255, 255, 255, 0.12);
            position: relative;
            overflow: hidden;
        }

        .beauty-btn.beauty-bg-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(10, 10, 20, 0.82), rgba(10, 10, 20, 0.25));
            z-index: 0;
        }

        .beauty-btn.beauty-bg-btn .beauty-name,
        .beauty-btn.beauty-bg-btn .beauty-desc {
            position: relative;
            z-index: 1;
        }

        .beauty-btn.beauty-bg-btn[data-bg="interior"] {
            background-image: url('https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?auto=format&fit=crop&w=400&q=70');
        }

        .beauty-btn.beauty-bg-btn[data-bg="nature"] {
            background-image: url('https://images.unsplash.com/photo-1506905925346-21bda4d32df4?auto=format&fit=crop&w=400&q=70');
        }

        .beauty-btn.beauty-bg-btn[data-bg="blur"] {
            background: linear-gradient(135deg, #4a5568, #2d3748);
        }

        .beauty-btn.beauty-bg-btn[data-bg="none"] {
            background: rgba(255, 255, 255, 0.03);
        }

        .beauty-btn.beauty-bg-btn[data-bg="none"]::before {
            display: none;
        }

        .beauty-btn {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.03);
            color: #e0e0e0;
            cursor: pointer;
            font-family: inherit;
            text-align: left;
            transition: background 0.15s, border-color 0.15s;
        }

        .beauty-btn:hover {
            background: rgba(255, 255, 255, 0.07);
        }

        .beauty-btn.active {
            background: rgba(74, 108, 247, 0.18);
            border-color: rgba(74, 108, 247, 0.45);
            color: #fff;
        }

        .beauty-btn .beauty-name {
            font-size: 13px;
            font-weight: 550;
        }

        .beauty-btn .beauty-desc {
            font-size: 10px;
            color: #8a8a9e;
            line-height: 1.3;
        }

        .beauty-btn.active .beauty-desc {
            color: #a8b4f0;
        }

        .beauty-intensity {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
            padding: 12px 16px 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 12px;
            color: #9a9aae;
            background: rgba(22, 22, 37, 0.98);
        }

        .beauty-intensity label {
            flex-shrink: 0;
        }

        .beauty-intensity input[type="range"] {
            flex: 1;
            accent-color: #4a6cf7;
        }

        .beauty-intensity span {
            width: 36px;
            text-align: right;
            color: #c8c8d8;
            font-variant-numeric: tabular-nums;
        }

        .beauty-mirror {
            display: flex;
            align-items: center;
            padding: 0 16px 14px;
            font-size: 13px;
            color: #c8c8d8;
            background: rgba(22, 22, 37, 0.98);
            flex-shrink: 0;
        }

        .beauty-mirror label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            user-select: none;
            width: 100%;
        }

        .beauty-mirror input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #4a6cf7;
            cursor: pointer;
            flex-shrink: 0;
        }

        video {
            transition: filter 0.35s ease, transform 0.25s ease;
        }

        video.is-mirrored {
            transform: scaleX(-1);
        }

        .settings-overlay {
            position: absolute;
            bottom: 80px;
            right: 20px;
            width: 340px;
            max-height: 420px;
            background: rgba(22, 22, 37, 0.96);
            backdrop-filter: blur(16px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 21;
        }

        .settings-overlay.active {
            display: flex;
        }

        .settings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            flex-shrink: 0;
        }

        .settings-header .title {
            font-weight: 600;
            font-size: 14px;
        }

        .settings-header .close-btn {
            background: none;
            border: none;
            color: #6a6a7e;
            font-size: 18px;
            cursor: pointer;
        }

        .settings-body {
            padding: 12px 16px 16px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .settings-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 4px;
            font-size: 13px;
            color: #d0d0dc;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            cursor: pointer;
        }

        .settings-row input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #4a6cf7;
            cursor: pointer;
            flex-shrink: 0;
        }

        .settings-row-stack {
            flex-direction: column;
            align-items: stretch;
            cursor: default;
            border-bottom: none;
            gap: 8px;
            padding-top: 12px;
        }

        .settings-link-row {
            display: flex;
            gap: 8px;
        }

        .settings-link-row input {
            flex: 1;
            min-width: 0;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.04);
            color: #c8c8d8;
            font-size: 12px;
            font-family: inherit;
        }

        .settings-link-row button {
            flex-shrink: 0;
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            background: #4a6cf7;
            color: #fff;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            font-family: inherit;
        }

        .settings-link-row button:hover {
            background: #5a7cf7;
        }

        .tool-btn#settingsToolBtn.active {
            background: rgba(255, 255, 255, 0.08);
            color: #e0e0e0;
        }

        .chat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .chat-header .title {
            font-weight: 600;
            font-size: 14px;
        }

        .chat-header .close-btn {
            background: none;
            border: none;
            color: #6a6a7e;
            font-size: 18px;
            cursor: pointer;
        }

        .chat-messages {
            flex: 1;
            padding: 12px 16px;
            overflow-y: auto;
            max-height: 260px;
        }

        .chat-message {
            margin-bottom: 10px;
        }

        .chat-message .sender {
            font-size: 11px;
            font-weight: 600;
            color: #4a6cf7;
        }

        .chat-message .text {
            font-size: 13px;
            color: #e0e0e0;
            margin-top: 2px;
        }

        .chat-input-area {
            display: flex;
            padding: 10px 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            gap: 8px;
        }

        .chat-input-area input {
            flex: 1;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.04);
            color: #e0e0e0;
            font-size: 13px;
            outline: none;
            font-family: inherit;
        }

        .chat-input-area input::placeholder {
            color: #4a4a5e;
        }

        .chat-input-area button {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            background: #4a6cf7;
            color: #fff;
            font-weight: 500;
            cursor: pointer;
            font-size: 13px;
            font-family: inherit;
        }

        .tool-btn#videoModeBtn.active-mode {
            background: rgba(74, 108, 247, 0.18);
            color: #4a6cf7;
        }

        .beauty-fab {
            display: none;
        }

        @media (max-width: 768px) {
            body {
                height: 100dvh;
            }

            .room {
                height: 100dvh;
            }

            .sidebar {
                width: 64px;
                min-width: 64px;
                padding-top: env(safe-area-inset-top, 0);
                padding-bottom: env(safe-area-inset-bottom, 0);
            }

            .host-video-panel {
                padding: 8px 6px 0;
            }

            .host-video-stage {
                aspect-ratio: 1;
                border-radius: 12px;
            }

            .host-video-stage .host-name-tag,
            .host-video-stage .host-status-dot {
                display: none;
            }

            .participants-section {
                padding: 8px 6px 4px;
            }

            .sidebar .section-header {
                display: none;
            }

            .participants-list {
                align-items: stretch;
                gap: 6px;
            }

            .participant-item {
                width: 100%;
                padding: 0;
            }

            .participant-video {
                width: 100%;
                max-width: none;
                aspect-ratio: 1;
                height: auto;
                border-radius: 10px;
            }

            .participant-video .participant-name-tag {
                font-size: 10px;
                height: 18px;
                padding: 0 7px;
                left: 5px;
                bottom: 5px;
                max-width: calc(100% - 28px);
            }

            .participant-video .participant-emoji {
                top: 4px;
                left: 4px;
                width: 18px;
                height: 18px;
                font-size: 11px;
            }

            .participant-video .status-dot {
                bottom: 5px;
                right: 5px;
                width: 8px;
                height: 8px;
            }

            .sidebar .tool-btn .label {
                display: none;
            }

            .sidebar .desktop-only,
            .sidebar-bottom.desktop-only {
                display: none;
            }

            .sidebar .tools-grid {
                grid-template-columns: 1fr;
                gap: 2px;
            }

            .tools-section {
                padding: 6px 4px;
                overflow-y: auto;
                max-height: 42vh;
                -webkit-overflow-scrolling: touch;
            }

            #videoModeBtn {
                order: -2;
            }

            #videoModeBtn.active-mode {
                background: rgba(74, 108, 247, 0.22);
                color: #4a6cf7;
            }

            #beautyToolBtn {
                order: -1;
                color: #f0abfc;
            }

            #beautyToolBtn .icon {
                font-size: 20px;
            }

            #beautyToolBtn.filter-on,
            #beautyToolBtn.active {
                background: rgba(240, 171, 252, 0.18);
                color: #f0abfc;
            }

            .beauty-fab {
                display: flex;
                position: absolute;
                left: 12px;
                bottom: calc(16px + env(safe-area-inset-bottom, 0px));
                z-index: 25;
                width: 48px;
                height: 48px;
                border: none;
                border-radius: 50%;
                background: rgba(22, 22, 37, 0.92);
                color: #f0abfc;
                font-size: 22px;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
                border: 1px solid rgba(240, 171, 252, 0.35);
                font-family: inherit;
            }

            .beauty-fab.active,
            .beauty-fab.filter-on {
                background: rgba(240, 171, 252, 0.25);
                border-color: rgba(240, 171, 252, 0.6);
            }

            .beauty-overlay {
                left: 0;
                right: 0;
                bottom: 0;
                width: 100%;
                max-width: none;
                max-height: none;
                border-radius: 20px 20px 0 0;
                padding-bottom: 0;
            }

            .beauty-body {
                padding: 12px 14px;
                overflow: visible;
            }

            .beauty-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .beauty-btn {
                min-height: 58px;
                padding: 12px;
                -webkit-tap-highlight-color: transparent;
            }

            .beauty-btn .beauty-name {
                font-size: 14px;
            }

            .beauty-btn .beauty-desc {
                font-size: 11px;
            }

            .beauty-intensity {
                flex-wrap: wrap;
                gap: 8px;
                padding: 12px 16px 8px;
            }

            .beauty-intensity input[type="range"] {
                width: 100%;
                flex: 1 1 100%;
                height: 28px;
            }

            .beauty-intensity span {
                margin-left: auto;
            }

            .beauty-mirror {
                padding: 0 16px calc(16px + env(safe-area-inset-bottom, 0px));
            }

            .sidebar .participant-item {
                padding: 4px;
                justify-content: center;
                gap: 0;
            }

            .sidebar-bottom {
                padding: 8px 6px 10px;
            }

            .main-area {
                min-width: 0;
            }

            .screen-canvas {
                align-items: stretch;
            }

            .cards-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                padding: 4px 2px 16px;
                width: 100%;
                box-sizing: border-box;
            }

            .card {
                border-radius: 12px;
                min-width: 0;
            }

            .card .card-back,
            .card .card-face {
                font-size: 22px;
            }

            .figures-grid {
                min-height: 260px;
            }

            .figure-item {
                width: 64px;
                height: 64px;
                font-size: 11px;
            }

            .figures-hint {
                white-space: normal;
                width: calc(100% - 16px);
                font-size: 11px;
            }

            .canvas-toolbar {
                gap: 6px;
                padding-top: 10px;
                min-height: auto;
                justify-content: flex-start;
                overflow-x: auto;
                flex-wrap: nowrap;
                -webkit-overflow-scrolling: touch;
            }

            .canvas-toolbar .toolbar-group {
                flex-wrap: nowrap;
            }

            .canvas-toolbar button {
                font-size: 11px;
                padding: 6px 10px;
                flex-shrink: 0;
            }

            .canvas-toolbar .toolbar-label {
                font-size: 10px;
                flex-shrink: 0;
            }

            .video-grid {
                gap: 12px;
                height: auto;
                max-height: calc(100% - 8px);
                overflow-y: auto;
                align-content: start;
                padding: 0 !important;
            }

            .screen-video {
                padding: 52px 12px 12px;
            }

            .top-right-controls {
                top: 10px;
                right: 10px;
                gap: 6px;
                flex-wrap: wrap;
                justify-content: flex-end;
                max-width: calc(100% - 20px);
            }

            .demo-count-control label {
                display: none;
            }

            .demo-count-control input {
                width: 40px;
                height: 30px;
            }

            .demo-count-control button {
                height: 30px;
                padding: 0 8px;
                font-size: 11px;
            }

            .record-controls {
                top: auto;
                right: auto;
            }

            .screen-header-dark {
                top: 10px;
                left: 12px;
                right: 12px;
            }

            .screen-header-dark .section-title {
                font-size: 16px;
            }

            .screen-header-dark .section-subtitle {
                font-size: 11px;
            }

            .screen-header-light {
                padding-bottom: 8px;
                padding-right: 110px;
            }

            .screen-header-light .section-title {
                font-size: 16px;
            }

            .screen-header-light .section-subtitle {
                font-size: 11px;
            }

            .screen-video {
                padding-top: 52px;
            }

            .canvas-wrapper {
                padding: 8px 8px 8px;
                padding-top: max(8px, env(safe-area-inset-top, 0px));
                padding-bottom: max(8px, env(safe-area-inset-bottom, 0px));
            }

            .canvas-scroll-area {
                padding-top: 8px;
                padding-bottom: 8px;
            }

            .video-tile {
                min-height: 160px;
                aspect-ratio: 16 / 10;
            }

            .video-pip {
                width: 96px;
                height: 72px;
                top: 8px;
                right: 8px;
                border-width: 1px;
                border-radius: 10px;
            }

            .video-pip .pip-name-tag {
                font-size: 10px;
                padding: 2px 6px;
                bottom: 4px;
                left: 6px;
            }

            .video-pip .pip-placeholder .pip-emoji {
                font-size: 28px;
            }

            .video-pip .pip-placeholder .pip-name {
                display: none;
            }

            .video-pip.expanded {
                width: 100%;
                height: 100%;
                top: 0;
                right: 0;
                border-radius: 0;
            }

            .chat-overlay {
                width: calc(100% - 16px);
                max-width: 320px;
                right: 8px;
                bottom: 60px;
                max-height: 50vh;
            }

            .beauty-overlay {
                width: 100%;
                max-width: none;
                left: 0;
                right: 0;
                bottom: 0;
                max-height: none;
            }

            .exit-fullscreen-btn {
                top: 10px;
                right: 10px;
                padding: 8px 10px;
                font-size: 12px;
            }

            .top-right-controls {
                top: 52px;
            }
        }

        @media (max-width: 400px) {
            .sidebar {
                width: 56px;
                min-width: 56px;
            }

            .participant-video {
                width: 40px;
                height: 40px;
            }

            .cards-grid {
                gap: 6px;
                padding: 2px 0 12px;
            }

            .video-pip {
                width: 84px;
                height: 64px;
            }
        }
    </style>
</head>
<body>

    <div class="room">

        <aside class="sidebar">

            <div class="host-video-panel is-speaking" id="hostVideoPanel">
                <div class="host-video-stage" id="hostVideoStage">
                    <video autoplay muted playsinline id="videoSelfBlur" class="host-bg-blur-video"></video>
                    <video autoplay muted playsinline id="videoSelf"></video>
                    <canvas id="videoSelfCanvas" class="host-processed-canvas"></canvas>
                    <div class="host-speaking-ring"></div>
                    <span class="host-status-dot"></span>
                    <div class="host-name-tag">Лена · Ведущий</div>
                </div>
            </div>

            <div class="participants-section">
                <div class="section-header">
                    <span>Участники</span>
                    <span class="participant-count" id="participantCount">10</span>
                </div>
                <div class="participants-list" id="participantsList"></div>
            </div>

            <div class="sidebar-divider"></div>

            <div class="tools-section">
                <div class="tools-grid">
                    <button type="button" class="tool-btn" id="videoModeBtn" onclick="showVideoMode()" title="Видеовстреча">
                        <span class="icon">🎥</span>
                        <span class="label">Видео</span>
                    </button>
                    <button class="tool-btn" data-tool="cards" onclick="switchTool('cards')">
                        <span class="icon">🃏</span>
                        <span class="label">Карты</span>
                    </button>
                    <button class="tool-btn" data-tool="helinger" onclick="switchTool('helinger')">
                        <span class="icon">📐</span>
                        <span class="label">Расстановка</span>
                    </button>
                    <button class="tool-btn" data-tool="drawing" onclick="switchTool('drawing')">
                        <span class="icon">✏️</span>
                        <span class="label">Рисование</span>
                    </button>
                    <button class="tool-btn" data-tool="presentation" onclick="openPresentationTool()">
                        <span class="icon">📽️</span>
                        <span class="label">Презентация</span>
                    </button>
                    <button class="tool-btn" onclick="toggleChat()">
                        <span class="icon">💬</span>
                        <span class="label">Чат</span>
                    </button>
                    <input type="file" id="presentationFileInput" accept="application/pdf,.pdf" hidden />
                    <button class="tool-btn" id="beautyToolBtn" onclick="toggleBeautyPanel()">
                        <span class="icon">✨</span>
                        <span class="label">Бьюти</span>
                    </button>
                    <button type="button" class="tool-btn" id="settingsToolBtn" onclick="toggleSettingsPanel()" title="Настройки">
                        <span class="icon">⚙️</span>
                        <span class="label">Настройки</span>
                    </button>
                </div>
            </div>
        </aside>

        <main class="main-area">

            <div class="screen screen-video visible" id="videoScreen">
                <div class="screen-header screen-header-dark">
                    <h2 class="section-title">Видеовстреча</h2>
                    <p class="section-subtitle">Выберите участника или разверните плитку</p>
                </div>
                <div class="video-grid" id="videoGrid"></div>
            </div>

            <div class="screen screen-canvas hidden" id="canvasScreen">
                <div class="canvas-wrapper" id="canvasWrapper">
                    <div class="screen-header screen-header-light" id="canvasHeader">
                        <h2 class="section-title" id="canvasSectionTitle">Холст</h2>
                        <p class="section-subtitle" id="canvasSectionSubtitle">Выберите инструмент в панели слева</p>
                    </div>

                    <div class="canvas-scroll-area" id="canvasScroll">
                        <div class="canvas-empty" id="canvasEmpty">
                            <div class="canvas-empty-icon">🖼️</div>
                            <div>Откройте карты, расстановку, рисование или презентацию</div>
                        </div>
                    </div>

                    <div class="canvas-toolbar" id="canvasToolbar"></div>
                </div>
            </div>

            <div class="chat-overlay" id="chatOverlay">
                <div class="chat-header">
                    <span class="title">💬 Чат</span>
                    <button class="close-btn" onclick="toggleChat()">✖</button>
                </div>
                <div class="chat-messages">
                    <div class="chat-message">
                        <div class="sender">Лена</div>
                        <div class="text">Давайте посмотрим на эту карту</div>
                    </div>
                    <div class="chat-message">
                        <div class="sender">Ольга</div>
                        <div class="text">Она напоминает мне мой страх</div>
                    </div>
                </div>
                <div class="chat-input-area">
                    <input type="text" placeholder="Введите сообщение..." />
                    <button>Отправить</button>
                </div>
            </div>

            <div class="beauty-overlay" id="beautyOverlay">
                <div class="beauty-header">
                    <span class="title">✨ Бьюти</span>
                    <button type="button" class="close-btn" onclick="toggleBeautyPanel()">✖</button>
                </div>
                <div class="beauty-body">
                    <div class="beauty-section-label">Лицо</div>
                    <div class="beauty-grid" id="beautyGrid"></div>
                    <div class="beauty-section-label">Задний фон</div>
                    <div class="beauty-grid beauty-bg-grid" id="backgroundGrid"></div>
                </div>
                <div class="beauty-intensity">
                    <label for="beautyIntensity">Интенсивность</label>
                    <input type="range" id="beautyIntensity" min="0" max="100" value="70" />
                    <span id="beautyIntensityValue">70%</span>
                </div>
                <div class="beauty-mirror">
                    <label for="videoMirrorToggle">
                        <input type="checkbox" id="videoMirrorToggle" />
                        <span>🪞 Отзеркалить видео</span>
                    </label>
                </div>
            </div>

            <div class="settings-overlay" id="settingsOverlay">
                <div class="settings-header">
                    <span class="title">⚙️ Настройки комнаты</span>
                    <button type="button" class="close-btn" onclick="toggleSettingsPanel()">✖</button>
                </div>
                <div class="settings-body">
                    <label class="settings-row">
                        <span>Пароль комнаты</span>
                        <input type="checkbox" id="settingRoomPassword" />
                    </label>
                    <label class="settings-row">
                        <span>Совместный режим расстановки</span>
                        <input type="checkbox" id="settingCollaborative" />
                    </label>
                    <label class="settings-row">
                        <span>Клиенты могут рисовать</span>
                        <input type="checkbox" id="settingClientDraw" checked />
                    </label>
                    <label class="settings-row">
                        <span>Клиенты могут шарить экран</span>
                        <input type="checkbox" id="settingClientShare" />
                    </label>
                    <div class="settings-row settings-row-stack">
                        <span>Ссылка комнаты</span>
                        <div class="settings-link-row">
                            <input type="text" readonly value="https://studio.local/r/demo-lena" id="settingRoomLink" />
                            <button type="button" onclick="copyRoomLink()">Копировать</button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="beauty-fab" id="beautyFab" onclick="toggleBeautyPanel()" title="Бьюти" aria-label="Бьюти">✨</button>

            <div class="top-right-controls">
                <div class="demo-count-control" title="Тест: число участников">
                    <label for="demoParticipantCount">Участники</label>
                    <input type="number" id="demoParticipantCount" min="1" max="10" value="10" />
                    <button type="button" id="demoParticipantApply" onclick="applyDemoParticipantCount()">Применить</button>
                </div>
                <div class="record-controls" id="recordControls">
                    <button type="button" class="record-start-btn" id="recordStartBtn" onclick="startRecording()" title="Начать запись" aria-label="Запись">
                        <span class="record-dot" aria-hidden="true"></span>
                        <span>Запись</span>
                    </button>
                    <div class="record-status" aria-live="polite">
                        <span class="record-dot" aria-hidden="true"></span>
                        <span class="record-timer" id="recordTimer">00:00</span>
                    </div>
                    <div class="record-actions">
                        <button type="button" class="record-icon-btn" id="recordPauseBtn" onclick="toggleRecordPause()" title="Пауза" aria-label="Пауза">
                            <svg class="icon-pause" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                            <svg class="icon-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5L8 5.5z"/></svg>
                        </button>
                        <button type="button" class="record-icon-btn record-stop-btn" id="recordStopBtn" onclick="stopRecording()" title="Стоп" aria-label="Стоп">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <button type="button" class="exit-fullscreen-btn" id="exitFullscreenBtn" title="Свернуть к плиткам">
                <span class="tiles-icon" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
                <span>К плиткам</span>
            </button>

        </main>
    </div>

    <script>
        // ============================================================
        //  ДАННЫЕ
        // ============================================================
        const ALL_PARTICIPANTS = [
            { id: 'lena', name: 'Лена', emoji: '👩', color: '#4a6cf7', colorEnd: '#818cf8', role: 'host', speaking: true, status: 'speaking' },
            { id: 'olga', name: 'Ольга', emoji: '👩', color: '#a78bfa', colorEnd: '#c4b5fd', role: 'participant', speaking: true, status: 'speaking' },
            { id: 'sasha', name: 'Саша', emoji: '🧑', color: '#fb923c', colorEnd: '#fdba74', role: 'observer', speaking: false, status: 'waiting' },
            { id: 'irina', name: 'Ирина', emoji: '👩', color: '#f472b6', colorEnd: '#f9a8d4', role: 'participant', speaking: false, status: 'muted' },
            { id: 'mikhail', name: 'Михаил', emoji: '🧑', color: '#60a5fa', colorEnd: '#93c5fd', role: 'participant', speaking: false, status: 'waiting' },
            { id: 'katya', name: 'Катя', emoji: '👩', color: '#34d399', colorEnd: '#6ee7b7', role: 'participant', speaking: false, status: 'online' },
            { id: 'anna', name: 'Анна', emoji: '👩', color: '#fbbf24', colorEnd: '#fcd34d', role: 'participant', speaking: false, status: 'muted' },
            { id: 'dmitry', name: 'Дмитрий', emoji: '🧑', color: '#22d3ee', colorEnd: '#67e8f9', role: 'participant', speaking: false, status: 'waiting' },
            { id: 'maria', name: 'Мария', emoji: '👩', color: '#e879f9', colorEnd: '#f0abfc', role: 'participant', speaking: true, status: 'speaking' },
            { id: 'pavel', name: 'Павел', emoji: '🧑', color: '#a3e635', colorEnd: '#bef264', role: 'observer', speaking: false, status: 'online' },
        ];

        let participants = ALL_PARTICIPANTS.slice();
        let visibleParticipantCount = 10;
        let hostStream = null;

        let activeParticipantId = 'olga';
        let isVideoMode = true;
        let isChatOpen = false;
        let currentTool = null;
        let isPipExpanded = false;
        let fullscreenTile = null;

        function getVideoGridCols(count) {
            if (count <= 1) return 1;
            if (count === 2) return 2;
            if (count <= 4) return 2;
            if (count <= 6) return 3;
            if (count === 9) return 3;
            return 4; // 7, 8, 10
        }

        function layoutVideoGrid() {
            const grid = document.getElementById('videoGrid');
            if (!grid) return;

            const count = grid.querySelectorAll('.video-tile:not(.fullscreen-tile)').length
                || grid.children.length;
            if (!count) return;

            const cols = getVideoGridCols(count);
            const rows = Math.ceil(count / cols);
            const gap = 16;
            const outer = gap;

            // Сначала сбрасываем паддинг, чтобы clientWidth/Height были чистыми
            grid.style.padding = '0';
            grid.style.gap = `${gap}px`;

            const availW = Math.max(0, grid.clientWidth - outer * 2);
            const availH = Math.max(0, grid.clientHeight - outer * 2);
            if (availW < 40 || availH < 40) return;

            const tileWByWidth = (availW - gap * (cols - 1)) / cols;
            const tileHByWidth = tileWByWidth * 9 / 16;
            const tileHByHeight = (availH - gap * (rows - 1)) / rows;
            const tileWByHeight = tileHByHeight * 16 / 9;

            let tileW;
            let tileH;
            if (tileHByWidth <= tileHByHeight) {
                tileW = tileWByWidth;
                tileH = tileHByWidth;
            } else {
                tileW = tileWByHeight;
                tileH = tileHByHeight;
            }

            tileW = Math.max(120, Math.floor(tileW));
            tileH = Math.max(68, Math.floor(tileW * 9 / 16));

            const usedW = cols * tileW + (cols - 1) * gap;
            const usedH = rows * tileH + (rows - 1) * gap;
            const padX = Math.max(outer, Math.floor((grid.clientWidth - usedW) / 2));
            const padY = Math.max(outer, Math.floor((grid.clientHeight - usedH) / 2));

            grid.dataset.count = String(count);
            grid.style.gridTemplateColumns = `repeat(${cols}, ${tileW}px)`;
            grid.style.gridTemplateRows = `repeat(${rows}, ${tileH}px)`;
            grid.style.padding = `${padY}px ${padX}px`;
            grid.style.justifyContent = 'center';
            grid.style.alignContent = 'center';
        }

        function attachHostThumbs() {
            if (!hostStream) return;
            const videoSelfThumb = document.getElementById('videoSelfThumb');
            if (videoSelfThumb) {
                videoSelfThumb.srcObject = hostStream.clone();
                videoSelfThumb.style.objectFit = 'cover';
                videoSelfThumb.play().catch(() => {});
            }
            const videoSelfThumbBlur = document.getElementById('videoSelfThumbBlur');
            if (videoSelfThumbBlur) {
                videoSelfThumbBlur.srcObject = hostStream.clone();
                videoSelfThumbBlur.style.objectFit = 'cover';
                videoSelfThumbBlur.play().catch(() => {});
            }
        }

        function applyDemoParticipantCount() {
            const input = document.getElementById('demoParticipantCount');
            let n = input ? Number(input.value) : visibleParticipantCount;
            if (!Number.isFinite(n)) n = 10;
            n = Math.max(1, Math.min(10, Math.round(n)));
            if (input) input.value = String(n);

            visibleParticipantCount = n;
            participants = ALL_PARTICIPANTS.slice(0, n);

            if (!participants.some(p => p.id === activeParticipantId)) {
                activeParticipantId = participants[0].id;
            }

            renderParticipantsList();
            attachHostThumbs();
            renderVideoGrid();
            applyBeautyFilter();
        }

        function getParticipantEmoji(p) {
            if (p.role === 'host') return { icon: '👑', title: 'Ведущий' };
            if (p.role === 'observer') return { icon: '👀', title: 'Наблюдатель' };
            if (p.status === 'speaking') return { icon: '🟢', title: 'Говорит' };
            if (p.status === 'muted') return { icon: '🔇', title: 'Микрофон выкл.' };
            if (p.status === 'waiting') return { icon: '🟡', title: 'Ожидание' };
            return { icon: '🟢', title: 'В сети' };
        }

        function renderParticipantsList() {
            const list = document.getElementById('participantsList');
            const countEl = document.getElementById('participantCount');
            if (!list) return;

            if (countEl) countEl.textContent = String(participants.length);
            list.innerHTML = '';

            participants.forEach(p => {
                const item = document.createElement('div');
                item.className = `participant-item${p.speaking ? ' speaking' : ''}${p.id === activeParticipantId ? ' active' : ''}`;
                item.dataset.id = p.id;
                item.dataset.name = p.name;
                item.dataset.emoji = p.emoji;
                item.dataset.color = p.color;

                const initial = (p.name || '?').charAt(0);
                const badge = getParticipantEmoji(p);
                const mediaHtml = p.id === 'lena'
                    ? `
                        <video autoplay muted playsinline id="videoSelfThumbBlur" class="host-bg-blur-video"></video>
                        <video autoplay muted playsinline id="videoSelfThumb"></video>
                        <canvas id="videoSelfThumbCanvas" class="host-processed-canvas"></canvas>
                    `
                    : `
                        <div class="video-placeholder" style="background: linear-gradient(135deg, ${p.color}, ${p.colorEnd || p.color});">
                            <div class="animated-bg"></div>
                            <span>${initial}</span>
                        </div>
                    `;

                item.innerHTML = `
                    <div class="participant-video">
                        ${mediaHtml}
                        ${p.speaking ? '<div class="speaking-ring"></div>' : ''}
                        <span class="participant-emoji" title="${badge.title}">${badge.icon}</span>
                        <div class="participant-name-tag">${p.name}</div>
                        <span class="status-dot ${p.status}"></span>
                    </div>
                `;

                item.addEventListener('click', () => selectParticipant(p.id));
                list.appendChild(item);
            });
        }

        // ============================================================
        //  ВИДЕО
        // ============================================================
        async function initVideoStreams() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                        facingMode: 'user',
                    },
                    audio: false
                });

                hostStream = stream;

                const videoSelf = document.getElementById('videoSelf');
                if (videoSelf) {
                    videoSelf.srcObject = stream;
                    videoSelf.style.objectFit = 'cover';
                    await videoSelf.play();
                }

                const videoSelfBlur = document.getElementById('videoSelfBlur');
                if (videoSelfBlur) {
                    videoSelfBlur.srcObject = stream.clone();
                    videoSelfBlur.style.objectFit = 'cover';
                    await videoSelfBlur.play();
                }

                attachHostThumbs();

                console.log('📷 Видео-потоки запущены');
                applyBeautyFilter();
                layoutVideoGrid();
            } catch (err) {
                console.warn('⚠️ Нет доступа к камере');
            }
        }

        function renderVideoGrid() {
            if (fullscreenTile) exitTileFullscreen();

            const grid = document.getElementById('videoGrid');
            grid.innerHTML = '';

            const active = participants.find(p => p.id === activeParticipantId);
            const host = participants.find(p => p.id === 'lena');
            const others = participants.filter(p => p.id !== activeParticipantId && p.id !== 'lena');
            const displayParticipants = [];
            if (active) displayParticipants.push(active);
            others.forEach(p => displayParticipants.push(p));
            if (host && host.id !== activeParticipantId) displayParticipants.push(host);

            displayParticipants.forEach(p => {
                if (!p) return;
                const tile = document.createElement('div');
                tile.className = `video-tile ${p.speaking ? 'speaking' : ''}`;
                if (p.id === activeParticipantId) {
                    tile.classList.add('active-participant');
                }
                tile.dataset.id = p.id;

                tile.addEventListener('click', () => {
                    if (tile.classList.contains('fullscreen-tile')) return;
                    selectParticipant(p.id);
                });

                const sourceVideo = document.querySelector(`.participant-item[data-id="${p.id}"] video:not(.host-bg-blur-video)`);
                const beautySrc = (p.id === 'lena' && currentVideoBackground !== 'none' && processedStream)
                    ? processedStream
                    : null;
                const hasVideo = beautySrc || (sourceVideo && sourceVideo.srcObject) || (p.id === 'lena' && hostStream);

                if (hasVideo) {
                    const video = document.createElement('video');
                    video.autoplay = true;
                    video.muted = true;
                    video.playsInline = true;
                    if (beautySrc) {
                        video.srcObject = beautySrc;
                        video.classList.add('is-beauty-processed');
                        tile.classList.add('has-beauty-bg');
                    } else {
                        const src = (sourceVideo && sourceVideo.srcObject) || hostStream;
                        video.srcObject = (src && typeof src.clone === 'function') ? src.clone() : src;
                    }
                    tile.appendChild(video);
                } else {
                    const avatar = document.createElement('div');
                    avatar.className = 'avatar-big';
                    avatar.style.background = `linear-gradient(135deg, ${p.color}, ${p.colorEnd || p.color})`;
                    avatar.innerHTML = `<span>${p.emoji || '👤'}</span>`;
                    tile.appendChild(avatar);
                }

                const nameTag = document.createElement('div');
                nameTag.className = 'name-tag';
                nameTag.textContent = p.id === activeParticipantId ? `${p.name} (активный)` : p.name;
                tile.appendChild(nameTag);

                if (p.speaking) {
                    const ring = document.createElement('div');
                    ring.className = 'speaking-ring';
                    tile.appendChild(ring);
                }

                const fsBtn = document.createElement('button');
                fsBtn.className = 'tile-fullscreen-btn';
                fsBtn.type = 'button';
                fsBtn.textContent = '⛶';
                fsBtn.title = 'Развернуть видео';
                fsBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    enterTileFullscreen(tile);
                });
                tile.appendChild(fsBtn);

                grid.appendChild(tile);
            });

            applyBeautyFilter();
            requestAnimationFrame(() => layoutVideoGrid());
        }

        function setExitFullscreenVisible(visible) {
            const btn = document.getElementById('exitFullscreenBtn');
            if (btn) btn.classList.toggle('visible', visible);
        }

        function exitTileFullscreen() {
            if (!fullscreenTile) {
                setExitFullscreenVisible(false);
                return;
            }

            const tile = fullscreenTile;
            const placeholder = tile._fsPlaceholder;

            tile.classList.remove('fullscreen-tile');
            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.insertBefore(tile, placeholder);
                placeholder.remove();
            }
            delete tile._fsPlaceholder;

            fullscreenTile = null;
            setExitFullscreenVisible(false);
            requestAnimationFrame(() => layoutVideoGrid());
        }

        function enterTileFullscreen(tile) {
            if (fullscreenTile === tile) {
                exitTileFullscreen();
                return;
            }
            if (fullscreenTile) {
                exitTileFullscreen();
            }

            const mainArea = document.querySelector('.main-area');
            const placeholder = document.createElement('div');
            placeholder.className = 'video-tile-placeholder';
            placeholder.style.cssText = 'display:none;';
            tile.parentNode.insertBefore(placeholder, tile);
            tile._fsPlaceholder = placeholder;

            mainArea.appendChild(tile);
            tile.classList.add('fullscreen-tile');
            fullscreenTile = tile;
            setExitFullscreenVisible(true);
        }

        function toggleTileFullscreen(tile) {
            if (fullscreenTile === tile) {
                exitTileFullscreen();
            } else {
                enterTileFullscreen(tile);
            }
        }

        // ============================================================
        //  PiP — перетаскивание и изменение размера
        // ============================================================
        let pipDrag = null;
        let pipResize = null;
        let pipSavedRect = null;

        function getPipBounds() {
            const wrapper = document.getElementById('canvasWrapper');
            return wrapper ? wrapper.getBoundingClientRect() : null;
        }

        function clampPipPosition(left, top, width, height) {
            const bounds = getPipBounds();
            if (!bounds) return { left, top };
            const maxLeft = Math.max(0, bounds.width - width);
            const maxTop = Math.max(0, bounds.height - height);
            return {
                left: Math.min(Math.max(0, left), maxLeft),
                top: Math.min(Math.max(0, top), maxTop),
            };
        }

        function applyPipRect(left, top, width, height) {
            const pip = document.getElementById('videoPip');
            const pos = clampPipPosition(left, top, width, height);
            pip.style.left = pos.left + 'px';
            pip.style.top = pos.top + 'px';
            pip.style.right = 'auto';
            pip.style.width = width + 'px';
            pip.style.height = height + 'px';
        }

        function savePipRect() {
            const pip = document.getElementById('videoPip');
            pipSavedRect = {
                left: pip.offsetLeft,
                top: pip.offsetTop,
                width: pip.offsetWidth,
                height: pip.offsetHeight,
            };
        }

        function restorePipRect() {
            const pip = document.getElementById('videoPip');
            if (pipSavedRect) {
                applyPipRect(pipSavedRect.left, pipSavedRect.top, pipSavedRect.width, pipSavedRect.height);
            } else {
                pip.style.left = '';
                pip.style.top = '16px';
                pip.style.right = '16px';
                pip.style.width = '';
                pip.style.height = '';
            }
        }

        function togglePipExpand() {
            isPipExpanded = !isPipExpanded;
            const pip = document.getElementById('videoPip');
            const overlay = document.getElementById('pipOverlay');
            const btn = document.getElementById('pipExpandBtn');

            if (isPipExpanded) {
                savePipRect();
            }

            pip.classList.toggle('expanded', isPipExpanded);
            overlay.classList.toggle('active', isPipExpanded);
            btn.textContent = isPipExpanded ? '⛶ Свернуть' : '⛶';

            if (!isPipExpanded) {
                restorePipRect();
            }
        }

        function pointerCoords(e) {
            return e.touches ? e.touches[0] : e;
        }

        function onPipPointerMove(e) {
            const point = pointerCoords(e);
            if (!point) return;

            if (pipDrag) {
                e.preventDefault();
                const left = point.clientX - pipDrag.boundsLeft - pipDrag.offsetX;
                const top = point.clientY - pipDrag.boundsTop - pipDrag.offsetY;
                applyPipRect(left, top, pipDrag.width, pipDrag.height);
                return;
            }

            if (pipResize) {
                e.preventDefault();
                const minW = 120;
                const minH = 80;
                const rightEdge = pipResize.startLeft + pipResize.startWidth;
                let left = point.clientX - pipResize.boundsLeft;
                left = Math.max(0, Math.min(left, rightEdge - minW));
                const width = rightEdge - left;
                const height = Math.max(minH, Math.min(pipResize.maxHeight, point.clientY - pipResize.boundsTop - pipResize.startTop));
                applyPipRect(left, pipResize.startTop, width, height);
            }
        }

        function onPipPointerUp() {
            const pip = document.getElementById('videoPip');
            if (pipDrag) {
                pip.classList.remove('dragging');
                pipDrag = null;
            }
            if (pipResize) {
                pip.classList.remove('resizing');
                pipResize = null;
            }
            document.removeEventListener('mousemove', onPipPointerMove);
            document.removeEventListener('mouseup', onPipPointerUp);
            document.removeEventListener('touchmove', onPipPointerMove);
            document.removeEventListener('touchend', onPipPointerUp);
        }

        function startPipDrag(e) {
            if (isPipExpanded) return;
            if (e.target.closest('.pip-expand-btn') || e.target.closest('.pip-resize-handle')) return;

            const pip = document.getElementById('videoPip');
            const bounds = getPipBounds();
            if (!bounds) return;

            const point = pointerCoords(e);
            const rect = pip.getBoundingClientRect();

            pipDrag = {
                offsetX: point.clientX - rect.left,
                offsetY: point.clientY - rect.top,
                width: rect.width,
                height: rect.height,
                boundsLeft: bounds.left,
                boundsTop: bounds.top,
            };

            applyPipRect(rect.left - bounds.left, rect.top - bounds.top, rect.width, rect.height);
            pip.classList.add('dragging');

            document.addEventListener('mousemove', onPipPointerMove);
            document.addEventListener('mouseup', onPipPointerUp);
            document.addEventListener('touchmove', onPipPointerMove, { passive: false });
            document.addEventListener('touchend', onPipPointerUp);
            e.preventDefault();
        }

        function startPipResize(e) {
            if (isPipExpanded) return;
            e.preventDefault();
            e.stopPropagation();

            const pip = document.getElementById('videoPip');
            const bounds = getPipBounds();
            if (!bounds) return;

            const rect = pip.getBoundingClientRect();
            const startLeft = rect.left - bounds.left;
            const startTop = rect.top - bounds.top;

            applyPipRect(startLeft, startTop, rect.width, rect.height);

            pipResize = {
                startLeft,
                startTop,
                startWidth: rect.width,
                startHeight: rect.height,
                boundsLeft: bounds.left,
                boundsTop: bounds.top,
                maxHeight: Math.max(80, bounds.height - startTop),
            };

            pip.classList.add('resizing');

            document.addEventListener('mousemove', onPipPointerMove);
            document.addEventListener('mouseup', onPipPointerUp);
            document.addEventListener('touchmove', onPipPointerMove, { passive: false });
            document.addEventListener('touchend', onPipPointerUp);
        }

        function initPipInteractions() {
            const pip = document.getElementById('videoPip');
            const handle = document.getElementById('pipResizeHandle');
            if (!pip || !handle) return;

            pip.addEventListener('mousedown', startPipDrag);
            pip.addEventListener('touchstart', startPipDrag, { passive: false });
            handle.addEventListener('mousedown', startPipResize);
            handle.addEventListener('touchstart', startPipResize, { passive: false });
            pip.addEventListener('dblclick', (e) => {
                if (e.target.closest('.pip-resize-handle')) return;
                togglePipExpand();
            });
            document.getElementById('pipOverlay').addEventListener('click', () => {
                if (isPipExpanded) togglePipExpand();
            });
        }

        let pipInteractionsReady = false;
        function ensurePipInteractions() {
            if (pipInteractionsReady) return;
            pipInteractionsReady = true;
            initPipInteractions();
        }
        document.addEventListener('DOMContentLoaded', ensurePipInteractions);
        if (document.readyState !== 'loading') {
            ensurePipInteractions();
        }

        function updatePip() {
            // PiP на холсте отключён — видео остаётся в боковой панели
        }

        function selectParticipant(participantId) {
            exitTileFullscreen();

            activeParticipantId = participantId;
            document.querySelectorAll('.participant-item').forEach(el => {
                el.classList.toggle('active', el.dataset.id === participantId);
            });

            renderVideoGrid();
            applyBeautyFilter();
        }

        // ============================================================
        //  КАРТЫ
        // ============================================================
        const CARD_DECK = [
            '🌊', '🔥', '🌿', '💎', '🌙', '☀️', '🌺', '🌪️',
            '🌈', '🕊️', '🌳', '🏔️', '🌄', '🌅', '🌌', '🍃',
            '🌱', '🌷', '🌻', '🌹', '⭐', '🦋', '🐚', '🍀', '💫'
        ];

        let cards = [];

        function initCards() {
            const shuffled = [...CARD_DECK]
                .sort(() => Math.random() - 0.5)
                .slice(0, 16)
                .map(symbol => ({ symbol, flipped: false }));
            cards = shuffled;
            if (document.getElementById('cardsGrid')) {
                renderCards();
                renderToolbar('cards');
            }
        }

        function renderCards(options = {}) {
            const { deal = false } = options;
            const grid = document.getElementById('cardsGrid');
            if (!grid) return;
            grid.innerHTML = '';
            grid.classList.remove('is-shuffling');

            cards.forEach((card, index) => {
                const cardEl = document.createElement('div');
                cardEl.className = `card ${card.flipped ? 'flipped' : ''}`;
                if (deal) cardEl.classList.add('card-deal');
                cardEl.dataset.index = index;
                if (deal) {
                    cardEl.style.animationDelay = `${index * 12}ms`;
                }

                const inner = document.createElement('div');
                inner.className = 'card-inner';

                const back = document.createElement('div');
                back.className = 'card-back';
                back.textContent = '🃏';

                const face = document.createElement('div');
                face.className = 'card-face';
                face.textContent = card.symbol;

                inner.appendChild(back);
                inner.appendChild(face);
                cardEl.appendChild(inner);

                cardEl.addEventListener('click', () => {
                    if (isShufflingCards) return;
                    cards[index].flipped = !cards[index].flipped;
                    renderCards();
                    renderToolbar('cards');
                });

                grid.appendChild(cardEl);
            });

            renderToolbar('cards');
        }

        let isShufflingCards = false;

        function shuffleCards() {
            if (isShufflingCards) return;
            const grid = document.getElementById('cardsGrid');
            if (!grid) return;

            isShufflingCards = true;
            const cardEls = [...grid.querySelectorAll('.card')];

            cards.forEach(c => { c.flipped = false; });
            cardEls.forEach(el => el.classList.remove('flipped'));

            grid.classList.add('is-shuffling');

            cardEls.forEach((el, i) => {
                el.style.animationDelay = `${i * 12}ms`;
                el.classList.remove('card-deal');
                el.classList.add('card-shuffle');
            });

            const shuffleBtn = document.querySelector('#canvasToolbar button[onclick="shuffleCards()"]');
            if (shuffleBtn) {
                shuffleBtn.disabled = true;
                shuffleBtn.textContent = '🔄 Тасуем...';
            }

            const duration = 450 + Math.max(0, cardEls.length - 1) * 12;

            setTimeout(() => {
                cards.sort(() => Math.random() - 0.5);
                renderCards({ deal: true });
                isShufflingCards = false;

                setTimeout(() => {
                    document.querySelectorAll('.card.card-deal').forEach(el => {
                        el.classList.remove('card-deal');
                        el.style.animationDelay = '';
                    });
                }, 400 + cards.length * 12);
            }, duration);
        }

        function flipAllCards() {
            const allFlipped = cards.every(c => c.flipped);
            cards.forEach(c => c.flipped = !allFlipped);
            renderCards();
            renderToolbar('cards');
        }


        // ============================================================
        //  РАССТАНОВКА — СВОБОДНОЕ ПЕРЕТАСКИВАНИЕ С ФИКСАЦИЕЙ
        // ============================================================
        let figureCounter = 0;
        let dragState = null;

        function ensureFiguresGrid() {
            const scrollArea = document.getElementById('canvasScroll');
            let grid = scrollArea.querySelector('.figures-grid');

            if (!grid) {
                scrollArea.innerHTML = '';
                grid = document.createElement('div');
                grid.className = 'figures-grid';
                scrollArea.appendChild(grid);

                const hint = document.createElement('div');
                hint.className = 'figures-hint';
                hint.textContent = 'Перетащите фигуру и отпустите — она останется на месте. ✕ — удалить.';
                grid.appendChild(hint);
            }

            return grid;
        }

        function clampFigurePosition(grid, left, top, size) {
            const maxLeft = Math.max(0, grid.clientWidth - size);
            const maxTop = Math.max(0, grid.clientHeight - size);
            return {
                left: Math.min(Math.max(0, left), maxLeft),
                top: Math.min(Math.max(0, top), maxTop),
            };
        }

        function placeFigureInGrid(fig, grid, left, top) {
            const size = fig.offsetWidth || 96;
            const pos = clampFigurePosition(grid, left, top, size);
            fig.style.left = pos.left + 'px';
            fig.style.top = pos.top + 'px';
        }

        function startFigureDrag(e, fig) {
            if (e.button !== undefined && e.button !== 0) return;
            if (e.target.closest('.delete-figure')) return;

            e.preventDefault();
            e.stopPropagation();

            const grid = fig.closest('.figures-grid');
            if (!grid) return;

            const figRect = fig.getBoundingClientRect();
            const gridRect = grid.getBoundingClientRect();

            dragState = {
                figure: fig,
                grid: grid,
                offsetX: e.clientX - figRect.left,
                offsetY: e.clientY - figRect.top,
                size: figRect.width,
            };

            fig.classList.add('dragging');
            fig.style.zIndex = '1000';

            document.addEventListener('mousemove', onFigureMove);
            document.addEventListener('mouseup', onFigureUp);
        }

        function onFigureMove(e) {
            if (!dragState) return;
            e.preventDefault();

            const { figure: fig, grid, offsetX, offsetY, size } = dragState;
            const gridRect = grid.getBoundingClientRect();
            const left = e.clientX - gridRect.left - offsetX;
            const top = e.clientY - gridRect.top - offsetY;
            placeFigureInGrid(fig, grid, left, top);
        }

        function onFigureUp() {
            if (!dragState) return;

            const fig = dragState.figure;
            fig.classList.remove('dragging');
            fig.style.zIndex = '';

            document.removeEventListener('mousemove', onFigureMove);
            document.removeEventListener('mouseup', onFigureUp);
            dragState = null;
        }

        function addFigure(name, color) {
            figureCounter++;
            const grid = ensureFiguresGrid();
            const size = 96;
            const slot = figureCounter - 1;
            const cols = Math.max(1, Math.floor((grid.clientWidth || 600) / (size + 24)));
            const col = slot % cols;
            const row = Math.floor(slot / cols);
            const left = 24 + col * (size + 24);
            const top = 24 + row * (size + 24);

            const fig = document.createElement('div');
            fig.className = 'figure-item';
            fig.style.background = color;
            fig.dataset.id = `figure-${figureCounter}`;
            fig.style.left = left + 'px';
            fig.style.top = top + 'px';

            const label = document.createElement('span');
            label.textContent = name;
            fig.appendChild(label);

            fig.addEventListener('mousedown', (e) => startFigureDrag(e, fig));

            const delBtn = document.createElement('button');
            delBtn.className = 'delete-figure';
            delBtn.type = 'button';
            delBtn.textContent = '✕';
            delBtn.addEventListener('mousedown', (e) => e.stopPropagation());
            delBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                fig.remove();
                if (grid.querySelectorAll('.figure-item').length === 0) {
                    grid.remove();
                }
            });
            fig.appendChild(delBtn);
            grid.appendChild(fig);
            placeFigureInGrid(fig, grid, left, top);
        }

        function clearFigures() {
            const scrollArea = document.getElementById('canvasScroll');
            const grid = scrollArea.querySelector('.figures-grid');
            if (grid) grid.remove();
            figureCounter = 0;
        }

        // ============================================================
        //  РИСОВАНИЕ
        // ============================================================
        let selectedDrawingTool = 'pen';
        let selectedColor = '#1a1a2e';

        function selectDrawingTool(btn, tool) {
            document.querySelectorAll('#canvasToolbar .drawing-tool-btn').forEach(b => {
                b.classList.remove('active-tool');
            });
            btn.classList.add('active-tool');
            selectedDrawingTool = tool;
        }

        function selectColor(btn, color) {
            document.querySelectorAll('#canvasToolbar .color-btn').forEach(b => {
                b.style.border = '2px solid transparent';
            });
            btn.style.border = '2px solid #fff';
            selectedColor = color;
        }

        function clearDrawing() {
            const scrollArea = document.getElementById('canvasScroll');
            const drawing = scrollArea.querySelector('.drawing-area');
            if (drawing) {
                const canvas = drawing.querySelector('canvas');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                }
            }
        }

        // ============================================================
        //  ДИНАМИЧЕСКАЯ ПАНЕЛЬ УПРАВЛЕНИЯ
        // ============================================================
        function renderToolbar(tool) {
            const toolbar = document.getElementById('canvasToolbar');
            toolbar.innerHTML = '';

            if (tool === 'cards') {
                const allFlipped = cards.length > 0 && cards.every(c => c.flipped);
                const flipBtnLabel = allFlipped ? '🙈 Закрыть все' : '🃏 Открыть все';

                toolbar.innerHTML = `
                            <div class="toolbar-group">
                                <button onclick="shuffleCards()">🔄 Перетасовать</button>
                                <button onclick="flipAllCards()">${flipBtnLabel}</button>
                            </div>
                            <div class="toolbar-divider"></div>
                            <div class="toolbar-group">
                                <button class="primary" onclick="alert('Выбрана колода: Ресурсы')">📚 Выбрать колоду</button>
                            </div>
                        `;
            } else if (tool === 'helinger') {
                toolbar.innerHTML = `
                            <div class="toolbar-group">
                                <span class="toolbar-label">➕ Добавить фигуру</span>
                                <button onclick="addFigure('Мама', '#a78bfa')">🟣 Мама</button>
                                <button onclick="addFigure('Папа', '#60a5fa')">🔵 Папа</button>
                                <button onclick="addFigure('Я', '#4ade80')">🟢 Я</button>
                                <button onclick="addFigure('Работа', '#fb923c')">🟠 Работа</button>
                                <button onclick="addFigure('Любовь', '#f472b6')">🩷 Любовь</button>
                            </div>
                            <div class="toolbar-divider"></div>
                            <div class="toolbar-group">
                                <button onclick="clearFigures()">🗑️ Очистить всё</button>
                                <button class="primary" onclick="alert('Расстановка сохранена!')">💾 Сохранить</button>
                            </div>
                        `;
            } else if (tool === 'drawing') {
                toolbar.innerHTML = `
                            <div class="toolbar-group">
                                <span class="toolbar-label">🖍️ Инструменты:</span>
                                <button class="drawing-tool-btn active-tool" onclick="selectDrawingTool(this, 'pen')">🖊️ Карандаш</button>
                                <button class="drawing-tool-btn" onclick="selectDrawingTool(this, 'eraser')">🧽 Ластик</button>
                                <button class="drawing-tool-btn" onclick="selectDrawingTool(this, 'line')">📐 Линии</button>
                            </div>
                            <div class="toolbar-divider"></div>
                            <div class="toolbar-group">
                                <span class="toolbar-label">🎨 Цвет:</span>
                                <button class="color-btn" style="background:#1a1a2e;color:#fff;border-radius:50%;width:28px;height:28px;padding:0;border:2px solid #fff;" onclick="selectColor(this, '#1a1a2e')"></button>
                                <button class="color-btn" style="background:#4a6cf7;color:#fff;border-radius:50%;width:28px;height:28px;padding:0;" onclick="selectColor(this, '#4a6cf7')"></button>
                                <button class="color-btn" style="background:#ef4444;color:#fff;border-radius:50%;width:28px;height:28px;padding:0;" onclick="selectColor(this, '#ef4444')"></button>
                                <button class="color-btn" style="background:#22c55e;color:#fff;border-radius:50%;width:28px;height:28px;padding:0;" onclick="selectColor(this, '#22c55e')"></button>
                                <button class="color-btn" style="background:#facc15;color:#fff;border-radius:50%;width:28px;height:28px;padding:0;" onclick="selectColor(this, '#facc15')"></button>
                            </div>
                            <div class="toolbar-divider"></div>
                            <div class="toolbar-group">
                                <button onclick="clearDrawing()">🗑️ Очистить</button>
                            </div>
                        `;
            } else if (tool === 'presentation') {
                const nameSafe = escapeHtml(presentationFileName);
                toolbar.innerHTML = `
                            <div class="toolbar-group">
                                <button class="primary" onclick="pickPresentationFile()">📂 Загрузить PDF</button>
                                ${presentationPdf ? `<button onclick="clearPresentation()">🗑️ Закрыть</button>` : ''}
                                ${presentationPdf ? `<button onclick="togglePresentationSidebar()">${presentationSidebarOpen ? '▸ Скрыть слайды' : '◂ Слайды'}</button>` : ''}
                            </div>
                            ${presentationPdf ? `
                            <div class="toolbar-divider"></div>
                            <div class="toolbar-group">
                                <button onclick="presentationPrevPage()">◀</button>
                                <span class="toolbar-label" id="presentationToolbarPage">${presentationPage} / ${presentationPageCount}</span>
                                <button onclick="presentationNextPage()">▶</button>
                            </div>
                            <div class="toolbar-divider"></div>
                            <div class="toolbar-group">
                                <span class="toolbar-label">${nameSafe}</span>
                            </div>` : `
                            <div class="toolbar-divider"></div>
                            <div class="toolbar-group">
                                <span class="toolbar-label">Drag & drop или Ctrl+V</span>
                            </div>`}
                        `;
            }
        }

        // ============================================================
        //  ПРЕЗЕНТАЦИЯ PDF
        // ============================================================
        let presentationUrl = null;
        let presentationFileName = '';
        let presentationPdf = null;
        let presentationPage = 1;
        let presentationPageCount = 0;
        let presentationSidebarOpen = true;
        let presentationRenderTask = null;
        let presentationPasteBound = false;
        let presentationWheelLock = false;
        let presentationSwipe = null;
        let presentationNavToken = 0;

        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc =
                'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }

        function escapeHtml(str) {
            return String(str || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function openPresentationTool() {
            switchTool('presentation');
        }

        function pickPresentationFile() {
            const input = document.getElementById('presentationFileInput');
            if (!input) return;
            input.value = '';
            input.click();
        }

        async function clearPresentation() {
            if (presentationRenderTask) {
                try { presentationRenderTask.cancel(); } catch (_) {}
                presentationRenderTask = null;
            }
            if (presentationPdf) {
                try { await presentationPdf.destroy(); } catch (_) {}
                presentationPdf = null;
            }
            if (presentationUrl) {
                URL.revokeObjectURL(presentationUrl);
                presentationUrl = null;
            }
            presentationFileName = '';
            presentationPage = 1;
            presentationPageCount = 0;
            if (currentTool === 'presentation') {
                renderPresentationView();
                renderToolbar('presentation');
            }
        }

        function isPdfFile(file) {
            if (!file) return false;
            return file.type === 'application/pdf' || /\.pdf$/i.test(file.name || '');
        }

        async function loadPresentationFromFile(file) {
            if (!isPdfFile(file)) {
                alert('Выберите файл в формате PDF');
                return;
            }
            if (typeof pdfjsLib === 'undefined') {
                alert('Библиотека PDF не загружена. Проверьте интернет-соединение.');
                return;
            }

            if (currentTool !== 'presentation') {
                switchTool('presentation');
            }

            await clearPresentationKeepingTool();

            presentationUrl = URL.createObjectURL(file);
            presentationFileName = file.name || 'presentation.pdf';
            presentationPage = 1;

            renderPresentationView(true);

            try {
                const loadingTask = pdfjsLib.getDocument({ url: presentationUrl });
                presentationPdf = await loadingTask.promise;
                presentationPageCount = presentationPdf.numPages;
                renderPresentationView();
                renderToolbar('presentation');
                await renderPresentationPage();
                await renderPresentationThumbnails();
            } catch (err) {
                console.error(err);
                alert('Не удалось открыть PDF');
                await clearPresentation();
            }
        }

        async function clearPresentationKeepingTool() {
            if (presentationRenderTask) {
                try { presentationRenderTask.cancel(); } catch (_) {}
                presentationRenderTask = null;
            }
            if (presentationPdf) {
                try { await presentationPdf.destroy(); } catch (_) {}
                presentationPdf = null;
            }
            if (presentationUrl) {
                URL.revokeObjectURL(presentationUrl);
                presentationUrl = null;
            }
            presentationFileName = '';
            presentationPage = 1;
            presentationPageCount = 0;
        }

        function renderPresentationView(loading = false) {
            const scrollArea = document.getElementById('canvasScroll');
            if (!scrollArea) return;

            if (!presentationPdf && !loading) {
                scrollArea.innerHTML = `
                    <div class="presentation-area" id="presentationArea">
                        <div class="presentation-dropzone" id="presentationDropzone">
                            <div class="icon-big">📽️</div>
                            <div class="drop-title">Загрузите презентацию (PDF)</div>
                            <div class="drop-hint">Перетащите файл сюда, вставьте из буфера (Ctrl+V) или выберите на диске</div>
                            <button type="button" class="drop-upload-btn" onclick="pickPresentationFile()">📂 Загрузить PDF</button>
                        </div>
                    </div>
                `;
                bindPresentationDropZone();
                return;
            }

            const nameSafe = escapeHtml(presentationFileName);
            const sidebarClass = presentationSidebarOpen ? '' : ' hidden';

            scrollArea.innerHTML = `
                <div class="presentation-area" id="presentationArea">
                    ${loading ? '<div class="presentation-loading">Загрузка PDF…</div>' : ''}
                    <div class="presentation-viewer">
                        <div class="presentation-stage">
                            <div class="presentation-stage-top">
                                <div class="presentation-filename-label" title="${nameSafe}">${nameSafe || 'Презентация'}</div>
                                <div class="presentation-stage-actions">
                                    <button type="button" onclick="togglePresentationSidebar()">${presentationSidebarOpen ? '▸ Скрыть слайды' : '◂ Слайды'}</button>
                                    <button type="button" onclick="pickPresentationFile()">Другой PDF</button>
                                </div>
                            </div>
                            <div class="presentation-carousel">
                                <button type="button" class="presentation-nav-btn" id="presentationPrevBtn" onclick="presentationPrevPage()" aria-label="Предыдущий слайд">‹</button>
                                <div class="presentation-page-wrap" id="presentationPageWrap">
                                    <canvas id="presentationCanvas"></canvas>
                                </div>
                                <button type="button" class="presentation-nav-btn" id="presentationNextBtn" onclick="presentationNextPage()" aria-label="Следующий слайд">›</button>
                            </div>
                            <div class="presentation-page-bar">
                                <span class="page-label" id="presentationPageLabel">${presentationPage} / ${presentationPageCount || '…'}</span>
                                <div class="presentation-page-dots" id="presentationPageDots"></div>
                            </div>
                        </div>
                        <aside class="presentation-sidebar${sidebarClass}" id="presentationSidebar">
                            <div class="presentation-sidebar-header">Слайды</div>
                            <div class="presentation-thumbs" id="presentationThumbs"></div>
                        </aside>
                    </div>
                </div>
            `;

            updatePresentationNavState();
            renderPresentationDots();
            bindPresentationDropZone();

            if (presentationPdf && !loading) {
                requestAnimationFrame(() => {
                    renderPresentationPage();
                    if (!document.querySelector('#presentationThumbs .presentation-thumb')) {
                        renderPresentationThumbnails();
                    }
                    bindPresentationGestures();
                });
            }
        }

        function bindPresentationDropZone() {
            const area = document.getElementById('presentationArea');
            const dropzone = document.getElementById('presentationDropzone') || area;
            if (!dropzone || dropzone.dataset.dropBound === '1') return;
            dropzone.dataset.dropBound = '1';

            ['dragenter', 'dragover'].forEach(evt => {
                dropzone.addEventListener(evt, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('drag-over');
                });
            });

            ['dragleave', 'drop'].forEach(evt => {
                dropzone.addEventListener(evt, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('drag-over');
                });
            });

            dropzone.addEventListener('drop', (e) => {
                const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
                if (file) loadPresentationFromFile(file);
            });
        }

        function ensurePresentationPaste() {
            if (presentationPasteBound) return;
            presentationPasteBound = true;
            document.addEventListener('paste', (e) => {
                if (currentTool !== 'presentation') return;
                const items = e.clipboardData && e.clipboardData.items;
                if (!items) return;
                for (const item of items) {
                    if (item.kind === 'file') {
                        const file = item.getAsFile();
                        if (isPdfFile(file)) {
                            e.preventDefault();
                            loadPresentationFromFile(file);
                            return;
                        }
                    }
                }
                const files = e.clipboardData && e.clipboardData.files;
                if (files && files[0] && isPdfFile(files[0])) {
                    e.preventDefault();
                    loadPresentationFromFile(files[0]);
                }
            });
        }

        function updatePresentationNavState() {
            const prev = document.getElementById('presentationPrevBtn');
            const next = document.getElementById('presentationNextBtn');
            if (prev) prev.disabled = presentationPage <= 1;
            if (next) next.disabled = presentationPage >= presentationPageCount;
            const label = document.getElementById('presentationPageLabel');
            if (label) label.textContent = `${presentationPage} / ${presentationPageCount || '…'}`;
            const toolbarPage = document.getElementById('presentationToolbarPage');
            if (toolbarPage) toolbarPage.textContent = `${presentationPage} / ${presentationPageCount}`;
        }

        function renderPresentationDots() {
            const dots = document.getElementById('presentationPageDots');
            if (!dots || !presentationPageCount) return;
            const maxDots = Math.min(presentationPageCount, 12);
            let html = '';
            for (let i = 1; i <= maxDots; i++) {
                const page = presentationPageCount <= 12
                    ? i
                    : Math.round(1 + (i - 1) * (presentationPageCount - 1) / (maxDots - 1));
                html += `<span class="${page === presentationPage ? 'active' : ''}"></span>`;
            }
            dots.innerHTML = html;
        }

        async function renderPresentationPage() {
            if (!presentationPdf) return;
            const canvas = document.getElementById('presentationCanvas');
            const wrap = document.getElementById('presentationPageWrap');
            if (!canvas || !wrap) return;

            const page = await presentationPdf.getPage(presentationPage);
            const baseViewport = page.getViewport({ scale: 1 });
            const pad = 16;
            const availW = Math.max(120, wrap.clientWidth - pad);
            const availH = Math.max(120, wrap.clientHeight - pad);
            const fitScale = Math.min(availW / baseViewport.width, availH / baseViewport.height);
            const outputScale = Math.min(window.devicePixelRatio || 1, 2.5);
            const cssScale = Math.max(0.25, fitScale);
            const viewport = page.getViewport({ scale: cssScale * outputScale });

            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.style.width = `${Math.floor(viewport.width / outputScale)}px`;
            canvas.style.height = `${Math.floor(viewport.height / outputScale)}px`;

            const ctx = canvas.getContext('2d', { alpha: false });
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (presentationRenderTask) {
                try { presentationRenderTask.cancel(); } catch (_) {}
            }

            presentationRenderTask = page.render({
                canvasContext: ctx,
                viewport,
                intent: 'display',
            });

            try {
                await presentationRenderTask.promise;
            } catch (err) {
                if (err && err.name !== 'RenderingCancelledException') console.warn(err);
            }

            updatePresentationNavState();
            renderPresentationDots();
            syncPresentationThumbActive();
        }

        function syncPresentationThumbActive() {
            const thumbs = document.querySelectorAll('.presentation-thumb');
            thumbs.forEach(el => {
                const isActive = Number(el.dataset.page) === presentationPage;
                el.classList.toggle('active', isActive);
                if (isActive && document.activeElement === el) {
                    // оставляем фокус только на активном
                } else if (document.activeElement === el) {
                    el.blur();
                }
            });

            // если фокус на старом превью — снимаем, чтобы не было «двойной» подсветки
            const activeEl = document.activeElement;
            if (activeEl && activeEl.classList && activeEl.classList.contains('presentation-thumb')) {
                if (Number(activeEl.dataset.page) !== presentationPage) {
                    activeEl.blur();
                }
            }
        }

        function updatePresentationSidebarLayout(pageWidth, pageHeight) {
            const sidebar = document.getElementById('presentationSidebar');
            // Одна ширина для вертикальных и горизонтальных превью
            if (sidebar && !sidebar.classList.contains('hidden')) {
                sidebar.style.width = '160px';
            }
        }

        async function renderPresentationThumbnails() {
            const container = document.getElementById('presentationThumbs');
            if (!container || !presentationPdf) return;
            container.innerHTML = '';

            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const firstPage = await presentationPdf.getPage(1);
            const firstViewport = firstPage.getViewport({ scale: 1 });
            updatePresentationSidebarLayout(firstViewport.width, firstViewport.height);

            await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
            const thumbInnerWidth = Math.max(100, (container.clientWidth || 160) - 16);

            for (let i = 1; i <= presentationPageCount; i++) {
                const page = await presentationPdf.getPage(i);
                const base = page.getViewport({ scale: 1 });
                const aspect = base.width / base.height;

                const thumb = document.createElement('button');
                thumb.type = 'button';
                thumb.className = `presentation-thumb${i === presentationPage ? ' active' : ''}`;
                thumb.dataset.page = String(i);
                thumb.innerHTML = `<div class="presentation-thumb-frame"><canvas></canvas></div><span class="thumb-num">${i}</span>`;
                thumb.querySelector('.presentation-thumb-frame').style.setProperty('--thumb-aspect', String(aspect));
                thumb.addEventListener('click', () => goToPresentationPage(i));
                container.appendChild(thumb);

                // Масштаб по ширине панели — горизонтальные той же ширины, ниже по высоте
                const fitScale = thumbInnerWidth / base.width;
                const viewport = page.getViewport({ scale: fitScale * dpr });
                const canvas = thumb.querySelector('canvas');
                canvas.width = Math.floor(viewport.width);
                canvas.height = Math.floor(viewport.height);
                canvas.style.width = `${Math.floor(viewport.width / dpr)}px`;
                canvas.style.height = `${Math.floor(viewport.height / dpr)}px`;
                await page.render({
                    canvasContext: canvas.getContext('2d', { alpha: false }),
                    viewport,
                    intent: 'display',
                }).promise;
            }

            bindPresentationSidebarSwipe();
        }

        async function goToPresentationPage(pageNum) {
            if (!presentationPdf) return;
            const next = Math.min(presentationPageCount, Math.max(1, pageNum));
            if (next === presentationPage && document.getElementById('presentationCanvas')) {
                syncPresentationThumbActive();
                updatePresentationNavState();
                return;
            }

            const token = ++presentationNavToken;
            presentationPage = next;
            updatePresentationNavState();
            syncPresentationThumbActive();

            await renderPresentationPage();
            if (token !== presentationNavToken) return;

            syncPresentationThumbActive();
            const activeThumb = document.querySelector(`.presentation-thumb.active`);
            if (activeThumb) {
                activeThumb.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }

        function presentationPrevPage() {
            goToPresentationPage(presentationPage - 1);
        }

        function presentationNextPage() {
            goToPresentationPage(presentationPage + 1);
        }

        function bindPresentationGestures() {
            const wrap = document.getElementById('presentationPageWrap');
            const carousel = document.querySelector('.presentation-carousel');
            const stage = document.querySelector('.presentation-stage');
            if (!wrap || wrap.dataset.gesturesBound === '1') return;
            wrap.dataset.gesturesBound = '1';

            const wheelTarget = stage || carousel || wrap;
            wheelTarget.addEventListener('wheel', (e) => {
                if (!presentationPdf) return;
                if (Math.abs(e.deltaY) < 2 && Math.abs(e.deltaX) < 2) return;
                e.preventDefault();
                if (presentationWheelLock) return;
                presentationWheelLock = true;

                const dominant = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
                if (dominant > 0) presentationNextPage();
                else presentationPrevPage();

                setTimeout(() => { presentationWheelLock = false; }, 280);
            }, { passive: false });

            const onPointerDown = (e) => {
                if (!presentationPdf) return;
                if (e.pointerType === 'mouse' && e.button !== 0) return;
                presentationSwipe = {
                    id: e.pointerId,
                    x: e.clientX,
                    y: e.clientY,
                    moved: false,
                };
                wrap.classList.add('is-swiping');
                try { wrap.setPointerCapture(e.pointerId); } catch (_) {}
            };

            const onPointerMove = (e) => {
                if (!presentationSwipe || presentationSwipe.id !== e.pointerId) return;
                const dx = e.clientX - presentationSwipe.x;
                const dy = e.clientY - presentationSwipe.y;
                if (Math.abs(dx) > 8 || Math.abs(dy) > 8) presentationSwipe.moved = true;
            };

            const onPointerUp = (e) => {
                if (!presentationSwipe || presentationSwipe.id !== e.pointerId) return;
                const dx = e.clientX - presentationSwipe.x;
                const dy = e.clientY - presentationSwipe.y;
                wrap.classList.remove('is-swiping');
                presentationSwipe = null;

                if (Math.abs(dx) < 48 || Math.abs(dx) < Math.abs(dy) * 1.2) return;
                if (dx < 0) presentationNextPage();
                else presentationPrevPage();
            };

            wrap.addEventListener('pointerdown', onPointerDown);
            wrap.addEventListener('pointermove', onPointerMove);
            wrap.addEventListener('pointerup', onPointerUp);
            wrap.addEventListener('pointercancel', () => {
                presentationSwipe = null;
                wrap.classList.remove('is-swiping');
            });
        }

        function bindPresentationSidebarSwipe() {
            const thumbs = document.getElementById('presentationThumbs');
            if (!thumbs || thumbs.dataset.swipeBound === '1') return;
            thumbs.dataset.swipeBound = '1';

            let swipe = null;

            thumbs.addEventListener('pointerdown', (e) => {
                if (!presentationPdf) return;
                if (e.pointerType === 'mouse' && e.button !== 0) return;
                swipe = {
                    id: e.pointerId,
                    x: e.clientX,
                    y: e.clientY,
                    scrollTop: thumbs.scrollTop,
                };
            });

            thumbs.addEventListener('pointermove', (e) => {
                if (!swipe || swipe.id !== e.pointerId) return;
                const dy = e.clientY - swipe.y;
                const dx = e.clientX - swipe.x;
                // vertical swipe scrolls the thumbnail list
                if (Math.abs(dy) > Math.abs(dx)) {
                    thumbs.scrollTop = swipe.scrollTop - dy;
                }
            });

            thumbs.addEventListener('pointerup', (e) => {
                if (!swipe || swipe.id !== e.pointerId) return;
                const dx = e.clientX - swipe.x;
                const dy = e.clientY - swipe.y;
                swipe = null;

                // horizontal swipe on sidebar changes slide
                if (Math.abs(dx) < 48 || Math.abs(dx) < Math.abs(dy) * 1.2) return;
                if (dx < 0) presentationNextPage();
                else presentationPrevPage();
            });

            thumbs.addEventListener('pointercancel', () => { swipe = null; });

            thumbs.addEventListener('wheel', (e) => {
                if (!presentationPdf) return;
                // horizontal wheel / shift+wheel flips slides in sidebar
                if (Math.abs(e.deltaX) > Math.abs(e.deltaY) || e.shiftKey) {
                    e.preventDefault();
                    if (presentationWheelLock) return;
                    presentationWheelLock = true;
                    const delta = e.shiftKey ? e.deltaY : e.deltaX;
                    if (delta > 0) presentationNextPage();
                    else presentationPrevPage();
                    setTimeout(() => { presentationWheelLock = false; }, 280);
                }
            }, { passive: false });
        }

        function togglePresentationSidebar() {
            presentationSidebarOpen = !presentationSidebarOpen;
            const sidebar = document.getElementById('presentationSidebar');
            if (sidebar) {
                sidebar.classList.toggle('hidden', !presentationSidebarOpen);
                if (presentationSidebarOpen) {
                    sidebar.style.width = '160px';
                } else {
                    sidebar.style.width = '';
                }
            }
            renderToolbar('presentation');
            const btn = document.querySelector('.presentation-stage-actions button');
            if (btn) btn.textContent = presentationSidebarOpen ? '▸ Скрыть слайды' : '◂ Слайды';
            requestAnimationFrame(() => {
                renderPresentationPage();
                bindPresentationGestures();
            });
        }

        function onPresentationFileSelected(e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            loadPresentationFromFile(file);
        }

        // ============================================================
        //  ПЕРЕКЛЮЧЕНИЕ РЕЖИМОВ
        // ============================================================
        function clearToolSelection() {
            currentTool = null;
            document.querySelectorAll('.tool-btn[data-tool]').forEach(btn => {
                btn.classList.remove('active');
            });
        }

        function updateModeButtons() {
            const videoBtn = document.getElementById('videoModeBtn');
            if (videoBtn) {
                videoBtn.classList.toggle('active-mode', isVideoMode);
            }
        }

        function showVideoMode() {
            if (isVideoMode) {
                updateModeButtons();
                return;
            }

            isVideoMode = true;
            const videoScreen = document.getElementById('videoScreen');
            const canvasScreen = document.getElementById('canvasScreen');

            if (fullscreenTile) {
                exitTileFullscreen();
            }

            videoScreen.classList.remove('hidden');
            videoScreen.classList.add('visible');
            canvasScreen.classList.remove('visible');
            canvasScreen.classList.add('hidden');
            clearToolSelection();
            updateModeButtons();
        }

        const TOOL_META = {
            cards: {
                title: 'Метафорические карты',
                subtitle: 'Выберите карту и откройте её кликом',
            },
            helinger: {
                title: 'Расстановка',
                subtitle: 'Добавьте фигуры и расположите их на поле',
            },
            drawing: {
                title: 'Рисование',
                subtitle: 'Свободное рисование на холсте',
            },
            presentation: {
                title: 'Презентация',
                subtitle: 'PDF на холсте для демонстрации участникам',
            },
        };

        function updateCanvasHeader(tool) {
            const titleEl = document.getElementById('canvasSectionTitle');
            const subtitleEl = document.getElementById('canvasSectionSubtitle');
            if (!titleEl || !subtitleEl) return;

            const meta = TOOL_META[tool];
            if (meta) {
                titleEl.textContent = meta.title;
                subtitleEl.textContent = meta.subtitle;
            } else {
                titleEl.textContent = 'Холст';
                subtitleEl.textContent = 'Выберите инструмент в панели слева';
            }
        }

        function showCanvasEmpty() {
            const scrollArea = document.getElementById('canvasScroll');
            const toolbar = document.getElementById('canvasToolbar');
            if (scrollArea) {
                scrollArea.innerHTML = `
                    <div class="canvas-empty" id="canvasEmpty">
                        <div class="canvas-empty-icon">🖼️</div>
                        <div>Откройте карты, расстановку, рисование или презентацию</div>
                    </div>
                `;
            }
            if (toolbar) toolbar.innerHTML = '';
            clearToolSelection();
            updateCanvasHeader(null);
        }

        function toggleMode() {
            showVideoMode();
        }

        function switchTool(tool) {
            if (isVideoMode) {
                currentTool = tool;
                isVideoMode = false;

                const videoScreen = document.getElementById('videoScreen');
                const canvasScreen = document.getElementById('canvasScreen');

                if (fullscreenTile) exitTileFullscreen();

                videoScreen.classList.remove('visible');
                videoScreen.classList.add('hidden');
                canvasScreen.classList.remove('hidden');
                canvasScreen.classList.add('visible');
                updateModeButtons();
            }

            currentTool = tool;

            document.querySelectorAll('.tool-btn[data-tool]').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tool === tool);
            });
            updateModeButtons();

            updateCanvasHeader(tool);

            const scrollArea = document.getElementById('canvasScroll');

            if (tool === 'cards') {
                scrollArea.innerHTML = `<div class="cards-grid" id="cardsGrid"></div>`;
                if (!cards.length) initCards();
                else renderCards();
            } else if (tool === 'helinger') {
                scrollArea.innerHTML = `
                            <div class="figures-grid" id="figuresGrid">
                                <div class="figures-hint">Добавьте фигуру в панели внизу, затем перетащите её на поле</div>
                            </div>
                        `;
                figureCounter = 0;
            } else if (tool === 'drawing') {
                scrollArea.innerHTML = `
                            <div class="drawing-area" id="drawingArea">
                                <div class="icon-big">✏️</div>
                                <div>Нажмите и рисуйте на этом поле</div>
                                <div style="font-size:12px;color:#c0b8b0;">(демо-режим)</div>
                            </div>
                        `;
            } else if (tool === 'presentation') {
                renderPresentationView();
            }

            renderToolbar(tool);
        }

        // ============================================================
        //  ЧАТ
        // ============================================================
        function toggleChat() {
            isChatOpen = !isChatOpen;
            if (isChatOpen && isBeautyOpen) {
                isBeautyOpen = false;
                document.getElementById('beautyOverlay').classList.remove('active');
            }
            if (isChatOpen && isSettingsOpen) {
                isSettingsOpen = false;
                document.getElementById('settingsOverlay').classList.remove('active');
                syncSettingsButton();
            }
            document.getElementById('chatOverlay').classList.toggle('active', isChatOpen);
            syncBeautyButtons();
        }

        // ============================================================
        //  НАСТРОЙКИ
        // ============================================================
        let isSettingsOpen = false;

        function syncSettingsButton() {
            const btn = document.getElementById('settingsToolBtn');
            if (btn) btn.classList.toggle('active', isSettingsOpen);
        }

        function toggleSettingsPanel() {
            isSettingsOpen = !isSettingsOpen;
            if (isSettingsOpen) {
                if (isBeautyOpen) {
                    isBeautyOpen = false;
                    document.getElementById('beautyOverlay').classList.remove('active');
                    syncBeautyButtons();
                }
                if (isChatOpen) {
                    isChatOpen = false;
                    document.getElementById('chatOverlay').classList.remove('active');
                }
            }
            document.getElementById('settingsOverlay').classList.toggle('active', isSettingsOpen);
            syncSettingsButton();
        }

        function copyRoomLink() {
            const input = document.getElementById('settingRoomLink');
            if (!input) return;
            const value = input.value;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(() => {
                    const btn = document.querySelector('.settings-link-row button');
                    if (btn) {
                        const prev = btn.textContent;
                        btn.textContent = 'Скопировано';
                        setTimeout(() => { btn.textContent = prev; }, 1200);
                    }
                }).catch(() => {
                    input.select();
                    document.execCommand('copy');
                });
            } else {
                input.select();
                document.execCommand('copy');
            }
        }

        // ============================================================
        //  БЬЮТИ-ФИЛЬТРЫ
        // ============================================================
        let isBeautyOpen = false;
        let currentBeautyFilter = 'none';
        let currentVideoBackground = 'none';
        let beautyIntensity = 70;
        let isVideoMirrored = false;

        const BEAUTY_FILTERS = {
            none: {
                name: 'Оригинал',
                desc: 'Без обработки',
                build: () => 'none',
            },
            soft: {
                name: 'Мягкий',
                desc: 'Сглаживание кожи',
                build: (t) => `brightness(${1 + 0.08 * t}) contrast(${1 - 0.08 * t}) saturate(${1 + 0.06 * t}) blur(${0.55 * t}px)`,
            },
            clarity: {
                name: 'Чёткость',
                desc: 'Свежее лицо',
                build: (t) => `brightness(${1 + 0.04 * t}) contrast(${1 + 0.08 * t}) saturate(${1 + 0.08 * t})`,
            },
        };

        const VIDEO_BACKGROUNDS = {
            none: {
                name: 'Без фона',
                desc: 'Только камера',
            },
            interior: {
                name: 'Интерьер',
                desc: 'Комната / студия',
                image: 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?auto=format&fit=crop&w=1280&q=80',
            },
            nature: {
                name: 'Природа',
                desc: 'Пейзаж',
                image: 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?auto=format&fit=crop&w=1280&q=80',
            },
            blur: {
                name: 'Замыливание',
                desc: 'Размытый фон',
            },
        };

        let selfieSegmentation = null;
        let beautySegReady = false;
        let beautySegFailed = false;
        let beautyProcessActive = false;
        let beautyRafId = 0;
        let beautySending = false;
        let processedStream = null;
        let processedStreamCanvas = null;
        const beautyBgImageCache = {};
        const beautyOffscreen = {
            person: null,
            mask: null,
            blur: null,
        };

        function getBeautyCss() {
            const preset = BEAUTY_FILTERS[currentBeautyFilter] || BEAUTY_FILTERS.none;
            if (currentBeautyFilter === 'none') return 'none';
            const t = Math.max(0, Math.min(1, beautyIntensity / 100));
            return preset.build(t);
        }

        function loadBeautyBgImage(url) {
            if (!url) return Promise.resolve(null);
            if (beautyBgImageCache[url]) return Promise.resolve(beautyBgImageCache[url]);
            return new Promise((resolve) => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => {
                    beautyBgImageCache[url] = img;
                    resolve(img);
                };
                img.onerror = () => resolve(null);
                img.src = url;
            });
        }

        function ensureOffscreenCanvas(key, w, h) {
            if (!beautyOffscreen[key]) {
                beautyOffscreen[key] = document.createElement('canvas');
            }
            const c = beautyOffscreen[key];
            if (c.width !== w || c.height !== h) {
                c.width = w;
                c.height = h;
            }
            return c;
        }

        function drawImageCover(ctx, source, w, h) {
            const sw = source.videoWidth || source.naturalWidth || source.width;
            const sh = source.videoHeight || source.naturalHeight || source.height;
            if (!sw || !sh) {
                ctx.drawImage(source, 0, 0, w, h);
                return;
            }
            const scale = Math.max(w / sw, h / sh);
            const dw = sw * scale;
            const dh = sh * scale;
            const dx = (w - dw) / 2;
            const dy = (h - dh) / 2;
            ctx.drawImage(source, dx, dy, dw, dh);
        }

        async function ensureSelfieSegmentation() {
            if (beautySegReady) return true;
            if (beautySegFailed) return false;
            if (typeof SelfieSegmentation === 'undefined') {
                beautySegFailed = true;
                console.warn('SelfieSegmentation недоступен');
                return false;
            }
            try {
                selfieSegmentation = new SelfieSegmentation({
                    locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/${file}`,
                });
                selfieSegmentation.setOptions({
                    modelSelection: 1,
                    selfieMode: true,
                });
                selfieSegmentation.onResults(onBeautySegmentationResults);
                beautySegReady = true;
                return true;
            } catch (err) {
                beautySegFailed = true;
                console.warn('Не удалось инициализировать сегментацию', err);
                return false;
            }
        }

        function syncProcessedOutputs(sourceCanvas) {
            const thumbCanvas = document.getElementById('videoSelfThumbCanvas');
            if (thumbCanvas && sourceCanvas) {
                const tw = Math.max(160, Math.round(sourceCanvas.width * 0.35));
                const th = Math.max(90, Math.round(sourceCanvas.height * 0.35));
                if (thumbCanvas.width !== tw || thumbCanvas.height !== th) {
                    thumbCanvas.width = tw;
                    thumbCanvas.height = th;
                }
                const tctx = thumbCanvas.getContext('2d');
                tctx.clearRect(0, 0, tw, th);
                tctx.drawImage(sourceCanvas, 0, 0, tw, th);
                thumbCanvas.classList.toggle('is-mirrored', isVideoMirrored);
                thumbCanvas.style.filter = getBeautyCss();
            }

            if (!processedStreamCanvas) {
                processedStreamCanvas = document.createElement('canvas');
            }
            if (
                processedStreamCanvas.width !== sourceCanvas.width ||
                processedStreamCanvas.height !== sourceCanvas.height
            ) {
                processedStreamCanvas.width = sourceCanvas.width;
                processedStreamCanvas.height = sourceCanvas.height;
                if (processedStream) {
                    processedStream.getTracks().forEach(t => t.stop());
                    processedStream = null;
                }
            }
            const pctx = processedStreamCanvas.getContext('2d');
            pctx.clearRect(0, 0, processedStreamCanvas.width, processedStreamCanvas.height);
            pctx.drawImage(sourceCanvas, 0, 0);
            if (!processedStream) {
                processedStream = processedStreamCanvas.captureStream(30);
            }

            document.querySelectorAll('.video-tile[data-id="lena"] video').forEach(video => {
                if (video.srcObject !== processedStream) {
                    video.srcObject = processedStream;
                    video.classList.add('is-beauty-processed');
                    video.play().catch(() => {});
                }
                video.style.filter = getBeautyCss();
                video.classList.toggle('is-mirrored', isVideoMirrored);
            });
        }

        function onBeautySegmentationResults(results) {
            const canvas = document.getElementById('videoSelfCanvas');
            const video = document.getElementById('videoSelf');
            if (!canvas || !video || !results || !results.image) return;

            const w = video.videoWidth || 640;
            const h = video.videoHeight || 360;
            if (canvas.width !== w || canvas.height !== h) {
                canvas.width = w;
                canvas.height = h;
            }

            const ctx = canvas.getContext('2d');
            const t = Math.max(0, Math.min(1, beautyIntensity / 100));
            const edgeSoft = 4 + t * 8;
            const blurPx = 8 + t * 22;

            const personCanvas = ensureOffscreenCanvas('person', w, h);
            const maskCanvas = ensureOffscreenCanvas('mask', w, h);
            const personCtx = personCanvas.getContext('2d');
            const maskCtx = maskCanvas.getContext('2d');

            // Мягкая маска человека
            maskCtx.clearRect(0, 0, w, h);
            maskCtx.filter = `blur(${edgeSoft}px)`;
            maskCtx.drawImage(results.segmentationMask, 0, 0, w, h);
            maskCtx.filter = 'none';

            personCtx.clearRect(0, 0, w, h);
            personCtx.drawImage(results.image, 0, 0, w, h);
            personCtx.globalCompositeOperation = 'destination-in';
            personCtx.drawImage(maskCanvas, 0, 0);
            personCtx.globalCompositeOperation = 'source-over';

            ctx.clearRect(0, 0, w, h);

            if (currentVideoBackground === 'blur') {
                const blurCanvas = ensureOffscreenCanvas('blur', w, h);
                const blurCtx = blurCanvas.getContext('2d');
                blurCtx.clearRect(0, 0, w, h);
                blurCtx.filter = `blur(${blurPx}px)`;
                // чуть увеличить, чтобы не было светлой каймы от blur
                const pad = Math.ceil(blurPx * 2);
                blurCtx.drawImage(results.image, -pad, -pad, w + pad * 2, h + pad * 2);
                blurCtx.filter = 'none';
                ctx.drawImage(blurCanvas, 0, 0, w, h);
            } else {
                const preset = VIDEO_BACKGROUNDS[currentVideoBackground];
                const bgImg = preset && preset.image ? beautyBgImageCache[preset.image] : null;
                if (bgImg) {
                    drawImageCover(ctx, bgImg, w, h);
                } else {
                    ctx.fillStyle = '#1a1a2e';
                    ctx.fillRect(0, 0, w, h);
                }
            }

            ctx.drawImage(personCanvas, 0, 0, w, h);

            canvas.classList.toggle('is-mirrored', isVideoMirrored);
            canvas.style.filter = getBeautyCss();
            syncProcessedOutputs(canvas);
        }

        async function beautyProcessLoop() {
            if (!beautyProcessActive) return;
            const video = document.getElementById('videoSelf');
            if (video && selfieSegmentation && video.readyState >= 2 && !beautySending) {
                beautySending = true;
                try {
                    await selfieSegmentation.send({ image: video });
                } catch (err) {
                    // ignore frame errors
                }
                beautySending = false;
            }
            beautyRafId = requestAnimationFrame(beautyProcessLoop);
        }

        function stopBeautyBackgroundPipeline() {
            beautyProcessActive = false;
            if (beautyRafId) {
                cancelAnimationFrame(beautyRafId);
                beautyRafId = 0;
            }
            if (processedStream) {
                processedStream.getTracks().forEach(t => t.stop());
                processedStream = null;
            }
            processedStreamCanvas = null;

            document.getElementById('hostVideoStage')?.classList.remove('has-beauty-bg');
            document.querySelector('.participant-item[data-id="lena"] .participant-video')?.classList.remove('has-beauty-bg');
            document.querySelectorAll('.video-tile[data-id="lena"]').forEach(el => {
                el.classList.remove('has-beauty-bg');
            });

            const mainCanvas = document.getElementById('videoSelfCanvas');
            const thumbCanvas = document.getElementById('videoSelfThumbCanvas');
            if (mainCanvas) {
                mainCanvas.style.filter = '';
                mainCanvas.classList.remove('is-mirrored');
            }
            if (thumbCanvas) {
                thumbCanvas.style.filter = '';
                thumbCanvas.classList.remove('is-mirrored');
            }

            // вернуть камеру на плитки Лены
            if (hostStream) {
                document.querySelectorAll('.video-tile[data-id="lena"] video').forEach(video => {
                    video.classList.remove('is-beauty-processed');
                    try {
                        video.srcObject = hostStream.clone();
                    } catch (e) {
                        video.srcObject = hostStream;
                    }
                    video.play().catch(() => {});
                });
            }
        }

        async function startBeautyBackgroundPipeline() {
            const ok = await ensureSelfieSegmentation();
            if (!ok) {
                console.warn('Фон: сегментация недоступна');
                return;
            }

            const preset = VIDEO_BACKGROUNDS[currentVideoBackground];
            if (preset && preset.image) {
                await loadBeautyBgImage(preset.image);
            }

            document.getElementById('hostVideoStage')?.classList.add('has-beauty-bg');
            document.querySelector('.participant-item[data-id="lena"] .participant-video')?.classList.add('has-beauty-bg');
            document.querySelectorAll('.video-tile[data-id="lena"]').forEach(el => {
                el.classList.add('has-beauty-bg');
            });

            if (!beautyProcessActive) {
                beautyProcessActive = true;
                beautyProcessLoop();
            }
        }

        function applyVideoBackground() {
            if (currentVideoBackground === 'none') {
                stopBeautyBackgroundPipeline();
                return;
            }
            startBeautyBackgroundPipeline();
        }

        function syncBeautyButtons() {
            const toolBtn = document.getElementById('beautyToolBtn');
            const fab = document.getElementById('beautyFab');
            const filterOn = currentBeautyFilter !== 'none' || currentVideoBackground !== 'none' || isVideoMirrored;

            if (toolBtn) {
                toolBtn.classList.toggle('filter-on', filterOn);
                toolBtn.classList.toggle('active', isBeautyOpen);
            }
            if (fab) {
                fab.classList.toggle('filter-on', filterOn);
                fab.classList.toggle('active', isBeautyOpen);
            }

            document.querySelectorAll('#beautyGrid .beauty-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.filter === currentBeautyFilter);
            });
            document.querySelectorAll('#backgroundGrid .beauty-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.bg === currentVideoBackground);
            });

            const mirrorToggle = document.getElementById('videoMirrorToggle');
            if (mirrorToggle) mirrorToggle.checked = isVideoMirrored;
        }

        function applyBeautyFilter() {
            const css = getBeautyCss();
            const bgOn = currentVideoBackground !== 'none';

            document.querySelectorAll('video').forEach(video => {
                if (video.classList.contains('host-bg-blur-video')) return;
                // при активном фоне фильтр лица идёт на canvas / processed stream
                if (bgOn && (video.id === 'videoSelf' || video.id === 'videoSelfThumb' || video.classList.contains('is-beauty-processed'))) {
                    video.style.filter = 'none';
                } else {
                    video.style.filter = css;
                }
                video.classList.toggle('is-mirrored', isVideoMirrored && !bgOn);
            });

            const mainCanvas = document.getElementById('videoSelfCanvas');
            const thumbCanvas = document.getElementById('videoSelfThumbCanvas');
            if (mainCanvas) {
                mainCanvas.style.filter = bgOn ? css : 'none';
                mainCanvas.classList.toggle('is-mirrored', bgOn && isVideoMirrored);
            }
            if (thumbCanvas) {
                thumbCanvas.style.filter = bgOn ? css : 'none';
                thumbCanvas.classList.toggle('is-mirrored', bgOn && isVideoMirrored);
            }

            applyVideoBackground();
            syncBeautyButtons();
        }

        function setVideoMirrored(on) {
            isVideoMirrored = !!on;
            applyBeautyFilter();
        }

        function setBeautyFilter(id) {
            if (!BEAUTY_FILTERS[id]) return;
            currentBeautyFilter = id;
            applyBeautyFilter();
        }

        function setVideoBackground(id) {
            if (!VIDEO_BACKGROUNDS[id]) return;
            currentVideoBackground = id;
            applyBeautyFilter();
        }

        function renderBeautyPanel() {
            const grid = document.getElementById('beautyGrid');
            if (!grid) return;
            grid.innerHTML = '';

            Object.entries(BEAUTY_FILTERS).forEach(([id, preset]) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `beauty-btn${id === currentBeautyFilter ? ' active' : ''}`;
                btn.dataset.filter = id;
                btn.innerHTML = `
                    <span class="beauty-name">${preset.name}</span>
                    <span class="beauty-desc">${preset.desc}</span>
                `;
                btn.addEventListener('click', () => setBeautyFilter(id));
                grid.appendChild(btn);
            });

            renderBackgroundPanel();
        }

        function renderBackgroundPanel() {
            const grid = document.getElementById('backgroundGrid');
            if (!grid) return;
            grid.innerHTML = '';

            Object.entries(VIDEO_BACKGROUNDS).forEach(([id, preset]) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `beauty-btn beauty-bg-btn${id === currentVideoBackground ? ' active' : ''}`;
                btn.dataset.bg = id;
                btn.innerHTML = `
                    <span class="beauty-name">${preset.name}</span>
                    <span class="beauty-desc">${preset.desc}</span>
                `;
                btn.addEventListener('click', () => setVideoBackground(id));
                grid.appendChild(btn);
            });
        }

        function toggleBeautyPanel() {
            isBeautyOpen = !isBeautyOpen;
            if (isBeautyOpen && isChatOpen) {
                isChatOpen = false;
                document.getElementById('chatOverlay').classList.remove('active');
            }
            if (isBeautyOpen && isSettingsOpen) {
                isSettingsOpen = false;
                document.getElementById('settingsOverlay').classList.remove('active');
                syncSettingsButton();
            }
            document.getElementById('beautyOverlay').classList.toggle('active', isBeautyOpen);
            if (isBeautyOpen) renderBeautyPanel();
            syncBeautyButtons();
        }

        function initBeautyControls() {
            renderBeautyPanel();
            const slider = document.getElementById('beautyIntensity');
            const valueEl = document.getElementById('beautyIntensityValue');
            if (slider) {
                slider.addEventListener('input', () => {
                    beautyIntensity = Number(slider.value);
                    if (valueEl) valueEl.textContent = `${beautyIntensity}%`;
                    applyBeautyFilter();
                });
            }
            const mirrorToggle = document.getElementById('videoMirrorToggle');
            if (mirrorToggle) {
                mirrorToggle.addEventListener('change', () => {
                    setVideoMirrored(mirrorToggle.checked);
                });
            }
            applyBeautyFilter();
        }

        document.getElementById('exitFullscreenBtn').addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            exitTileFullscreen();
        });

        // ============================================================
        //  ЗАПИСЬ (прототип UI)
        // ============================================================
        let isRecording = false;
        let isRecordPaused = false;
        let recordElapsedMs = 0;
        let recordSegmentStartedAt = 0;
        let recordTimerId = null;

        function formatRecordTime(ms) {
            const totalSec = Math.floor(ms / 1000);
            const m = String(Math.floor(totalSec / 60)).padStart(2, '0');
            const s = String(totalSec % 60).padStart(2, '0');
            return `${m}:${s}`;
        }

        function getRecordElapsedMs() {
            if (!isRecording) return 0;
            if (isRecordPaused) return recordElapsedMs;
            return recordElapsedMs + (Date.now() - recordSegmentStartedAt);
        }

        function syncRecordButton() {
            const controls = document.getElementById('recordControls');
            const timer = document.getElementById('recordTimer');
            const pauseBtn = document.getElementById('recordPauseBtn');
            if (!controls || !timer || !pauseBtn) return;

            controls.classList.toggle('is-active', isRecording);
            controls.classList.toggle('is-paused', isRecording && isRecordPaused);
            pauseBtn.title = isRecordPaused ? 'Продолжить' : 'Пауза';
            pauseBtn.setAttribute('aria-label', isRecordPaused ? 'Продолжить' : 'Пауза');
            timer.textContent = formatRecordTime(getRecordElapsedMs());
        }

        function stopRecordTimer() {
            if (recordTimerId) {
                clearInterval(recordTimerId);
                recordTimerId = null;
            }
        }

        function startRecordTimer() {
            stopRecordTimer();
            recordTimerId = setInterval(syncRecordButton, 1000);
        }

        function startRecording() {
            if (isRecording) return;
            isRecording = true;
            isRecordPaused = false;
            recordElapsedMs = 0;
            recordSegmentStartedAt = Date.now();
            syncRecordButton();
            startRecordTimer();
        }

        function stopRecording() {
            if (!isRecording) return;
            isRecording = false;
            isRecordPaused = false;
            recordElapsedMs = 0;
            stopRecordTimer();
            syncRecordButton();
        }

        function toggleRecordPause() {
            if (!isRecording) return;

            if (isRecordPaused) {
                isRecordPaused = false;
                recordSegmentStartedAt = Date.now();
                startRecordTimer();
                syncRecordButton();
                return;
            }

            recordElapsedMs += Date.now() - recordSegmentStartedAt;
            isRecordPaused = true;
            stopRecordTimer();
            syncRecordButton();
        }

        // ============================================================
        //  ЗАПУСК
        // ============================================================
        renderParticipantsList();
        initCards();
        initVideoStreams();
        selectParticipant('olga');
        document.querySelectorAll('.tool-btn[data-tool]').forEach(btn => btn.classList.remove('active'));
        showCanvasEmpty();
        updateModeButtons();
        initBeautyControls();
        syncRecordButton();
        ensurePresentationPaste();
        layoutVideoGrid();
        window.addEventListener('resize', () => layoutVideoGrid());

        const videoGridEl = document.getElementById('videoGrid');
        if (videoGridEl && typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(() => layoutVideoGrid()).observe(videoGridEl);
        }

        const demoCountInput = document.getElementById('demoParticipantCount');
        if (demoCountInput) {
            demoCountInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyDemoParticipantCount();
                }
            });
        }

        const presentationInput = document.getElementById('presentationFileInput');
        if (presentationInput) {
            presentationInput.addEventListener('change', onPresentationFileSelected);
        }

        // ============================================================
        //  ГОРЯЧИЕ КЛАВИШИ
        // ============================================================
        document.addEventListener('keydown', (e) => {
            if (e.key === 'v' || e.key === 'V') {
                e.preventDefault();
                toggleMode();
            }
            if (e.key === 'Escape') {
                if (isSettingsOpen) {
                    toggleSettingsPanel();
                    return;
                }
                if (isBeautyOpen) {
                    toggleBeautyPanel();
                    return;
                }
                if (isChatOpen) {
                    toggleChat();
                    return;
                }
                if (fullscreenTile) {
                    exitTileFullscreen();
                    return;
                }
            }
            if (currentTool === 'presentation' && presentationPdf) {
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    presentationPrevPage();
                    return;
                }
                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    presentationNextPage();
                    return;
                }
            }
            if (e.key === '1') switchTool('cards');
            if (e.key === '2') switchTool('helinger');
            if (e.key === '3') switchTool('drawing');
            if (e.key === '4') openPresentationTool();
            const num = parseInt(e.key);
            if (num >= 1 && num <= 9) {
                const p = participants[num - 1];
                if (p) selectParticipant(p.id);
            }
            if (e.key === '0') {
                const p = participants[9];
                if (p) selectParticipant(p.id);
            }
        });

        console.log('🎯 Прототип загружен');
        console.log('📌 1 — Карты | 2 — Расстановка | 3 — Рисование | 4 — Презентация');
        console.log('📌 V — переключить режим');
        console.log('📌 В расстановках: зажмите фигуру и перетащите');
        console.log('📌 Escape — закрыть всё');
    </script>

</body>
</html>
