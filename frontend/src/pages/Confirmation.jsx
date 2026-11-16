import { Link } from 'react-router-dom';
import { Header } from '../share/Header';
import { Footer } from '../share/Footer';
import { PageLayout } from '../share/PageLayout';
import './Confirmation.css';

const demoMessages = [
  {
    id: 1,
    tag: "MENSAGEM DEMONSTRATIVA 1",
    title: "Compra de créditos confirmada"
  },
  {
    id: 2,
    tag: "MENSAGEM DEMONSTRATIVA 2",
    title: "Gravação de conteúdo concluída"
  },
  {
    id: 3,
    tag: "MENSAGEM DEMONSTRATIVA 3",
    title: "Captura de tela concluída"
  }
];

export function Confirmation() {
  return (
    <div className="confirmation-layout">
      <Header />
      
      <PageLayout 
        tag="COMUNICAÇÃO" 
        title="Confirmações por e-mail" 
        description="Prévia de mensagens. Nenhum e-mail real é enviado."
      >
        <div className="confirmation-content">
          
          {/* Coluna Esquerda - Lista de Mensagens */}
          <div className="confirmation-messages">
            {demoMessages.map((msg) => (
              <div key={msg.id} className="message-card">
                <span className="message-tag">{msg.tag}</span>
                <h3 className="message-title">{msg.title}</h3>
                
                <p className="message-recipient">Para: pessoa@example.test</p>
                
                <p className="message-body">
                  Olá, Pessoa demonstrativa. A operação demonstrativa foi concluída. 
                  Consulte seus registros e o saldo na plataforma.
                </p>
                
                <span className="status-badge">Prévia local</span>
              </div>
            ))}
          </div>

          {/* Coluna Direita - Acompanhamento */}
          <div className="confirmation-sidebar">
            <div className="sidebar-card">
              <h3 className="sidebar-title">Acompanhamento</h3>
              
              <p className="sidebar-text">
                O desenho do Notification prevê envio assíncrono, 
                retentativas e registro de falhas.
              </p>
              
              <p className="sidebar-text highlight-text">
                Esta tela permite revisar o conteúdo das confirmações 
                exigidas nos requisitos.
              </p>
              
              <Link to="/details" className="btn-primary">
                Meus registros
              </Link>
            </div>
          </div>

        </div>
      </PageLayout>

      <Footer />
    </div>
  );
}
