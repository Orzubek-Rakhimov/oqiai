import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ChildController from '@/actions/App/Http/Controllers/Parent/ChildController';
import ChildFormFields from '@/components/child-form-fields';
import { Button } from '@/components/ui/button';
import { home as parentHome } from '@/routes/parent';
import type { AvatarKey } from '@/types';

export default function CreateChild({
    isFirstChild,
    avatars,
}: {
    isFirstChild: boolean;
    avatars: AvatarKey[];
}) {
    const title = isFirstChild
        ? "Birinchi farzandingizni qo'shing"
        : "Farzand qo'shish";

    return (
        <>
            <Head title={title} />

            {!isFirstChild && (
                <Link
                    href={parentHome()}
                    className="mb-4 inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-muted-foreground"
                >
                    <ArrowLeft className="size-4" />
                    Orqaga
                </Link>
            )}

            <div className="mb-6 space-y-2">
                <h1 className="text-3xl font-extrabold">{title}</h1>
                <p className="text-muted-foreground">
                    {isFirstChild
                        ? "Farzandingiz o'z rasmini tanib, o'qishni boshlaydi. Faqat ismi, sinfi va rasmi kerak."
                        : "Har bir farzandning o'z natijalari bo'ladi."}
                </p>
            </div>

            <Form
                {...ChildController.store.form()}
                className="space-y-6 rounded-3xl border-2 bg-card p-5"
            >
                {({ processing, errors }) => (
                    <>
                        <ChildFormFields avatars={avatars} errors={errors} />

                        <Button
                            size="lg"
                            className="h-15 w-full rounded-full text-base"
                            disabled={processing}
                            data-test="create-child-button"
                        >
                            Saqlash
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
