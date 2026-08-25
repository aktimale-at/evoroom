import { useState, type FormEvent } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../auth/AuthContext';

export function RegisterPage() {
  const { user, setSession } = useAuth();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  if (user) return <Navigate to="/app" replace />;

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const res = await api.register({ name, email, password });
      setSession(res.accessToken, res.user);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Ошибка');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="auth-page">
      <div className="auth-card">
        <p className="brand">Evoroom</p>
        <h1>Регистрация ЛК</h1>
        <form onSubmit={onSubmit} className="stack">
          <label>
            Имя
            <input value={name} onChange={(e) => setName(e.target.value)} required minLength={2} />
          </label>
          <label>
            Email
            <input value={email} onChange={(e) => setEmail(e.target.value)} type="email" required />
          </label>
          <label>
            Пароль
            <input value={password} onChange={(e) => setPassword(e.target.value)} type="password" required minLength={6} />
          </label>
          {error && <p className="error">{error}</p>}
          <button disabled={busy} type="submit">
            Создать аккаунт
          </button>
        </form>
        <p className="muted">
          Уже есть аккаунт? <Link to="/login">Войти</Link>
        </p>
      </div>
    </div>
  );
}
