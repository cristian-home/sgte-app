import { useForm } from '@inertiajs/react';
import { Eye, EyeOff, Mail } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    store as usersStore,
    update as usersUpdate,
} from '@/actions/App/Http/Controllers/UserController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import {
    PasswordStrengthMeter,
    passwordScore,
} from './password-strength-meter';
import { RoleMultiCombobox, type RoleOption } from './role-multi-combobox';

export interface UserFormUser {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    roles: { id: number; name: string; label: string }[];
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    availableRoles: RoleOption[];
    user?: UserFormUser | null;
}

export function UserDialog({
    open,
    onOpenChange,
    mode,
    availableRoles,
    user,
}: Props) {
    const [showPassword, setShowPassword] = useState(false);

    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        roles: [] as string[],
        is_active: true,
        send_welcome_email: false,
    });

    useEffect(() => {
        if (!open) return;
        if (mode === 'edit' && user) {
            form.setData({
                name: user.name,
                email: user.email,
                password: '',
                password_confirmation: '',
                roles: user.roles.map((r) => r.name),
                is_active: user.is_active,
                send_welcome_email: false,
            });
        } else {
            form.setData({
                name: '',
                email: '',
                password: '',
                password_confirmation: '',
                roles: [],
                is_active: true,
                send_welcome_email: false,
            });
        }
        form.clearErrors();
        setShowPassword(false);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, mode, user?.id]);

    const passwordsMismatch =
        mode === 'create' &&
        form.data.password !== '' &&
        form.data.password !== form.data.password_confirmation;

    const isValid = useMemo(() => {
        if (form.data.name.trim() === '') return false;
        if (!form.data.email.includes('@')) return false;
        if (form.data.roles.length === 0) return false;
        if (mode === 'create') {
            if (passwordScore(form.data.password) < 1) return false;
            if (form.data.password.length < 8) return false;
            if (form.data.password !== form.data.password_confirmation)
                return false;
        }
        return true;
    }, [form.data, mode]);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!isValid) return;
        if (mode === 'create') {
            form.post(usersStore().url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else if (user) {
            form.put(usersUpdate(user.id).url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[580px]">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {mode === 'create'
                                ? 'Nuevo usuario'
                                : 'Editar usuario'}
                        </DialogTitle>
                        <DialogDescription>
                            {mode === 'create'
                                ? 'Crea una cuenta para que alguien acceda al sistema.'
                                : 'Actualiza la información de la cuenta.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="user-name">
                                    Nombre completo
                                </Label>
                                <Input
                                    id="user-name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    placeholder="María Fernanda González"
                                    aria-invalid={!!form.errors.name}
                                />
                                {form.errors.name && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.name}
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="user-email">
                                    Correo electrónico
                                </Label>
                                <Input
                                    id="user-email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                    placeholder="nombre@sgte.app"
                                    aria-invalid={!!form.errors.email}
                                />
                                {form.errors.email && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.email}
                                    </p>
                                )}
                            </div>
                        </div>

                        {mode === 'create' && (
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="user-password">
                                        Contraseña
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id="user-password"
                                            type={
                                                showPassword
                                                    ? 'text'
                                                    : 'password'
                                            }
                                            value={form.data.password}
                                            onChange={(e) =>
                                                form.setData(
                                                    'password',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Mín. 8 caracteres"
                                            className="pr-9"
                                            aria-invalid={
                                                !!form.errors.password
                                            }
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setShowPassword((s) => !s)
                                            }
                                            className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                            aria-label={
                                                showPassword
                                                    ? 'Ocultar contraseña'
                                                    : 'Mostrar contraseña'
                                            }
                                        >
                                            {showPassword ? (
                                                <EyeOff className="size-4" />
                                            ) : (
                                                <Eye className="size-4" />
                                            )}
                                        </button>
                                    </div>
                                    <PasswordStrengthMeter
                                        password={form.data.password}
                                    />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="user-password-confirm">
                                        Confirmar contraseña
                                    </Label>
                                    <Input
                                        id="user-password-confirm"
                                        type={
                                            showPassword ? 'text' : 'password'
                                        }
                                        value={form.data.password_confirmation}
                                        onChange={(e) =>
                                            form.setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={passwordsMismatch}
                                    />
                                    {passwordsMismatch && (
                                        <p className="text-xs text-destructive">
                                            Las contraseñas no coinciden.
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="user-roles">Roles</Label>
                            <RoleMultiCombobox
                                id="user-roles"
                                options={availableRoles}
                                value={form.data.roles}
                                onChange={(next) => form.setData('roles', next)}
                                invalid={!!form.errors.roles}
                                placeholder="Selecciona roles…"
                            />
                            <p className="text-xs text-muted-foreground">
                                Los permisos se acumulan cuando un usuario tiene
                                más de un rol.
                            </p>
                            {form.errors.roles && (
                                <p className="text-xs text-destructive">
                                    {form.errors.roles}
                                </p>
                            )}
                        </div>

                        <Separator />

                        <div className="flex items-start gap-3">
                            <Switch
                                id="user-active"
                                checked={form.data.is_active}
                                onCheckedChange={(v) =>
                                    form.setData('is_active', v)
                                }
                            />
                            <div className="flex flex-col">
                                <Label htmlFor="user-active">
                                    Cuenta activa
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    {form.data.is_active
                                        ? 'El usuario puede iniciar sesión.'
                                        : 'El usuario no podrá iniciar sesión.'}
                                </p>
                            </div>
                        </div>

                        {mode === 'create' && (
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="user-welcome"
                                    checked={form.data.send_welcome_email}
                                    onCheckedChange={(v) =>
                                        form.setData(
                                            'send_welcome_email',
                                            v === true,
                                        )
                                    }
                                />
                                <div className="flex flex-1 flex-col">
                                    <Label
                                        htmlFor="user-welcome"
                                        className="flex items-center gap-1.5"
                                    >
                                        <Mail className="size-3.5" />
                                        Enviar correo de bienvenida
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Con instrucciones para configurar su
                                        contraseña.
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={!isValid || form.processing}
                            className={cn(form.processing && 'opacity-70')}
                        >
                            {mode === 'create'
                                ? 'Guardar usuario'
                                : 'Guardar cambios'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
