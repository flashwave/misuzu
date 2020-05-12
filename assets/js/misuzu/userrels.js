Misuzu.UserRelations = {};
Misuzu.UserRelations.Type = DefineEnum({ none: 0, follow: 1, });
Misuzu.UserRelations.init = function() {
    var buttons = document.getElementsByClassName('js-user-relation-action');

    for(var i = 0; i < buttons.length; ++i) {
        switch(buttons[i].tagName.toLowerCase()) {
            case 'a':
                buttons[i].removeAttribute('href');
                buttons[i].removeAttribute('target');
                buttons[i].removeAttribute('rel');
                break;
        }

        buttons[i].addEventListener('click', Misuzu.UserRelations.setRelationHandler);
    }
};
Misuzu.UserRelations.setRelation = function(user, type, onSuccess, onFailure) {
    var xhr = new XMLHttpRequest;
    xhr.addEventListener('readystatechange', function() {
        if(xhr.readyState !== 4)
            return;

        Misuzu.CSRF.setToken(xhr.getResponseHeader('X-Misuzu-CSRF'));

        var json = JSON.parse(xhr.responseText),
            message = json.error || json.message;

        if(message && onFailure)
            onFailure(message);
        else if(!message && onSuccess)
            onSuccess(json);
    });
    xhr.open('GET', Misuzu.Urls.format('user-relation-create', [Misuzu.Urls.v('user', user), Misuzu.Urls.v('type', type)]));
    xhr.setRequestHeader('X-Misuzu-XHR', 'user_relation');
    xhr.setRequestHeader('X-Misuzu-CSRF', Misuzu.CSRF.getToken());
    xhr.send();
};
Misuzu.UserRelations.ICO_ADD = 'fas fa-user-plus';
Misuzu.UserRelations.ICO_REM = 'fas fa-user-minus';
Misuzu.UserRelations.ICO_BUS = 'fas fa-spinner fa-pulse';
Misuzu.UserRelations.BTN_BUS = 'input__button--busy';
Misuzu.UserRelations.setRelationHandler = function(ev) {
    var target       = this,
        userId       = parseInt(target.dataset.relationUser),
        relationType = parseInt(target.dataset.relationType),
        isButton     = target.classList.contains('input__button'),
        icon         = target.querySelector('[class^="fa"]');

    if(isButton) {
        if(target.classList.contains(Misuzu.UserRelations.BTN_BUS))
            return;
        target.classList.add(Misuzu.UserRelations.BTN_BUS);
    }

    if(icon)
        icon.className = Misuzu.UserRelations.ICO_BUS;

    Misuzu.UserRelations.setRelation(
        userId,
        relationType,
        function(info) {
            target.classList.remove(Misuzu.UserRelations.BTN_BUS);

            switch(info.relation_type) {
                case Misuzu.UserRelations.Type.none:
                    if(isButton) {
                        if(target.classList.contains('input__button--destroy'))
                            target.classList.remove('input__button--destroy');

                        target.textContent = 'Follow';
                    }

                    if(icon) {
                        icon.className = Misuzu.UserRelations.ICO_ADD;
                        target.title = 'Follow';
                    }

                    target.dataset.relationType = Misuzu.UserRelations.Type.follow.toString();
                    break;

                case Misuzu.UserRelations.Type.follow:
                    if(isButton) {
                        if(!target.classList.contains('input__button--destroy'))
                            target.classList.add('input__button--destroy');

                        target.textContent = 'Unfollow';
                    }

                    if(icon) {
                        icon.className = Misuzu.UserRelations.ICO_REM;
                        target.title = 'Unfollow';
                    }

                    target.dataset.relationType = Misuzu.UserRelations.Type.none.toString();
                    break;
            }
        },
        function(msg) {
            target.classList.remove(Misuzu.UserRelations.BTN_BUS);
            Misuzu.showMessageBox(msg);
        }
    );
};
