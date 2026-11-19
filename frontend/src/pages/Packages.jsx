import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Header } from '../share/Header';
import { Footer } from '../share/Footer';
import { PageLayout } from '../share/PageLayout';
import { getBalance, listPackages } from '../api/commerce';
import './Packages.css';

const HIGHLIGHTED = 'medio';

// Componente Card interno
function Card({ tag, pkg, isHighlighted, onSelect }) {
  return (
    <div className={`package-card ${isHighlighted ? 'highlighted' : ''}`}>
      <div className="card-header">
        <span className="card-tag">{tag}</span>
        <h3 className="card-title">{pkg.name}</h3>
      </div>

      <div className="card-body">
        <div className="card-credits">
          <strong>{pkg.credits}</strong> <span>créditos</span>
        </div>

        <div className="card-price">
          {pkg.price} <span className="price-detail">- {pkg.price_per_credit} por crédito</span>
        </div>

        <ul className="card-features">
          <li>Registros de imagem (1 crédito) e vídeo (2 créditos)</li>
          <li>Consulta aos registros</li>
          <li>Relatório e arquivos para download</li>
          <li>Créditos não expiram</li>
        </ul>
      </div>

      <div className="card-footer">
        <button className="btn-select" onClick={() => onSelect(pkg)}>Selecionar {pkg.name}</button>
      </div>
    </div>
  );
}

/** T04 — Pacotes (REQ05 / D2): catálogo e saldo vindos do Commerce. */
export function Packages() {
  const navigate = useNavigate();
  const [packages, setPackages] = useState(null);
  const [balance, setBalance] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    listPackages().then(setPackages).catch((e) => setError(e.message));
    getBalance().then(setBalance).catch(() => setBalance(null));
  }, []);

  const description = balance === null
    ? 'Escolha a quantidade de créditos para registrar imagens e vídeos.'
    : `Saldo atual: ${balance} ${balance === 1 ? 'crédito' : 'créditos'}.`;

  return (
    <div className="packages-layout">
      <Header />

      <PageLayout tag="CRÉDITOS" title="Escolha um pacote" description={description}>
        {error && <div className="packages-alert packages-error">{error}</div>}

        {!packages && !error && <p className="packages-loading">Carregando pacotes…</p>}

        {packages && (
          <div className="packages-grid">
            {packages.map((pkg, index) => (
              <Card
                key={pkg.code}
                tag={`PACOTE ${index + 1}`}
                pkg={pkg}
                isHighlighted={pkg.code === HIGHLIGHTED}
                onSelect={() => navigate(`/checkout/${pkg.code}`)}
              />
            ))}
          </div>
        )}

        <div className="packages-alert">
          Pagamento por PIX (QR Code) ou Mercado Pago. Os créditos entram assim que o pagamento é confirmado.
        </div>
      </PageLayout>

      <Footer />
    </div>
  );
}
