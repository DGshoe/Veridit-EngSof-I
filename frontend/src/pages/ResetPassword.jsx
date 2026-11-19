import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Header } from '../share/Header';
import { Footer } from '../share/Footer';
import { PageLayout } from '../share/PageLayout';
import { Alert, FormField } from '../share/FormField';
import { resetPassword } from '../api/identity';
import './Recovery.css';
import './Auth.css';

// REQ02 — page opened from the e-mail link:
//   /redefinir-senha?token=...&email=...   ->   POST /api/v1/auth/reset-password
export function ResetPassword() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const token = params.get('token') || '';
  const email = params.get('email') || '';

  const [form, setForm] = useState({ password: '', password_confirmation: '' });
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState('');
  const [sending, setSending] = useState(false);

  const update = (field) => (e) => setForm({ ...form, [field]: e.currentTarget.value });
  const invalidLink = !token || !email;

  async function handleSubmit(e) {
    e.preventDefault();
    setSending(true);
    setErrors({});
    setMessage('');

    try {
      await resetPassword({ token, email, ...form });
      navigate('/auth/login', {
        replace: true,
        state: { notice: 'Senha redefinida. Entre com a nova senha.' },
      });
    } catch (error) {
      setErrors(error.errors || {});
      // Token errors are not tied to a visible field: show them in the alert.
      setMessage(error.errors?.token?.join(' ') || error.errors?.email?.join(' ') || error.message);
    } finally {
      setSending(false);
    }
  }

  return (
    <div className="auth-layout">
      <Header />

      <PageLayout tag="ACESSO" title="Redefinir senha" description={email ? `Conta: ${email}` : 'Defina uma nova senha.'}>
        <div className="auth-card">
          {invalidLink ? (
            <Alert>Link de redefinição inválido. Solicite um novo link.</Alert>
          ) : (
            <>
              <Alert>{message}</Alert>
              <form className="auth-form" onSubmit={handleSubmit} noValidate>
                <FormField label="Nova senha" name="password" type="password" autoComplete="new-password" required
                  hint="Mínimo 8 caracteres, com letras e números."
                  value={form.password} onInput={update('password')} errors={errors} />
                <FormField label="Confirmar nova senha" name="password_confirmation" type="password" autoComplete="new-password" required
                  value={form.password_confirmation} onInput={update('password_confirmation')} errors={errors} />

                <button type="submit" className="btn-primary" disabled={sending}>
                  {sending ? 'Salvando…' : 'Redefinir senha'}
                </button>
              </form>
            </>
          )}

          <div className="auth-links">
            <Link to="/auth/recovery">Solicitar novo link</Link>
            <Link to="/auth/login">Voltar ao login</Link>
          </div>
        </div>
      </PageLayout>

      <Footer />
    </div>
  );
}
