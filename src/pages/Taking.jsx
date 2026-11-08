import { Header } from '../share/Header';
import { Footer } from '../share/Footer';
import { PageLayout } from '../share/PageLayout';
import './Taking.css';

export function Taking() {
  return (
    <div className="taking-layout">
      <Header />
      
      <PageLayout 
        tag="CAPTURA" 
        title="Registro em andamento" 
        description="Página de exemplo"
      >
        <div className="taking-content">
          
          {/* Coluna Esquerda - Simulação de Navegador */}
          <div className="taking-card">
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

            <div className="taking-actions">
              <button className="btn-primary">Concluir gravação</button>
              <button className="btn-text">Cancelar demonstração</button>
            </div>
          </div>

          {/* Coluna Direita - Informações de Captura */}
          <div className="taking-card">
            <span className="status-badge">Captura demonstrativa ativa</span>
            
            <h2 className="taking-title">Vídeo</h2>
            
            <p className="taking-description">
              Conclua para salvar o registro de exemplo e disponibilizar a confirmação.
            </p>

            <div className="taking-alert">
              Nenhuma captura real está em execução
            </div>
          </div>

        </div>
      </PageLayout>

      <Footer />
    </div>
  );
}
