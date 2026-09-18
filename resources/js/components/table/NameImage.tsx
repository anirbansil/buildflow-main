import { useEffect, useRef } from 'react';

interface NameImageProps {
    name: string;
}

export default function NameImage({ name }: NameImageProps) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const imgRef = useRef<HTMLImageElement>(null);

    const getRandomColor = () => {
        const letters = '0123456789ABCDEF';
        let color = '#';
        for (let i = 0; i < 6; i++) {
            color += letters[Math.floor(Math.random() * 16)];
        }
        return color;
    };

    useEffect(() => {
        const canvas = canvasRef.current;
        const img = imgRef.current;
        if (!canvas || !img) return;

        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        const size = 50;
        canvas.width = size;
        canvas.height = size;

        ctx.fillStyle = getRandomColor();
        ctx.fillRect(0, 0, size, size);

        const initials = name
            .split(' ')
            .map((w) => w[0]?.toUpperCase() ?? '')
            .join('');

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 18px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(initials, size / 2, size / 2);

        img.src = canvas.toDataURL('image/png');
    }, [name]);

    return (
        <div>
            <canvas ref={canvasRef} className="hidden" />
            <img ref={imgRef} className="h-10 w-10 rounded-full" alt={`${name} initials`} />
        </div>
    );
}
