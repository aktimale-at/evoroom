import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import {
  Room,
  RoomEvent,
  Track,
  VideoPresets,
  VideoQuality,
  type LocalTrack,
  type LocalVideoTrack,
  type RemoteTrack,
  type RemoteParticipant,
  type RemoteTrackPublication,
  createLocalVideoTrack,
  createLocalAudioTrack,
} from 'livekit-client';
import { api } from '../lib/api';
import { useAuth } from '../auth/AuthContext';
import { DrawingBoard } from '../components/DrawingBoard';

type VideoTile = {
  key: string;
  label: string;
  isLocal: boolean;
  videoTrack: LocalVideoTrack | RemoteTrack | null;
  camOff?: boolean;
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
  const trackMuted = Boolean(tile.videoTrack?.isMuted);
  const showPlaceholder = tile.camOff || trackMuted || !tile.videoTrack;

  useEffect(() => {
    const el = videoRef.current;
    const track = tile.videoTrack;
    if (!el || !track || track.kind !== Track.Kind.Video || showPlaceholder) return;
    track.attach(el);
    void el.play().catch(() => {});
    return () => {
      track.detach(el);
    };
  }, [tile.videoTrack, tile.key, showPlaceholder]);

  const className = `video-tile ${tile.isLocal ? 'local' : ''} ${active ? 'active' : ''} ${
    large ? 'large' : 'thumb'
  } ${showPlaceholder ? 'cam-off' : ''}`;

  const media = (
    <>
      {showPlaceholder ? (
        <div className="tile-placeholder">{tile.isLocal ? 'Камера выкл.' : 'Нет видео'}</div>
      ) : (
        <video
          ref={videoRef}
          autoPlay
          muted={tile.isLocal}
          playsInline
          className="tile-video"
        />
      )}
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

function ScreenSurface({
  track,
  label,
}: {
  track: LocalVideoTrack | RemoteTrack | null;
  label: string;
}) {
  const videoRef = useRef<HTMLVideoElement>(null);

  useEffect(() => {
    const el = videoRef.current;
    if (!el || !track) return;
    track.attach(el);
    void el.play().catch(() => {});
    return () => {
      track.detach(el);
    };
  }, [track]);

  if (!track) return null;

  return (
    <div className="screen-surface">
      <video ref={videoRef} autoPlay playsInline className="screen-video" />
      <div className="screen-label">{label}</div>
    </div>
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
  const [viewMode, setViewMode] = useState<'video' | 'canvas'>('video');
  const [micOn, setMicOn] = useState(true);
  const [camOn, setCamOn] = useState(true);
  const [screenBusy, setScreenBusy] = useState(false);
  const [screenTrack, setScreenTrack] = useState<LocalVideoTrack | RemoteTrack | null>(null);
  const [screenLabel, setScreenLabel] = useState('Экран');
  const [lkRoom, setLkRoom] = useState<Room | null>(null);

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
      camOff: !camOn,
    };
    return [local, ...remoteVideos];
  }, [localVideoTrack, remoteVideos, camOn]);

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

  const preferHighQuality = (publication?: RemoteTrackPublication) => {
    if (!publication || publication.kind !== Track.Kind.Video) return;
    try {
      publication.setVideoQuality(VideoQuality.HIGH);
    } catch {
      // ignore
    }
  };

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

  const clearScreenIfMatch = (track: LocalTrack | RemoteTrack) => {
    setScreenTrack((cur) => {
      if (cur && cur.sid === track.sid) {
        return null;
      }
      return cur;
    });
  };

  const attachExistingRemotes = (room: Room) => {
    for (const participant of room.remoteParticipants.values()) {
      for (const publication of participant.trackPublications.values()) {
        const track = publication.track;
        if (!track) continue;
        if (track.kind === Track.Kind.Audio) {
          track.attach();
          continue;
        }
        if (track.kind !== Track.Kind.Video) continue;
        if (publication.source === Track.Source.ScreenShare) {
          preferHighQuality(publication as RemoteTrackPublication);
          setScreenTrack(track as RemoteTrack);
          setScreenLabel(`${participant.name || participant.identity} · экран`);
          setViewMode('canvas');
        } else {
          preferHighQuality(publication as RemoteTrackPublication);
          upsertRemoteVideo(track as RemoteTrack, participant);
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
    setMicOn(true);
    setCamOn(true);
    setScreenTrack(null);
    setLkRoom(null);

    const room = new Room({
      adaptiveStream: false,
      dynacast: true,
      publishDefaults: {
        videoCodec: 'h264',
        videoEncoding: {
          maxBitrate: 2_500_000,
          maxFramerate: 30,
        },
        simulcast: true,
        videoSimulcastLayers: [VideoPresets.h180, VideoPresets.h360],
        degradationPreference: 'maintain-resolution',
      },
    });
    roomRef.current = room;
    setLkRoom(room);

    room.on(RoomEvent.TrackSubscribed, (track, publication, participant) => {
      if (track.kind === Track.Kind.Audio) {
        track.attach();
        return;
      }
      if (track.kind !== Track.Kind.Video) return;
      if (publication.source === Track.Source.ScreenShare) {
        preferHighQuality(publication);
        setScreenTrack(track);
        setScreenLabel(`${participant.name || participant.identity} · экран`);
        setViewMode('canvas');
        return;
      }
      preferHighQuality(publication);
      upsertRemoteVideo(track, participant);
    });
    room.on(RoomEvent.TrackUnsubscribed, (track, publication, participant) => {
      if (publication.source === Track.Source.ScreenShare) {
        clearScreenIfMatch(track);
        return;
      }
      if (track.kind === Track.Kind.Video) removeRemoteVideo(participant);
    });
    room.on(RoomEvent.LocalTrackPublished, (publication) => {
      if (publication.source !== Track.Source.ScreenShare || !publication.track) return;
      const track = publication.track as LocalVideoTrack;
      setScreenTrack(track);
      setScreenLabel('Ваш экран');
      setViewMode('canvas');
    });
    room.on(RoomEvent.LocalTrackUnpublished, (publication) => {
      if (publication.source === Track.Source.ScreenShare && publication.track) {
        clearScreenIfMatch(publication.track);
      }
      if (publication.source === Track.Source.Camera) {
        setLocalVideoTrack(null);
      }
    });
    room.on(RoomEvent.ParticipantDisconnected, (participant) => {
      removeRemoteVideo(participant);
    });
    room.on(RoomEvent.Disconnected, () => setStatus('Отключено'));

    await room.connect(livekitUrl, token);
    setStatus(`В комнате · ${room.name}`);

    const videoTrack = await createLocalVideoTrack({
      resolution: {
        width: VideoPresets.h720.width,
        height: VideoPresets.h720.height,
        frameRate: 30,
      },
      facingMode: 'user',
    });
    const audioTrack = await createLocalAudioTrack({
      echoCancellation: true,
      noiseSuppression: true,
      autoGainControl: true,
    });
    setLocalVideoTrack(videoTrack);
    await room.localParticipant.publishTrack(videoTrack, {
      source: Track.Source.Camera,
      videoCodec: 'h264',
      videoEncoding: {
        maxBitrate: 2_500_000,
        maxFramerate: 30,
      },
    });
    await room.localParticipant.publishTrack(audioTrack);

    attachExistingRemotes(room);
  };

  const leaveRoom = () => {
    localVideoTrack?.stop();
    setLocalVideoTrack(null);
    setRemoteVideos([]);
    setFocusKey('local');
    setViewMode('video');
    setMicOn(true);
    setCamOn(true);
    setScreenTrack(null);
    void roomRef.current?.disconnect();
    roomRef.current = null;
    setLkRoom(null);
    setJoined(false);
    setRecording(false);
    setRecordingNote('');
    setMeetingId(null);
    setRole(null);
    setLinkCopied(false);
    setStatus('Не в комнате');
  };

  const toggleMic = async () => {
    const room = roomRef.current;
    if (!room) return;
    const next = !micOn;
    try {
      await room.localParticipant.setMicrophoneEnabled(next);
      setMicOn(next);
    } catch (err) {
      setRecordingNote(err instanceof Error ? err.message : 'Не удалось переключить микрофон');
    }
  };

  const toggleCam = async () => {
    const room = roomRef.current;
    if (!room) return;
    const next = !camOn;
    try {
      await room.localParticipant.setCameraEnabled(next);
      setCamOn(next);
      if (next) {
        const pub = room.localParticipant.getTrackPublication(Track.Source.Camera);
        if (pub?.track) setLocalVideoTrack(pub.track as LocalVideoTrack);
      }
    } catch (err) {
      setRecordingNote(err instanceof Error ? err.message : 'Не удалось переключить камеру');
    }
  };

  const toggleScreen = async () => {
    const room = roomRef.current;
    if (!room || screenBusy) return;
    setScreenBusy(true);
    setRecordingNote('');
    try {
      const localPub = room.localParticipant.getTrackPublication(Track.Source.ScreenShare);
      if (localPub) {
        await room.localParticipant.setScreenShareEnabled(false);
        setScreenTrack(null);
      } else {
        await room.localParticipant.setScreenShareEnabled(true, { audio: false });
        setViewMode('canvas');
      }
    } catch (err) {
      setRecordingNote(
        err instanceof Error
          ? err.message
          : 'Не удалось показать экран (нужен HTTPS и разрешение браузера)',
      );
    } finally {
      setScreenBusy(false);
    }
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

  const sharingLocally = Boolean(
    roomRef.current?.localParticipant.getTrackPublication(Track.Source.ScreenShare),
  );

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

      <div className="room-main">
        <div className={`room-stage ${viewMode === 'video' ? 'is-visible' : 'is-hidden'}`}>
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

        <div className={`canvas-workspace ${viewMode === 'canvas' ? 'is-visible' : 'is-hidden'}`}>
          <div className="canvas-surface">
            {screenTrack ? (
              <ScreenSurface track={screenTrack} label={screenLabel} />
            ) : (
              <div className="canvas-empty">
                <div className="canvas-empty-mark" aria-hidden />
                <h2>Холст</h2>
                <p>
                  Рисуйте поверх поля или включите «Экран». Штрихи синхронизируются с участниками.
                </p>
              </div>
            )}
            <DrawingBoard room={lkRoom} />
          </div>
          <div className="pip-stack" aria-label="Видео участников">
            {tiles.map((tile) => (
              <MediaTile
                key={`pip-${tile.key}`}
                tile={tile}
                active={tile.key === focusKey}
                onSelect={() => {
                  setFocusKey(tile.key);
                  setViewMode('video');
                }}
              />
            ))}
          </div>
        </div>
      </div>

      <div className="room-controls">
        <div className="media-controls" aria-label="Медиа">
          <button
            type="button"
            className={`media-btn ${micOn ? '' : 'off'}`}
            onClick={() => void toggleMic()}
            title={micOn ? 'Выключить микрофон' : 'Включить микрофон'}
          >
            {micOn ? 'Микрофон' : 'Микрофон выкл.'}
          </button>
          <button
            type="button"
            className={`media-btn ${camOn ? '' : 'off'}`}
            onClick={() => void toggleCam()}
            title={camOn ? 'Выключить камеру' : 'Включить камеру'}
          >
            {camOn ? 'Камера' : 'Камера выкл.'}
          </button>
          <button
            type="button"
            className={`media-btn ${sharingLocally ? 'active' : ''}`}
            disabled={screenBusy}
            onClick={() => void toggleScreen()}
            title={sharingLocally ? 'Остановить показ экрана' : 'Показать экран'}
          >
            {screenBusy ? '…' : sharingLocally ? 'Стоп экран' : 'Экран'}
          </button>
        </div>

        <nav className="room-dock" aria-label="Режим комнаты">
          <button
            type="button"
            className={viewMode === 'video' ? 'active' : ''}
            onClick={() => setViewMode('video')}
          >
            Видео
          </button>
          <button
            type="button"
            className={viewMode === 'canvas' ? 'active' : ''}
            onClick={() => setViewMode('canvas')}
          >
            Холст
          </button>
        </nav>
      </div>

      <p className="room-hint muted">
        {recordingNote ||
          (viewMode === 'canvas'
            ? screenTrack
              ? 'Экран на холсте. Можно рисовать поверх. PiP справа — участники.'
              : 'Рисуйте на холсте — штрихи видны всем. Или нажмите «Экран».'
            : tiles.length > 1
              ? 'Нажмите на миниатюру, чтобы переключить главное видео.'
              : canRecord
                ? 'Запись: LiveKit Egress → MinIO'
                : 'Ожидание второго участника…')}
      </p>
    </div>
  );
}
