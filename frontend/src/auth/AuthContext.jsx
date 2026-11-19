import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import * as identity from '../api/identity';
import { SESSION_EXPIRED_EVENT } from '../api/client';

const AuthContext = createContext(null);

/**
 * Session state shared by the whole app:
 *   user     logged-in user (or null)
 *   loading  true while the saved token is being validated on startup
 */
export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(identity.hasToken());

  // Startup: if a token was saved, confirm it is still valid (GET /me).
  useEffect(() => {
    if (!identity.hasToken()) return;
    identity.me()
      .then(setUser)
      .catch(() => setUser(null))
      .finally(() => setLoading(false));
  }, []);

  // Token expired and could not be refreshed: drop the session.
  useEffect(() => {
    const onExpired = () => setUser(null);
    window.addEventListener(SESSION_EXPIRED_EVENT, onExpired);
    return () => window.removeEventListener(SESSION_EXPIRED_EVENT, onExpired);
  }, []);

  const login = useCallback(async (email, password) => {
    const loggedUser = await identity.login(email, password);
    setUser(loggedUser);
    return loggedUser;
  }, []);

  const logout = useCallback(async () => {
    try {
      await identity.logout();
    } finally {
      setUser(null);
    }
  }, []);

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
