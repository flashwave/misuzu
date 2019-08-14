/// <reference path="FormUtilities.ts" />

enum CommentVoteType {
    Indifferent = 0,
    Like = 1,
    Dislike = -1,
}

interface CommentNotice {
    error: string;
    message: string;
}

interface CommentDeletionInfo extends CommentNotice {
    id: number; // minor inconsistency, deal with it
}

interface CommentPostInfo extends CommentNotice {
    comment_id: number;
    category_id: number;
    comment_text: string;
    comment_html: string;
    comment_created: Date;
    comment_edited: Date | null;
    comment_deleted: Date | null;
    comment_reply_to: number;
    comment_pinned: Date | null;
    user_id: number;
    username: string;
    user_colour: number;
}

interface CommentVotesInfo extends CommentNotice {
    comment_id: number;
    likes: number;
    dislikes: number;
}

function commentDeleteEventHandler(ev: Event): void {
    const target: HTMLAnchorElement = ev.target as HTMLAnchorElement,
        commentId: number = parseInt(target.dataset.commentId);

    commentDelete(
        commentId,
        info => {
            let elem = document.getElementById('comment-' + info.id);

            if(elem)
                elem.parentNode.removeChild(elem);
        },
        message => messageBox(message)
    );
}

function commentDelete(commentId: number, onSuccess: (info: CommentDeletionInfo) => void = null, onFail: (message: string) => void = null): void
{
    if(!checkUserPerm('comments', CommentPermission.Delete)) {
        if(onFail)
            onFail("You aren't allowed to delete comments.");
        return;
    }

    const xhr: XMLHttpRequest = new XMLHttpRequest;

    xhr.addEventListener('readystatechange', () => {
        if(xhr.readyState !== 4)
            return;

        updateCSRF(xhr.getResponseHeader('X-Misuzu-CSRF'));

        let json: CommentDeletionInfo = JSON.parse(xhr.responseText) as CommentDeletionInfo,
            message = json.error || json.message;

        if(message && onFail)
            onFail(message);
        else if(!message && onSuccess)
            onSuccess(json);
    });
    xhr.open('GET', urlFormat('comments-delete', [{name:'comment',value:commentId}]));
    xhr.setRequestHeader('X-Misuzu-XHR', 'comments');
    xhr.send();
}

function commentPostEventHandler(ev: Event): void
{
    const form: HTMLFormElement = ev.target as HTMLFormElement;

    if(form.dataset.disabled)
        return;
    form.dataset.disabled = '1';
    form.style.opacity = '0.5';

    commentPost(
        extractFormData(form, true),
        info => commentPostSuccess(form, info),
        message => commentPostFail(form, message)
    );
}

function commentPost(formData: FormData, onSuccess: (comment: CommentPostInfo) => void = null, onFail: (message: string) => void = null): void
{
    if(!checkUserPerm('comments', CommentPermission.Create)) {
        if(onFail)
            onFail("You aren't allowed to post comments.");
        return;
    }

    const xhr = new XMLHttpRequest();

    xhr.addEventListener('readystatechange', () => {
        if(xhr.readyState !== 4)
            return;

        updateCSRF(xhr.getResponseHeader('X-Misuzu-CSRF'));

        const json: CommentPostInfo = JSON.parse(xhr.responseText) as CommentPostInfo,
            message: string = json.error || json.message;

        if(message && onFail)
            onFail(message);
        else if(!message && onSuccess)
            onSuccess(json);
    });

    xhr.open('POST', urlFormat('comment-create'));
    xhr.setRequestHeader('X-Misuzu-XHR', 'comments');
    xhr.send(formData);
}

function commentPostSuccess(form: HTMLFormElement, comment: CommentPostInfo): void {
    if(form.classList.contains('comment--reply'))
        (form.parentNode.parentNode.querySelector('label.comment__action') as HTMLLabelElement).click();

    commentInsert(comment, form);
    form.style.opacity = '1';
    form.dataset.disabled = '';
}

function commentPostFail(form: HTMLFormElement, message: string): void {
    messageBox(message);
    form.style.opacity = '1';
    form.dataset.disabled = '';
}

