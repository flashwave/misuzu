/// <reference path="User.ts" />
/// <reference path="Colour.ts" />
/// <reference path="Support.ts" />
/// <reference path="Permissions.ts" />
/// <reference path="Comments.tsx" />
/// <reference path="Common.ts" />
/// <reference path="FormUtilities.ts" />
/// <reference path="UserRelations.ts" />
/// <reference path="Forum/Posting.ts" />
/// <reference path="UrlRegistry.ts" />
/// <reference path="Forum/Polls.ts" />

declare const timeago: any;
declare const hljs: any;

let loginFormAvatarTimeout: number = 0;

function mszCreateElement(type: string, properties: {} = {}, children: any[] = []): HTMLElement {
    const element: HTMLElement = document.createElement(type);

    if(!Array.isArray(children))
        children = [children];

    if(arguments.length > 3)
        for(let i = 3; i < arguments.length; i++)
            children.push(arguments[i]);

    if(properties)
        for(let prop in properties) {
            switch(typeof properties[prop]) {
                case 'function':
                    element.addEventListener(
                        prop.substring(0, 2) === 'on'
                            ? prop.substring(2).toLowerCase()
                            : prop,
                        properties[prop]
                    );
                    break;
                default:
                    element.setAttribute(prop, properties[prop]);
                    break;
            }
        }

    if(children)
        for(let child in children as []) {
            switch(typeof children[child]) {
                case 'string':
                    element.appendChild(document.createTextNode(children[child]));
                    break;
                default:
                    if(children[child] instanceof Element)
                        element.appendChild(children[child]);
                    break;
            }
        }

    return element;
}

// Initialisation process.
document.addEventListener('DOMContentLoaded', () => {
    console.log("%c     __  ____\n   /  |/  (_)______  ______  __  __\n  / /|_/ / / ___/ / / /_  / / / / /\n / /  / / (__  ) /_/ / / /_/ /_/ /\n/_/  /_/_/____/\\__,_/ /___/\\__,_/\nhttps://github.com/flashwave/misuzu", 'color: #8559a5');

    timeago.render(document.querySelectorAll('time'));
    hljs.initHighlighting();

    initCSRF();
    urlRegistryInit();
    userInit();
    userRelationsInit();

    const changelogChangeAction: HTMLDivElement = document.querySelector('.changelog__change__action') as HTMLDivElement;

    if(changelogChangeAction && !Support.sidewaysText) {
        changelogChangeAction.title = "This is supposed to be sideways, but your browser doesn't support that.";
    }

    const loginForms: HTMLCollectionOf<HTMLFormElement> = document.getElementsByClassName('js-login-form') as HTMLCollectionOf<HTMLFormElement>;

    if(loginForms.length > 0) {
        for(let i = 0; i < loginForms.length; i++) {
            const loginForm: HTMLFormElement = loginForms[i],
                loginAvatar: HTMLImageElement = loginForm.getElementsByClassName('js-login-avatar')[0] as HTMLImageElement,
                loginUsername: HTMLInputElement = loginForm.getElementsByClassName('js-login-username')[0] as HTMLInputElement;

            // Initial bump, in case anything is prefilled.
            loginFormUpdateAvatar(loginAvatar, loginUsername, true);

            loginUsername.addEventListener('keyup', () => loginFormUpdateAvatar(loginAvatar, loginUsername));
        }
    }

    const ctrlSubmit: HTMLCollectionOf<HTMLInputElement> = document.getElementsByClassName('js-ctrl-enter-submit') as HTMLCollectionOf<HTMLInputElement>;

    if(ctrlSubmit.length > 0) {
        for(let i = 0; i < ctrlSubmit.length; i++) {
            ctrlSubmit[i].addEventListener('keydown', ev => {
                if((ev.code === 'Enter' || ev.code === 'NumpadEnter') /* i hate this fucking language so much */
                    && ev.ctrlKey && !ev.altKey && !ev.shiftKey && !ev.metaKey) {
                    // for a hackjob
                    forumPostingCloseOK = true;

                    ctrlSubmit[i].form.submit();
                    ev.preventDefault();
                }
            });
        }
    }

    commentsInit();
    forumPostingInit();
    forumPollsInit();

    var d: Date = new Date;

    if(d.getMonth() === 11 && d.getDay() >= 5 && d.getDay() <= 25)
        mszEventChristmas();
});

function mszEventChristmas(): void {
    var headerBg: HTMLDivElement = document.querySelector('.header__background'),
        menuBgs: NodeListOf<HTMLDivElement> = document.querySelectorAll('.header__desktop__submenu__background'),
        propName: string = 'msz-christmas-' + (new Date).getFullYear().toString();

    if(!localStorage.getItem(propName))
        localStorage.setItem(propName, '0');

    var changeColour = function() {
        var count = parseInt(localStorage.getItem(propName));
        document.body.style.setProperty('--header-accent-colour', (count++ % 2) ? 'green' : 'red');
        localStorage.setItem(propName, count.toString());
    };

    if(headerBg)
        headerBg.style.transition = 'background-color .4s';

    setTimeout(function() {
        if(headerBg)
            headerBg.style.transition = 'background-color 1s';

        for(var i = 0; i < menuBgs.length; i++)
            menuBgs[i].style.transition = 'background-color 1s';
    }, 1000);

    changeColour();
    setInterval(changeColour, 10000);
}

function loginFormUpdateAvatar(avatarElement: HTMLImageElement, usernameElement: HTMLInputElement, force: boolean = false): void {
    if(!force) {
        if(loginFormAvatarTimeout)
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
        if(xhr.readyState !== 4)
            return;

        avatarElement.src = urlFormat('user-avatar', [
            { name: 'user', value: xhr.responseText.indexOf('<') !== -1 ? '0' : xhr.responseText },
            { name: 'res', value: 100 },
        ]);
    });
    xhr.open('GET', urlFormat('auth-resolve-user', [{name: 'username', value: encodeURIComponent(usernameElement.value)}]));
    xhr.send();
}

interface MessageBoxButton {
    text: string;
    callback: Function;
}

function messageBox(text: string, title: string = null, buttons: MessageBoxButton[] = []): boolean {
    if(document.querySelector('.messagebox')) {
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

    if(buttons.length < 1) {
        firstButton = buttonsContainer.appendChild(document.createElement('button'));
        firstButton.className = 'input__button';
        firstButton.textContent = 'OK';
        firstButton.addEventListener('click', () => element.remove());
    } else {
        for(let i = 0; i < buttons.length; i++) {
            let button = buttonsContainer.appendChild(document.createElement('button'));
            button.className = 'input__button';
            button.textContent = buttons[i].text;
            button.addEventListener('click', () => {
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
}
