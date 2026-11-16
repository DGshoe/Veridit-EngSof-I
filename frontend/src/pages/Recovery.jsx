import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Header } from '../share/Header';
import { Footer } from '../share/Footer';
import { PageLayout } from '../share/PageLayout';
import { Alert, FormField } from '../share/FormField';
import { forgotPassword } from '../api/identity';
import './Recovery.css';
import './Auth.css';

// REQ02 — POST /api/v1/auth/forgot-password
// The API always answers with the same message (it never reveals whether the e-mail exists).
export function Recovery() {
  const [email, setEmail] = useState('');
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState('');
  const [success, setSuccess] = useState('');
  const [sending, setSending] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setSending(true);
    setErrors({});
    setMessage('');
    setSuccess('');

    try {
      setSuccess(await forgotPassword(email));
    } catch (error) {
      setErrors(error.errors || {});
      setMessage(error.message);
    } finally {
      setSending(false);
    }
  }

  return (
    <div className="recovery-layout">
      <Header />

      <PageLayout
        tag="ACESSO"
        title="Recuperar senha"
        description="Informe o e-mail associado à conta"
      >
        <div className="recovery-card">
          <Alert type="success">{success}</Alert>
          <Alert>{message}</Alert>

          <form className="recovery-form" onSubmit={handleSubmit} noValidate>
            <FormField label="E-mail" name="email" type="email" autoComplete="email" required
              value={email} onInput={(e) => setEmail(e.currentTarget.value)} errors={errors} />

            <button type="submit" className="btn-primary" disabled={sending}>
              {sending ? 'Enviando…' : 'Solicitar recuperação'}
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
