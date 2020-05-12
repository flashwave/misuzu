Misuzu.FormUtils = {};
Misuzu.FormUtils.extractFormData = function(form, resetSource) {
    var formData = new FormData;

    for(var i = 0; i < form.length; ++i) {
        if(form[i].type.toLowerCase() === 'checkbox' && !form[i].checked)
            continue;
        formData.append(form[i].name, form[i].value || '');
    }

    if(resetSource)
        Misuzu.FormUtils.resetFormData(form);

    return formData;
};
Misuzu.FormUtils.resetFormData = function(form, defaults) {
    defaults = defaults || [];

    for(var i = 0; i < form.length; ++i) {
        var input = form[i];

        switch(input.type.toLowerCase()) {
            case 'checkbox':
                input.checked = false;
                break;

            case 'hidden':
                var hiddenDefault = defaults.find(function(fhd) { return fhd.Name.toLowerCase() === input.name.toLowerCase(); });
                if(hiddenDefault)
                    input.value = hiddenDefault.Value;
                break;

            default:
                input.value = '';
        }
    }
};
Misuzu.FormUtils.initDataRequestMethod = function() {
    var links = document.links;

    for(var i = 0; i < links.length; ++i) {
        if(!links[i].href || !links[i].dataset || !links[i].dataset.mszMethod)
            continue;

        links[i].addEventListener('click', function(ev) {
            Misuzu.FormUtils.handleDataRequestMethod(this, this.dataset.mszMethod, this.href);
            ev.preventDefault();
        });
    }
};
Misuzu.FormUtils.handleDataRequestMethod = function(elem, method, url) {
    var split  = url.split('?', 2),
        target = split[0],
        query  = split[1] || null;

    if(elem.getAttribute('disabled'))
        return;
    elem.setAttribute('disabled', 'disabled');

    var xhr = new XMLHttpRequest;
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
    xhr.setRequestHeader('X-Misuzu-CSRF', Misuzu.CSRF.getToken());
    xhr.setRequestHeader('X-Misuzu-XHR', '1');
    xhr.send(query);
};
