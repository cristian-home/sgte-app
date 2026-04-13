import { useForm } from '@inertiajs/react';
import VehicleController from '@/actions/App/Http/Controllers/VehicleController';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import VehicleForm, {
    type ThirdPartyOption,
} from '@/components/vehicles/vehicle-form';

interface VehicleCreateDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    municipalities: MunicipalityOption[];
    thirdParties: ThirdPartyOption[];
}

export default function VehicleCreateDialog({
    open,
    onOpenChange,
    municipalities,
    thirdParties,
}: VehicleCreateDialogProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        internal_code: '',
        plate: '',
        mobile_number: '',
        brand: '',
        line: '',
        model_year: '',
        type: '',
        engine_number: '',
        chassis_number: '',
        capacity: '',
        municipality_id: '',
        is_third_party: false,
        third_party_id: '',
        soat_due_date: '',
        rtm_due_date: '',
        operation_card_due_date: '',
        status: 'active',
    });

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        post(VehicleController.store().url, {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    }

    function handleOpenChange(value: boolean) {
        if (!value) {
            reset();
        }
        onOpenChange(value);
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="flex max-h-[calc(100vh-4rem)] flex-col px-0 sm:max-w-2xl">
                <DialogHeader className="px-6">
                    <DialogTitle>Crear Vehículo</DialogTitle>
                    <DialogDescription>
                        Complete los campos para registrar un nuevo vehículo.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <div className="flex-1 space-y-6 overflow-y-auto px-6 py-2">
                        <VehicleForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            municipalities={municipalities}
                            thirdParties={thirdParties}
                            idPrefix="dlg"
                        />
                    </div>

                    <DialogFooter className="mt-4 gap-2 px-6">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
