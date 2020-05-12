Misuzu.CSRF = {};
Misuzu.CSRF.tokenValue = undefined;
Misuzu.CSRF.tokenElement = undefined;
Misuzu.CSRF.init = function() {
    Misuzu.CSRF.tokenElement = document.querySelector('[name="csrf-token"]');
    Misuzu.CSRF.tokenValue = Misuzu.CSRF.tokenElement.getAttribute('value');
};
Misuzu.CSRF.getToken = function() { return Misuzu.CSRF.tokenValue || ''; };
Misuzu.CSRF.setToken = function(token) {
    if(!token)
        return;
    Misuzu.CSRF.tokenElement.setAttribute('value', Misuzu.CSRF.tokenValue = token);

    var elems = document.getElementsByName('csrf');
    for(var i = 0; i < elems.length; ++i)
        elems[i].value = token;
};
