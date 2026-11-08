import { Routes, Route, Navigate } from 'react-router-dom';
import { Login } from './pages/Login';
import { Recovery } from './pages/Recovery';
import { Packages } from './pages/Packages';
import { Taking } from './pages/Taking';
import { Conclusion } from './pages/Conclusion';
import { Details } from './pages/Details'
import { Confirmation } from './pages/Confirmation'

export function App() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/auth/login" replace />} />
      <Route path="/auth/login" element={<Login />} />
      <Route path="/auth/recovery" element={<Recovery />} />
      <Route path="/packages" element={<Packages />} />
      <Route path="/taking" element={<Taking />} />
      <Route path="/conclusion" element={<Conclusion />} />
      <Route path="/details" element={<Details />} />
      <Route path="/confirmation" element={<Confirmation />} />
    </Routes>
  );
}
