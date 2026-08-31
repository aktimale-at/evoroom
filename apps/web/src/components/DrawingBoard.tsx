import { useEffect, useRef, useState, type PointerEvent } from 'react';
import { Room, RoomEvent } from 'livekit-client';

type Tool = 'pen' | 'eraser';

type Stroke = {
  color: string;
  width: number;
  eraser: boolean;
  points: Array<[number, number]>;
};

type DrawMsg =
  | { t: 'draw'; op: 'seg'; id: string; color: string; width: number; eraser: boolean; a: [number, number]; b: [number, number] }
  | { t: 'draw'; op: 'stroke'; stroke: Stroke }
  | { t: 'draw'; op: 'clear' }
  | { t: 'draw'; op: 'sync'; strokes: Stroke[] }
  | { t: 'draw'; op: 'req' };

const COLORS = ['#1a1a22', '#b91c1c', '#1d4ed8', '#15803d', '#eab308', '#c2410c'] as const;
const enc = new TextEncoder();
const dec = new TextDecoder();

function encode(msg: DrawMsg): Uint8Array {
  return enc.encode(JSON.stringify(msg));
}

function decode(data: Uint8Array): DrawMsg | null {
  try {
    const raw = JSON.parse(dec.decode(data)) as DrawMsg;
    return raw?.t === 'draw' ? raw : null;
  } catch {
    return null;
  }
}

function paintStroke(
  ctx: CanvasRenderingContext2D,
  stroke: Stroke,
  w: number,
  h: number,
) {
  const { points, color, width, eraser } = stroke;
  if (points.length < 2) return;
  ctx.save();
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.lineWidth = width;
  if (eraser) {
    ctx.globalCompositeOperation = 'destination-out';
    ctx.strokeStyle = 'rgba(0,0,0,1)';
  } else {
    ctx.globalCompositeOperation = 'source-over';
    ctx.strokeStyle = color;
  }
  ctx.beginPath();
  ctx.moveTo(points[0][0] * w, points[0][1] * h);
  for (let i = 1; i < points.length; i += 1) {
    ctx.lineTo(points[i][0] * w, points[i][1] * h);
  }
  ctx.stroke();
  ctx.restore();
}

function paintSeg(
  ctx: CanvasRenderingContext2D,
  a: [number, number],
  b: [number, number],
  color: string,
  width: number,
  eraser: boolean,
  w: number,
  h: number,
) {
  paintStroke(ctx, { color, width, eraser, points: [a, b] }, w, h);
}

