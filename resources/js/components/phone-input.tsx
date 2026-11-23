import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';

/**
 * A phone number field for Uzbek numbers. The server accepts the number with
 * or without +998, spaces or dashes, so the field takes free text.
 */
export default function PhoneInput(
    props: Omit<ComponentProps<'input'>, 'type'>,
) {
    return (
        <Input
            type="tel"
            inputMode="tel"
            autoComplete="tel"
            placeholder="+998 90 123 45 67"
            {...props}
        />
    );
}
