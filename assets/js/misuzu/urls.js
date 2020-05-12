Misuzu.Urls = {};
Misuzu.Urls.registry = [];
Misuzu.Urls.loadFromDocument = function() {
    var elem = document.getElementById('js-urls-list');
    if(!elem)
        return;
    Misuzu.Urls.registry = JSON.parse(elem.textContent);
};
Misuzu.Urls.handleVariable = function(value, vars) {
    if(value[0] === '<' && value.slice(-1) === '>')
        return (vars.find(function(x) { return x.name == value.slice(1, -1); }) || {}).value || '';
    if(value[0] === '[' && value.slice(-1) === ']')
        return ''; // not sure if there's a proper substitute for this, should probably resolve these in url_list
    if(value[0] === '{' && value.slice(-1) === '}')
        return Misuzu.CSRF.getToken();
    return value;
};
Misuzu.Urls.v = function(name, value) {
    if(typeof value === 'undefined' || value === null)
        value = '';
    return { name: name.toString(), value: value.toString() };
};
Misuzu.Urls.format = function(name, vars) {
    vars = vars || [];
    var entry = Misuzu.Urls.registry.find(function(x) { return x.name == name; });
    if(!entry || !entry.path)
        return '';

    var split = entry.path.split('/');
    for(var i = 0; i < split.length; ++i)
        split[i] = Misuzu.Urls.handleVariable(split[i], vars);

    var url = split.join('/');

    if(entry.query) {
        url += '?';

        for(var i = 0; i < entry.query.length; ++i) {
            var query = entry.query[i],
                value = Misuzu.Urls.handleVariable(query.value, vars);

            if(!value || (query.name === 'page' && parseInt(value) < 2))
                continue;

            url += query.name + '=' + value.toString() + '&';
        }

        url = url.replace(/^[\?\&]+|[\?\&]+$/g, '');
    }

    if(entry.fragment)
        url += ('#' + Misuzu.Urls.handleVariable(entry.fragment, vars)).replace(/[\#]+$/g, '');

    return url;
};