import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ChildController from '@/actions/App/Http/Controllers/Parent/ChildController';
import ChildFormFields from '@/components/child-form-fields';
import { Button } from '@/components/ui/button';
import { home as parentHome } from '@/routes/parent';
import type { AvatarKey, Child } from '@/types';

export default function EditChild({
    child,
    avatars,
}: {
    child: Child;
    avatars: AvatarKey[];
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
        </>
    );
}
