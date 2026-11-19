import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, Navigate, useLocation, useParams, useSearchParams } from 'react-router-dom';
import { Header } from '../share/Header';
import { Footer } from '../share/Footer';
import { PageLayout } from '../share/PageLayout';
import { getBalance, getOrder, notifyWalletChanged, simulatePayment } from '../api/commerce';
import './Payment.css';

const POLL_MS = 4000;

/** Remaining time until `iso`, refreshed every second ("mm:ss"; null when expired or unknown). */
function useCountdown(iso) {
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (!iso) return undefined;
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(timer);
  }, [iso]);

  if (!iso) return { label: null, expired: false };
  const left = Math.max(0, new Date(iso).getTime() - now);
  const minutes = Math.floor(left / 60000);
  const seconds = Math.floor((left % 60000) / 1000);
  return { label: `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`, expired: left === 0 };
}

function PixPayment({ order }) {
  const data = order.payment.data || {};
  const { label, expired } = useCountdown(data.expires_at);
  const [copied, setCopied] = useState(false);
  const codeRef = useRef(null);

  async function copyCode() {
    try {
      await navigator.clipboard.writeText(data.qr_code);
    } catch {
      codeRef.current?.select(); // clipboard blocked: select so the user can copy by hand
      document.execCommand?.('copy');
    }
    setCopied(true);
    setTimeout(() => setCopied(false), 2500);
  }

  if (expired) {
    return (
      <div className="payment-state payment-state-failed">
        <h3>QR Code expirado</h3>
        <p>O prazo para pagar este PIX terminou e nenhum valor foi cobrado. Gere um novo QR Code para continuar.</p>
        <Link to={`/checkout/${order.package.code}`} className="payment-btn-primary">Gerar novo PIX</Link>
      </div>
    );
  }

  return (
    <div className="pix-box">
      <div className="pix-qr">
        <img src={data.qr_code_image} alt="QR Code PIX para pagamento" width="240" height="240" />
        {label && <p className="pix-timer">Expira em <strong>{label}</strong></p>}
      </div>

      <div className="pix-steps">
        <ol>
          <li>Abra o app do seu banco e escolha <strong>pagar com PIX</strong>.</li>
          <li>Escaneie o QR Code ou use o <strong>PIX copia e cola</strong> abaixo.</li>
          <li>Confirme o pagamento de <strong>{order.amount}</strong>. Os créditos entram em seguida.</li>
        </ol>

        <label className="pix-code-label" htmlFor="pix-code">PIX copia e cola</label>
        <textarea id="pix-code" ref={codeRef} className="pix-code" readOnly rows={3} value={data.qr_code} onFocus={(e) => e.target.select()} />
        <button type="button" className="payment-btn-primary" onClick={copyCode}>
          {copied ? 'Código copiado!' : 'Copiar código PIX'}
        </button>
      </div>
    </div>
  );
}

function MercadoPagoPayment({ order }) {
  const url = order.payment.data?.checkout_url;
  return (
    <div className="payment-state">
      <h3>Pague na página do Mercado Pago</h3>
      <p>Você pode usar cartão de crédito, saldo Mercado Pago ou PIX. Ao terminar, você volta para esta tela.</p>
      {url
        ? <a href={url} className="payment-btn-primary">Ir para o Mercado Pago</a>
        : <p className="payment-error">Link de pagamento indisponível. Tente comprar novamente.</p>}
    </div>
  );
}

