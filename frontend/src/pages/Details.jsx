import { Link } from 'react-router-dom';
import { Header } from '../share/Header';
import { Footer } from '../share/Footer';
import { PageLayout } from '../share/PageLayout';
import './Details.css';

export function Details() {
  return (
    <div className="details-layout">
      <Header />
      
      <PageLayout 
        tag="REGISTRO REG-DEMO-001" 
        title="Página de exemplo" 
        description="Informações do registro selecionado."
        action={<Link to="/conclusion" className="btn-voltar">Voltar</Link>}
      >
        <div className="details-content">
          <div className="details-card">
            <div className="fake-browser">
              <div className="browser-url">
                https://example.com
              </div>
              <div className="browser-view">
                <h4>Conteúdo de exemplo</h4>
                <p>Imagem simétrica para apresentar o fluxo de registro.</p>
                <p>Nenhuma página externa foi acessada ou gravada.</p>
              </div>
            </div>

            <div className="details-actions">
              <button className="btn-primary">Relatório e arquivos</button>
            </div>
          </div>

          <div className="details-card">
            <h3 className="details-title">Detalhes</h3>
            
            <ul className="details-list">
              <li><span>Usuário</span> <strong>Pessoas demonstrativa</strong></li>
              <li><span>Início</span> <strong>21/10/2026 14:20:00</strong></li>
              <li><span>Fim</span> <strong>21/10/2026 14:20:12</strong></li>
              <li><span>Tipo</span> <strong>Imagem</strong></li>
              <li><span>Imagens</span> <strong>1</strong></li>
              <li><span>Vídeos</span> <strong>0</strong></li>
              <li><span>Estado</span> <strong className="status-concluido">Concluído</strong></li>
            </ul>
          </div>

        </div>
      </PageLayout>

      <Footer />
    </div>
  );
}
