import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface PaginateLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginateProps {
    data: {
        current_page: number;
        last_page: number;
        from: number;
        to: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        links: PaginateLink[];
    };
}

export default function Paginate({ data }: PaginateProps) {
    const { current_page, last_page, from, to, total, prev_page_url, next_page_url, links } = data;

    const startPage = Math.max(1, current_page - 1);
    const endPage = Math.min(last_page, current_page + 1);

    const numericLinks = links.filter((link) => {
        const page = parseInt(link.label, 10);
        return !isNaN(page) && (page === 1 || page === last_page || (page >= startPage && page <= endPage));
    });

    const processedLinks: PaginateLink[] = [];
    numericLinks.forEach((link, index) => {
        const prev = numericLinks[index - 1];
        if (prev && parseInt(link.label, 10) > parseInt(prev.label, 10) + 1) {
            processedLinks.push({ label: '...', url: null, active: false });
        }
        processedLinks.push(link);
    });

    return (
        <nav className="flex flex-wrap items-center justify-between pt-4" aria-label="Table navigation">
            <span className="mb-4 block w-full text-sm font-normal text-gray-500 dark:text-gray-400 md:mb-0 md:inline md:w-auto">
                Showing{' '}
                <span className="font-semibold text-gray-900 dark:text-white">
                    {from}–{to}
                </span>{' '}
                of{' '}
                <span className="font-semibold text-gray-900 dark:text-white">{total}</span>
            </span>

            <ul className="inline-flex h-8 -space-x-px text-sm rtl:space-x-reverse">
                <Link
                    as="button"
                    href={prev_page_url ?? '#'}
                    disabled={current_page === 1}
                    className="flex h-10 items-center justify-center border border-gray-300 bg-white px-3 leading-tight text-gray-500 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                >
                    <ChevronLeft className="h-4 w-4" />
                </Link>

                {processedLinks.map((link, index) =>
                    link.url ? (
                        <li key={index}>
                            <Link
                                href={link.url}
                                className={`flex h-10 items-center justify-center border border-gray-300 px-4 leading-tight ${
                                    current_page === parseInt(link.label, 10)
                                        ? 'border-blue-300 bg-blue-50 text-blue-600 hover:bg-blue-100 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-700 dark:text-white'
                                        : 'bg-white text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white'
                                }`}
                            >
                                {link.label}
                            </Link>
                        </li>
                    ) : (
                        <li key={index}>
                            <span className="flex h-10 items-center justify-center border border-gray-300 bg-white px-4 leading-tight text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                ...
                            </span>
                        </li>
                    ),
                )}

                <Link
                    as="button"
                    href={next_page_url ?? '#'}
                    disabled={next_page_url === null}
                    className="flex h-10 items-center justify-center border border-gray-300 bg-white px-3 leading-tight text-gray-500 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                >
                    <ChevronRight className="h-4 w-4" />
                </Link>
            </ul>
        </nav>
    );
}
