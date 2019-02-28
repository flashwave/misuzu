enum UserRelationType {
    None = 0,
    Follow = 1,
}

interface UserRelationInfo {
    user_id: number;
    subject_id: number;
    relation_type: UserRelationType;

    error: string;
    message: string;
}

function userRelationsInit(): void
{
    const relationButtons: HTMLCollectionOf<HTMLElement> = document.getElementsByClassName('js-user-relation-action') as HTMLCollectionOf<HTMLElement>;

    for (let i = 0; i < relationButtons.length; i++) {
        switch (relationButtons[i].tagName.toLowerCase()) {
            case 'a':
                const anchor: HTMLAnchorElement = relationButtons[i] as HTMLAnchorElement;
                anchor.removeAttribute('href');
                anchor.removeAttribute('target');
                anchor.removeAttribute('rel');
                break;
        }

        relationButtons[i].addEventListener('click', userRelationSetEventHandler);
    }
}

function userRelationSetEventHandler(ev: Event): void {
    const target: HTMLElement = this as HTMLElement,
        userId: number = parseInt(target.dataset.relationUser),
        relationType: UserRelationType = parseInt(target.dataset.relationType),
        isButton: boolean = target.classList.contains('input__button'),
        icon: HTMLElement = target.querySelector('[class^="fa"]'),
        buttonBusy: string = 'input__button--busy',
        iconAdd: string = 'fas fa-user-plus',
        iconRemove: string = 'fas fa-user-minus',
        iconBusy: string = 'fas fa-spinner fa-pulse';

    if (isButton) {
        if (target.classList.contains(buttonBusy))
            return;

        target.classList.add(buttonBusy);
    }

    if (icon) {
        icon.className = iconBusy;
    }

    userRelationSet(
        userId,
        relationType,
        info => {
            target.classList.remove(buttonBusy);

            switch (info.relation_type) {
                case UserRelationType.None:
                    if (isButton) {
                        if (target.classList.contains('input__button--destroy'))
                            target.classList.remove('input__button--destroy');

                        target.textContent = 'Follow';
                    }

                    if (icon) {
                        icon.className = iconAdd;
                        target.title = 'Follow';
                    }

                    target.dataset.relationType = UserRelationType.Follow.toString();
                    break;

                case UserRelationType.Follow:
                    if (isButton) {
                        if (!target.classList.contains('input__button--destroy'))
                            target.classList.add('input__button--destroy');

                        target.textContent = 'Unfollow';
                    }

                    if (icon) {
                        icon.className = iconRemove;
                        target.title = 'Unfollow';
                    }

                    target.dataset.relationType = UserRelationType.None.toString();
                    break;
            }
        },
        msg => {
            target.classList.remove(buttonBusy);
            messageBox(msg);
        }
    );
}

function userRelationSet(
    userId: number,
    relationType: UserRelationType,
    onSuccess: (info: UserRelationInfo) => void = null,
    onFail: (message: string) => void = null
): void {
    const xhr: XMLHttpRequest = new XMLHttpRequest;

    xhr.addEventListener('readystatechange', () => {
        if (xhr.readyState !== 4)
            return;

        updateCSRF(xhr.getResponseHeader('X-Misuzu-CSRF'));

        let json: UserRelationInfo = JSON.parse(xhr.responseText) as UserRelationInfo,
            message = json.error || json.message;

        if (message && onFail)
            onFail(message);
        else if (!message && onSuccess)
            onSuccess(json);
    });
    xhr.open('GET', `/relations.php?u=${userId}&m=${relationType}`);
    xhr.setRequestHeader('X-Misuzu-XHR', 'user_relation');
    xhr.setRequestHeader('X-Misuzu-CSRF', getCSRFToken('user_relation'));
    xhr.send();
}
