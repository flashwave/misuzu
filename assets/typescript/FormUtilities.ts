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

function getRawCSRFTokenList(): CSRFToken[]
{
    const csrfTokenList: HTMLDivElement = document.getElementById('js-csrf-tokens') as HTMLDivElement;

    if (!csrfTokenList)
        return [];

    return JSON.parse(csrfTokenList.textContent) as CSRFToken[];
}

class CSRFToken {
    realm: string;
    token: string;
}

let CSRFTokenStore: CSRFToken[] = [];

function initCSRF(): void {
    CSRFTokenStore = getRawCSRFTokenList();
}

function getCSRF(realm: string): CSRFToken {
    return CSRFTokenStore.find(i => i.realm.toLowerCase() === realm.toLowerCase());
}

function getCSRFToken(realm: string): string {
    return getCSRF(realm).token || '';
}

function setCSRF(realm: string, token: string): void {
    let csrf: CSRFToken = getCSRF(realm);

    if (csrf) {
        csrf.token = token;
    } else {
        csrf = new CSRFToken;
        csrf.realm = realm;
        csrf.token = token;
        CSRFTokenStore.push(csrf);
    }
}

function updateCSRF(token: string, realm: string = null, name: string = 'csrf'): void
{
    if (token === null) {
        return;
    }

    const tokenSplit: string[] = token.split(';');

    if (tokenSplit.length > 1) {
        token = tokenSplit[1];

        if (!realm) {
            realm = tokenSplit[0];
        }
    }

    setCSRF(realm, token);

    const elements: NodeListOf<HTMLInputElement> = document.getElementsByName(`${name}[${realm}]`) as NodeListOf<HTMLInputElement>;

    for (let i = 0; i < elements.length; i++) {
        elements[i].value = token;
    }
}
