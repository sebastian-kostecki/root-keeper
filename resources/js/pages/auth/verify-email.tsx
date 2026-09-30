// Components
import { Form, Head } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Weryfikacja adresu e-mail" />

            {status === 'verification-link-sent' && (
                <Alert>
                    <CircleCheck />
                    <AlertTitle>Wysłaliśmy nowy link</AlertTitle>
                    <AlertDescription>
                        Sprawdź skrzynkę na adres podany przy rejestracji.
                    </AlertDescription>
                </Alert>
            )}

            <Form {...send.form()} className="flex flex-col gap-6 text-center">
                {({ processing }) => (
                    <>
                        <Button
                            disabled={processing}
                            variant="secondary"
                            className="w-full"
                        >
                            {processing && <Spinner />}
                            Wyślij link ponownie
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            Wyloguj się
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Potwierdź adres e-mail',
    description:
        'Wysłaliśmy link na adres podany przy rejestracji. Kliknij go, aby wejść do drzewa.',
};