function commentsInit(): void {
    const commentDeletes: HTMLCollectionOf<HTMLAnchorElement> = document.getElementsByClassName('comment__action--delete') as HTMLCollectionOf<HTMLAnchorElement>;

    for(let i = 0; i < commentDeletes.length; i++) {
        commentDeletes[i].addEventListener('click', commentDeleteEventHandler);
        commentDeletes[i].dataset.href = commentDeletes[i].href;
        commentDeletes[i].href = 'javascript:void(0);';
    }

    const commentInputs: HTMLCollectionOf<HTMLTextAreaElement> = document.getElementsByClassName('comment__text--input') as HTMLCollectionOf<HTMLTextAreaElement>;

    for(let i = 0; i < commentInputs.length; i++) {
        commentInputs[i].form.action = 'javascript:void(0);';
        commentInputs[i].form.addEventListener('submit', commentPostEventHandler);
        commentInputs[i].addEventListener('keydown', commentInputEventHandler);
    }

    const voteButtons: HTMLCollectionOf<HTMLAnchorElement> = document.getElementsByClassName('comment__action--vote') as HTMLCollectionOf<HTMLAnchorElement>;

    for(let i = 0; i < voteButtons.length; i++)
    {
        voteButtons[i].href = 'javascript:void(0);';
        voteButtons[i].addEventListener('click', commentVoteEventHandler);
    }

    const pinButtons: HTMLCollectionOf<HTMLAnchorElement> = document.getElementsByClassName('comment__action--pin') as HTMLCollectionOf<HTMLAnchorElement>;

    for(let i = 0; i < pinButtons.length; i++) {
        pinButtons[i].href = 'javascript:void(0);';
        pinButtons[i].addEventListener('click', commentPinEventHandler);
    }
}

function commentInputEventHandler(ev: KeyboardEvent): void {
    if(ev.code === 'Enter' && ev.ctrlKey && !ev.altKey && !ev.shiftKey && !ev.metaKey) {
        const form: HTMLFormElement = (ev.target as HTMLTextAreaElement).form;
        commentPost(
            extractFormData(form, true),
            info => commentPostSuccess(form, info),
            message => commentPostFail
        );
    }
}

function commentConstruct(comment: CommentPostInfo, layer: number = 0): HTMLElement {
    const commentDate = new Date(comment.comment_created + 'Z'),
        commentTime: HTMLElement = <time class="comment__date" title={commentDate.toLocaleString()} dateTime={commentDate.toISOString()}>{timeago.format(commentDate)}</time>;
    let actions: HTMLElement[] = [];

    if(checkUserPerm('comments', CommentPermission.Vote)) {
        actions.push(<a class="comment__action comment__action--link comment__action--vote comment__action--like"
            data-comment-id={comment.comment_id} data-comment-vote={CommentVoteType.Like}
            href="javascript:void(0);" onClick={commentVoteEventHandler}>Like</a>);
        actions.push(<a class="comment__action comment__action--link comment__action--vote comment__action--dislike"
            data-comment-id={comment.comment_id} data-comment-vote={CommentVoteType.Dislike}
            href="javascript:void(0);" onClick={commentVoteEventHandler}>Dislike</a>);
    }

    const commentText: HTMLDivElement = <div class="comment__text"></div>;

    if(comment.comment_html)
        commentText.innerHTML = comment.comment_html;
    else
        commentText.textContent = comment.comment_text;

    const commentElement: HTMLDivElement = <div class="comment" id={"comment-" + comment.comment_id}>
        <div class="comment__container">
            <a class="avatar comment__avatar" href={urlFormat('user-profile', [{name:'user',value:comment.user_id}])}
                style={"background-image: url('{0}')".replace('{0}', urlFormat('user-avatar', [
                    { name: 'user', value: comment.user_id },
                    { name: 'res', value: layer < 1 ? 100 : 80 }
                ]))}></a>
            <div class="comment__content">
                <div class="comment__info">
                    <a class="comment__user comment__user--link" href={urlFormat('user-profile', [{name:'user',value:comment.user_id}])}
                        style={"--user-colour: " + colourGetCSS(comment.user_colour)}>{comment.username}</a>
                    <a class="comment__link" href={"#comment-" + comment.comment_id}>{commentTime}</a>
                </div>
                {commentText}
                <div class="comment__actions">
                    {actions}
                    <label class="comment__action comment__action--link" for={"comment-reply-toggle-" + comment.comment_id}>Reply</label>
                </div>
            </div>
        </div>
        <div class={"comment__replies comment__replies--indent-" + layer} id={"comment-" + comment.comment_id + "-replies"}>
            <input type="checkbox" id={"comment-reply-toggle-" + comment.comment_id} class="comment__reply-toggle" />
            <form id={"comment-reply-" + comment.comment_id} class="comment comment--input comment--reply" method="post"
                action="javascript:void(0);" onSubmit={commentPostEventHandler}>
                <input type="hidden" name="comment[category]" value={comment.category_id} />
                <input type="hidden" name="csrf[comments]" value={getCSRFToken()} />
                <input type="hidden" name="comment[reply]" value={comment.comment_id} />
                <div class="comment__container">
                    <div class="avatar comment__avatar"
                        style={"background-image: url('{0}')".replace('{0}', urlFormat('user-avatar', [{name:'user',value:comment.user_id},{name:'res',value:80}]))}></div>
                    <div class="comment__content">
                        <div class="comment__info"></div>
                        <textarea class="comment__text input__textarea comment__text--input" name="comment[text]" placeholder="Share your extensive insights..." onKeyDown={commentInputEventHandler}></textarea>
                        <div class="comment__actions">
                            <button class="input__button comment__action comment__action--button comment__action--post">Reply</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>;

    timeago.render(commentTime);

    return commentElement;
}

