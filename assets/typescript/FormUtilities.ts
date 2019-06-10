function extractFormData(form: HTMLFormElement, resetSource: boolean = false): FormData
{
    const formData: FormData = new FormData;

    for (let i = 0; i < form.length; i++) {
        let input: HTMLInputElement = form[i] as HTMLInputElement,
            type = input.type.toLowerCase(),
            isCheckbox = type === 'checkbox';

        if (isCheckbox && !input.checked)
            continue;

        formData.append(input.name, input.value || '');
    }

    if (resetSource)
        resetForm(form);

    return formData;
}

interface FormHiddenDefault {
    Name: string;
    Value: string;
}

function resetForm(form: HTMLFormElement, defaults: FormHiddenDefault[] = []): void
{
    for (let i = 0; i < form.length; i++) {
        let input: HTMLInputElement = form[i] as HTMLInputElement;

        switch (input.type.toLowerCase()) {
            case 'checkbox':
                input.checked = false;
                break;

            case 'hidden':
                let hiddenDefault: FormHiddenDefault = defaults.find(fhd => fhd.Name.toLowerCase() === input.name.toLowerCase());

                if (hiddenDefault)
                    input.value = hiddenDefault.Value;
                break;

            default:
                input.value = '';
        }
    }
}

let CSRFToken: string;

function initCSRF(): void {
    CSRFToken = document.querySelector('[name="csrf-token"]').getAttribute('value');
}

function getCSRFToken(): string {
    return CSRFToken;
}

function updateCSRF(token: string): void
{
    if (token === null) {
        return;
    }

    document.querySelector('[name="csrf-token"]').setAttribute('value', CSRFToken = token);

    const elements: NodeListOf<HTMLInputElement> = document.getElementsByName('csrf') as NodeListOf<HTMLInputElement>;

    for (let i = 0; i < elements.length; i++) {
        elements[i].value = token;
    }
}
