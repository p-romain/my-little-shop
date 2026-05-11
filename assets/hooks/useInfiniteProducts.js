import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { authorizationHeaders } from '../lib/api';

export function useInfiniteProducts(token, shopIds = []) {
    const [products, setProducts] = useState([]);
    const [page, setPage] = useState(1);
    const [hasNextPage, setHasNextPage] = useState(true);
    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [error, setError] = useState('');
    const loadingRef = useRef(false);
    const shopIdsKey = shopIds.join(',');

    const loadPage = useCallback(async (nextPage, replace = false) => {
        if (loadingRef.current) {
            return;
        }

        loadingRef.current = true;

        if (replace) {
            setLoading(true);
        } else {
            setLoadingMore(true);
        }

        setError('');

        try {
            const url = new URL('/api/products', window.location.origin);
            url.searchParams.set('page', String(nextPage));

            shopIds.forEach((id) => {
                url.searchParams.append('shops[]', String(id));
            });

            const response = await fetch(url, {
                headers: authorizationHeaders(token),
            });

            if (!response.ok) {
                throw new Error('Unable to load products.');
            }

            const json = await response.json();
            const nextProducts = json.items ?? [];

            setProducts((current) => (replace ? nextProducts : [...current, ...nextProducts]));
            setPage(nextPage);
            setHasNextPage(nextPage < (json.pages ?? 1));
        } catch (apiError) {
            setError(apiError instanceof Error ? apiError.message : 'Unable to reach the API.');
        } finally {
            loadingRef.current = false;
            setLoading(false);
            setLoadingMore(false);
        }
    }, [token, shopIdsKey]);

    const reload = useCallback(() => loadPage(1, true), [loadPage]);

    const loadMore = useCallback(() => {
        if (!loading && !loadingMore && hasNextPage) {
            loadPage(page + 1);
        }
    }, [hasNextPage, loadPage, loading, loadingMore, page]);

    useEffect(() => {
        reload();
    }, [reload]);

    return useMemo(
        () => ({ products, loading, loadingMore, error, hasNextPage, reload, loadMore }),
        [error, hasNextPage, loadMore, loading, loadingMore, products, reload]
    );
}
