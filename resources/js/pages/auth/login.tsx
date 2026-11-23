import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PhoneInput from '@/components/phone-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';

export default function Login() {
    return (
        <>
            <Head title="Kirish" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="phone">Telefon raqami</Label>
                                <PhoneInput
                                    id="phone"
                                    name="phone"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                />
                                <InputError message={errors.phone} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Parol</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Parol"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Meni eslab qol</Label>
                            </div>

                            <Button
                                type="submit"
                                size="lg"
                                className="mt-2 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Kirish
                            </Button>
                        </div>

                        <p className="text-center text-sm text-muted-foreground">
                            Parolni unutdingizmi? Bizga yozing, tiklab beramiz.
                        </p>

                        <div className="text-center text-sm text-muted-foreground">
                            Hisobingiz yo&apos;qmi?{' '}
                            <TextLink href={register()} tabIndex={5}>
                                Ro&apos;yxatdan o&apos;tish
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Xush kelibsiz!',
    description: 'Telefon raqamingiz va parolingiz bilan kiring',
};
