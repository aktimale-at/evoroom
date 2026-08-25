import { Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './auth/AuthContext';
import { LoginPage } from './pages/LoginPage';
import { RegisterPage } from './pages/RegisterPage';
import { DashboardPage } from './pages/DashboardPage';
import { RoomPage } from './pages/RoomPage';
import './App.css';

function HomePage() {
  return (
    <div className="auth-page">
      <div className="auth-card">
        <p className="brand">Evoroom</p>
        <h1>Психологическая студия</h1>
        <p className="muted">Видео + холст + ЛК. Open source, self-host в РФ.</p>
        <div className="row-actions">
          <a className="button" href="/login">
            ЛК специалиста
          </a>
          <a className="ghost button" href="/register">
            Регистрация
          </a>
        </div>
      </div>
    </div>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <Routes>
        <Route path="/" element={<HomePage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="/app" element={<DashboardPage />} />
        <Route path="/r/:slug" element={<RoomPage />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </AuthProvider>
  );
}