export function DrawingBoard({ room }: { room: Room | null }) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const wrapRef = useRef<HTMLDivElement>(null);
  const drawingRef = useRef(false);
  const pointsRef = useRef<Array<[number, number]>>([]);
  const strokesRef = useRef<Stroke[]>([]);
  const strokeIdRef = useRef(`s-${Math.random().toString(36).slice(2, 9)}`);
  const lastSendRef = useRef(0);

  const [tool, setTool] = useState<Tool>('pen');
  const [color, setColor] = useState<string>(COLORS[0]);
  const [width, setWidth] = useState(4);
  const toolRef = useRef(tool);
  const colorRef = useRef(color);
  const widthRef = useRef(width);
  toolRef.current = tool;
  colorRef.current = color;
  widthRef.current = width;

  const getCtx = () => {
    const canvas = canvasRef.current;
    if (!canvas) return null;
    const ctx = canvas.getContext('2d');
    if (!ctx) return null;
    return { canvas, ctx, w: canvas.clientWidth, h: canvas.clientHeight };
  };

  const redrawAll = () => {
    const got = getCtx();
    if (!got) return;
    const { ctx, w, h } = got;
    ctx.save();
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, got.canvas.width, got.canvas.height);
    ctx.restore();
    for (const s of strokesRef.current) paintStroke(ctx, s, w, h);
  };

  const resize = () => {
    const canvas = canvasRef.current;
    const wrap = wrapRef.current;
    if (!canvas || !wrap) return;
    const rect = wrap.getBoundingClientRect();
    if (rect.width < 2 || rect.height < 2) return;
    const dpr = window.devicePixelRatio || 1;
    canvas.width = Math.max(1, Math.floor(rect.width * dpr));
    canvas.height = Math.max(1, Math.floor(rect.height * dpr));
    canvas.style.width = `${rect.width}px`;
    canvas.style.height = `${rect.height}px`;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.scale(dpr, dpr);
    redrawAll();
  };

  const publish = (msg: DrawMsg) => {
    if (!room) return;
    void room.localParticipant.publishData(encode(msg), {
      reliable: msg.op !== 'seg',
    }).catch(() => {
      // ignore transient data channel errors
    });
  };

  const applyMsg = (msg: DrawMsg, fromSelf = false) => {
    if (msg.op === 'req') {
      if (!fromSelf && strokesRef.current.length) {
        // chunk sync if large
        const all = strokesRef.current;
        const chunkSize = 8;
        for (let i = 0; i < all.length; i += chunkSize) {
          publish({ t: 'draw', op: 'sync', strokes: all.slice(i, i + chunkSize) });
        }
      }
      return;
    }
    if (msg.op === 'clear') {
      strokesRef.current = [];
      redrawAll();
      return;
    }
    if (msg.op === 'sync') {
      for (const s of msg.strokes) strokesRef.current.push(s);
      redrawAll();
      return;
    }
    if (msg.op === 'stroke') {
      strokesRef.current.push(msg.stroke);
      // redraw so live segs aren't double-painted
      redrawAll();
      return;
    }
    if (msg.op === 'seg') {
      const got = getCtx();
      if (got) paintSeg(got.ctx, msg.a, msg.b, msg.color, msg.width, msg.eraser, got.w, got.h);
    }
  };

  useEffect(() => {
    resize();
    const wrap = wrapRef.current;
    if (!wrap) return;
    const ro = new ResizeObserver(() => resize());
    ro.observe(wrap);
    return () => ro.disconnect();
  }, []);

  useEffect(() => {
    if (!room) return;
    const onData = (payload: Uint8Array) => {
      const msg = decode(payload);
      if (msg) applyMsg(msg);
    };
    room.on(RoomEvent.DataReceived, onData);
    // Ask peers for current drawing when we join / board mounts
    publish({ t: 'draw', op: 'req' });
    return () => {
      room.off(RoomEvent.DataReceived, onData);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [room]);

  const normPoint = (e: PointerEvent<HTMLCanvasElement>): [number, number] => {
    const canvas = canvasRef.current!;
    const rect = canvas.getBoundingClientRect();
    const x = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
    const y = Math.min(1, Math.max(0, (e.clientY - rect.top) / rect.height));
    return [x, y];
  };

  const onPointerDown = (e: PointerEvent<HTMLCanvasElement>) => {
    e.currentTarget.setPointerCapture(e.pointerId);
    drawingRef.current = true;
    strokeIdRef.current = `s-${Date.now().toString(36)}`;
    pointsRef.current = [normPoint(e)];
    lastSendRef.current = 0;
  };

  const onPointerMove = (e: PointerEvent<HTMLCanvasElement>) => {
    if (!drawingRef.current) return;
    const got = getCtx();
    if (!got) return;
    const next = normPoint(e);
    const prev = pointsRef.current[pointsRef.current.length - 1];
    if (!prev) return;
    const dx = next[0] - prev[0];
    const dy = next[1] - prev[1];
    if (dx * dx + dy * dy < 0.0000004) return;
    pointsRef.current.push(next);
    paintSeg(
      got.ctx,
      prev,
      next,
      colorRef.current,
      widthRef.current,
      toolRef.current === 'eraser',
      got.w,
      got.h,
    );
    const now = performance.now();
    if (now - lastSendRef.current > 33) {
      lastSendRef.current = now;
      publish({
        t: 'draw',
        op: 'seg',
        id: strokeIdRef.current,
        color: colorRef.current,
        width: widthRef.current,
        eraser: toolRef.current === 'eraser',
        a: prev,
        b: next,
      });
    }
  };

  const onPointerUp = () => {
    if (!drawingRef.current) return;
    drawingRef.current = false;
    const points = pointsRef.current;
    pointsRef.current = [];
    if (points.length < 2) return;
    // downsample long strokes so reliable packet stays small
    const maxPts = 240;
    let pts = points;
    if (pts.length > maxPts) {
      const step = (pts.length - 1) / (maxPts - 1);
      pts = Array.from({ length: maxPts }, (_, i) => pts[Math.round(i * step)]);
    }
    const stroke: Stroke = {
      color: colorRef.current,
      width: widthRef.current,
      eraser: toolRef.current === 'eraser',
      points: pts,
    };
    strokesRef.current.push(stroke);
    publish({ t: 'draw', op: 'stroke', stroke });
  };

  const clearAll = () => {
    strokesRef.current = [];
    redrawAll();
    publish({ t: 'draw', op: 'clear' });
  };

  return (
    <div className="drawing-board" ref={wrapRef}>
      <canvas
        ref={canvasRef}
        className={`drawing-canvas tool-${tool}`}
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={onPointerUp}
        onPointerCancel={onPointerUp}
      />
      <div className="drawing-toolbar" onPointerDown={(e) => e.stopPropagation()}>
        <button
          type="button"
          className={`tool-icon-btn ${tool === 'pen' ? 'active' : ''}`}
          onClick={() => setTool('pen')}
          title="Карандаш"
          aria-label="Карандаш"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
              d="M15.5 4.5l4 4L8 20H4v-4L15.5 4.5z"
              stroke="currentColor"
              strokeWidth="1.8"
              strokeLinejoin="round"
            />
            <path d="M13.5 6.5l4 4" stroke="currentColor" strokeWidth="1.8" />
          </svg>
        </button>
        <button
          type="button"
          className={`tool-icon-btn ${tool === 'eraser' ? 'active' : ''}`}
          onClick={() => setTool('eraser')}
          title="Ластик"
          aria-label="Ластик"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
              d="M4 16l6.5-6.5 7 7L13 21H6.5L4 18.5V16z"
              stroke="currentColor"
              strokeWidth="1.8"
              strokeLinejoin="round"
            />
            <path
              d="M12 8.5l3.5-3.5 4 4L16 12.5"
              stroke="currentColor"
              strokeWidth="1.8"
              strokeLinejoin="round"
            />
          </svg>
        </button>
        <span className="drawing-sep" />
        {COLORS.map((c) => (
          <button
            key={c}
            type="button"
            className={`swatch ${color === c ? 'active' : ''}`}
            style={{ background: c }}
            aria-label={`Цвет ${c}`}
            onClick={() => {
              setColor(c);
              setTool('pen');
            }}
          />
        ))}
        <label className="drawing-width">
          Толщина
          <input
            type="range"
            min={2}
            max={24}
            value={width}
            onChange={(e) => setWidth(Number(e.target.value))}
          />
        </label>
        <button type="button" className="ghost" onClick={clearAll}>
          Очистить
        </button>
      </div>
    </div>
  );
}
