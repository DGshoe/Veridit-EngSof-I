import { Routes, Route, Navigate } from 'react-router-dom';
import { Login } from './pages/Login';
import { Recovery } from './pages/Recovery';

export function App() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/auth/login" replace />} />
      <Route path="/auth/login" element={<Login />} />
      <Route path="/auth/recovery" element={<Recovery />} />
    </Routes>
  );
}
