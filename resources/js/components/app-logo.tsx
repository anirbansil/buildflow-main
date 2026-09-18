import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { name } = usePage().props as { name: string };

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary">
                <img
                    src="/images/logo.png"
                    alt={name}
                    className="size-8 object-contain"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {name}
                </span>
            </div>
        </>
    );
}
