import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { Trash2, FileText, ImageIcon } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import UserSelect from '@/components/ui/user-select';

interface UserOption {
    id: number;
    full_name: string;
    role?: string;
}

interface ExistingImage {
    id: number;
    name: string;
    url: string;
}

interface ProjectData {
    id?: number;
    name?: string;
    description?: string;
    location?: string;
    lat?: number | null;
    long?: number | null;
    total_budget?: number | null;
    deadline?: string;
    status?: string;
    assigned_users?: number[];
    existing_images?: ExistingImage[];
}

interface Props {
    project?: ProjectData;
    users: UserOption[];
}

// File types that show an image preview vs a file icon
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

export default function CreateEdit({ project, users }: Props) {
    const isEditing = Boolean(project?.id);

    const { data, setData, post, processing, errors } = useForm({
        name:            project?.name         ?? '',
        description:     project?.description  ?? '',
        location:        project?.location     ?? '',
        lat:             project?.lat  != null ? String(project.lat)         : '',
        long:            project?.long != null ? String(project.long)        : '',
        total_budget:    project?.total_budget != null ? String(project.total_budget) : '',
        deadline:        project?.deadline     ?? '',
        status:          project?.status       ?? '1',
        assigned_users:  project?.assigned_users ?? [] as number[],
        images:          [] as File[],
        image_names:     [] as string[],
        deleted_images:  [] as number[],
    });

    // Existing images state (edit mode only) — we manage deletions locally
    const [existingImages, setExistingImages] = useState<ExistingImage[]>(
        project?.existing_images ?? []
    );

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
        setData(e.target.name as keyof typeof data, e.target.value as never);
    };

    const handleFiles = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(e.target.files ?? []);
        if (!files.length) return;
        setData('images', [...(data.images as File[]), ...files] as never);
        setData('image_names', [
            ...(data.image_names as string[]),
            ...files.map((f) => f.name),
        ] as never);
        // Reset input so same file can be re-added if removed
        e.target.value = '';
    };

    const removeNewFile = (index: number) => {
        const imgs = [...(data.images as File[])];
        const names = [...(data.image_names as string[])];
        imgs.splice(index, 1);
        names.splice(index, 1);
        setData('images', imgs as never);
        setData('image_names', names as never);
    };

    const updateFileName = (index: number, value: string) => {
        const names = [...(data.image_names as string[])];
        names[index] = value;
        setData('image_names', names as never);
    };

    const removeExistingImage = (id: number) => {
        setExistingImages((prev) => prev.filter((img) => img.id !== id));
        setData('deleted_images', [...(data.deleted_images as number[]), id] as never);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        const url = isEditing ? `/admin/projects/${project!.id}` : '/admin/projects';
        post(url, { preserveScroll: true, forceFormData: true });
    };

    const inputClass = "block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-700 dark:text-white";

    return (
        <>
            <Head title={`Admin | ${isEditing ? 'Edit' : 'Create'} Project`} />

            <div className="p-4">
                <div className="mb-4 rounded bg-white p-4 text-2xl font-semibold shadow dark:bg-gray-900 dark:text-white">
                    {isEditing ? 'Edit' : 'Create'} Project
                </div>

                <form onSubmit={handleSubmit} className="rounded bg-white p-6 shadow dark:bg-gray-900">
                    <div className="grid gap-6 sm:grid-cols-2">

                        {/* Name */}
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="name">Project Name</Label>
                            <Input id="name" name="name" placeholder="Enter project name" value={data.name} onChange={handleChange} />
                            <InputError message={errors.name} />
                        </div>

                        {/* Description */}
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="description">Description</Label>
                            <textarea
                                id="description" name="description" rows={3}
                                placeholder="Enter description" value={data.description}
                                onChange={handleChange} className={inputClass}
                            />
                            <InputError message={errors.description} />
                        </div>

                        {/* Location */}
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="location">Location</Label>
                            <Input id="location" name="location" placeholder="Enter location" value={data.location} onChange={handleChange} />
                            <InputError message={errors.location} />
                        </div>

                        {/* Latitude */}
                        <div className="grid gap-2">
                            <Label htmlFor="lat">Latitude</Label>
                            <Input id="lat" name="lat" type="number" step="any" placeholder="e.g. 23.8103" value={data.lat} onChange={handleChange} />
                            <InputError message={errors.lat} />
                        </div>

                        {/* Longitude */}
                        <div className="grid gap-2">
                            <Label htmlFor="long">Longitude</Label>
                            <Input id="long" name="long" type="number" step="any" placeholder="e.g. 90.4125" value={data.long} onChange={handleChange} />
                            <InputError message={errors.long} />
                        </div>

                        {/* Total Budget */}
                        <div className="grid gap-2">
                            <Label htmlFor="total_budget">Total Budget ($)</Label>
                            <Input id="total_budget" name="total_budget" type="number" step="0.01" min="0" placeholder="e.g. 50000" value={data.total_budget} onChange={handleChange} />
                            <InputError message={errors.total_budget} />
                        </div>

                        {/* Deadline */}
                        <div className="grid gap-2">
                            <Label htmlFor="deadline">Deadline</Label>
                            <Input id="deadline" name="deadline" type="date" value={data.deadline} onChange={handleChange} />
                            <InputError message={errors.deadline} />
                        </div>

                        {/* Status */}
                        <div className="grid gap-2">
                            <Label htmlFor="status">Status</Label>
                            <select id="status" name="status" value={data.status} onChange={handleChange} className={inputClass}>
                                <option value="1">Active</option>
                                <option value="2">Inactive</option>
                            </select>
                            <InputError message={errors.status} />
                        </div>

                        {/* Assign Users */}
                        <div className="grid gap-2 sm:col-span-2">
                            <Label>Assign Users</Label>
                            <UserSelect
                                users={users}
                                selected={data.assigned_users as number[]}
                                onChange={(ids) => setData('assigned_users', ids as never)}
                                error={errors.assigned_users}
                            />
                            <InputError message={errors.assigned_users} />
                        </div>

                        {/* Documents / Images */}
                        <div className="grid gap-3 sm:col-span-2">
                            <Label>Project Documents & Images</Label>

                            {/* Existing images (edit mode) */}
                            {existingImages.length > 0 && (
                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    {existingImages.map((img) => {
                                        const isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(img.url);
                                        return (
                                            <div key={img.id} className="group relative flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-600 dark:bg-gray-800">
                                                {isImg ? (
                                                    <img src={img.url} alt={img.name} className="h-10 w-10 rounded object-cover shrink-0" />
                                                ) : (
                                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-gray-200 dark:bg-gray-700">
                                                        <FileText className="h-5 w-5 text-gray-500" />
                                                    </div>
                                                )}
                                                <a href={img.url} target="_blank" rel="noopener noreferrer"
                                                    className="flex-1 truncate text-xs text-blue-600 hover:underline dark:text-blue-400">
                                                    {img.name}
                                                </a>
                                                <button type="button" onClick={() => removeExistingImage(img.id)}
                                                    className="shrink-0 rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/30">
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            {/* Upload new files */}
                            <label className="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 py-6 transition hover:border-primary hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700">
                                <ImageIcon className="mb-2 h-8 w-8 text-gray-400" />
                                <span className="text-sm text-gray-500 dark:text-gray-400">
                                    Click to upload images or documents
                                </span>
                                <span className="mt-1 text-xs text-gray-400">
                                    JPG, PNG, GIF, WEBP, PDF, DOC, DOCX, XLS, XLSX — max 10MB each
                                </span>
                                <input
                                    type="file" multiple className="hidden"
                                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx"
                                    onChange={handleFiles}
                                />
                            </label>
                            <InputError message={(errors as Record<string, string>)['images']} />

                            {/* Newly selected files preview */}
                            {(data.images as File[]).length > 0 && (
                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    {(data.images as File[]).map((file, i) => {
                                        const isImg = IMAGE_TYPES.includes(file.type);
                                        const preview = isImg ? URL.createObjectURL(file) : null;
                                        return (
                                            <div key={i} className="flex flex-col gap-1.5 rounded-lg border border-primary/30 bg-primary/5 p-2">
                                                <div className="flex items-center gap-2">
                                                    {preview ? (
                                                        <img src={preview} alt={file.name} className="h-10 w-10 rounded object-cover shrink-0" />
                                                    ) : (
                                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-gray-200 dark:bg-gray-700">
                                                            <FileText className="h-5 w-5 text-gray-500" />
                                                        </div>
                                                    )}
                                                    <span className="flex-1 truncate text-xs text-gray-600 dark:text-gray-400">{file.name}</span>
                                                    <button type="button" onClick={() => removeNewFile(i)}
                                                        className="shrink-0 rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/30">
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                                <Input
                                                    placeholder="Custom name (optional)"
                                                    value={(data.image_names as string[])[i] ?? ''}
                                                    onChange={(e) => updateFileName(i, e.target.value)}
                                                    className="h-7 text-xs"
                                                />
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                    </div>

                    <div className="mt-6 flex justify-end">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving...' : isEditing ? 'Update Project' : 'Create Project'}
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
        { title: 'Projects', href: '/admin/projects' },
        { title: 'Manage Project', href: '#' },
    ],
};
