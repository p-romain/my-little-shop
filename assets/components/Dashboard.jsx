import React from 'react';
import { Navigate, NavLink, Route, Routes } from 'react-router-dom';
import { ProductsPage } from './ProductsPage';
import { ShopsPage } from './ShopsPage';
import { UsersPage } from './UsersPage';

export function Dashboard({ token, onLogout }) {
    return (
        <div className="flex h-screen flex-col overflow-hidden bg-gray-50 font-sans text-gray-900">
            <header className="flex shrink-0 items-center justify-between border-b border-gray-300 bg-white px-4 py-3">
                <div className="flex items-center gap-4">
                    <strong className="text-sm">My Little Shop</strong>
                    <nav className="flex gap-2 text-sm">
                        <MenuLink to="/shops">Shops</MenuLink>
                        <MenuLink to="/products">Products</MenuLink>
                        <MenuLink to="/users">Users</MenuLink>
                    </nav>
                </div>
                <button
                    type="button"
                    onClick={onLogout}
                    className="cursor-pointer rounded border border-gray-300 bg-transparent px-3 py-1 text-sm font-normal text-gray-700 hover:bg-gray-100"
                >
                    Logout
                </button>
            </header>

            <div className="flex min-h-0 flex-1">
                <Routes>
                    <Route path="/shops" element={<ShopsPage token={token} />} />
                    <Route path="/users" element={<UsersPage token={token} />} />
                    <Route path="/products" element={<ProductsPage token={token} />} />
                    <Route path="*" element={<Navigate to="/shops" replace />} />
                </Routes>
            </div>
        </div>
    );
}

function MenuLink({ to, children }) {
    return (
        <NavLink
            to={to}
            className={({ isActive }) => (
                isActive
                    ? 'rounded bg-blue-50 px-3 py-1 font-medium text-blue-700'
                    : 'rounded px-3 py-1 text-gray-600 hover:bg-gray-100'
            )}
        >
            {children}
        </NavLink>
    );
}
