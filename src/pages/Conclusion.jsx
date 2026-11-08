import { Link } from 'react-router-dom';
import { Header } from '../share/Header';
import { Footer } from '../share/Footer';
import { PageLayout } from '../share/PageLayout';
import './Conclusion.css';

export function Conclusion() {
  return (
    <div className="conclusion-layout">
      <Header />
      
      <PageLayout 
        tag="REGISTRO SALVO" 
        title="Captura concluída" 
        description="O exemplo está disponível nos seus registros."
      >
        <div className="conclusion-card">
          <span className="status-badge-success">Concluído</span>
          
          <h2 className="conclusion-title">Página de exemplo</h2>
          
          <p className="conclusion-id">ID REG-DEMO-001</p>
          
          <p className="conclusion-description">
            Uma prévia do e-mail de confirmação está disponível. Nenhum e-mail foi enviado.
          </p>

          <div className="conclusion-actions">
            <button className="btn-primary">Ver detalhes</button>
            <Link to="/packages" className="btn-link">Meus registros</Link>
            <Link to="/taking" className="btn-link">Ver confirmação</Link>
          </div>
        </div>
      </PageLayout>

      <Footer />
    </div>
  );
}
