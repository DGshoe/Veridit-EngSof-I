export function Header() {
  return (
    <header className="header-container">
      <div className="header-logo">
        veridt.
      </div>
      
      <nav className="header-nav">
        <a href="#registros">Meus registros</a>
        <a href="#creditos">Comprar créditos</a>
        <a href="#confirmacoes">Confirmações</a>
      </nav>

      <div className="header-user">
        <span className="user-credits">12 créditos</span>
        <button className="btn-sair">Sair</button>
      </div>
    </header>
  );
}
