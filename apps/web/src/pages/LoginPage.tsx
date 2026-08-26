import { useState, type FormEvent } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../auth/AuthContext';

export function LoginPage() {
  const { user, setSession } = useAuth();
  const [mode, setMode] = useState<'password' | 'code'>('password');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [code, setCode] = useState('');
  const [codeSent, setCodeSent] = useState(false);
  const [devCode, setDevCode] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  if (user) return <Navigate to="/app" replace />;

  const onPasswordLogin = async (e: FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const res = await api.login({ email, password });
      setSession(res.accessToken, res.user);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Ошибка входа');
    } finally {
      setBusy(false);
    }
  };

  const onRequestCode = async (e: FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const res = await api.requestCode(email);
      setCodeSent(true);
      setDevCode(res.devCode || '');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Ошибка');
    } finally {
      setBusy(false);
    }
  };

  const onVerifyCode = async (e: FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const res = await api.verifyCode({ email, code });
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
        <h1>Вход специалиста</h1>
        <div className="tabs">
          <button type="button" className={mode === 'password' ? 'active' : ''} onClick={() => setMode('password')}>
            Email / пароль
          </button>
          <button type="button" className={mode === 'code' ? 'active' : ''} onClick={() => setMode('code')}>
            Код на почту
          </button>
        </div>

        {mode === 'password' ? (
          <form onSubmit={onPasswordLogin} className="stack">
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
              Войти
            </button>
          </form>
        ) : (
          <form onSubmit={codeSent ? onVerifyCode : onRequestCode} className="stack">
            <label>
              Email
              <input value={email} onChange={(e) => setEmail(e.target.value)} type="email" required />
            </label>
            {codeSent && (
              <label>
                Код из письма {devCode ? `(dev: ${devCode})` : ''}
                <input value={code} onChange={(e) => setCode(e.target.value)} inputMode="numeric" required minLength={6} maxLength={6} />
              </label>
            )}
            {error && <p className="error">{error}</p>}
            {codeSent && (
              <p className="muted">
                Код отправлен на почту{devCode ? ` (dev: ${devCode})` : ''}. Действует 10 минут.
              </p>
            )}
            <button disabled={busy} type="submit">
              {codeSent ? 'Войти по коду' : 'Отправить код'}
            </button>
            {codeSent && (
              <button
                type="button"
                className="ghost"
                disabled={busy}
                onClick={() => void onRequestCode({ preventDefault() {} } as FormEvent)}
              >
                Отправить код ещё раз
              </button>
            )}
          </form>
        )}

        <p className="muted">
          Нет аккаунта? <Link to="/register">Регистрация</Link>
          {' · '}
          <Link to="/">На главную</Link>
        </p>
      </div>
    </div>
  );
}
