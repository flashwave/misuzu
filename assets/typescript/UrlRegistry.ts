interface UrlRegistryVariable {
    name: string;
    value: string | number;
}

interface UrlRegistryEntryQuery {
    name: string;
    value: string;
}

interface UrlRegistryEntry {
    name: string;
    path: string;
    query: UrlRegistryEntryQuery[];
    fragment: string;
}

let urlRegistryTable: UrlRegistryEntry[] = [];

function getRawUrlRegistry(): UrlRegistryEntry[] {
    const urlListElement: HTMLElement = document.getElementById('js-urls-list') as HTMLElement;

    if(!urlListElement)
        return null;

    return JSON.parse(urlListElement.textContent) as UrlRegistryEntry[];
}

function urlRegistryInit(): void {
    urlRegistryTable = getRawUrlRegistry();
}

function urlFormat(name: string, vars: UrlRegistryVariable[] = []): string {
    const entry: UrlRegistryEntry = urlRegistryTable.find(x => x.name == name);

    if(!entry || !entry.path) {
        return '';
    }

    const splitUrl: string[] = entry.path.split('/');

    for(let i = 0; i < splitUrl.length; i++) {
        splitUrl[i] = urlVariable(splitUrl[i], vars);
    }

    let url: string = splitUrl.join('/');

    if(entry.query) {
        url += '?';

        for(let i = 0; i < entry.query.length; i++) {
            const query: UrlRegistryEntryQuery = entry.query[i],
                value: string = urlVariable(query.value, vars);

            if(!value || (query.name === 'page' && parseInt(value) < 2)) {
                continue;
            }

            url += `${query.name}=${value}&`;
        }

        url = url.replace(/^[\?\&]+|[\?\&]+$/g, '');
    }

    if(entry.fragment) {
        url += ('#' + urlVariable(entry.fragment, vars)).replace(/[\#]+$/g, '');
    }

    return url;
}

function urlVariable(value: string, vars: UrlRegistryVariable[]): string {
    if(value[0] === '<' && value.slice(-1) === '>') {
        const urvar: UrlRegistryVariable = vars.find(x => x.name == value.slice(1, -1));
        return urvar ? urvar.value.toString() : '';
    }

    if(value[0] === '[' && value.slice(-1) === ']') {
        return ''; // not sure if there's a proper substitute for this, should probably resolve these in url_list
    }

    if(value[0] === '{' && value.slice(-1) === '}') {
        return getCSRFToken();
    }

    return value;
}
