import React, { useMemo, useState } from 'react';

const SearchIcon = () => (
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg>
);

const SortIcon = ({ direction }) => (
    <span className={`data-table__sort ${direction ? 'is-active' : ''}`} aria-hidden="true">
        {direction === 'asc' ? '↑' : direction === 'desc' ? '↓' : '↕'}
    </span>
);

export default function DataTable({
    rows,
    columns,
    rowKey = 'id',
    searchPlaceholder = 'Search…',
    searchableKeys = [],
    filterKey,
    filterOptions = [],
    pageSize = 5,
    emptyMessage = 'No records found.',
}) {
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState('all');
    const [sort, setSort] = useState({ key: null, direction: 'asc' });
    const [page, setPage] = useState(1);

    const filteredRows = useMemo(() => {
        const normalizedQuery = query.trim().toLowerCase();
        let result = rows.filter((row) => {
            const matchesSearch = !normalizedQuery || searchableKeys.some((key) => String(row[key] ?? '').toLowerCase().includes(normalizedQuery));
            const matchesFilter = !filterKey || filter === 'all' || row[filterKey] === filter;
            return matchesSearch && matchesFilter;
        });

        if (sort.key) {
            result = [...result].sort((first, second) => {
                const firstValue = String(first[sort.key] ?? '').toLowerCase();
                const secondValue = String(second[sort.key] ?? '').toLowerCase();
                const comparison = firstValue.localeCompare(secondValue, undefined, { numeric: true });
                return sort.direction === 'asc' ? comparison : -comparison;
            });
        }

        return result;
    }, [filter, filterKey, query, rows, searchableKeys, sort]);

    const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));
    const currentPage = Math.min(page, pageCount);
    const visibleRows = filteredRows.slice((currentPage - 1) * pageSize, currentPage * pageSize);
    const firstRecord = filteredRows.length ? (currentPage - 1) * pageSize + 1 : 0;
    const lastRecord = Math.min(currentPage * pageSize, filteredRows.length);

    const updateSearch = (event) => {
        setQuery(event.target.value);
        setPage(1);
    };

    const updateFilter = (event) => {
        setFilter(event.target.value);
        setPage(1);
    };

    const updateSort = (key) => {
        setSort((current) => ({ key, direction: current.key === key && current.direction === 'asc' ? 'desc' : 'asc' }));
        setPage(1);
    };

    return (
        <div className="data-table">
            <div className="data-table__toolbar">
                <label className="data-table__search">
                    <span className="sr-only">Search table</span>
                    <SearchIcon />
                    <input value={query} onChange={updateSearch} placeholder={searchPlaceholder} />
                </label>
                {filterOptions.length > 0 && (
                    <label className="data-table__filter">
                        <span>Show</span>
                        <select value={filter} onChange={updateFilter}>
                            <option value="all">All responses</option>
                            {filterOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                        </select>
                    </label>
                )}
            </div>

            <div className="data-table__scroll">
                <table>
                    <thead>
                        <tr>
                            {columns.map((column) => (
                                <th key={column.key} scope="col" className={column.className ?? ''}>
                                    {column.sortable === false ? column.label : (
                                        <button type="button" onClick={() => updateSort(column.key)}>
                                            {column.label}<SortIcon direction={sort.key === column.key ? sort.direction : null} />
                                        </button>
                                    )}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {visibleRows.map((row) => (
                            <tr key={row[rowKey]}>
                                {columns.map((column) => <td key={column.key} className={column.className ?? ''}>{column.render ? column.render(row) : row[column.key]}</td>)}
                            </tr>
                        ))}
                        {visibleRows.length === 0 && (
                            <tr><td className="data-table__empty" colSpan={columns.length}>{emptyMessage}</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="data-table__footer">
                <p>Showing <strong>{firstRecord}–{lastRecord}</strong> of <strong>{filteredRows.length}</strong> participants</p>
                <div className="data-table__pagination">
                    <button type="button" onClick={() => setPage((current) => Math.max(1, current - 1))} disabled={currentPage === 1} aria-label="Previous page">‹</button>
                    {Array.from({ length: pageCount }, (_, index) => index + 1).map((number) => (
                        <button type="button" key={number} className={number === currentPage ? 'is-current' : ''} onClick={() => setPage(number)} aria-label={`Page ${number}`} aria-current={number === currentPage ? 'page' : undefined}>{number}</button>
                    ))}
                    <button type="button" onClick={() => setPage((current) => Math.min(pageCount, current + 1))} disabled={currentPage === pageCount} aria-label="Next page">›</button>
                </div>
            </div>
        </div>
    );
}
