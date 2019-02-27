/// <reference path="User.ts" />
/// <reference path="Colour.ts" />
/// <reference path="Support.ts" />
/// <reference path="Permissions.ts" />
/// <reference path="Comments.ts" />
/// <reference path="Common.ts" />
/// <reference path="FormUtilities.ts" />
/// <reference path="UserRelations.ts" />
/// <reference path="Forum/Posting.ts" />

declare const timeago: any;
declare const hljs: any;

let loginFormAvatarTimeout: number = 0;

// Initialisation process.
window.addEventListener('load', () => {
    timeago().render(document.querySelectorAll('time'));
    hljs.initHighlighting();

    initCSRF();
    userInit();
    userRelationsInit();

    const changelogChangeAction: HTMLDivElement = document.querySelector('.changelog__change__action') as HTMLDivElement;

    if (changelogChangeAction && !Support.sidewaysText) {
        changelogChangeAction.title = "This is supposed to be sideways, but your browser doesn't support that.";
    }

    const loginButtons: HTMLCollectionOf<HTMLAnchorElement> = document.getElementsByClassName('js-login-button') as HTMLCollectionOf<HTMLAnchorElement>;

    if (loginButtons.length > 0) {
        for (let i = 0; i < loginButtons.length; i++) {
            loginButtons[i].href = 'javascript:void(0);';
            loginButtons[i].addEventListener('click', () => loginModal());
        }
    }

    const loginForms: HTMLCollectionOf<HTMLFormElement> = document.getElementsByClassName('js-login-form') as HTMLCollectionOf<HTMLFormElement>;

    if (loginForms.length > 0) {
        for (let i = 0; i < loginForms.length; i++) {
            const loginForm: HTMLFormElement = loginForms[i],
                loginAvatar: HTMLElement = loginForm.getElementsByClassName('js-login-avatar')[0] as HTMLElement,
                loginUsername: HTMLInputElement = loginForm.getElementsByClassName('js-login-username')[0] as HTMLInputElement;

            // Initial bump, in case anything is prefilled.
            loginFormUpdateAvatar(loginAvatar, loginUsername, true);

            loginUsername.addEventListener('keyup', () => loginFormUpdateAvatar(loginAvatar, loginUsername));
        }
    }

    commentsInit();
    forumPostingInit();
});

function loginFormUpdateAvatar(avatarElement: HTMLElement, usernameElement: HTMLInputElement, force: boolean = false): void {
    if (!force) {
        if (loginFormAvatarTimeout)
            return;

        loginFormAvatarTimeout = setTimeout(() => {
            loginFormUpdateAvatar(avatarElement, usernameElement, true);
            clearTimeout(loginFormAvatarTimeout);
            loginFormAvatarTimeout = 0;
        }, 750);
        return;
    }

    const xhr: XMLHttpRequest = new XMLHttpRequest;
    xhr.addEventListener('readystatechange', () => {
        if (xhr.readyState !== 4)
            return;

        avatarElement.style.backgroundImage = `url('/user-assets.php?m=avatar&u=${xhr.responseText}')`;
    });
    xhr.open('GET', `/auth.php?m=get_user&u=${encodeURI(usernameElement.value)}`);
    xhr.send();
}

interface MessageBoxButton {
    text: string;
    callback: Function;
}

