import { Head, Link, router } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { debounce, pickBy } from 'lodash-es';
import Paginate from '@/components/table/Paginate';
import Perpage from '@/components/table/Perpage';
import Thead from '@/components/table/Thead';
import NameImage from '@/components/table/NameImage';
import ListHelper from '@/helpers/ListHelper';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface User {
    id: number;
    full_name: string;
    email: string;
    phone: string;
    status: number;
    role: string;
    profile_photo: string | null;
}

interface PaginatedUsers {
    data: User[];
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
    email?: string;
    phone?: string;
    status?: string;
    role?: string;
}

interface Props {
    users: PaginatedUsers;
    filters: Filters;
}

const ROLE_COLORS: Record<string, string> = {
    OWNER:      'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    SUPERVISOR: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    WORKER:     'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    USER:       'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
};

export default function UserIndex({ users, filters }: Props) {
    const [name,   setName]   = useState(filters.name   ?? '');
    const [email,  setEmail]  = useState(filters.email  ?? '');
    const [phone,  setPhone]  = useState(filters.phone  ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [role,   setRole]   = useState(filters.role   ?? '');

    const search = useCallback(
        debounce((params: Filters) => {
            router.visit('/admin/users', {
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

        let updated: Filters = { name, email, phone, status, role };

        if (field === 'name')   { setName(value);   updated = { ...updated, name: value }; }
        if (field === 'email')  { setEmail(value);  updated = { ...updated, email: value }; }
        if (field === 'phone')  { setPhone(value);  updated = { ...updated, phone: value }; }
        if (field === 'status') { setStatus(value); updated = { ...updated, status: value }; }
        if (field === 'role')   { setRole(value);   updated = { ...updated, role: value }; }

        search(updated);
    };

    useEffect(() => () => search.cancel(), []);

    const changeStatus = (id: number) => {
        ListHelper.confirmAlert(
            `/admin/users/${id}/change-status`,
            'Are you sure you want to change this status?',
        );
    };

    const deleteUser = (id: number) => {
        ListHelper.confirmAlert(
            `/admin/users/${id}/delete`,
            'Are you sure you want to delete this user?',
        );
    };

    return (
        <>
            <Head title="Admin | Users" />

            <div className="p-4">
                <div className="overflow-x-auto rounded-lg shadow">
                    {/* Toolbar */}
                    <div className="flex flex-wrap items-center justify-between gap-3 bg-white px-4 py-4 dark:bg-gray-900">
                        <Perpage />
                        <Link
                            href="/admin/users/create"
                            className="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus:ring-4 focus:ring-primary/30 focus:outline-none"
                        >
                            Add New
                        </Link>
                    </div>

                    {/* Table */}
                    <table className="w-full text-left text-sm text-gray-500 dark:text-gray-400 rtl:text-right">
                        <thead className="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <Thead field_name="first_name">Name</Thead>
                                <Thead field_name="email">Email</Thead>
                                <Thead field_name="phone">Phone</Thead>
                                <th className="px-6 py-3">Role</th>
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
                                        name="email"
                                        placeholder="Search email"
                                        value={email}
                                        onChange={handleChange}
                                        className="h-9 w-full rounded-md border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    />
                                </th>
                                <th className="px-6 py-2">
                                    <input
                                        type="search"
                                        name="phone"
                                        placeholder="Search phone"
                                        value={phone}
                                        onChange={handleChange}
                                        className="h-9 w-full rounded-md border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    />
                                </th>
                                <th className="px-6 py-2">
                                    <select
                                        name="role"
                                        value={role}
                                        onChange={handleChange}
                                        className="h-9 rounded-md border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    >
                                        <option value="">All</option>
                                        <option value="OWNER">Owner</option>
                                        <option value="SUPERVISOR">Supervisor</option>
                                        <option value="WORKER">Worker</option>
                                    </select>
                                </th>
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
                            {users.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-8 text-center text-gray-400">
                                        No users found.
                                    </td>
                                </tr>
                            ) : (
                                users.data.map((user) => (
                                    <tr
                                        key={user.id}
                                        className="border-b bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-600"
                                    >
                                        <td className="flex items-center gap-3 px-6 py-4 whitespace-nowrap text-gray-900 dark:text-white">
                                            {user.profile_photo ? (
                                                <img
                                                    src={user.profile_photo}
                                                    alt={user.full_name}
                                                    className="h-10 w-10 rounded-full object-cover"
                                                />
                                            ) : (
                                                <NameImage name={user.full_name} />
                                            )}
                                            <span className="text-sm text-gray-500 dark:text-gray-300">
                                                {user.full_name}
                                            </span>
                                        </td>

                                        <td className="px-6 py-4">{user.email}</td>
                                        <td className="px-6 py-4">{user.phone}</td>

                                        <td className="px-6 py-4">
                                            {user.role ? (
                                                <span
                                                    className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                        ROLE_COLORS[user.role] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                                    }`}
                                                >
                                                    {user.role.charAt(0) + user.role.slice(1).toLowerCase()}
                                                </span>
                                            ) : (
                                                <span className="text-gray-400">—</span>
                                            )}
                                        </td>

                                        <td className="px-6 py-4">
                                            <span
                                                onClick={() => changeStatus(user.id)}
                                                className={`cursor-pointer rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                    user.status === 1
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                                        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                                                }`}
                                            >
                                                {user.status === 1 ? 'Active' : 'Inactive'}
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
                                                            href={`/admin/users/${user.id}/edit`}
                                                            className="flex items-center gap-2"
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                            Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => deleteUser(user.id)}
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
                        <Paginate data={users} />
                    </div>
                </div>
            </div>
        </>
    );
}

UserIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/admin/users' },
    ],
};
