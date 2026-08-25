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

export function DashboardPage() {
  const { user, loading, logout } = useAuth();
  const [rooms, setRooms] = useState<RoomRow[]>([]);
  const [title, setTitle] = useState('Сессия');
  const [password, setPassword] = useState('');
  const [videoQuality, setVideoQuality] = useState('720p');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const load = async () => {
    const list = await api.rooms();
    setRooms(list);
  };

  useEffect(() => {
    if (!user) return;
    void load().catch((err) => setError(err instanceof Error ? err.message : 'Ошибка загрузки'));
  }, [user]);

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
                      onClick={() => navigator.clipboard.writeText(`${window.location.origin}/r/${room.slug}`)}
                    >
                      Копировать ссылку
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>
      </main>
    </div>
  );
}