function messageBox(text: string, title: string = null, buttons: MessageBoxButton[] = []): boolean {
    if (document.querySelector('.messagebox')) {
        return false;
    }

    const element = document.createElement('div');
    element.className = 'messagebox';

    const container = element.appendChild(document.createElement('div'));
    container.className = 'container messagebox__container';

    const titleElement = container.appendChild(document.createElement('div')),
        titleBackground = titleElement.appendChild(document.createElement('div')),
        titleText = titleElement.appendChild(document.createElement('div'));

    titleElement.className = 'container__title';
    titleBackground.className = 'container__title__background';
    titleText.className = 'container__title__text';
    titleText.textContent = title || 'Information';

    const textElement = container.appendChild(document.createElement('div'));
    textElement.className = 'container__content';
    textElement.textContent = text;

    const buttonsContainer = container.appendChild(document.createElement('div'));
    buttonsContainer.className = 'messagebox__buttons';

    let firstButton = null;

    if (buttons.length < 1) {
        firstButton = buttonsContainer.appendChild(document.createElement('button'));
        firstButton.className = 'input__button';
        firstButton.textContent = 'OK';
        firstButton.addEventListener('click', () => element.remove());
    } else {
        for (let i = 0; i < buttons.length; i++) {
            let button = buttonsContainer.appendChild(document.createElement('button'));
            button.className = 'input__button';
            button.textContent = buttons[i].text;
            button.addEventListener('click', () => {
                element.remove();
                buttons[i].callback();
            });

            if (firstButton === null)
                firstButton = button;
        }
    }

    document.body.appendChild(element);
    firstButton.focus();
    return true;
}

function loginModal(): boolean {
    if (document.querySelector('.messagebox') || getCurrentUser('user_id') > 0) {
        return false;
    }

    const element: HTMLDivElement = document.createElement('div');
    element.className = 'messagebox';

    const container: HTMLFormElement = element.appendChild(document.createElement('form'));
    container.className = 'container messagebox__container auth js-login-form';
    container.method = 'post';
    container.action = '/auth.php';

    const titleElement = container.appendChild(document.createElement('div')),
        titleBackground = titleElement.appendChild(document.createElement('div')),
        titleHeader = titleElement.appendChild(document.createElement('div'));

    titleElement.className = 'container__title';
    titleBackground.className = 'container__title__background';
    titleHeader.className = 'auth__header';

    const authAvatar: HTMLDivElement = titleHeader.appendChild(document.createElement('div'));
    authAvatar.className = 'avatar auth__avatar';
    authAvatar.style.backgroundImage = "url('/user-assets.php?u=0&m=avatar')";

    const hiddenMode: HTMLInputElement = container.appendChild(document.createElement('input'));
    hiddenMode.type = 'hidden';
    hiddenMode.name = 'auth[mode]';
    hiddenMode.value = 'login';

    const hiddenCsrf: HTMLInputElement = container.appendChild(document.createElement('input'));
    hiddenCsrf.type = 'hidden';
    hiddenCsrf.name = 'csrf[login]';
    hiddenCsrf.value = getCSRFToken('login');

    const hiddenRedirect: HTMLInputElement = container.appendChild(document.createElement('input'));
    hiddenRedirect.type = 'hidden';
    hiddenRedirect.name = 'auth[redirect]';
    hiddenRedirect.value = location.toString();

    const authForm: HTMLDivElement = container.appendChild(document.createElement('div'));
    authForm.className = 'auth__form';

    const inputUsername: HTMLInputElement = authForm.appendChild(document.createElement('input'));
    inputUsername.className = 'input__text auth__input';
    inputUsername.placeholder = 'Username';
    inputUsername.type = 'text';
    inputUsername.name = 'auth[username]';
    inputUsername.addEventListener('keyup', () => loginFormUpdateAvatar(authAvatar, inputUsername));

    const inputPassword: HTMLInputElement = authForm.appendChild(document.createElement('input'));
    inputPassword.className = 'input__text auth__input';
    inputPassword.placeholder = 'Password';
    inputPassword.type = 'password';
    inputPassword.name = 'auth[password]';

    const formButtons: HTMLDivElement = authForm.appendChild(document.createElement('div'));
    formButtons.className = 'auth__buttons';

    const inputLogin: HTMLButtonElement = formButtons.appendChild(document.createElement('button'));
    inputLogin.className = 'input__button auth__button';
    inputLogin.textContent = 'Log in';

    const inputClose: HTMLButtonElement = formButtons.appendChild(document.createElement('button'));
    inputClose.className = 'input__button auth__button';
    inputClose.textContent = 'Close';
    inputClose.type = 'button';
    inputClose.addEventListener('click', () => element.remove());

    document.body.appendChild(element);
    inputUsername.focus();
    return true;
}
