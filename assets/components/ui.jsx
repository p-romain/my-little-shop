import React from 'react';

export function Page({ title, subtitle, children }) {
    return (
        <main className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
            <div className="mx-auto flex max-w-6xl flex-col gap-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-normal">{title}</h1>
                    <p className="text-sm text-gray-500">{subtitle}</p>
                </div>
                {children}
            </div>
        </main>
    );
}

export function Panel({ title, action, children, className = '' }) {
    return (
        <section className={`overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm ${className}`}>
            <header className="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3">
                <h2 className="text-base font-semibold">{title}</h2>
                {action && <div className="text-xs text-gray-500">{action}</div>}
            </header>
            {children}
        </section>
    );
}

export function EmptyState({ label }) {
    return <p className="px-4 py-3 text-sm text-gray-500">{label}</p>;
}

export function FormField({ label, required = false, children }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-medium">
                {label}
                {required && <span className="text-red-600"> *</span>}
            </span>
            {children}
        </label>
    );
}

export function Pagination({ page, totalPages, onPageChange }) {
    const hasPrev = page > 1;
    const hasNext = page < totalPages;

    const start = Math.max(1, page - 2);
    const end = Math.min(totalPages, page + 2);
    const pages = Array.from({ length: end - start + 1 }, (_, i) => start + i);

    return (
        <nav className="flex justify-center text-sm">
            <div className="flex w-fit items-center gap-2">
                <button
                    type="button"
                    disabled={!hasPrev}
                    onClick={() => onPageChange(page - 1)}
                    className="cursor-pointer rounded border border-gray-300 bg-white px-3 py-1 text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    &laquo;
                </button>
                <div className="flex items-center gap-1">
                    {start > 1 && (
                        <>
                            <button type="button" onClick={() => onPageChange(1)} className="cursor-pointer rounded px-3 py-1 text-gray-600 hover:bg-gray-100">1</button>
                            {start > 2 && <span className="px-1 text-gray-400">…</span>}
                        </>
                    )}
                    {pages.map((item) => (
                        <button
                            key={item}
                            type="button"
                            onClick={() => onPageChange(item)}
                            className={item === page
                                ? 'rounded bg-blue-50 px-3 py-1 font-medium text-blue-700'
                                : 'cursor-pointer rounded px-3 py-1 text-gray-600 hover:bg-gray-100'}
                        >
                            {item}
                        </button>
                    ))}
                    {end < totalPages && (
                        <>
                            {end < totalPages - 1 && <span className="px-1 text-gray-400">…</span>}
                            <button type="button" onClick={() => onPageChange(totalPages)} className="cursor-pointer rounded px-3 py-1 text-gray-600 hover:bg-gray-100">{totalPages}</button>
                        </>
                    )}
                </div>
                <button
                    type="button"
                    disabled={!hasNext}
                    onClick={() => onPageChange(page + 1)}
                    className="cursor-pointer rounded border border-gray-300 bg-white px-3 py-1 text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    &raquo;
                </button>
            </div>
        </nav>
    );
}

export function ErrorBox({ message }) {
    return (
        <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {message}
        </div>
    );
}
