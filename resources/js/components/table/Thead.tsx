import { ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';
import ListHelper from '@/helpers/ListHelper';

interface TheadProps {
    field_name?: string;
    sortable?: boolean;
    children: React.ReactNode;
}

export default function Thead({ field_name = '', sortable = true, children }: TheadProps) {
    const params = new URLSearchParams(window.location.search);
    const [sortDesc, setSortDesc] = useState(params.get('short_by') === 'desc');

    const handleSort = () => {
        if (!sortable || !field_name) return;
        setSortDesc((prev) => !prev);
        ListHelper.sortBy(field_name);
    };

    return (
        <th
            scope="col"
            className={`px-6 py-3 ${sortable && field_name ? 'cursor-pointer select-none' : ''}`}
            onClick={handleSort}
        >
            <div className="flex items-center justify-between gap-1">
                <span>{children}</span>
                {sortable && field_name && (
                    <span className="flex flex-col">
                        <ChevronUp
                            className={`-mb-1 h-3 w-3 ${sortDesc ? 'text-muted-foreground' : 'text-blue-500'}`}
                        />
                        <ChevronDown
                            className={`h-3 w-3 ${sortDesc ? 'text-blue-500' : 'text-muted-foreground'}`}
                        />
                    </span>
                )}
            </div>
        </th>
    );
}
