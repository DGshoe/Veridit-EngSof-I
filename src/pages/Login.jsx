import { Header } from '../share/Header';
import { Footer } from '../share/Footer';

export function Login() {
  return (
    <div className="login-layout">
      <Header />
      
      <main className="login-content">
        <div className="login-box">
          <h2>Acesse sua conta</h2>
          <p>Preencha os dados abaixo para continuar.</p>
          
          {/* Aqui você pode adicionar o formulário posteriormente */}
          <div className="form-placeholder">
            <p>[Formulário de Login vai aqui]</p>
          </div>
        </div>
      </main>

      <Footer />
    </div>
  );
}
