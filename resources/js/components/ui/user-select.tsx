import { useEffect, useRef, useState } from 'react';
import { X, ChevronDown, Search } from 'lucide-react';

interface UserOption {
    id: number;
    full_name: string;
    role?: string;
}

interface Props {
    users: UserOption[];
    selected: number[];
    onChange: (ids: number[]) => void;
    error?: string;
}

export default function UserSelect({ users, selected, onChange, error }: Props) {
    const [open, setOpen]       = useState(false);
    const [query, setQuery]     = useState('');
    const containerRef          = useRef<HTMLDivElement>(null);
    const inputRef              = useRef<HTMLInputElement>(null);

    // Close on outside click
    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
                setQuery('');
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    // Focus search when opening
    useEffect(() => {
        if (open) inputRef.current?.focus();
    }, [open]);

    const filtered = users.filter((u) =>
        u.full_name.toLowerCase().includes(query.toLowerCase()),
    );

    const toggle = (id: number) => {
        onChange(
            selected.includes(id)
                ? selected.filter((s) => s !== id)
                : [...selected, id],
        );
    };

    const remove = (e: React.MouseEvent, id: number) => {
        e.stopPropagation();
        onChange(selected.filter((s) => s !== id));
    };

    const selectedUsers = users.filter((u) => selected.includes(u.id));

    return (
        <div ref={containerRef} className="relative">
            {/* Trigger box */}
            <div
                onClick={() => setOpen((o) => !o)}
                className={`flex min-h-[42px] w-full cursor-pointer flex-wrap items-center gap-1.5 rounded-lg border px-3 py-2 text-sm transition-colors ${
                    open
                        ? 'border-blue-500 ring-1 ring-blue-500'
                        : error
                        ? 'border-red-500'
                        : 'border-gray-300 hover:border-gray-400 dark:border-gray-600'
                } bg-white dark:bg-gray-700`}
            >
                {selectedUsers.length === 0 ? (
                    <span className="text-gray-400 dark:text-gray-400">Select users…</span>
                ) : (
                    selectedUsers.map((u) => (
                        <span
                            key={u.id}
                            className="flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                        >
                            {u.full_name}
                            {u.role && (
                                <span className="rounded-full bg-blue-200 px-1.5 py-px text-[10px] text-blue-700 dark:bg-blue-800 dark:text-blue-300">
                                    {u.role.charAt(0) + u.role.slice(1).toLowerCase()}
                                </span>
                            )}
                            <button
                                type="button"
                                onClick={(e) => remove(e, u.id)}
                                className="ml-0.5 rounded-full hover:bg-blue-200 dark:hover:bg-blue-800"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </span>
                    ))
                )}
                <ChevronDown
                    className={`ml-auto h-4 w-4 shrink-0 text-gray-400 transition-transform ${open ? 'rotate-180' : ''}`}
                />
            </div>

            {/* Dropdown */}
            {open && (
                <div className="absolute z-50 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800">
                    {/* Search */}
                    <div className="flex items-center gap-2 border-b border-gray-100 px-3 py-2 dark:border-gray-700">
                        <Search className="h-4 w-4 shrink-0 text-gray-400" />
                        <input
                            ref={inputRef}
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Search users…"
                            className="w-full bg-transparent text-sm text-gray-900 outline-none placeholder:text-gray-400 dark:text-white"
                        />
                        {query && (
                            <button type="button" onClick={() => setQuery('')}>
                                <X className="h-3.5 w-3.5 text-gray-400 hover:text-gray-600" />
                            </button>
                        )}
                    </div>

                    {/* Options */}
                    <ul className="max-h-56 overflow-y-auto py-1">
                        {filtered.length === 0 ? (
                            <li className="px-4 py-3 text-sm text-gray-400">No users found.</li>
                        ) : (
                            filtered.map((u) => {
                                const isSelected = selected.includes(u.id);
                                return (
                                    <li
                                        key={u.id}
                                        onClick={() => toggle(u.id)}
                                        className={`flex cursor-pointer items-center gap-2 px-4 py-2 text-sm transition-colors ${
                                            isSelected
                                                ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'
                                                : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700'
                                        }`}
                                    >
                                        <span
                                            className={`flex h-4 w-4 shrink-0 items-center justify-center rounded border text-xs ${
                                                isSelected
                                                    ? 'border-blue-500 bg-blue-500 text-white'
                                                    : 'border-gray-300 dark:border-gray-500'
                                            }`}
                                        >
                                            {isSelected && '✓'}
                                        </span>
                                        <span className="flex items-center gap-1.5">
                                            <span>{u.full_name}</span>
                                            {u.role && (
                                                <span className="rounded-full bg-gray-100 px-2 py-px text-[10px] font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                                    {u.role.charAt(0) + u.role.slice(1).toLowerCase()}
                                                </span>
                                            )}
                                        </span>
                                    </li>
                                );
                            })
                        )}
                    </ul>

                    {/* Footer */}
                    {selected.length > 0 && (
                        <div className="flex items-center justify-between border-t border-gray-100 px-4 py-2 dark:border-gray-700">
                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                {selected.length} selected
                            </span>
                            <button
                                type="button"
                                onClick={() => onChange([])}
                                className="text-xs text-red-500 hover:text-red-700"
                            >
                                Clear all
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
