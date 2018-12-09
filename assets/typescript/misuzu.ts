/// <reference path="User.ts" />
/// <reference path="Colour.ts" />
/// <reference path="Support.ts" />
/// <reference path="Permissions.ts" />
/// <reference path="Comments.ts" />
/// <reference path="Common.ts" />

declare const timeago: any;
declare const hljs: any;

let loginFormAvatarTimeout: number = 0;

// Initialisation process.
window.addEventListener('load', () => {
    timeago().render(document.querySelectorAll('time'));
    hljs.initHighlighting();

    userInit();

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
