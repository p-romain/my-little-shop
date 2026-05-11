import React, { useEffect, useRef, useState } from 'react';
import { useInfiniteProducts } from '../hooks/useInfiniteProducts';
import { authorizationHeaders } from '../lib/api';
import { EmptyState, ErrorBox, Panel } from './ui';
import { IconFilter } from './icons';

export function ProductsPage({ token }) {
    const [selectedShopIds, setSelectedShopIds] = useState(
        () => new URLSearchParams(window.location.search).getAll('shops[]').map(Number).filter(Boolean)
    );
    const [filterOpen, setFilterOpen] = useState(false);
    const { products, loading, loadingMore, error, hasNextPage, loadMore } = useInfiniteProducts(token, selectedShopIds);
    const [selectedProductId, setSelectedProductId] = useState(null);
    const selectedProduct = products.find((product) => product.id === selectedProductId) ?? products[0] ?? null;

    useEffect(() => {
        const params = new URLSearchParams();
        selectedShopIds.forEach((id) => params.append('shops[]', String(id)));
        const search = params.toString();
        window.history.replaceState(null, '', search ? `?${search}` : window.location.pathname);
    }, [selectedShopIds]);

    useEffect(() => {
        if (!products.length) {
            setSelectedProductId(null);
            return;
        }

        if (!selectedProductId || !products.some((product) => product.id === selectedProductId)) {
            setSelectedProductId(products[0].id);
        }
    }, [products, selectedProductId]);

    const hasFilter = selectedShopIds.length > 0;

    function handleProductScroll(event) {
        const element = event.currentTarget;
        const threshold = 120;

        if (element.scrollTop + element.clientHeight >= element.scrollHeight - threshold) {
            loadMore();
        }
    }

    return (
        <main className="h-full min-h-0 flex-1 overflow-hidden p-4 sm:p-6">
            <div className="mx-auto flex h-full max-w-6xl flex-col gap-4">
                <div className="shrink-0">
                    <h1 className="text-2xl font-semibold tracking-normal">Products</h1>
                    <p className="text-sm text-gray-500">Product catalog with external picture URLs.</p>
                </div>

                {error && <ErrorBox message={error} />}

                <section className="grid min-h-0 flex-1 grid-rows-[minmax(0,1fr)_minmax(0,1.35fr)] gap-6 lg:grid-cols-[minmax(240px,16rem)_1fr] lg:grid-rows-none">
                    <Panel
                        title="Products"
                        className="flex min-h-0 flex-col"
                        action={(
                            <button
                                type="button"
                                onClick={() => setFilterOpen(true)}
                                className={hasFilter
                                    ? 'cursor-pointer rounded border border-blue-400 bg-blue-50 px-3 py-1 text-sm text-blue-700 hover:bg-blue-100'
                                    : 'cursor-pointer rounded border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 hover:bg-gray-100'}
                                aria-label="Filter by shop"
                            >
                                <IconFilter className="h-4 w-4" />
                            </button>
                        )}
                    >
                        <div onScroll={handleProductScroll} className="min-h-0 flex-1 overflow-y-auto divide-y divide-gray-100">
                            {loading && <EmptyState label="Loading products..." />}
                            {!loading && products.map((product) => (
                                <ProductListItem
                                    key={product.id}
                                    product={product}
                                    selected={product.id === selectedProduct?.id}
                                    onSelect={() => setSelectedProductId(product.id)}
                                />
                            ))}
                            {!loading && products.length === 0 && <EmptyState label="No products yet." />}
                            {loadingMore && <EmptyState label="Loading more products..." />}
                            {!hasNextPage && products.length > 0 && (
                                <p className="px-4 py-3 text-center text-xs text-gray-400">End of list</p>
                            )}
                        </div>
                    </Panel>

                    <Panel title={selectedProduct?.name ?? 'Product'} className="flex min-h-0 flex-col">
                        <ProductDetail product={selectedProduct} />
                    </Panel>
                </section>
            </div>

            {filterOpen && (
                <ShopFilterModal
                    token={token}
                    selectedShopIds={selectedShopIds}
                    onClose={() => setFilterOpen(false)}
                    onApply={(ids) => {
                        setSelectedShopIds(ids);
                        setFilterOpen(false);
                    }}
                />
            )}
        </main>
    );
}