function commentInsert(comment: CommentPostInfo, form: HTMLFormElement): void
{
    const isReply: boolean = form.classList.contains('comment--reply'),
        parent: Element = isReply
            ? form.parentElement
            : form.parentElement.parentElement.getElementsByClassName('comments__listing')[0],
        repliesIndent: number = isReply
            ? (parseInt(parent.classList[1].substr(25)) + 1)
            : 1,
        commentElement: HTMLElement = commentConstruct(comment, repliesIndent);

    if(isReply)
        parent.appendChild(commentElement);
    else
        parent.insertBefore(commentElement, parent.firstElementChild);

    const placeholder: HTMLElement = document.getElementById('_no_comments_notice_' + comment.category_id);

    if(placeholder)
        placeholder.parentNode.removeChild(placeholder);
}

function commentVoteEventHandler(ev: Event): void {
    const target: HTMLAnchorElement = this as HTMLAnchorElement,
        commentId: number = parseInt(target.dataset.commentId),
        voteType: CommentVoteType = parseInt(target.dataset.commentVote),
        buttons: NodeListOf<HTMLAnchorElement> = document.querySelectorAll(`.comment__action--vote[data-comment-id="${commentId}"]`),
        likeButton: HTMLAnchorElement = document.querySelector(`.comment__action--like[data-comment-id="${commentId}"]`),
        dislikeButton: HTMLAnchorElement = document.querySelector(`.comment__action--dislike[data-comment-id="${commentId}"]`),
        classVoted: string = 'comment__action--voted';

    for(let i = 0; i < buttons.length; i++) {
        let button: HTMLAnchorElement = buttons[i];

        button.textContent = button === target ? '...' : '';
        button.classList.remove(classVoted);

        if(button === likeButton) {
            button.dataset.commentVote = (voteType === CommentVoteType.Like ? CommentVoteType.Indifferent : CommentVoteType.Like).toString();
        } else if(button === dislikeButton) {
            button.dataset.commentVote = (voteType === CommentVoteType.Dislike ? CommentVoteType.Indifferent : CommentVoteType.Dislike).toString();
        }
    }

    commentVote(
        commentId,
        voteType,
        info => {
            switch(voteType) {
                case CommentVoteType.Like:
                    likeButton.classList.add(classVoted);
                    break;

                case CommentVoteType.Dislike:
                    dislikeButton.classList.add(classVoted);
                    break;
            }

            likeButton.textContent = info.likes > 0 ? `Like (${info.likes.toLocaleString()})` : 'Like';
            dislikeButton.textContent = info.dislikes > 0 ? `Dislike (${info.dislikes.toLocaleString()})` : 'Dislike';
        },
        message => {
            likeButton.textContent = 'Like';
            dislikeButton.textContent = 'Dislike';
            messageBox(message);
        }
    );
}

