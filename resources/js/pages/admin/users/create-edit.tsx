import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface UserData {
    id?: number;
    first_name?: string;
    last_name?: string;
    email?: string;
    phone?: string;
    dob?: string;
    status?: number;
    role?: string;
    profile_photo?: string | null;
}

interface Props {
    user?: UserData;
}

export default function CreateEdit({ user }: Props) {
    const isEditing = Boolean(user?.id);

    const { data, setData, post, processing, errors } = useForm({
        first_name:    user?.first_name ?? '',
        last_name:     user?.last_name  ?? '',
        email:         user?.email      ?? '',
        password:      '',
        phone:         user?.phone      ?? '',
        dob:           user?.dob        ?? '',
        status:        String(user?.status ?? 1),
        role:          user?.role ?? 'WORKER',
        profile_photo: null as File | null,
    });

    const [preview, setPreview] = useState<string | null>(user?.profile_photo ?? null);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        setData(e.target.name as keyof typeof data, e.target.value as never);
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setData('profile_photo', file as never);
        setPreview(URL.createObjectURL(file));
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        const url = isEditing ? `/admin/users/${user!.id}` : '/admin/users';
        post(url, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`Admin | ${isEditing ? 'Edit' : 'Create'} User`} />

            <div className="p-4">
                <div className="mb-4 rounded bg-white p-4 text-2xl font-semibold shadow dark:bg-gray-900 dark:text-white">
                    {isEditing ? 'Edit' : 'Create'} User
                </div>

                <form
                    onSubmit={handleSubmit}
                    encType="multipart/form-data"
                    className="rounded bg-white p-6 shadow dark:bg-gray-900"
                >
                    <div className="grid gap-6 sm:grid-cols-2">

                        {/* First Name */}
                        <div className="grid gap-2">
                            <Label htmlFor="first_name">First Name</Label>
                            <Input
                                id="first_name"
                                name="first_name"
                                placeholder="Enter first name"
                                value={data.first_name}
                                onChange={handleChange}
                            />
                            <InputError message={errors.first_name} />
                        </div>

                        {/* Last Name */}
                        <div className="grid gap-2">
                            <Label htmlFor="last_name">Last Name</Label>
                            <Input
                                id="last_name"
                                name="last_name"
                                placeholder="Enter last name"
                                value={data.last_name}
                                onChange={handleChange}
                            />
                            <InputError message={errors.last_name} />
                        </div>

                        {/* Password — create only */}
                        {!isEditing && (
                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    placeholder="Enter password"
                                    value={data.password}
                                    onChange={handleChange}
                                />
                                <InputError message={errors.password} />
                            </div>
                        )}

                        {/* Email */}
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                placeholder="Enter email"
                                value={data.email}
                                onChange={handleChange}
                            />
                            <InputError message={errors.email} />
                        </div>

                        {/* Phone */}
                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone</Label>
                            <Input
                                id="phone"
                                name="phone"
                                type="tel"
                                placeholder="Enter phone"
                                value={data.phone}
                                onChange={handleChange}
                            />
                            <InputError message={errors.phone} />
                        </div>

                        {/* Status */}
                        <div className="grid gap-2">
                            <Label htmlFor="status">Status</Label>
                            <select
                                id="status"
                                name="status"
                                value={data.status}
                                onChange={handleChange}
                                className="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="1">Active</option>
                                <option value="2">Inactive</option>
                            </select>
                            <InputError message={errors.status} />
                        </div>

                        {/* Role */}
                        <div className="grid gap-2">
                            <Label htmlFor="role">Role</Label>
                            <select
                                id="role"
                                name="role"
                                value={data.role}
                                onChange={handleChange}
                                className="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="OWNER">Owner</option>
                                <option value="SUPERVISOR">Supervisor</option>
                                <option value="WORKER">Worker</option>
                                
                            </select>
                            <InputError message={errors.role} />
                        </div>

                        {/* Date of Birth */}
                        <div className="grid gap-2">
                            <Label htmlFor="dob">Date of Birth</Label>
                            <Input
                                id="dob"
                                name="dob"
                                type="date"
                                value={data.dob}
                                onChange={handleChange}
                            />
                            <InputError message={errors.dob} />
                        </div>

                        {/* Profile Photo */}
                        <div className="grid gap-2">
                            <Label htmlFor="profile_photo">Profile Photo</Label>
                            <input
                                id="profile_photo"
                                name="profile_photo"
                                type="file"
                                accept="image/*"
                                onChange={handleFileChange}
                                className="block w-full cursor-pointer rounded-lg border border-gray-300 bg-gray-50 text-sm text-gray-900 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400"
                            />
                            <InputError message={errors.profile_photo} />
                            {preview && (
                                <img
                                    src={preview}
                                    alt="Profile preview"
                                    className="mt-2 h-24 w-24 rounded-full object-cover"
                                />
                            )}
                        </div>

                    </div>

                    <div className="mt-6 flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving...' : isEditing ? 'Update User' : 'Create User'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

CreateEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/admin/users' },
        { title: 'Manage User', href: '#' },
    ],
};
