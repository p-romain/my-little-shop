import React, { useEffect } from 'react';
import { usePaginatedApi } from '../hooks/usePaginatedApi';
import { EmptyState, ErrorBox, Page, Pagination, Panel } from './ui';

export function UsersPage({ token }) {
    const initialPage = Math.max(1, Number(new URLSearchParams(window.location.search).get('page')) || 1);
    const { data: users, loading, error, page, totalPages, setPage } = usePaginatedApi('/api/users', token, initialPage);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        if (page <= 1) {
            params.delete('page');
        } else {
            params.set('page', String(page));
        }
        const search = params.toString();
        window.history.replaceState(null, '', search ? `?${search}` : window.location.pathname);
    }, [page]);

    return (
        <Page title="Users" subtitle="Application users allowed to manage shops.">
            {error && <ErrorBox message={error} />}
            <Panel title="Users">
                <div className="divide-y divide-gray-100">
                    {loading && <EmptyState label="Loading users..." />}
                    {!loading && users.map((user) => (
                        <div key={user.id} className="flex items-center gap-3 px-4 py-3">
                            <span className="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">#{user.id}</span>
                            <span className="truncate text-sm font-medium">{user.email}</span>
                        </div>
                    ))}
                    {!loading && users.length === 0 && <EmptyState label="No users yet." />}
                </div>
            </Panel>
            <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
        </Page>
    );
}
