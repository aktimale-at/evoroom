import { useEffect, useState, type FormEvent } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../auth/AuthContext';

type RoomRow = {
  id: string;
  slug: string;
  title: string;
  collabMode: string;
  videoQuality: string;
  maxParticipants: number;
};

type RecordingRow = {
  id: string;
  status: string;
  objectKey: string | null;
  durationSec: number | null;
  startedAt: string | null;
  endedAt: string | null;
  createdAt: string;
  roomTitle: string;
  roomSlug: string;
  roomDeleted?: boolean;
  meetingId: string;
  quality?: string | null;
  sizeBytes?: number | null;
};

function formatDuration(sec: number | null) {
  if (sec == null) return '—';
  const m = Math.floor(sec / 60);
  const s = sec % 60;
  return `${m}:${String(s).padStart(2, '0')}`;
}

function formatWhen(iso: string | null) {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString('ru-RU', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return iso;
  }
}

function formatSize(bytes: number | null | undefined) {
  if (bytes == null || !Number.isFinite(bytes) || bytes < 0) return '—';
  if (bytes < 1024) return `${bytes} Б`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} КБ`;
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} МБ`;
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} ГБ`;
}

function IconTrash() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function IconPlay() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path d="M8 5v14l11-7L8 5z" fill="currentColor" />
    </svg>
  );
}

export function DashboardPage() {
  const { user, loading, logout } = useAuth();
  const [rooms, setRooms] = useState<RoomRow[]>([]);
  const [recordings, setRecordings] = useState<RecordingRow[]>([]);
  const [title, setTitle] = useState('Сессия');
  const [password, setPassword] = useState('');
  const [videoQuality, setVideoQuality] = useState('720p');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [downloadId, setDownloadId] = useState<string | null>(null);
  const [deletingSlug, setDeletingSlug] = useState<string | null>(null);
  const [deleteRoomTarget, setDeleteRoomTarget] = useState<RoomRow | null>(null);
  const [deleteRoomVideos, setDeleteRoomVideos] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [bulkBusy, setBulkBusy] = useState(false);
  const [deletingRecId, setDeletingRecId] = useState<string | null>(null);
  const [preview, setPreview] = useState<{
    id: string;
    title: string;
    url: string;
  } | null>(null);
  const [previewLoadingId, setPreviewLoadingId] = useState<string | null>(null);

  const load = async () => {
    const [roomList, recList] = await Promise.all([api.rooms(), api.listRecordings()]);
    setRooms(roomList);
    setRecordings(recList);
    setSelectedIds((prev) => {
      const next = new Set<string>();
      for (const id of prev) {
        if (recList.some((r) => r.id === id)) next.add(id);
      }
      return next;
    });
  };

  useEffect(() => {
    if (!user) return;
    void load().catch((err) => setError(err instanceof Error ? err.message : 'Ошибка загрузки'));
  }, [user]);

  useEffect(() => {
    return () => {
      if (preview?.url) URL.revokeObjectURL(preview.url);
    };
  }, [preview?.url]);

  if (loading) return <div className="center">Загрузка…</div>;
  if (!user) return <Navigate to="/login" replace />;

  const onCreate = async (e: FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await api.createRoom({
        title,
        password: password || undefined,
        videoQuality,
        collabMode: 'host_control',
      });
      setTitle('Сессия');
      setPassword('');
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Ошибка');
    } finally {
      setBusy(false);
    }
  };

  const onDownload = async (rec: RecordingRow) => {
    setDownloadId(rec.id);
    setError('');
    try {
      await api.downloadRecording(rec.id, `${rec.roomSlug}-${rec.id.slice(-6)}.mp4`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Не удалось скачать');
    } finally {
      setDownloadId(null);
    }
  };

  const closePreview = () => {
    setPreview((cur) => {
      if (cur?.url) URL.revokeObjectURL(cur.url);
      return null;
    });
  };

  const onPreview = async (rec: RecordingRow) => {
    setPreviewLoadingId(rec.id);
    setError('');
    try {
      const { blob } = await api.fetchRecordingBlob(rec.id, 'stream');
      const url = URL.createObjectURL(blob);
      setPreview((cur) => {
        if (cur?.url) URL.revokeObjectURL(cur.url);
        return { id: rec.id, title: rec.roomTitle, url };
      });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Не удалось открыть видео');
    } finally {
      setPreviewLoadingId(null);
    }
  };

  const confirmDeleteRoom = async () => {
    if (!deleteRoomTarget) return;
    const room = deleteRoomTarget;
    setDeletingSlug(room.slug);
    setError('');
    try {
      await api.deleteRoom(room.slug, { deleteVideos: deleteRoomVideos });
      setDeleteRoomTarget(null);
      setDeleteRoomVideos(false);
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Не удалось удалить');
    } finally {
      setDeletingSlug(null);
    }
  };

  const toggleSelected = (id: string) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const allSelected = recordings.length > 0 && selectedIds.size === recordings.length;

  const toggleSelectAll = () => {
    if (allSelected) setSelectedIds(new Set());
    else setSelectedIds(new Set(recordings.map((r) => r.id)));
  };

  const onDeleteRecording = async (rec: RecordingRow) => {
    const ok = window.confirm(`Удалить запись «${rec.roomTitle}»? Файл будет удалён безвозвратно.`);
    if (!ok) return;
    setDeletingRecId(rec.id);
    setError('');
    try {
      await api.deleteRecording(rec.id);
      if (preview?.id === rec.id) closePreview();
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Не удалось удалить запись');
    } finally {
      setDeletingRecId(null);
    }
  };

  const onBulkDelete = async () => {
    const ids = [...selectedIds];
    if (!ids.length) return;
    const ok = window.confirm(
      `Удалить выбранные записи (${ids.length})? Файлы будут удалены безвозвратно.`,
    );
    if (!ok) return;
    setBulkBusy(true);
    setError('');
    try {
      await api.deleteRecordings(ids);
      if (preview && ids.includes(preview.id)) closePreview();
      setSelectedIds(new Set());
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Не удалось удалить записи');
    } finally {
      setBulkBusy(false);
    }
  };

  return (
    <div className="app-shell">
      <header className="topbar">
        <div>
          <strong>Evoroom</strong>
          <span className="muted"> / ЛК специалиста</span>
        </div>
        <div className="topbar-right">
          <span>{user.name}</span>
          <button type="button" className="ghost" onClick={logout}>
            Выйти
          </button>
        </div>
      </header>

      <main className="dashboard">
        <section className="panel">
          <h2>Новая комната</h2>
          <form className="stack" onSubmit={onCreate}>
            <label>
              Название
              <input value={title} onChange={(e) => setTitle(e.target.value)} required />
            </label>
            <label>
              Пароль комнаты (опционально)
              <input value={password} onChange={(e) => setPassword(e.target.value)} />
            </label>
            <label>
              Качество видео
              <select value={videoQuality} onChange={(e) => setVideoQuality(e.target.value)}>
                <option value="720p">720p</option>
                <option value="1080p">1080p</option>
              </select>
            </label>
            {error && <p className="error">{error}</p>}
            <button disabled={busy} type="submit">
              Создать
            </button>
          </form>
        </section>

        <section className="panel grow">
          <h2>Мои комнаты</h2>
          {rooms.length === 0 ? (
            <p className="muted">Пока нет комнат</p>
          ) : (
            <ul className="room-list">
              {rooms.map((room) => (
                <li key={room.id}>
                  <div>
                    <strong>{room.title}</strong>
                    <div className="muted">
                      {room.slug} · {room.videoQuality} · до {room.maxParticipants}
                    </div>
                    <div className="muted">Ссылка клиента: /r/{room.slug}</div>
                  </div>
                  <div className="row-actions">
                    <Link className="button" to={`/r/${room.slug}?host=1`}>
                      Войти как ведущий
                    </Link>
                    <button
                      type="button"
                      className="ghost"
                      onClick={() =>
                        void navigator.clipboard.writeText(`${window.location.origin}/r/${room.slug}`)
                      }
                    >
                      Копировать ссылку
                    </button>
                    <button
                      type="button"
                      className="danger"
                      disabled={deletingSlug === room.slug}
                      onClick={() => {
                        setDeleteRoomVideos(false);
                        setDeleteRoomTarget(room);
                      }}
                    >
                      {deletingSlug === room.slug ? 'Удаление…' : 'Удалить'}
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="panel recordings-panel">
          <div className="recordings-header">
            <h2>Записи</h2>
            {recordings.length > 0 && (
              <div className="recordings-toolbar">
                <label className="check-inline">
                  <input type="checkbox" checked={allSelected} onChange={toggleSelectAll} />
                  Выбрать все
                </label>
                <button
                  type="button"
                  className="danger"
                  disabled={bulkBusy || selectedIds.size === 0}
                  onClick={() => void onBulkDelete()}
                >
                  {bulkBusy ? 'Удаление…' : `Удалить выбранные (${selectedIds.size})`}
                </button>
              </div>
            )}
          </div>
          {recordings.length === 0 ? (
            <p className="muted">
              Пока нет готовых записей. В комнате нажмите «● Запись», затем «■ Стоп» — файл появится
              здесь.
            </p>
          ) : (
            <ul className="recording-list">
              {recordings.map((rec) => (
                <li key={rec.id}>
                  <label className="rec-check">
                    <input
                      type="checkbox"
                      checked={selectedIds.has(rec.id)}
                      onChange={() => toggleSelected(rec.id)}
                    />
                  </label>
                  <div className="rec-meta">
                    <strong>
                      {rec.roomTitle}
                      <span className="rec-notes muted">
                        {' '}
                        · качество {rec.quality || '720p'} · размер {formatSize(rec.sizeBytes)}
                      </span>
                    </strong>
                    <div className="muted">
                      {formatWhen(rec.endedAt || rec.startedAt || rec.createdAt)} ·{' '}
                      {formatDuration(rec.durationSec)} · {rec.roomSlug}
                    </div>
                  </div>
                  <div className="row-actions">
                    <button
                      type="button"
                      className="ghost icon-btn"
                      title="Смотреть"
                      disabled={previewLoadingId === rec.id}
                      onClick={() => void onPreview(rec)}
                    >
                      <IconPlay />
                      <span>{previewLoadingId === rec.id ? 'Загрузка…' : 'Смотреть'}</span>
                    </button>
                    <button
                      type="button"
                      className="button"
                      disabled={downloadId === rec.id}
                      onClick={() => void onDownload(rec)}
                    >
                      {downloadId === rec.id ? 'Скачивание…' : 'Скачать'}
                    </button>
                    <button
                      type="button"
                      className="ghost icon-btn danger-text"
                      title="Удалить запись"
                      disabled={deletingRecId === rec.id}
                      onClick={() => void onDeleteRecording(rec)}
                    >
                      <IconTrash />
                      <span className="sr-only">Удалить</span>
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>
      </main>

      {deleteRoomTarget && (
        <div className="modal-backdrop" role="presentation" onClick={() => setDeleteRoomTarget(null)}>
          <div
            className="modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-room-title"
            onClick={(e) => e.stopPropagation()}
          >
            <h3 id="delete-room-title">Удалить комнату?</h3>
            <p>
              Комната «{deleteRoomTarget.title}» будет скрыта. Ссылка перестанет работать.
            </p>
            <label className="check-inline modal-check">
              <input
                type="checkbox"
                checked={deleteRoomVideos}
                onChange={(e) => setDeleteRoomVideos(e.target.checked)}
              />
              Удалить все видео комнаты
            </label>
            <p className="muted modal-hint">
              {deleteRoomVideos
                ? 'Записи этой комнаты будут удалены из хранилища безвозвратно.'
                : 'По умолчанию записи сохраняются и останутся в списке «Записи».'}
            </p>
            <div className="modal-actions">
              <button type="button" className="ghost" onClick={() => setDeleteRoomTarget(null)}>
                Отмена
              </button>
              <button
                type="button"
                className="danger"
                disabled={deletingSlug === deleteRoomTarget.slug}
                onClick={() => void confirmDeleteRoom()}
              >
                {deletingSlug === deleteRoomTarget.slug ? 'Удаление…' : 'Удалить комнату'}
              </button>
            </div>
          </div>
        </div>
      )}

      {preview && (
        <div className="modal-backdrop" role="presentation" onClick={closePreview}>
          <div
            className="modal preview-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="preview-title"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="preview-header">
              <h3 id="preview-title">{preview.title}</h3>
              <button type="button" className="ghost" onClick={closePreview}>
                Закрыть
              </button>
            </div>
            <video className="preview-video" src={preview.url} controls autoPlay playsInline />
          </div>
        </div>
      )}
    </div>
  );
}
