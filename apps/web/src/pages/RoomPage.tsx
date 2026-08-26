import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import {
  Room,
  RoomEvent,
  Track,
  type LocalVideoTrack,
  type RemoteTrack,
  type RemoteParticipant,
  createLocalVideoTrack,
  createLocalAudioTrack,
} from 'livekit-client';
import { api } from '../lib/api';
import { useAuth } from '../auth/AuthContext';

type VideoTile = {
  key: string;
  label: string;
  isLocal: boolean;
  videoTrack: LocalVideoTrack | RemoteTrack | null;
};

function MediaTile({
  tile,
  active,
  large,
  onSelect,
}: {
  tile: VideoTile;
  active: boolean;
  large?: boolean;
  onSelect?: () => void;
}) {
  const videoRef = useRef<HTMLVideoElement>(null);

  useEffect(() => {
    const el = videoRef.current;
    const track = tile.videoTrack;
    if (!el || !track || track.kind !== Track.Kind.Video) return;
    track.attach(el);
    void el.play().catch(() => {});
    return () => {
      track.detach(el);
    };
  }, [tile.videoTrack, tile.key]);

  const className = `video-tile ${tile.isLocal ? 'local' : ''} ${active ? 'active' : ''} ${
    large ? 'large' : 'thumb'
  }`;

  const media = (
    <>
      <video
        ref={videoRef}
        autoPlay
        muted={tile.isLocal}
        playsInline
        className="tile-video"
      />
      <div className="name-tag">{tile.label}</div>
    </>
  );

  if (large || !onSelect) {
    return <div className={className}>{media}</div>;
  }

  return (
    <button
      type="button"
      className={className}
      onClick={onSelect}
      aria-pressed={active}
      title={`Показать: ${tile.label}`}
    >
      {media}
    </button>
  );
}

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
  const [remoteVideos, setRemoteVideos] = useState<VideoTile[]>([]);
  const [localVideoTrack, setLocalVideoTrack] = useState<LocalVideoTrack | null>(null);
  const [focusKey, setFocusKey] = useState('local');

  const roomRef = useRef<Room | null>(null);

  const canHostJoin = useMemo(() => asHost && Boolean(user), [asHost, user]);
  const canRecord = role === 'host' && Boolean(meetingId);
  const guestInviteUrl = useMemo(() => `${window.location.origin}/r/${slug}`, [slug]);

  const tiles = useMemo<VideoTile[]>(() => {
    const local: VideoTile = {
      key: 'local',
      label: 'Вы',
      isLocal: true,
      videoTrack: localVideoTrack,
    };
    return [local, ...remoteVideos];
  }, [localVideoTrack, remoteVideos]);

  const focusTile = tiles.find((t) => t.key === focusKey) ?? tiles[0];
  const stripTiles = tiles.filter((t) => t.key !== focusTile?.key);

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
      localVideoTrack?.stop();
      void roomRef.current?.disconnect();
      roomRef.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- unmount only
  }, []);

  useEffect(() => {
    if (!tiles.some((t) => t.key === focusKey)) {
      setFocusKey(remoteVideos[0]?.key ?? 'local');
    }
  }, [tiles, focusKey, remoteVideos]);

  const upsertRemoteVideo = (track: RemoteTrack, participant: RemoteParticipant) => {
    if (track.kind !== Track.Kind.Video) return;
    const key = `remote:${participant.identity}`;
    const tile: VideoTile = {
      key,
      label: participant.name || participant.identity,
      isLocal: false,
      videoTrack: track,
    };
    setRemoteVideos((prev) => {
      const next = [...prev.filter((t) => t.key !== key), tile];
      if (prev.length === 0) setFocusKey(key);
      return next;
    });
  };

  const removeRemoteVideo = (participant: RemoteParticipant) => {
    const key = `remote:${participant.identity}`;
    setRemoteVideos((prev) => prev.filter((t) => t.key !== key));
  };

  const attachExistingRemotes = (room: Room) => {
    for (const participant of room.remoteParticipants.values()) {
      for (const publication of participant.trackPublications.values()) {
        const track = publication.track;
        if (track?.kind === Track.Kind.Video) {
          upsertRemoteVideo(track as RemoteTrack, participant);
        }
        if (track?.kind === Track.Kind.Audio) {
          track.attach();
        }
      }
    }
  };

  const connect = async (livekitUrl: string, token: string) => {
    if (!navigator.mediaDevices?.getUserMedia) {
      throw new Error(
        'Камера недоступна: откройте сайт по HTTPS или разрешите незащищённый origin в Chrome.',
      );
    }

    setRemoteVideos([]);
    setFocusKey('local');

    const room = new Room({ adaptiveStream: true, dynacast: true });
    roomRef.current = room;

    room.on(RoomEvent.TrackSubscribed, (track, _pub, participant) => {
      if (track.kind === Track.Kind.Audio) {
        track.attach();
        return;
      }
      if (track.kind === Track.Kind.Video) {
        upsertRemoteVideo(track, participant);
      }
    });
    room.on(RoomEvent.TrackUnsubscribed, (track, _pub, participant) => {
      if (track.kind === Track.Kind.Video) removeRemoteVideo(participant);
    });
    room.on(RoomEvent.ParticipantDisconnected, (participant) => {
      removeRemoteVideo(participant);
    });
    room.on(RoomEvent.Disconnected, () => setStatus('Отключено'));

    await room.connect(livekitUrl, token);
    setStatus(`В комнате · ${room.name}`);

    const videoTrack = await createLocalVideoTrack({
      resolution: { width: 1280, height: 720 },
    });
    const audioTrack = await createLocalAudioTrack();
    setLocalVideoTrack(videoTrack);
    await room.localParticipant.publishTrack(videoTrack);
    await room.localParticipant.publishTrack(audioTrack);

    // Guest joins after host: tracks already in room must be attached now
    attachExistingRemotes(room);
  };

  const leaveRoom = () => {
    localVideoTrack?.stop();
    setLocalVideoTrack(null);
    setRemoteVideos([]);
    setFocusKey('local');
    void roomRef.current?.disconnect();
    roomRef.current = null;
    setJoined(false);
    setRecording(false);
    setRecordingNote('');
    setMeetingId(null);
    setRole(null);
    setLinkCopied(false);
    setStatus('Не в комнате');
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
            : 'Запись сохранена. Скачать в ЛК → /app',
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
            <form className="stack" onSubmit={(e) => void onJoinGuest(e)}>
              <label>
                Ваше имя
                <input value={name} onChange={(e) => setName(e.target.value)} required />
              </label>
              {roomMeta?.hasPassword && (
                <label>
                  Пароль комнаты
                  <input
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    type="password"
                    required
                  />
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
                {linkCopied ? '✓' : '⧉'}
              </button>
            </div>
          )}
          {canRecord && (
            <button
              type="button"
              className={recording ? 'danger' : ''}
              disabled={recordingBusy}
              onClick={() => void toggleRecording()}
            >
              {recordingBusy ? '…' : recording ? '■ Стоп записи' : '● Запись'}
            </button>
          )}
          <button type="button" className="ghost" onClick={leaveRoom}>
            Выйти
          </button>
        </div>
      </header>

      <div className="room-stage">
        {focusTile && <MediaTile tile={focusTile} active large />}
        {stripTiles.length > 0 && (
          <div className="video-strip" role="list">
            {stripTiles.map((tile) => (
              <MediaTile
                key={tile.key}
                tile={tile}
                active={false}
                onSelect={() => setFocusKey(tile.key)}
              />
            ))}
          </div>
        )}
      </div>

      <p className="room-hint muted">
        {recordingNote ||
          (tiles.length > 1
            ? 'Нажмите на миниатюру, чтобы переключить главное видео.'
            : canRecord
              ? 'Запись: LiveKit Egress → MinIO'
              : 'Ожидание второго участника…')}
      </p>
    </div>
  );
}
