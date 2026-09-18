import { router } from '@inertiajs/react';
import { useState } from 'react';

export default function Perpage() {
    const params = new URLSearchParams(window.location.search);
    const [perPage, setPerPage] = useState(params.get('perPage') ?? '10');

    const handleChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const value = e.target.value;
        setPerPage(value);
        router.reload({
            method: 'get',
            data: { perPage: value },
            replace: false,
        });
    };

    return (
        <div className="flex items-center gap-0">
            <span className="inline-flex h-10 items-center border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-400">
                Show
            </span>
            <select
                className="block h-10 border border-gray-300 bg-gray-50 p-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                onChange={handleChange}
                value={perPage}
            >
                <option>5</option>
                <option>10</option>
                <option>20</option>
                <option>50</option>
            </select>
        </div>
    );
}
