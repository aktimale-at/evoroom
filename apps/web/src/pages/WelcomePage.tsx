import { Link, Navigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';

export function WelcomePage() {
  const { user, loading } = useAuth();

  if (loading) {
    return <div className="center muted">Загрузка…</div>;
  }
  if (user) {
    return <Navigate to="/app" replace />;
  }

  return (
    <div className="welcome">
      <div className="welcome-glow" aria-hidden />
      <div className="welcome-grain" aria-hidden />

      <header className="welcome-top">
        <span className="welcome-logo">Evoroom</span>
        <Link className="welcome-login-link" to="/login">
          Войти
        </Link>
      </header>

      <main className="welcome-hero">
        <p className="welcome-brand">Evoroom</p>
        <h1 className="welcome-title">Пространство для живой сессии</h1>
        <p className="welcome-lead">
          Встречи, где важны контакт, внимание и общая среда.
        </p>
        <div className="welcome-cta">
          <Link className="welcome-btn primary" to="/login">
            Войти
          </Link>
          <Link className="welcome-btn ghost" to="/register">
            Создать аккаунт
          </Link>
        </div>
      </main>

      <footer className="welcome-foot">
        <span>Для специалистов, которые работают онлайн</span>
      </footer>
    </div>
  );
}
