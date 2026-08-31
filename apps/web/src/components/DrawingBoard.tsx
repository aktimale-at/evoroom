import { useEffect, useRef, useState, type PointerEvent } from 'react';
import { Room, RoomEvent } from 'livekit-client';

type Tool = 'pen' | 'eraser';

type DrawMsg =
  | {
      t: 'draw';
      op: 'stroke';
      color: string;
      width: number;
      eraser: boolean;
      points: Array<[number, number]>;
    }
  | { t: 'draw'; op: 'clear' };

const COLORS = ['#1a1a22', '#b91c1c', '#1d4ed8', '#15803d', '#c2410c'] as const;

function encode(msg: DrawMsg): Uint8Array {
  return new TextEncoder().encode(JSON.stringify(msg));
}

function decode(data: Uint8Array): DrawMsg | null {
  try {
    const raw = JSON.parse(new TextDecoder().decode(data)) as DrawMsg;
    if (raw?.t !== 'draw') return null;
    return raw;
  } catch {
    return null;
  }
}

function drawStroke(
  ctx: CanvasRenderingContext2D,
  points: Array<[number, number]>,
  color: string,
  width: number,
  eraser: boolean,
  w: number,
  h: number,
) {
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

export function DrawingBoard({ room }: { room: Room | null }) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const wrapRef = useRef<HTMLDivElement>(null);
  const drawingRef = useRef(false);
  const pointsRef = useRef<Array<[number, number]>>([]);
  const [tool, setTool] = useState<Tool>('pen');
  const [color, setColor] = useState<string>(COLORS[0]);
  const [width, setWidth] = useState(4);
  const toolRef = useRef(tool);
  const colorRef = useRef(color);
  const widthRef = useRef(width);
  toolRef.current = tool;
  colorRef.current = color;
  widthRef.current = width;

  const resize = () => {
    const canvas = canvasRef.current;
    const wrap = wrapRef.current;
    if (!canvas || !wrap) return;
    const rect = wrap.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    const prev = document.createElement('canvas');
    prev.width = canvas.width;
    prev.height = canvas.height;
    const pctx = prev.getContext('2d');
    if (pctx && canvas.width > 0) pctx.drawImage(canvas, 0, 0);

    canvas.width = Math.max(1, Math.floor(rect.width * dpr));
    canvas.height = Math.max(1, Math.floor(rect.height * dpr));
    canvas.style.width = `${rect.width}px`;
    canvas.style.height = `${rect.height}px`;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.scale(dpr, dpr);
    if (pctx && prev.width > 0) {
      ctx.drawImage(prev, 0, 0, rect.width, rect.height);
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

  const publish = (msg: DrawMsg) => {
    if (!room) return;
    void room.localParticipant.publishData(encode(msg), { reliable: true });
  };

  const applyRemote = (msg: DrawMsg) => {
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext('2d');
    if (!canvas || !ctx) return;
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    if (msg.op === 'clear') {
      ctx.clearRect(0, 0, w, h);
      return;
    }
    drawStroke(ctx, msg.points, msg.color, msg.width, msg.eraser, w, h);
  };

  useEffect(() => {
    if (!room) return;
    const onData = (payload: Uint8Array) => {
      const msg = decode(payload);
      if (msg) applyRemote(msg);
    };
    room.on(RoomEvent.DataReceived, onData);
    return () => {
      room.off(RoomEvent.DataReceived, onData);
    };
  }, [room]);

  const normPoint = (e: PointerEvent<HTMLCanvasElement>): [number, number] => {
    const canvas = canvasRef.current!;
    const rect = canvas.getBoundingClientRect();
    return [(e.clientX - rect.left) / rect.width, (e.clientY - rect.top) / rect.height];
  };

  const onPointerDown = (e: PointerEvent<HTMLCanvasElement>) => {
    e.currentTarget.setPointerCapture(e.pointerId);
    drawingRef.current = true;
    pointsRef.current = [normPoint(e)];
  };

  const onPointerMove = (e: PointerEvent<HTMLCanvasElement>) => {
    if (!drawingRef.current) return;
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext('2d');
    if (!canvas || !ctx) return;
    const next = normPoint(e);
    const prev = pointsRef.current[pointsRef.current.length - 1];
    pointsRef.current.push(next);
    drawStroke(
      ctx,
      [prev, next],
      colorRef.current,
      widthRef.current,
      toolRef.current === 'eraser',
      canvas.clientWidth,
      canvas.clientHeight,
    );
  };

  const onPointerUp = () => {
    if (!drawingRef.current) return;
    drawingRef.current = false;
    const points = pointsRef.current;
    pointsRef.current = [];
    if (points.length < 2) return;
    publish({
      t: 'draw',
      op: 'stroke',
      color: colorRef.current,
      width: widthRef.current,
      eraser: toolRef.current === 'eraser',
      points,
    });
  };

  const clearAll = () => {
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext('2d');
    if (canvas && ctx) ctx.clearRect(0, 0, canvas.clientWidth, canvas.clientHeight);
    publish({ t: 'draw', op: 'clear' });
  };

  return (
    <div className="drawing-board" ref={wrapRef}>
      <canvas
        ref={canvasRef}
        className="drawing-canvas"
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={onPointerUp}
        onPointerCancel={onPointerUp}
      />
      <div className="drawing-toolbar" onPointerDown={(e) => e.stopPropagation()}>
        <button
          type="button"
          className={tool === 'pen' ? 'active' : ''}
          onClick={() => setTool('pen')}
        >
          Карандаш
        </button>
        <button
          type="button"
          className={tool === 'eraser' ? 'active' : ''}
          onClick={() => setTool('eraser')}
        >
          Ластик
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
