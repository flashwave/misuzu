function ExtractFormData(form: HTMLFormElement, resetSource: boolean = false): FormData
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
        ResetForm(form);

    return formData;
}

interface FormHiddenDefault {
    Name: string;
    Value: string;
}

function ResetForm(form: HTMLFormElement, defaults: FormHiddenDefault[] = []): void
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