function ProductListItem({ product, selected, onSelect }) {
    const totalStock = product.stocks.reduce((sum, s) => sum + s.quantity, 0);

    return (
        <button
            type="button"
            onClick={onSelect}
            className={selected
                ? 'flex w-full cursor-pointer items-center gap-3 bg-blue-50 px-4 py-3 text-left'
                : 'flex w-full cursor-pointer items-center gap-3 px-4 py-3 text-left hover:bg-gray-50'}
        >
            <span className="h-10 w-10 shrink-0 overflow-hidden rounded bg-gray-100">
                <img src={product.picture} alt="" className="h-full w-full object-cover" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium">{product.name}</span>
                {totalStock > 0 ? (
                    <span className="mt-1 inline-flex rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                        {totalStock} in stock
                    </span>
                ) : (
                    <span className="block text-xs text-gray-400">Out of stock</span>
                )}
            </span>
        </button>
    );
}

function ShopFilterModal({ token, selectedShopIds, onClose, onApply }) {
    const [shops, setShops] = useState([]);
    const [loadingShops, setLoadingShops] = useState(true);
    const [draft, setDraft] = useState(new Set(selectedShopIds));
    const [dropdownOpen, setDropdownOpen] = useState(false);
    const [dropdownRect, setDropdownRect] = useState(null);
    const triggerRef = useRef(null);
    const dropdownRef = useRef(null);

    useEffect(() => {
        const onKey = (e) => { if (e.key === 'Escape') { if (dropdownOpen) setDropdownOpen(false); else onClose(); } };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [onClose, dropdownOpen]);

    useEffect(() => {
        fetch('/api/shops', { headers: authorizationHeaders(token) })
            .then((r) => r.json())
            .then((data) => { setShops(data.items ?? []); setLoadingShops(false); })
            .catch(() => setLoadingShops(false));
    }, [token]);

    useEffect(() => {
        if (!dropdownOpen) { setDropdownRect(null); return; }
        if (triggerRef.current) {
            const rect = triggerRef.current.getBoundingClientRect();
            setDropdownRect({ top: rect.bottom + 4, left: rect.left, width: rect.width });
        }
    }, [dropdownOpen]);

    useEffect(() => {
        if (!dropdownOpen) return;
        function onMouseDown(e) {
            if (
                triggerRef.current && !triggerRef.current.contains(e.target) &&
                dropdownRef.current && !dropdownRef.current.contains(e.target)
            ) {
                setDropdownOpen(false);
            }
        }
        document.addEventListener('mousedown', onMouseDown);
        return () => document.removeEventListener('mousedown', onMouseDown);
    }, [dropdownOpen]);

    function toggle(id) {
        setDraft((prev) => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    }

    const triggerLabel = draft.size === 0
        ? 'All shops'
        : `${draft.size} shop${draft.size > 1 ? 's' : ''} selected`;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-white/40 p-4 backdrop-blur-md">
            <div className="absolute inset-0 bg-black/40" onClick={onClose} />
            <section className="relative z-10 w-full max-w-xl rounded-lg border border-white/50 bg-white/85 shadow-xl">
                <header className="flex items-center justify-between rounded-t-lg border-b border-gray-200 px-4 py-3">
                    <h2 className="text-base font-semibold">Filter products</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="cursor-pointer rounded px-2 py-1 text-sm text-gray-600 hover:bg-gray-100"
                    >
                        Close
                    </button>
                </header>

                <div className="p-4">
                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Shops</p>
                    <button
                        ref={triggerRef}
                        type="button"
                        onClick={() => setDropdownOpen((o) => !o)}
                        className="flex w-full cursor-pointer items-center justify-between rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    >
                        <span className={draft.size > 0 ? 'font-medium text-blue-700' : 'text-gray-500'}>
                            {loadingShops ? 'Loading…' : triggerLabel}
                        </span>
                        <svg viewBox="0 0 20 20" className={`h-4 w-4 text-gray-400 transition-transform ${dropdownOpen ? 'rotate-180' : ''}`} fill="currentColor">
                            <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                        </svg>
                    </button>

                    {dropdownOpen && dropdownRect && (
                        <div
                            ref={dropdownRef}
                            className="fixed z-[60] max-h-60 overflow-y-auto rounded border border-gray-200 bg-white shadow-lg"
                            style={{ top: dropdownRect.top, left: dropdownRect.left, width: dropdownRect.width }}
                        >
                            {shops.length === 0 && <p className="px-3 py-2 text-sm text-gray-400">No shops available.</p>}
                            {shops.map((shop) => (
                                <label
                                    key={shop.id}
                                    className="flex cursor-pointer items-center gap-3 border-b border-gray-100 px-3 py-2.5 last:border-b-0 hover:bg-gray-50"
                                >
                                    <input
                                        type="checkbox"
                                        checked={draft.has(shop.id)}
                                        onChange={() => toggle(shop.id)}
                                        className="h-4 w-4 rounded border-gray-300 text-blue-600"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium">{shop.name}</span>
                                        {shop.address && (
                                            <span className="block truncate text-xs text-gray-400">{shop.address}</span>
                                        )}
                                    </span>
                                </label>
                            ))}
                        </div>
                    )}
                </div>

                <footer className="flex justify-end rounded-b-lg border-t border-gray-200 px-4 py-3">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => onApply([])}
                            className="cursor-pointer text-sm text-gray-500 underline-offset-2 hover:text-gray-700 hover:underline"
                        >
                            Reset
                        </button>
                        <button
                            type="button"
                            onClick={() => onApply([...draft])}
                            className="cursor-pointer rounded bg-blue-600 px-3 py-1 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            Apply
                        </button>
                    </div>
                </footer>
            </section>
        </div>
    );
}

