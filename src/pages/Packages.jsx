import { Header } from '../share/Header';
import { Footer } from '../share/Footer';
import { PageLayout } from '../share/PageLayout';
import './Packages.css';

// Componente Card interno
function Card({ tag, title, credits, price, pricePerCredit, isHighlighted }) {
  return (
    <div className={`package-card ${isHighlighted ? 'highlighted' : ''}`}>
      <div className="card-header">
        <span className="card-tag">{tag}</span>
        <h3 className="card-title">{title}</h3>
      </div>

      <div className="card-body">
        <div className="card-credits">
          <strong>{credits}</strong> <span>créditos</span>
        </div>
        
        <div className="card-price">
          {price} <span className="price-detail">- {pricePerCredit} por crédito</span>
        </div>

        <ul className="card-features">
          <li>Registros de imagem e vídeo</li>
          <li>Consulta aos registros</li>
          <li>Relatório e arquivos para download</li>
        </ul>
      </div>

      <div className="card-footer">
        <button className="btn-select">Selecionar {title}</button>
      </div>
    </div>
  );
}

export function Packages() {
  return (
    <div className="packages-layout">
      <Header />
      
      <PageLayout 
        tag="CRÉDITOS" 
        title="Escolha um pacote" 
        description="Saldo demonstrativo atual: 12 créditos."
      >
        <div className="packages-grid">
          <Card 
            tag="PACOTE 1" 
            title="Básico" 
            credits="10" 
            price="R$ 30,00" 
            pricePerCredit="R$ 3,00" 
          />
          <Card 
            tag="PACOTE 2" 
            title="Médio" 
            credits="25" 
            price="R$ 65,00" 
            pricePerCredit="R$ 2,60" 
            isHighlighted={true} 
          />
          <Card 
            tag="PACOTE 3" 
            title="Premium" 
            credits="50" 
            price="R$ 120,00" 
            pricePerCredit="R$ 2,40" 
          />
        </div>

        <div className="packages-alert">
          Valores, quantidades e benefícios comerciais são exemplos de interface. D2 define apenas os nomes dos três pacotes.
        </div>
      </PageLayout>

      <Footer />
    </div>
  );
}
