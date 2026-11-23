import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center bg-background px-4 py-10 sm:justify-center sm:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-5">
                        <Link
                            href={home()}
                            className="flex items-center gap-2 font-heading text-2xl font-extrabold text-foreground"
                        >
                            <span className="flex size-11 items-center justify-center rounded-2xl bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-6" />
                            </span>
                            O&apos;qi AI
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-2xl font-bold">{title}</h1>
                            {description && (
                                <p className="text-center text-sm text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="rounded-3xl border bg-card p-6 shadow-xs">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