function ProductDetail({ product }) {
    const [qty, setQty] = useState(1);
    const [wished, setWished] = useState(false);

    useEffect(() => {
        setQty(1);
        setWished(false);
    }, [product?.id]);

    if (!product) {
        return (
            <div className="flex min-h-0 flex-1 items-center justify-center px-4 text-center text-sm text-gray-500">
                Select a product to display its details.
            </div>
        );
    }

    return (
        <div className="flex min-h-0 flex-1 flex-col lg:flex-row">
            <div className="flex min-h-64 shrink-0 flex-col border-b border-gray-100 bg-gray-50 lg:w-80 lg:border-b-0 lg:border-r xl:w-96">
                <div className="relative min-h-0 flex-1 overflow-hidden">
                    <img src={product.picture} alt={product.name} className="h-full w-full object-cover" />
                    <button
                        type="button"
                        onClick={() => setWished((w) => !w)}
                        className="absolute right-2 top-2 flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-white/80 shadow backdrop-blur-sm transition-colors hover:bg-white"
                        aria-label={wished ? 'Remove from wishlist' : 'Add to wishlist'}
                    >
                        <svg viewBox="0 0 24 24" className={`h-4 w-4 transition-colors ${wished ? 'fill-red-500 stroke-red-500' : 'fill-none stroke-gray-500'}`} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                        </svg>
                    </button>
                    <div className="absolute left-2 top-2 rounded bg-red-500 px-1.5 py-0.5 text-xs font-semibold text-white">
                        NEW
                    </div>
                </div>

                <div className="shrink-0 divide-y divide-gray-100 border-t border-gray-100 px-4 py-3 text-xs text-gray-500">
                    <div className="flex items-center gap-2 py-2 first:pt-0 last:pb-0">
                        <svg viewBox="0 0 24 24" className="h-3.5 w-3.5 shrink-0 fill-none stroke-current" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <rect x="1" y="3" width="15" height="13" rx="1" /><path d="M16 8h4l3 5v3h-7V8z" /><circle cx="5.5" cy="18.5" r="2.5" /><circle cx="18.5" cy="18.5" r="2.5" />
                        </svg>
                        Free delivery over 50 €
                    </div>
                    <div className="flex items-center gap-2 py-2 first:pt-0 last:pb-0">
                        <svg viewBox="0 0 24 24" className="h-3.5 w-3.5 shrink-0 fill-none stroke-current" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <polyline points="1 4 1 10 7 10" /><path d="M3.51 15a9 9 0 1 0 .49-3.99" />
                        </svg>
                        30-day free returns
                    </div>
                    <div className="flex items-center gap-2 py-2 first:pt-0 last:pb-0">
                        <svg viewBox="0 0 24 24" className="h-3.5 w-3.5 shrink-0 fill-none stroke-current" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                        Secure payment
                    </div>
                </div>
            </div>

            <div className="flex flex-1 flex-col overflow-y-auto p-6">
                <p className="mb-0.5 text-xs font-medium uppercase tracking-widest text-gray-400">Premium collection</p>
                <h3 className="mb-1 text-xl font-semibold leading-tight">{product.name}</h3>

                <div className="mb-2 flex items-center gap-1.5">
                    <div className="flex text-amber-400">
                        {[1, 2, 3, 4, 5].map((s) => (
                            <svg key={s} viewBox="0 0 20 20" className={`h-3.5 w-3.5 ${s <= 4 ? 'fill-current' : 'fill-gray-200'}`}>
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        ))}
                    </div>
                    <span className="text-xs text-gray-400">4.0 · 128 reviews</span>
                </div>

                <div className="mb-4 flex items-baseline gap-2">
                    <span className="text-2xl font-bold text-gray-900">19,99 €</span>
                    <span className="text-sm text-gray-400 line-through">34,99 €</span>
                    <span className="rounded bg-red-50 px-1.5 py-0.5 text-xs font-semibold text-red-600">-43%</span>
                </div>

                <p className="mb-5 text-sm leading-relaxed text-gray-500">
                    Crafted from premium materials, this piece blends timeless style with everyday comfort. Perfect for any occasion — from casual outings to evening events.
                </p>

                <div className="mb-4">
                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Size</p>
                    <div className="flex gap-1.5">
                        {['XS', 'S', 'M', 'L', 'XL'].map((size, i) => (
                            <button
                                key={size}
                                type="button"
                                className={`cursor-pointer rounded border px-3 py-1.5 text-xs font-medium ${i === 2 ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 text-gray-600 hover:border-gray-400'}`}
                            >
                                {size}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="mb-6 flex items-center gap-3">
                    <div className="flex items-center rounded border border-gray-200">
                        <button
                            type="button"
                            onClick={() => setQty((q) => Math.max(1, q - 1))}
                            className="cursor-pointer px-3 py-2 text-gray-600 hover:bg-gray-50"
                        >
                            −
                        </button>
                        <span className="w-8 text-center text-sm font-medium">{qty}</span>
                        <button
                            type="button"
                            onClick={() => setQty((q) => q + 1)}
                            className="cursor-pointer px-3 py-2 text-gray-600 hover:bg-gray-50"
                        >
                            +
                        </button>
                    </div>
                    <button
                        type="button"
                        className="flex-1 cursor-pointer rounded bg-gray-900 py-2 text-sm font-semibold text-white hover:bg-gray-800"
                    >
                        Add to cart
                    </button>
                    <button
                        type="button"
                        onClick={() => setWished((w) => !w)}
                        className="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded border border-gray-200 hover:bg-gray-50"
                        aria-label={wished ? 'Remove from wishlist' : 'Add to wishlist'}
                    >
                        <svg viewBox="0 0 24 24" className={`h-4 w-4 transition-colors ${wished ? 'fill-red-500 stroke-red-500' : 'fill-none stroke-gray-500'}`} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                        </svg>
                    </button>
                </div>

                <h4 className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Available in stores
                </h4>

                {product.stocks.length === 0 ? (
                    <p className="text-sm text-gray-400">Not available in any store.</p>
                ) : (
                    <div className="divide-y divide-gray-100 rounded border border-gray-200">
                        {product.stocks.map((stock) => (
                            <div key={stock.shop.id} className="px-3 py-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium">{stock.shop.name}</span>
                                    <span className="rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                        {stock.quantity} in stock
                                    </span>
                                </div>
                                {stock.shop.address && (
                                    <p className="mt-0.5 text-xs text-gray-400">{stock.shop.address}</p>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
