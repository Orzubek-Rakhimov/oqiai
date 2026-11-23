import type { ReactNode } from 'react';

/**
 * The phone-first shell for the picker, Child screens and Child profile forms.
 */
export default function PhoneLayout({ children }: { children: ReactNode }) {
    return (
        <div className="min-h-svh bg-background text-foreground">
            <main className="mx-auto flex min-h-svh w-full max-w-md flex-col px-4 py-6 sm:py-10">
                {children}
            </main>
        </div>
    );
}
