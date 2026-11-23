import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Xush kelibsiz" />
            <div className="flex min-h-svh flex-col items-center bg-background px-4 py-10 text-foreground sm:justify-center">
                <main className="flex w-full max-w-sm flex-col items-center gap-8 text-center">
                    <span className="flex size-20 items-center justify-center rounded-3xl bg-primary text-primary-foreground">
                        <AppLogoIcon className="size-11" />
                    </span>

                    <div className="space-y-3">
                        <h1 className="text-4xl font-extrabold">
                            O&apos;qi AI
                        </h1>
                        <p className="text-base text-muted-foreground">
                            Farzandingiz ovoz chiqarib o&apos;qiydi, ustoz
                            tinglaydi va qiyin so&apos;zlarda yordam beradi.
                        </p>
                    </div>

                    <div className="flex w-full flex-col gap-3">
                        {auth.user ? (
                            <Button size="lg" className="w-full" asChild>
                                <Link href={dashboard()}>Davom etish</Link>
                            </Button>
                        ) : (
                            <>
                                <Button size="lg" className="w-full" asChild>
                                    <Link href={register()}>
                                        Ro&apos;yxatdan o&apos;tish
                                    </Link>
                                </Button>
                                <Button
                                    size="lg"
                                    variant="outline"
                                    className="w-full"
                                    asChild
                                >
                                    <Link href={login()}>Kirish</Link>
                                </Button>
                            </>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}
