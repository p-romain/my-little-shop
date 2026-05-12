import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';

export function LoginPage({ token, onLogin }) {
    const navigate = useNavigate();
    const [email, setEmail] = useState('admin@example.com');
    const [password, setPassword] = useState('password');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (token) {
            navigate('/shops', { replace: true });
        }
    }, [navigate, token]);

    async function submit(event) {
        event.preventDefault();
        setLoading(true);
        setError('');

        try {
            const response = await fetch('/api/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password }),
            });
            const data = await response.json();

            if (!response.ok || !data.token) {
                throw new Error(data.message || 'Invalid credentials.');
            }

            onLogin(data.token);
            navigate('/shops', { replace: true });
        } catch (loginError) {
            setError(loginError instanceof Error ? loginError.message : 'Unable to log in.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <main className="mx-auto mt-16 max-w-sm px-4 font-sans">
            <h1 className="mb-8 text-center text-2xl font-black uppercase tracking-[0.18em] text-gray-900">
                My Little Shop
            </h1>

            <form onSubmit={submit} noValidate>
                <div className="mb-4">
                    <label htmlFor="email" className="mb-1 block text-sm font-medium">Email</label>
                    <input
                        id="email"
                        type="text"
                        value={email}
                        onChange={(event) => setEmail(event.target.value)}
                        className="box-border w-full rounded border border-gray-300 p-2 focus:border-blue-500 focus:outline-none"
                    />
                </div>

                <div className="mb-4">
                    <label htmlFor="password" className="mb-1 block text-sm font-medium">Password</label>
                    <input
                        id="password"
                        type="password"
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        className="box-border w-full rounded border border-gray-300 p-2 focus:border-blue-500 focus:outline-none"
                    />
                </div>

                {error && <p className="mb-4 text-sm text-red-600">{error}</p>}

                <button
                    type="submit"
                    disabled={loading}
                    className="mb-4 w-full cursor-pointer rounded bg-blue-600 p-2 font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {loading ? 'Loading...' : 'Log in'}
                </button>
            </form>
        </main>
    );
}
