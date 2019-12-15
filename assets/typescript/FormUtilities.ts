function extractFormData(form: HTMLFormElement, resetSource: boolean = false): FormData {
    const formData: FormData = new FormData;

    for(let i = 0; i < form.length; i++) {
        let input: HTMLInputElement = form[i] as HTMLInputElement,
            type = input.type.toLowerCase(),
            isCheckbox = type === 'checkbox';

        if(isCheckbox && !input.checked)
            continue;

        formData.append(input.name, input.value || '');
    }

    if(resetSource)
        resetForm(form);

    return formData;
}

interface FormHiddenDefault {
    Name: string;
    Value: string;
}

function resetForm(form: HTMLFormElement, defaults: FormHiddenDefault[] = []): void {
    for(let i = 0; i < form.length; i++) {
        let input: HTMLInputElement = form[i] as HTMLInputElement;

        switch(input.type.toLowerCase()) {
            case 'checkbox':
                input.checked = false;
                break;

            case 'hidden':
                let hiddenDefault: FormHiddenDefault = defaults.find(fhd => fhd.Name.toLowerCase() === input.name.toLowerCase());

                if(hiddenDefault)
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

function updateCSRF(token: string): void {
    if(token === null) {
        return;
    }

    document.querySelector('[name="csrf-token"]').setAttribute('value', CSRFToken = token);

    const elements: NodeListOf<HTMLInputElement> = document.getElementsByName('csrf') as NodeListOf<HTMLInputElement>;

    for(let i = 0; i < elements.length; i++) {
        elements[i].value = token;
    }
}

function handleDataRequestMethod(elem: HTMLAnchorElement, method: string, url: string): void {
    const split: string[] = url.split('?', 2),
        target: string = split[0],
        query: string = split[1] || null;

    if(elem.getAttribute('disabled'))
        return;
    elem.setAttribute('disabled', 'disabled');

    const xhr: XMLHttpRequest = new XMLHttpRequest;
    xhr.onreadystatechange = function(ev) {
        if(xhr.readyState !== 4)
            return;
        elem.removeAttribute('disabled');

        if(xhr.status === 301 || xhr.status === 302 || xhr.status === 307 || xhr.status === 308) {
            location.assign(xhr.getResponseHeader('X-Misuzu-Location'));
            return;
        }

        if(xhr.status >= 400 && xhr.status <= 599) {
            alert(xhr.responseText);
            return;
        }
    };
    xhr.open(method, target);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Misuzu-CSRF', getCSRFToken());
    xhr.setRequestHeader('X-Misuzu-XHR', '1');
    xhr.send(query);
}

function initDataRequestMethod(): void {
    const links: HTMLCollection = document.links;

    for(let i = 0; i < links.length; i++) {
        let elem: HTMLAnchorElement = links[i] as HTMLAnchorElement;

        if(!elem.href || !elem.dataset || !elem.dataset.mszMethod)
            continue;

        elem.onclick = function(ev) {
            ev.preventDefault();
            handleDataRequestMethod(elem, elem.dataset.mszMethod, elem.href);
        };
    }
}
