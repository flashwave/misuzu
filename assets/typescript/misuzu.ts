/// <reference path="User.ts" />
/// <reference path="Colour.ts" />
/// <reference path="Support.ts" />
/// <reference path="Permissions.ts" />
/// <reference path="Comments.ts" />
/// <reference path="Common.ts" />
/// <reference path="FormUtilities.ts" />
/// <reference path="UserRelations.ts" />

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

        avatarElement.style.backgroundImage = `url('/profile.php?m=avatar&u=${xhr.responseText}')`;
    });
    xhr.open('GET', `/auth.php?m=get_user&u=${encodeURI(usernameElement.value)}`);
    xhr.send();
}

interface MessageBoxButton {
    text: string;
    callback: Function;
}

function messageBox(text: string, title: string = null, buttons: MessageBoxButton[] = []): boolean
{
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

    if (buttons.length < 1) {
        const okButton = buttonsContainer.appendChild(document.createElement('button'));
        okButton.className = 'input__button';
        okButton.textContent = 'OK';
        okButton.addEventListener('click', () => element.remove());
    } else {
        for (let i = 0; i < buttons.length; i++) {
            let button = buttonsContainer.appendChild(document.createElement('button'));
            button.className = 'input__button';
            button.textContent = buttons[i].text;
            button.addEventListener('click', () => {
                element.remove();
                buttons[i].callback();
            });
        }
    }

    document.body.appendChild(element);
    return true;
}
