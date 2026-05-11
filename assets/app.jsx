import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { Dashboard } from './components/Dashboard';
import { LoginPage } from './components/LoginPage';
import './styles/app.css';

const tokenKey = 'my_google_shop_token';

function App() {
    const [token, setToken] = useState(() => localStorage.getItem(tokenKey));

    function handleLogin(jwt) {
        localStorage.setItem(tokenKey, jwt);
        setToken(jwt);
    }

    function handleLogout() {
        localStorage.removeItem(tokenKey);
        setToken(null);
    }

    return (
        <BrowserRouter>
            <Routes>
                <Route path="/login" element={<LoginPage token={token} onLogin={handleLogin} />} />
                <Route path="/" element={<Navigate to={token ? '/shops' : '/login'} replace />} />
                <Route
                    path="/*"
                    element={token ? <Dashboard token={token} onLogout={handleLogout} /> : <Navigate to="/login" replace />}
                />
            </Routes>
        </BrowserRouter>
    );
}

const root = document.getElementById('root');

if (root) {
    createRoot(root).render(<App />);
}
