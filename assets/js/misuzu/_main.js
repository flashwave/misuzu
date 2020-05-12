var Misuzu = function() {
    if(Misuzu.initialised)
        throw 'Misuzu script has already initialised.';
    Misuzu.started = true;

    console.log(
        "%cMisuzu%c\nhttps://github.com/flashwave/misuzu",
        'font-size: 48px; color: #8559a5; background: #111;'
        + 'border-radius: 5px; padding: 0 10px; text-shadow: 0 0 1em #fff;',
    );

    timeago.render(document.querySelectorAll('time'));
    hljs.initHighlighting();

    Misuzu.CSRF.init();
    Misuzu.Urls.loadFromDocument();
    Misuzu.User.refreshLocalUser();
    Misuzu.UserRelations.init();
    Misuzu.FormUtils.initDataRequestMethod();
    Misuzu.initQuickSubmit();
    Misuzu.Comments.init();
    Misuzu.Forum.Editor.init();
    Misuzu.Forum.Polls.init();

    if(Misuzu.User.isLoggedIn())
        console.log(
            'You are %s with user id %d and colour %s.',
            Misuzu.User.localUser.getUsername(),
            Misuzu.User.localUser.getId(),
            Misuzu.User.localUser.getColour().getCSS()
        );
    else
        console.log('You aren\'t logged in.');

    Misuzu.Events.dispatch();

    Misuzu.initLoginPage();
};
Misuzu.Parser = DefineEnum({
    plain:    0,
    bbcode:   1,
    markdown: 2,
});
Misuzu.supportsSidewaysText = function() { return CSS.supports('writing-mode', 'sideways-lr'); };
Misuzu.showMessageBox = function(text, title, buttons) {
    if(document.querySelector('.messagebox'))
        return false;

    text = text || '';
    title = title || '';
    buttons = buttons || [];

    var element = document.createElement('div');
    element.className = 'messagebox';

    var container = element.appendChild(document.createElement('div'));
    container.className = 'container messagebox__container';

    var titleElement = container.appendChild(document.createElement('div')),
        titleBackground = titleElement.appendChild(document.createElement('div')),
        titleText = titleElement.appendChild(document.createElement('div'));

    titleElement.className = 'container__title';
    titleBackground.className = 'container__title__background';
    titleText.className = 'container__title__text';
    titleText.textContent = title || 'Information';

    var textElement = container.appendChild(document.createElement('div'));
    textElement.className = 'container__content';
    textElement.textContent = text;

    var buttonsContainer = container.appendChild(document.createElement('div'));
    buttonsContainer.className = 'messagebox__buttons';

    var firstButton = null;

    if(buttons.length < 1) {
        firstButton = buttonsContainer.appendChild(document.createElement('button'));
        firstButton.className = 'input__button';
        firstButton.textContent = 'OK';
        firstButton.addEventListener('click', function() { element.remove(); });
    } else {
        for(var i = 0; i < buttons.length; i++) {
            var button = buttonsContainer.appendChild(document.createElement('button'));
            button.className = 'input__button';
            button.textContent = buttons[i].text;
            button.addEventListener('click', function() {
                element.remove();
                buttons[i].callback();
            });

            if(firstButton === null)
                firstButton = button;
        }
    }

    document.body.appendChild(element);
    firstButton.focus();
    return true;
};
Misuzu.initLoginPage = function() {
    var updateForm = function(avatarElem, usernameElem) {
        var xhr = new XMLHttpRequest;
        xhr.addEventListener('readystatechange', function() {
            if(xhr.readyState !== 4)
                return;

            avatarElem.src = Misuzu.Urls.format('user-avatar', [
                { name: 'user', value: xhr.responseText.indexOf('<') !== -1 ? '0' : xhr.responseText },
                { name: 'res', value: 100 },
            ]);
        });
        xhr.open('GET', Misuzu.Urls.format('auth-resolve-user', [{name: 'username', value: encodeURIComponent(usernameElem.value)}]));
        xhr.send();
    };

    var loginForms = document.getElementsByClassName('js-login-form');

    for(var i = 0; i < loginForms.length; ++i)
        (function(form) {
            var loginTimeOut = 0,
                loginAvatar = form.querySelector('.js-login-avatar'),
                loginUsername = form.querySelector('.js-login-username');

            updateForm(loginAvatar, loginUsername);
            loginUsername.addEventListener('keyup', function() {
                if(loginTimeOut)
                    return;
                loginTimeOut = setTimeout(function() {
                    updateForm(loginAvatar, loginUsername);
                    clearTimeout(loginTimeOut);
                    loginTimeOut = 0;
                }, 750);
            });
        })(loginForms[i]);
};
Misuzu.initQuickSubmit = function() {
    var ctrlSubmit = document.getElementsByClassName('js-quick-submit').toArray().concat(document.getElementsByClassName('js-ctrl-enter-submit').toArray());
    if(!ctrlSubmit)
        return;

    for(var i = 0; i < ctrlSubmit.length; ++i)
        ctrlSubmit[i].addEventListener('keydown', function(ev) {
            if((ev.code === 'Enter' || ev.code === 'NumpadEnter') // i hate this fucking language so much
                && ev.ctrlKey && !ev.altKey && !ev.shiftKey && !ev.metaKey) {
                // hack: prevent forum editor from screaming when using this keycombo
                //       can probably be done in a less stupid manner
                Misuzu.Forum.Editor.allowWindowClose = true;

                this.form.submit();
                ev.preventDefault();
            }
        });
};
