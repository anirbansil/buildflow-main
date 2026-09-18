export default function AppLogoIcon({ className }: { className?: string }) {
    return (
        <img
            src="/images/logo.png"
            alt="Logo"
            className={className ?? 'size-5 object-contain'}
        />
    );
}
