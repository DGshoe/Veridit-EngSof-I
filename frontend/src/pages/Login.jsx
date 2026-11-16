import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { Header } from '../share/Header';
import { Footer } from '../share/Footer';
import { PageLayout } from '../share/PageLayout';
import { Alert, FormField } from '../share/FormField';
import { useAuth } from '../auth/AuthContext';
import './Recovery.css';
import './Auth.css';

// REQ03 — POST /api/v1/auth/login
export function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const [form, setForm] = useState({ email: '', password: '' });
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState('');
  const [sending, setSending] = useState(false);

  const notice = location.state?.notice; // e.g. after registration or password reset
  const update = (field) => (e) => setForm({ ...form, [field]: e.currentTarget.value });

  async function handleSubmit(e) {
    e.preventDefault();
    setSending(true);
    setErrors({});
    setMessage('');

    try {
      await login(form.email, form.password);
      navigate(location.state?.from || '/packages', { replace: true });
    } catch (error) {
      // 401 credenciais | 403 bloqueado | 422 validação | 429 muitas tentativas
      setErrors(error.errors || {});
      setMessage(error.message);
    } finally {
      setSending(false);
    }
  }

  return (
    <div className="auth-layout">
      <Header />

      <PageLayout tag="ACESSO" title="Acesse sua conta" description="Preencha os dados abaixo para continuar.">
        <div className="auth-card">
          <Alert type="success">{notice}</Alert>
          <Alert>{message}</Alert>

          <form className="auth-form" onSubmit={handleSubmit} noValidate>
            <FormField label="E-mail" name="email" type="email" autoComplete="email" required
              value={form.email} onInput={update('email')} errors={errors} />
            <FormField label="Senha" name="password" type="password" autoComplete="current-password" required
              value={form.password} onInput={update('password')} errors={errors} />

            <button type="submit" className="btn-primary" disabled={sending}>
              {sending ? 'Entrando…' : 'Entrar'}
            </button>
          </form>

          <div className="auth-links">
            <Link to="/auth/register">Criar conta</Link>
            <Link to="/auth/recovery">Esqueci minha senha</Link>
          </div>
        </div>
      </PageLayout>

      <Footer />
    </div>
  );
}
