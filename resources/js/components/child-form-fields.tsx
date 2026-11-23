import ChildAvatar, { avatarOptions } from '@/components/child-avatar';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { AvatarKey, Child } from '@/types';

const grades = [1, 2, 3, 4];

const chipClassName =
    'flex cursor-pointer items-center justify-center rounded-[20px] border-2 bg-card font-heading font-extrabold transition-colors has-checked:border-foreground has-checked:bg-foreground has-checked:text-background has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50';

/**
 * Name, Grade and avatar: the only details a Child profile asks for.
 */
export default function ChildFormFields({
    avatars,
    child,
    errors,
}: {
    avatars: AvatarKey[];
    child?: Child;
    errors: Partial<Record<'name' | 'grade' | 'avatar', string>>;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="name">Ismi</Label>
                <Input
                    id="name"
                    name="name"
                    defaultValue={child?.name}
                    required
                    maxLength={50}
                    autoComplete="off"
                    placeholder="Masalan: Ali"
                />
                <InputError message={errors.name} />
            </div>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">Sinfi</legend>
                <div className="grid grid-cols-4 gap-2">
                    {grades.map((grade) => (
                        <label
                            key={grade}
                            className={cn(chipClassName, 'h-14 text-lg')}
                        >
                            <input
                                type="radio"
                                name="grade"
                                value={grade}
                                defaultChecked={child?.grade === grade}
                                className="sr-only"
                            />
                            {grade}-sinf
                        </label>
                    ))}
                </div>
                <InputError message={errors.grade} />
            </fieldset>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">
                    Rasmini tanlang
                </legend>
                <div className="grid grid-cols-3 gap-3">
                    {avatars.map((key) => (
                        <label
                            key={key}
                            className={cn(
                                chipClassName,
                                'flex-col gap-1 rounded-3xl py-3 text-sm',
                            )}
                        >
                            <input
                                type="radio"
                                name="avatar"
                                value={key}
                                defaultChecked={child?.avatar === key}
                                className="sr-only"
                            />
                            <ChildAvatar avatar={key} />
                            {avatarOptions[key].label}
                        </label>
                    ))}
                </div>
                <InputError message={errors.avatar} />
            </fieldset>
        </>
    );
}
