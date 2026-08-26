import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import {
  Room,
  RoomEvent,
  Track,
  type LocalVideoTrack,
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
  const [recordingBusy, setRecordingBusy] = useState(false);
  const [recordingNote, setRecordingNote] = useState('');
  const [meetingId, setMeetingId] = useState<string | null>(null);
  const [role, setRole] = useState<'host' | 'participant' | null>(null);
  const [status, setStatus] = useState('Не в комнате');
  const [linkCopied, setLinkCopied] = useState(false);

  const roomRef = useRef<Room | null>(null);
  const localVideoRef = useRef<HTMLVideoElement>(null);
  const localVideoTrackRef = useRef<LocalVideoTrack | null>(null);
  const remoteContainerRef = useRef<HTMLDivElement>(null);

  const canHostJoin = useMemo(() => asHost && Boolean(user), [asHost, user]);
  const canRecord = role === 'host' && Boolean(meetingId);
  const guestInviteUrl = useMemo(
    () => `${window.location.origin}/r/${slug}`,
    [slug],
  );

  const copyGuestLink = async () => {
    try {
      await navigator.clipboard.writeText(guestInviteUrl);
      setLinkCopied(true);
      window.setTimeout(() => setLinkCopied(false), 2000);
    } catch {
      setRecordingNote('Не удалось скопировать ссылку');
    }
  };

  useEffect(() => {
    void api
      .getRoom(slug)
      .then((r) => setRoomMeta({ title: r.title, hasPassword: r.hasPassword }))
      .catch((err) => setError(err instanceof Error ? err.message : 'Комната не найдена'));
  }, [slug]);

  useEffect(() => {
    return () => {
      localVideoTrackRef.current?.stop();
      localVideoTrackRef.current = null;
      void roomRef.current?.disconnect();
      roomRef.current = null;
    };
  }, []);

  // После появления <video> в DOM — привязать локальный трек
  useEffect(() => {
    const el = localVideoRef.current;
    const track = localVideoTrackRef.current;
    if (!joined || !el || !track) return;
    track.attach(el);
    void el.play().catch(() => {});
    return () => {
      track.detach(el);
    };
  }, [joined]);

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
        el.muted = false;
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
    wrap?.parentElement?.remove();
    wrap?.remove();
  };

  const connect = async (livekitUrl: string, token: string) => {
    if (!navigator.mediaDevices?.getUserMedia) {
      throw new Error(
        'Камера недоступна: откройте сайт по HTTPS или разрешите незащищённый origin в Chrome.',
      );
    }

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

    const videoTrack = await createLocalVideoTrack({
      resolution: { width: 1280, height: 720 },
    });
    const audioTrack = await createLocalAudioTrack();
    localVideoTrackRef.current = videoTrack;
    await room.localParticipant.publishTrack(videoTrack);
    await room.localParticipant.publishTrack(audioTrack);
  };

  const onJoinGuest = async (e: FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const res = await api.joinGuest(slug, { name, password: password || undefined });
      setMeetingId(res.meetingId);
      setRole(res.role === 'host' ? 'host' : 'participant');
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
      setMeetingId(res.meetingId);
      setRole('host');
      await connect(res.livekit.url, res.livekit.token);
      setJoined(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Не удалось войти');
    } finally {
      setBusy(false);
    }
  };

  const toggleRecording = async () => {
    if (!meetingId || !canRecord || recordingBusy) return;
    setRecordingBusy(true);
    setRecordingNote('');
    try {
      if (recording) {
        const res = await api.stopRecording(slug, meetingId);
        setRecording(false);
        const mins = res.durationSec != null ? Math.round(res.durationSec / 60) : null;
        setRecordingNote(
          mins != null
            ? `Запись сохранена (~${mins} мин). Скачать в ЛК → /app`
            : `Запись сохранена. Скачать в ЛК → /app`,
        );
      } else {
        await api.startRecording(slug, meetingId);
        setRecording(true);
        setRecordingNote('Идёт запись (Room Composite → MinIO)');
      }
    } catch (err) {
      setRecordingNote(err instanceof Error ? err.message : 'Ошибка записи');
    } finally {
      setRecordingBusy(false);
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
                Войти ведущим
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
          {role === 'host' && (
            <div className="invite-link" title={guestInviteUrl}>
              <span className="invite-label">Ссылка для участников</span>
              <code className="invite-url">{guestInviteUrl}</code>
              <button
                type="button"
                className="invite-copy"
                onClick={() => void copyGuestLink()}
                aria-label="Копировать ссылку"
                title={linkCopied ? 'Скопировано' : 'Копировать'}
              >
                {linkCopied ? (
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
                    <path
                      d="M5 13l4 4L19 7"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    />
                  </svg>
                ) : (
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
                    <rect
                      x="9"
                      y="9"
                      width="11"
                      height="11"
                      rx="2"
                      stroke="currentColor"
                      strokeWidth="2"
                    />
                    <path
                      d="M5 15V5a2 2 0 0 1 2-2h10"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                    />
                  </svg>
                )}
              </button>
            </div>
          )}
          {canRecord && (
            <button
              type="button"
              className={recording ? 'danger' : ''}
              disabled={recordingBusy}
              onClick={() => void toggleRecording()}
              title="Room Composite Egress → MinIO"
            >
              {recordingBusy ? '…' : recording ? '■ Стоп записи' : '● Запись'}
            </button>
          )}
          <button
            type="button"
            className="ghost"
            onClick={() => {
              localVideoTrackRef.current?.stop();
              localVideoTrackRef.current = null;
              void roomRef.current?.disconnect();
              setJoined(false);
              setRecording(false);
              setRecordingNote('');
              setMeetingId(null);
              setRole(null);
              setLinkCopied(false);
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
        {recordingNote ||
          (canRecord
            ? 'Запись: LiveKit Egress (grid) → бакет MinIO evoroom/recordings/…'
            : 'Видео через LiveKit. Запись доступна ведущему.')}
      </p>
    </div>
  );
}
