import { Link } from 'react-router-dom';
import { Header } from '../share/Header';
import { Footer } from '../share/Footer';
import { PageLayout } from '../share/PageLayout';
import './Recovery.css';

export function Recovery() {
  return (
    <div className="recovery-layout">
      <Header />
      
      <PageLayout 
        tag="ACESSO" 
        title="Recuperar senha" 
        description="Informe o e-mail associado à conta"
      >
        <div className="recovery-card">
          <form className="recovery-form" onSubmit={(e) => e.preventDefault()}>
            <div className="input-group">
              <label htmlFor="email">E-mail</label>
              <input 
                type="email" 
                id="email" 
                placeholder="" 
                required 
              />
            </div>

            <button type="submit" className="btn-primary">
              Solicitar recuperação
            </button>
          </form>

          <div className="recovery-footer">
            <Link to="/auth/login" className="link-voltar">
              Voltar ao login
            </Link>
          </div>
        </div>
      </PageLayout>

      <Footer />
    </div>
  );
}
