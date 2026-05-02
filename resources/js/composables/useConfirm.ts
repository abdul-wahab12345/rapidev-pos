import { ref } from 'vue';

export type ConfirmVariant = 'danger' | 'warning' | 'info';

export interface ConfirmOptions {
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: ConfirmVariant;
}

const open = ref(false);
const options = ref<ConfirmOptions>({
    title: '',
    message: '',
    variant: 'danger',
});

let resolveFn: ((value: boolean) => void) | null = null;

export function useConfirm() {
    function confirm(opts: ConfirmOptions): Promise<boolean> {
        options.value = {
            variant: 'danger',
            ...opts,
        };
        open.value = true;

        return new Promise<boolean>((resolve) => {
            resolveFn = resolve;
        });
    }

    function accept() {
        open.value = false;
        resolveFn?.(true);
        resolveFn = null;
    }

    function cancel() {
        open.value = false;
        resolveFn?.(false);
        resolveFn = null;
    }

    return { confirm, accept, cancel, open, options };
}
