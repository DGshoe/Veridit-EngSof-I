import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from './AuthContext';

/** Protects internal pages: without a session, go to the login page. */
export function RequireAuth({ children }) {
  const { user, loading } = useAuth();
  const location = useLocation();

  if (loading) {
    return <p className="auth-loading">Carregando…</p>;
  }

  if (!user) {
    return <Navigate to="/auth/login" replace state={{ from: location.pathname }} />;
  }

  return children;
}

/** Auth pages (login, register...): a logged-in user goes straight inside. */
export function GuestOnly({ children }) {
  const { user, loading } = useAuth();

  if (loading) return <p className="auth-loading">Carregando…</p>;
  if (user) return <Navigate to="/packages" replace />;

  return children;
}
