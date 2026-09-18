import { Head, Link, router } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { debounce, pickBy } from 'lodash-es';
import Paginate from '@/components/table/Paginate';
import Perpage from '@/components/table/Perpage';
import Thead from '@/components/table/Thead';
import ListHelper from '@/helpers/ListHelper';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface Project {
    id: number;
    name: string;
    location: string | null;
    total_budget: number | null;
    deadline: string | null;
    status: number;
    users_count: number;
}

interface PaginatedProjects {
    data: Project[];
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Filters {
    name?: string;
    location?: string;
    status?: string;
}

interface Props {
    projects: PaginatedProjects;
    filters: Filters;
}

export default function ProjectIndex({ projects, filters }: Props) {
    const [name,     setName]     = useState(filters.name     ?? '');
    const [location, setLocation] = useState(filters.location ?? '');
    const [status,   setStatus]   = useState(filters.status   ?? '');

    const search = useCallback(
        debounce((params: Filters) => {
            router.visit('/admin/projects', {
                method: 'get',
                data: pickBy(params) as Record<string, string>,
                preserveState: true,
                replace: true,
            });
        }, 500),
        [],
    );

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        const { name: field, value } = e.target;
        let updated: Filters = { name, location, status };
        if (field === 'name')     { setName(value);     updated = { ...updated, name: value }; }
        if (field === 'location') { setLocation(value); updated = { ...updated, location: value }; }
        if (field === 'status')   { setStatus(value);   updated = { ...updated, status: value }; }
        search(updated);
    };

    useEffect(() => () => search.cancel(), []);

    const changeStatus = (id: number) => {
        ListHelper.confirmAlert(
            `/admin/projects/${id}/change-status`,
            'Are you sure you want to change this status?',
        );
    };

    const deleteProject = (id: number) => {
        ListHelper.confirmAlert(
            `/admin/projects/${id}/delete`,
            'Are you sure you want to delete this project?',
        );
    };

    return (
        <>
            <Head title="Admin | Projects" />

            <div className="p-4">
                <div className="overflow-x-auto rounded-lg shadow">
                    {/* Toolbar */}
                    <div className="flex flex-wrap items-center justify-between gap-3 bg-white px-4 py-4 dark:bg-gray-900">
                        <Perpage />
                        <Link
                            href="/admin/projects/create"
                            className="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus:ring-4 focus:ring-primary/30 focus:outline-none"
                        >
                            Add New
                        </Link>
                    </div>

                    {/* Table */}
                    <table className="w-full text-left text-sm text-gray-500 dark:text-gray-400 rtl:text-right">
                        <thead className="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <Thead field_name="name">Name</Thead>
                                <Thead field_name="location">Location</Thead>
                                <Thead field_name="total_budget">Budget</Thead>
                                <Thead field_name="deadline">Deadline</Thead>
                                <th className="px-6 py-3">Assigned</th>
                                <Thead field_name="status">Status</Thead>
                                <th className="px-6 py-3">Action</th>
                            </tr>
                            <tr>
                                <th className="px-6 py-2">
                                    <input
                                        type="search"
                                        name="name"
                                        placeholder="Search name"
                                        value={name}
                                        onChange={handleChange}
                                        className="h-9 w-full rounded-md border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    />
                                </th>
                                <th className="px-6 py-2">
                                    <input
                                        type="search"
                                        name="location"
                                        placeholder="Search location"
                                        value={location}
                                        onChange={handleChange}
                                        className="h-9 w-full rounded-md border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    />
                                </th>
                                <th className="px-6 py-2" />
                                <th className="px-6 py-2" />
                                <th className="px-6 py-2" />
                                <th className="px-6 py-2">
                                    <select
                                        name="status"
                                        value={status}
                                        onChange={handleChange}
                                        className="h-9 rounded-md border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    >
                                        <option value="">All</option>
                                        <option value="1">Active</option>
                                        <option value="2">Inactive</option>
                                    </select>
                                </th>
                                <th className="px-6 py-2" />
                            </tr>
                        </thead>

                        <tbody>
                            {projects.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-6 py-8 text-center text-gray-400">
                                        No projects found.
                                    </td>
                                </tr>
                            ) : (
                                projects.data.map((project) => (
                                    <tr
                                        key={project.id}
                                        className="border-b bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-600"
                                    >
                                        <td className="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                            {project.name}
                                        </td>
                                        <td className="px-6 py-4">{project.location ?? '—'}</td>
                                        <td className="px-6 py-4">
                                            {project.total_budget != null
                                                ? '$' + Number(project.total_budget).toLocaleString()
                                                : '—'}
                                        </td>
                                        <td className="px-6 py-4">{project.deadline ?? '—'}</td>
                                        <td className="px-6 py-4">
                                            <span className="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                {project.users_count} user{project.users_count !== 1 ? 's' : ''}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span
                                                onClick={() => changeStatus(project.id)}
                                                className={`cursor-pointer rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                    project.status === 1
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                                        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                                                }`}
                                            >
                                                {project.status === 1 ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8">
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={`/admin/projects/${project.id}/edit`}
                                                            className="flex items-center gap-2"
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                            Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => deleteProject(project.id)}
                                                        className="flex items-center gap-2 text-red-600 focus:text-red-600"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    <div className="bg-white px-4 py-3 dark:bg-gray-900">
                        <Paginate data={projects} />
                    </div>
                </div>
            </div>
        </>
    );
}

ProjectIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Projects', href: '/admin/projects' },
    ],
};
