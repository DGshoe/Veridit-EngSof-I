import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Header } from '../share/Header';
import { Footer } from '../share/Footer';
import { PageLayout } from '../share/PageLayout';
import { useAuth } from '../auth/AuthContext';
import { createOrder, listPackages, listPaymentMethods } from '../api/commerce';
import './Checkout.css';

function newIdempotencyKey() {
  if (globalThis.crypto?.randomUUID) return `compra-${crypto.randomUUID()}`;
  return `compra-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}

/**
 * T05 — Faturamento (REQ05). Resumo do pacote, dados de faturamento (só os do cadastro:
 * nome, e-mail e CPF mascarado) e escolha da forma de pagamento.
 */
export function Checkout() {
  const { code } = useParams();
  const { user } = useAuth();
  const navigate = useNavigate();

  const [pkg, setPkg] = useState(undefined); // undefined = loading, null = not found
  const [methods, setMethods] = useState(null);
  const [method, setMethod] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  // One key per visit to this screen: a double click or a retry never creates two orders.
  const idempotencyKey = useMemo(newIdempotencyKey, [code]);

  useEffect(() => {
    listPackages()
      .then((list) => setPkg(list.find((p) => p.code === code) ?? null))
      .catch((e) => { setPkg(null); setError(e.message); });

    listPaymentMethods()
      .then((list) => {
        setMethods(list);
        setMethod((current) => current || list[0]?.code || '');
      })
      .catch((e) => { setMethods([]); setError(e.message); });
  }, [code]);

  async function handleSubmit(event) {
    event.preventDefault();
    if (!method || submitting) return;

    setSubmitting(true);
    setError('');
    try {
      const order = await createOrder({ packageCode: code, paymentMethod: method, idempotencyKey });
      navigate(`/pagamento/${order.id}`, { replace: true, state: { order } });
    } catch (e) {
      const fieldError = Object.values(e.errors || {})[0]?.[0];
      setError(fieldError || e.message);
      setSubmitting(false);
    }
  }

  if (pkg === null) {
    return (
      <div className="checkout-layout">
        <Header />
        <PageLayout tag="FATURAMENTO" title="Pacote não encontrado" description={error || 'Escolha um dos pacotes disponíveis.'}>
          <Link to="/packages" className="checkout-btn-secondary">Ver pacotes</Link>
        </PageLayout>
        <Footer />
      </div>
    );
  }

  return (
    <div className="checkout-layout">
      <Header />

      <PageLayout
        tag="FATURAMENTO"
        title="Revise sua compra"
        description="Confira o pacote e os dados de faturamento e escolha como pagar."
      >
        <form className="checkout-content" onSubmit={handleSubmit}>
          <div className="checkout-main">
            {/* Dados de faturamento: apenas o que já existe no cadastro (regra da equipe para T05) */}
            <section className="checkout-card">
              <h3 className="checkout-card-title">Dados de faturamento</h3>
              <dl className="checkout-billing">
                <div><dt>Nome</dt><dd>{user?.name}</dd></div>
                <div><dt>E-mail</dt><dd>{user?.email}</dd></div>
                <div><dt>CPF</dt><dd>{user?.cpf}</dd></div>
                {user?.oab && <div><dt>OAB</dt><dd>{user.oab}</dd></div>}
              </dl>
              <p className="checkout-hint">Os dados vêm do seu cadastro e serão usados no comprovante da compra.</p>
            </section>

            <section className="checkout-card">
              <h3 className="checkout-card-title">Forma de pagamento</h3>

              {methods === null && <p className="checkout-hint">Carregando formas de pagamento…</p>}

              {methods?.length === 0 && (
                <p className="checkout-warning">
                  Nenhuma forma de pagamento está disponível neste ambiente no momento. Tente novamente mais tarde.
                </p>
              )}

              <div className="checkout-methods" role="radiogroup" aria-label="Forma de pagamento">
                {methods?.map((m) => (
                  <label key={m.code} className={`checkout-method ${method === m.code ? 'selected' : ''}`}>
                    <input
                      type="radio"
                      name="payment_method"
                      value={m.code}
                      checked={method === m.code}
                      onChange={() => setMethod(m.code)}
                    />
                    <span className="checkout-method-text">
                      <strong>{m.name}</strong>
                      <span>{m.description}</span>
                    </span>
                  </label>
                ))}
              </div>
            </section>
          </div>

          <aside className="checkout-summary">
            <h3 className="checkout-card-title">Resumo</h3>
            {pkg === undefined ? (
              <p className="checkout-hint">Carregando…</p>
            ) : (
              <>
                <div className="summary-line"><span>Pacote</span><strong>{pkg.name}</strong></div>
                <div className="summary-line"><span>Créditos</span><strong>{pkg.credits}</strong></div>
                <div className="summary-line"><span>Preço por crédito</span><span>{pkg.price_per_credit}</span></div>
                <div className="summary-total"><span>Total</span><strong>{pkg.price}</strong></div>
              </>
            )}

            {error && <p className="checkout-error" role="alert">{error}</p>}

            <button
              type="submit"
              className="checkout-btn-primary"
              disabled={!pkg || !method || submitting}
            >
              {submitting ? 'Gerando pagamento…' : method === 'pix' ? 'Gerar QR Code PIX' : 'Continuar para o pagamento'}
            </button>
            <Link to="/packages" className="checkout-btn-link">Trocar de pacote</Link>
          </aside>
        </form>
      </PageLayout>

      <Footer />
    </div>
  );
}
