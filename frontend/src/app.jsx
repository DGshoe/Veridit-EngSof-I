import { Routes, Route, Navigate } from 'react-router-dom';
import { Login } from './pages/Login';
import { Register } from './pages/Register';
import { Recovery } from './pages/Recovery';
import { ResetPassword } from './pages/ResetPassword';
import { Packages } from './pages/Packages';
import { Taking } from './pages/Taking';
import { Conclusion } from './pages/Conclusion';
import { Details } from './pages/Details'
import { Confirmation } from './pages/Confirmation'
import { Checkout } from './pages/Checkout';
import { Payment, PaymentReturn } from './pages/Payment';
import { GuestOnly, RequireAuth } from './auth/RequireAuth';

export function App() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/auth/login" replace />} />

      {/* Identity Service (públicas) */}
      <Route path="/auth/login" element={<GuestOnly><Login /></GuestOnly>} />
      <Route path="/auth/register" element={<GuestOnly><Register /></GuestOnly>} />
      <Route path="/auth/recovery" element={<GuestOnly><Recovery /></GuestOnly>} />
      {/* Link do e-mail de recuperação: FRONTEND_URL/redefinir-senha?token=...&email=... */}
      <Route path="/redefinir-senha" element={<ResetPassword />} />

      {/* Telas internas: exigem login */}
      <Route path="/packages" element={<RequireAuth><Packages /></RequireAuth>} />
      {/* Commerce Service: T05 faturamento e T06 pagamento */}
      <Route path="/checkout/:code" element={<RequireAuth><Checkout /></RequireAuth>} />
      <Route path="/pagamento/retorno" element={<RequireAuth><PaymentReturn /></RequireAuth>} />
      <Route path="/pagamento/:id" element={<RequireAuth><Payment /></RequireAuth>} />
      <Route path="/taking" element={<RequireAuth><Taking /></RequireAuth>} />
      <Route path="/conclusion" element={<RequireAuth><Conclusion /></RequireAuth>} />
      <Route path="/details" element={<RequireAuth><Details /></RequireAuth>} />
      <Route path="/confirmation" element={<RequireAuth><Confirmation /></RequireAuth>} />

      <Route path="*" element={<Navigate to="/auth/login" replace />} />
    </Routes>
  );
}