function commentVote(
    commentId: number,
    vote: CommentVoteType,
    onSuccess: (voteInfo: CommentVotesInfo) => void = null,
    onFail: (message: string) => void = null
): void {
    if(!checkUserPerm('comments', CommentPermission.Vote)) {
        if(onFail)
            onFail("You aren't allowed to vote on comments.");
        return;
    }

    const xhr: XMLHttpRequest = new XMLHttpRequest;
    xhr.onreadystatechange = () => {
        if(xhr.readyState !== 4)
            return;

        updateCSRF(xhr.getResponseHeader('X-Misuzu-CSRF'));

        const json: CommentVotesInfo = JSON.parse(xhr.responseText),
            message: string = json.error || json.message;

        if(message && onFail)
            onFail(message);
        else if(!message && onSuccess)
            onSuccess(json);
    };
    xhr.open('GET', urlFormat('comment-vote', [{name: 'comment', value: commentId}, {name: 'vote', value: vote}]));
    xhr.setRequestHeader('X-Misuzu-XHR', 'comments');
    xhr.send();
}

function commentPinEventHandler(ev: Event): void {
    const target: HTMLAnchorElement = this as HTMLAnchorElement,
        commentId: number = parseInt(target.dataset.commentId),
        isPinned: boolean = target.dataset.commentPinned !== '0';

    target.textContent = '...';

    commentPin(
        commentId,
        !isPinned,
        info => {
            if(info.comment_pinned === null) {
                target.textContent = 'Pin';
                target.dataset.commentPinned = '0';
                const pinElement: HTMLDivElement = document.querySelector(`#comment-${info.comment_id} .comment__pin`);
                pinElement.parentElement.removeChild(pinElement);
            } else {
                target.textContent = 'Unpin';
                target.dataset.commentPinned = '1';

                const pinInfo: HTMLDivElement = document.querySelector(`#comment-${info.comment_id} .comment__info`),
                    pinElement: HTMLDivElement = document.createElement('div'),
                    pinTime: HTMLTimeElement = document.createElement('time'),
                    pinDateTime = new Date(info.comment_pinned + 'Z');

                pinTime.title = pinDateTime.toLocaleString();
                pinTime.dateTime = pinDateTime.toISOString();
                pinTime.textContent = timeago().format(pinDateTime);
                timeago().render(pinTime);

                pinElement.className = 'comment__pin';
                pinElement.appendChild(document.createTextNode('Pinned '));
                pinElement.appendChild(pinTime);
                pinInfo.appendChild(pinElement);
            }
        },
        message => {
            target.textContent = isPinned ? 'Unpin' : 'Pin';
            messageBox(message);
        }
    );
}

function commentPin(
    commentId: number,
    pin: boolean,
    onSuccess: (commentInfo: CommentPostInfo) => void = null,
    onFail: (message: string) => void = null
): void {
    if(!checkUserPerm('comments', CommentPermission.Pin)) {
        if(onFail)
            onFail("You aren't allowed to pin comments.");
        return;
    }

    const mode: string = pin ? 'pin' : 'unpin';
    const xhr: XMLHttpRequest = new XMLHttpRequest;
    xhr.onreadystatechange = () => {
        if(xhr.readyState !== 4)
            return;

        updateCSRF(xhr.getResponseHeader('X-Misuzu-CSRF'));

        const json: CommentPostInfo = JSON.parse(xhr.responseText),
            message: string = json.error || json.message;

        if(message && onFail)
            onFail(message);
        else if(!message && onSuccess)
            onSuccess(json);
    };
    xhr.open('GET', urlFormat(`comment-${mode}`, [{name: 'comment', value: commentId}]));
    xhr.setRequestHeader('X-Misuzu-XHR', 'comments');
    xhr.send();
}
