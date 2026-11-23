import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Mic, MicOff } from 'lucide-react';
import ChildController from '@/actions/App/Http/Controllers/Parent/ChildController';
import MicrophoneCheckController from '@/actions/App/Http/Controllers/Parent/MicrophoneCheckController';
import ChildFormFields from '@/components/child-form-fields';
import { Button } from '@/components/ui/button';
import { home as parentHome } from '@/routes/parent';
import type { AvatarKey, Child } from '@/types';

export default function EditChild({
    child,
    avatars,
    microphoneChecked,
}: {
    child: Child;
    avatars: AvatarKey[];
    microphoneChecked: boolean;
}) {
    return (
        <>
            <Head title="Profilni tahrirlash" />

            <Link
                href={parentHome()}
                className="mb-4 inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-muted-foreground"
            >
                <ArrowLeft className="size-4" />
                Orqaga
            </Link>

            <h1 className="mb-6 text-3xl font-extrabold">
                Profilni tahrirlash
            </h1>

            <Form
                {...ChildController.update.form(child.id)}
                className="space-y-6 rounded-3xl border-2 bg-card p-5"
            >
                {({ processing, errors }) => (
                    <>
                        <ChildFormFields
                            avatars={avatars}
                            child={child}
                            errors={errors}
                        />

                        <Button
                            size="lg"
                            className="h-15 w-full rounded-full text-base"
                            disabled={processing}
                            data-test="update-child-button"
                        >
                            Saqlash
                        </Button>
                    </>
                )}
            </Form>

            <div className="mt-6 flex items-center gap-4 rounded-3xl border-2 bg-card p-5">
                {microphoneChecked ? (
                    <Mic className="size-6 shrink-0 text-success" />
                ) : (
                    <MicOff className="size-6 shrink-0 text-muted-foreground" />
                )}
                <div className="flex-1">
                    <p className="font-bold">Mikrofon</p>
                    <p className="text-sm text-muted-foreground">
                        {microphoneChecked
                            ? 'Tekshirilgan: ustoz eshitadi.'
                            : 'Hali tekshirilmagan.'}
                    </p>
                </div>
                <Button
                    asChild
                    variant="outline"
                    className="min-h-11 rounded-full"
                >
                    <Link
                        href={MicrophoneCheckController.show(child.id)}
                        data-test="microphone-check-link"
                    >
                        Tekshirish
                    </Link>
                </Button>
            </div>
        </>
    );
}
