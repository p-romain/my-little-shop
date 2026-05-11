import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { authorizationHeaders } from '../lib/api';

export function usePaginatedApi(path, token, initialPage = 1) {
    const [data, setData] = useState([]);
    const [total, setTotal] = useState(0);
    const [totalPages, setTotalPages] = useState(1);
    const [page, setPage] = useState(initialPage);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const hasNextPage = page < totalPages;
    const mountedRef = useRef(false);

    const reload = useCallback(async () => {
        setLoading(true);
        setError('');

        try {
            const url = new URL(path, window.location.origin);
            url.searchParams.set('page', String(page));

            const response = await fetch(url, {
                headers: authorizationHeaders(token),
            });

            if (!response.ok) {
                throw new Error(`Unable to load ${path}.`);
            }

            const json = await response.json();
            setData(json.items ?? []);
            setTotal(json.total ?? 0);
            setTotalPages(json.pages ?? 1);
        } catch (apiError) {
            setError(apiError instanceof Error ? apiError.message : 'Unable to reach the API.');
        } finally {
            setLoading(false);
        }
    }, [page, path, token]);

    // Reset to page 1 when path changes, but not on first mount
    // (first mount respects initialPage from the URL)
    useEffect(() => {
        if (!mountedRef.current) {
            mountedRef.current = true;
            return;
        }
        setPage(1);
    }, [path]);

    useEffect(() => {
        reload();
    }, [reload]);

    return useMemo(
        () => ({ data, loading, error, page, hasNextPage, totalPages, total, setPage, reload }),
        [data, error, hasNextPage, loading, page, reload, total, totalPages]
    );
}