function SimulatorPayment({ order, onDone }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  async function simulate(outcome) {
    setBusy(true);
    setError('');
    try {
      await simulatePayment(order.id, outcome);
      await onDone();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="payment-state">
      <h3>Ambiente de simulação</h3>
      <p>Nenhum valor é cobrado. Escolha a resposta do provedor de pagamento.</p>
      <div className="payment-actions">
        <button type="button" className="payment-btn-primary" disabled={busy} onClick={() => simulate('aprovado')}>Simular aprovação</button>
        <button type="button" className="payment-btn-secondary" disabled={busy} onClick={() => simulate('recusado')}>Simular falha</button>
      </div>
      {error && <p className="payment-error">{error}</p>}
    </div>
  );
}

function PaidState({ order }) {
  const [balance, setBalance] = useState(null);

  useEffect(() => {
    notifyWalletChanged(); // header balance
    getBalance().then(setBalance).catch(() => {});
  }, []);

  return (
    <div className="payment-state payment-state-paid">
      <span className="payment-badge payment-badge-paid">Pagamento confirmado</span>
      <h3>{order.package.credits} créditos adicionados</h3>
      <p>
        Recebemos o pagamento de {order.amount}.
        {balance !== null && <> Seu saldo agora é de <strong>{balance} créditos</strong>.</>}
        {' '}Você também receberá a confirmação por e-mail.
      </p>
      <div className="payment-actions">
        <Link to="/taking" className="payment-btn-primary">Fazer um registro</Link>
        <Link to="/packages" className="payment-btn-secondary">Comprar mais créditos</Link>
      </div>
    </div>
  );
}

function FailedState({ order }) {
  return (
    <div className="payment-state payment-state-failed">
      <span className="payment-badge payment-badge-failed">Pagamento não concluído</span>
      <h3>O pagamento não foi aprovado</h3>
      <p>Nenhum crédito foi lançado. Você pode tentar novamente com a mesma ou outra forma de pagamento.</p>
      <div className="payment-actions">
        <Link to={`/checkout/${order.package.code}`} className="payment-btn-primary">Tentar novamente</Link>
        <Link to="/packages" className="payment-btn-secondary">Ver pacotes</Link>
      </div>
    </div>
  );
}

const METHOD_NAMES = { pix: 'PIX', mercadopago: 'Mercado Pago', simulador: 'Simulador' };

/**
 * T06 — Pagamento (REQ06). Mostra a cobrança do pedido e acompanha o status até o provedor
 * confirmar (o crédito só entra pelo webhook; esta tela apenas consulta o pedido).
 */
export function Payment() {
  const { id } = useParams();
  const location = useLocation();
  const [order, setOrder] = useState(location.state?.order ?? null);
  const [error, setError] = useState('');

  const refresh = useCallback(async () => {
    try {
      setOrder(await getOrder(id));
      setError('');
    } catch (e) {
      setError(e.status === 404 || e.status === 403 ? 'Pedido não encontrado.' : e.message);
    }
  }, [id]);

  // First load + polling while the payment is pending.
  useEffect(() => {
    refresh();
  }, [refresh]);

  const pending = order?.status === 'pendente';
  useEffect(() => {
    if (!pending) return undefined;
    const timer = setInterval(refresh, POLL_MS);
    return () => clearInterval(timer);
  }, [pending, refresh]);

  const title = !order ? 'Pagamento'
    : order.status === 'pago' ? 'Compra concluída'
      : order.status === 'falhou' ? 'Pagamento não concluído'
        : order.payment.method === 'pix' ? 'Pague com PIX' : 'Pagamento';

  return (
    <div className="payment-layout">
      <Header />

      <PageLayout tag="PAGAMENTO" title={title} description={order ? `Pedido ${order.id.slice(0, 8)} · ${METHOD_NAMES[order.payment.method] ?? order.payment.method}` : undefined}>
        {!order && !error && <p className="payment-hint">Carregando pedido…</p>}
        {!order && error && (
          <div className="payment-state payment-state-failed">
            <h3>{error}</h3>
            <Link to="/packages" className="payment-btn-primary">Ver pacotes</Link>
          </div>
        )}

        {order && (
          <div className="payment-content">
            <div className="payment-main">
              {order.status === 'pago' && <PaidState order={order} />}
              {order.status === 'falhou' && <FailedState order={order} />}
              {pending && order.payment.method === 'pix' && <PixPayment order={order} />}
              {pending && order.payment.method === 'mercadopago' && <MercadoPagoPayment order={order} />}
              {pending && order.payment.method === 'simulador' && <SimulatorPayment order={order} onDone={refresh} />}

              {pending && (
                <p className="payment-waiting" aria-live="polite">
                  <span className="payment-spinner" aria-hidden="true" /> Aguardando a confirmação do pagamento…
                  esta tela atualiza sozinha.
                </p>
              )}
              {error && order && <p className="payment-error">{error}</p>}
            </div>

            <aside className="payment-summary">
              <h3>Resumo</h3>
              <div className="summary-line"><span>Pacote</span><strong>{order.package.code.charAt(0).toUpperCase() + order.package.code.slice(1)}</strong></div>
              <div className="summary-line"><span>Créditos</span><strong>{order.package.credits}</strong></div>
              <div className="summary-line"><span>Faturado para</span><span>{order.billing.name}</span></div>
              <div className="summary-line"><span>CPF</span><span>{order.billing.cpf}</span></div>
              <div className="summary-total"><span>Total</span><strong>{order.amount}</strong></div>
            </aside>
          </div>
        )}
      </PageLayout>

      <Footer />
    </div>
  );
}

/** Mercado Pago back_url: /pagamento/retorno?pedido=<id>&status=... → the order's payment screen. */
export function PaymentReturn() {
  const [params] = useSearchParams();
  const orderId = params.get('pedido') || params.get('external_reference');
  return <Navigate to={orderId ? `/pagamento/${orderId}` : '/packages'} replace />;
}
