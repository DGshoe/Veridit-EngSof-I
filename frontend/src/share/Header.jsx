import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { getBalance, WALLET_CHANGED_EVENT } from '../api/commerce';
import "./Header.css"

/** Balance from the Commerce Service, refreshed whenever a purchase is confirmed. */
function useBalance(enabled) {
  const [balance, setBalance] = useState(null);

  useEffect(() => {
    if (!enabled) return undefined;
    let active = true;
    const load = () => getBalance().then((value) => active && setBalance(value)).catch(() => active && setBalance(null));

    load();
    window.addEventListener(WALLET_CHANGED_EVENT, load);
    return () => {
      active = false;
      window.removeEventListener(WALLET_CHANGED_EVENT, load);
    };
  }, [enabled]);

  return balance;
}

export function Header() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const balance = useBalance(Boolean(user));

  // REQ04 — POST /api/v1/auth/logout (invalidates the token on the server)
  async function handleLogout() {
    await logout().catch(() => {});
    navigate('/auth/login', { replace: true });
  }

  return (
    <header className="header-container">
      <div className="header-logo">
        veridt.
      </div>

      {user && (
        <nav className="header-nav">
          <Link to="/details">Meus registros</Link>
          <Link to="/packages">Comprar créditos</Link>
          <Link to="/confirmation">Confirmações</Link>
        </nav>
      )}

      {user && (
        <div className="header-user">
          <span className="user-name" title={user.email}>{user.name}</span>
          <Link to="/packages" className="user-credits" title="Comprar créditos">
            {balance === null ? '— créditos' : `${balance} ${balance === 1 ? 'crédito' : 'créditos'}`}
          </Link>
          <button className="btn-sair" onClick={handleLogout}>Sair</button>
        </div>
      )}
    </header>
  );
}
