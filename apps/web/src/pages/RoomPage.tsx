import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import {
  Room,
  RoomEvent,
  Track,
  type RemoteTrack,
  type RemoteTrackPublication,
  type RemoteParticipant,
  createLocalVideoTrack,
  createLocalAudioTrack,
} from 'livekit-client';
import { api } from '../lib/api';
import { useAuth } from '../auth/AuthContext';

export function RoomPage() {
  const { slug = '' } = useParams();
  const [params] = useSearchParams();
  const asHost = params.get('host') === '1';
  const { user } = useAuth();

  const [roomMeta, setRoomMeta] = useState<{ title: string; hasPassword: boolean } | null>(null);
  const [name, setName] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [joined, setJoined] = useState(false);
  const [busy, setBusy] = useState(false);
  const [recording, setRecording] = useState(false);
  const [status, setStatus] = useState('Не в комнате');

  const roomRef = useRef<Room | null>(null);
  const localVideoRef = useRef<HTMLVideoElement>(null);
  const remoteContainerRef = useRef<HTMLDivElement>(null);

  const canHostJoin = useMemo(() => asHost && Boolean(user), [asHost, user]);

  useEffect(() => {
    void api
      .getRoom(slug)
      .then((r) => setRoomMeta({ title: r.title, hasPassword: r.hasPassword }))
      .catch((err) => setError(err instanceof Error ? err.message : 'Комната не найдена'));
  }, [slug]);

  useEffect(() => {
    return () => {
      void roomRef.current?.disconnect();
      roomRef.current = null;
    };
  }, []);

  const attachRemote = (track: RemoteTrack, participant: RemoteParticipant) => {
    if (track.kind !== Track.Kind.Video && track.kind !== Track.Kind.Audio) return;
    const container = remoteContainerRef.current;
    if (!container) return;
    const id = `${participant.identity}-${track.sid}`;
    let el = container.querySelector(`[data-track-id="${id}"]`) as HTMLMediaElement | null;
    if (!el) {
      el = document.createElement(track.kind === Track.Kind.Video ? 'video' : 'audio');
      el.dataset.trackId = id;
      el.autoplay = true;
      if (el instanceof HTMLVideoElement) {
        el.playsInline = true;
        el.className = 'tile-video';
        const wrap = document.createElement('div');
        wrap.className = 'video-tile';
        wrap.dataset.trackId = id;
        const label = document.createElement('div');
        label.className = 'name-tag';
        label.textContent = participant.name || participant.identity;
        wrap.appendChild(el);
        wrap.appendChild(label);
        container.appendChild(wrap);
      } else {
        container.appendChild(el);
      }
    }
    track.attach(el);
  };

  const detachRemote = (track: RemoteTrack, participant: RemoteParticipant) => {
    const id = `${participant.identity}-${track.sid}`;
    const container = remoteContainerRef.current;
    if (!container) return;
    const wrap = container.querySelector(`[data-track-id="${id}"]`);
    track.detach();
    wrap?.remove();
  };

  const connect = async (livekitUrl: string, token: string) => {
    const room = new Room({ adaptiveStream: true, dynacast: true });
    roomRef.current = room;

    room.on(RoomEvent.TrackSubscribed, (track, _pub: RemoteTrackPublication, participant) => {
      attachRemote(track, participant);
    });
    room.on(RoomEvent.TrackUnsubscribed, (track, _pub, participant) => {
      detachRemote(track, participant);
    });
    room.on(RoomEvent.Disconnected, () => setStatus('Отключено'));

    await room.connect(livekitUrl, token);
    setStatus(`В комнате · ${room.name}`);

    const videoTrack = await createLocalVideoTrack({ resolution: { width: 1280, height: 720 } });
    const audioTrack = await createLocalAudioTrack();
    await room.localParticipant.publishTrack(videoTrack);
    await room.localParticipant.publishTrack(audioTrack);
    if (localVideoRef.current) {
      videoTrack.attach(localVideoRef.current);
    }
  };

  const onJoinGuest = async (e: FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const res = await api.joinGuest(slug, { name, password: password || undefined });
      await connect(res.livekit.url, res.livekit.token);
      setJoined(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Не удалось войти');
    } finally {
      setBusy(false);
    }
  };

  const onJoinHost = async () => {
    setBusy(true);
    setError('');
    try {
      const res = await api.joinHost(slug);
      await connect(res.livekit.url, res.livekit.token);
      setJoined(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Не удалось войти');
    } finally {
      setBusy(false);
    }
  };

  if (!joined) {
    return (
      <div className="auth-page">
        <div className="auth-card">
          <p className="brand">Evoroom</p>
          <h1>{roomMeta?.title || 'Комната'}</h1>
          <p className="muted">/{slug}</p>

          {canHostJoin ? (
            <div className="stack">
              <p>Вы вошли как специалист. Можно открыть комнату ведущим.</p>
              {error && <p className="error">{error}</p>}
              <button type="button" disabled={busy} onClick={() => void onJoinHost()}>
                Войти ведущим (видео + запись дальше)
              </button>
              <Link to="/app">← В ЛК</Link>
            </div>
          ) : (
            <form className="stack" onSubmit={onJoinGuest}>
              <label>
                Ваше имя
                <input value={name} onChange={(e) => setName(e.target.value)} required />
              </label>
              {roomMeta?.hasPassword && (
                <label>
                  Пароль комнаты
                  <input value={password} onChange={(e) => setPassword(e.target.value)} type="password" required />
                </label>
              )}
              {error && <p className="error">{error}</p>}
              <button disabled={busy} type="submit">
                Войти
              </button>
            </form>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="room-shell">
      <header className="topbar">
        <div>
          <strong>{roomMeta?.title}</strong>
          <span className="muted"> · {status}</span>
        </div>
        <div className="topbar-right">
          <button
            type="button"
            className={recording ? 'danger' : ''}
            onClick={() => setRecording((v) => !v)}
            title="Пайплайн Egress подключим следующим шагом"
          >
            {recording ? '■ Стоп записи' : '● Запись'}
          </button>
          <button
            type="button"
            className="ghost"
            onClick={() => {
              void roomRef.current?.disconnect();
              setJoined(false);
            }}
          >
            Выйти
          </button>
        </div>
      </header>
      <div className="room-grid">
        <div className="video-tile local">
          <video ref={localVideoRef} autoPlay muted playsInline className="tile-video" />
          <div className="name-tag">Вы</div>
        </div>
        <div className="remote-grid" ref={remoteContainerRef} />
      </div>
      <p className="room-hint muted">
        Видео через LiveKit. Кнопка записи пока UI-заглушка — серверный Egress подключим сразу после стабилизации комнаты.
      </p>
    </div>
  );
}
