import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Header } from '../share/Header';
import { Footer } from '../share/Footer';
import { PageLayout } from '../share/PageLayout';
import { Alert, FormField } from '../share/FormField';
import { register } from '../api/identity';
import './Recovery.css';
import './Auth.css';

const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
  'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

const EMPTY = {
  type: 'comum', name: '', email: '', cpf: '', oab_number: '', oab_uf: '',
  password: '', password_confirmation: '',
};

// Visual mask only (000.000.000-00); the API accepts it with or without the mask.
function maskCpf(value) {
  const d = value.replace(/\D/g, '').slice(0, 11);
  return d
    .replace(/^(\d{3})(\d)/, '$1.$2')
    .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
}

// REQ01 — POST /api/v1/auth/register (does not log in)
export function Register() {
  const navigate = useNavigate();
  const [form, setForm] = useState(EMPTY);
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState('');
  const [sending, setSending] = useState(false);

  const isLawyer = form.type === 'advogado';
  const update = (field, transform = (v) => v) => (e) =>
    setForm({ ...form, [field]: transform(e.currentTarget.value) });

  async function handleSubmit(e) {
    e.preventDefault();
    setSending(true);
    setErrors({});
    setMessage('');

    const payload = { ...form };
    if (!isLawyer) {
      delete payload.oab_number;
      delete payload.oab_uf;
    }

    try {
      await register(payload);
      navigate('/auth/login', {
        replace: true,
        state: { notice: 'Conta criada com sucesso. Faça login para continuar.' },
      });
    } catch (error) {
      setErrors(error.errors || {});
      setMessage(error.message);
    } finally {
      setSending(false);
    }
  }

  return (
    <div className="auth-layout">
      <Header />

      <PageLayout tag="CADASTRO" title="Criar conta" description="Cadastre-se como advogado ou usuário comum.">
        <div className="auth-card wide">
          <Alert>{message}</Alert>

          <form className="auth-form" onSubmit={handleSubmit} noValidate>
            <div className="type-switch" role="radiogroup" aria-label="Tipo de conta">
              {[['comum', 'Usuário comum'], ['advogado', 'Advogado']].map(([value, label]) => (
                <label key={value} className={form.type === value ? 'selected' : ''}>
                  <input type="radio" name="type" value={value}
                    checked={form.type === value} onChange={update('type')} />
                  {label}
                </label>
              ))}
            </div>
            {errors.type && <small className="field-error">{errors.type.join(' ')}</small>}

            <FormField label="Nome completo" name="name" autoComplete="name" required
              value={form.name} onInput={update('name')} errors={errors} />

            <div className="auth-grid">
              <FormField label="E-mail" name="email" type="email" autoComplete="email" required
                value={form.email} onInput={update('email')} errors={errors} />
              <FormField label="CPF" name="cpf" inputMode="numeric" placeholder="000.000.000-00" required
                value={form.cpf} onInput={update('cpf', maskCpf)} errors={errors} />
            </div>

            {isLawyer && (
              <div className="auth-grid">
                <FormField label="Número da OAB" name="oab_number" inputMode="numeric" maxLength={8} required
                  value={form.oab_number} onInput={update('oab_number', (v) => v.replace(/\D/g, ''))} errors={errors} />
                <div className={`input-group ${errors.oab_uf ? 'has-error' : ''}`}>
                  <label htmlFor="field-oab_uf">UF da OAB</label>
                  <select id="field-oab_uf" name="oab_uf" value={form.oab_uf} onChange={update('oab_uf')} required>
                    <option value="">Selecione</option>
                    {UFS.map((uf) => <option key={uf} value={uf}>{uf}</option>)}
                  </select>
                  {errors.oab_uf && <small className="field-error">{errors.oab_uf.join(' ')}</small>}
                </div>
              </div>
            )}

            <div className="auth-grid">
              <FormField label="Senha" name="password" type="password" autoComplete="new-password" required
                hint="Mínimo 8 caracteres, com letras e números."
                value={form.password} onInput={update('password')} errors={errors} />
              <FormField label="Confirmar senha" name="password_confirmation" type="password" autoComplete="new-password" required
                value={form.password_confirmation} onInput={update('password_confirmation')} errors={errors} />
            </div>

            <button type="submit" className="btn-primary" disabled={sending}>
              {sending ? 'Cadastrando…' : 'Criar conta'}
            </button>
          </form>

          <div className="auth-links">
            <Link to="/auth/login">Já tenho conta</Link>
          </div>
        </div>
      </PageLayout>

      <Footer />
    </div>
  );
}
