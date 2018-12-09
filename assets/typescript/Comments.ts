/// <reference path="FormUtilities.ts" />


let globalCommentLock = false;

function commentsLocked(): boolean
{
    return globalCommentLock;
}

function commentsRequestLock(): boolean
{
    if (commentsLocked())
        return false;

    return globalCommentLock = true;
}

function commentsFreeLock(): void
{
    globalCommentLock = false;
}

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
    comment_id: number;
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

function commentDelete(ev: Event): void
{
    if (!checkUserPerm('comments', CommentPermission.Delete) || !commentsRequestLock())
        return;

    const xhr: XMLHttpRequest = new XMLHttpRequest(),
        target: HTMLAnchorElement = ev.target as HTMLAnchorElement;

    xhr.addEventListener('readystatechange', () => {
        if (xhr.readyState !== 4)
            return;
        commentsFreeLock();

        let json: CommentDeletionInfo = JSON.parse(xhr.responseText) as CommentDeletionInfo,
            message = json.error || json.message;

        if (message)
            alert(message);
        else {
            let elem = document.getElementById('comment-' + json.comment_id);

            if (elem)
                elem.parentNode.removeChild(elem);
        }
    });
    xhr.open('GET', target.dataset.href);
    xhr.setRequestHeader('X-Misuzu-XHR', 'comments');
    xhr.send();
}

function commentPostEventHandler(ev: Event): void
{
    const form: HTMLFormElement = ev.target as HTMLFormElement;

    commentPost(
        ExtractFormData(form, true),
        info => commentPostSuccess(form, info),
        commentPostFail
    );
}

function commentPost(formData: FormData, onSuccess: (comment: CommentPostInfo) => void = null, onFail: (message: string) => void = null): void
{
    if (!checkUserPerm('comments', CommentPermission.Create) || !commentsRequestLock())
        return;

    const xhr = new XMLHttpRequest();

    xhr.addEventListener('readystatechange', () => {
        if (xhr.readyState !== 4)
            return;

        commentsFreeLock();

        console.log(xhr.getResponseHeader('X-Misuzu-CSRF'));

        const json: CommentPostInfo = JSON.parse(xhr.responseText) as CommentPostInfo,
            message: string = json.error || json.message;

        if (message && onFail)
            onFail(message);
        else if (!message && onSuccess)
            onSuccess(json);
    });

    xhr.open('POST', '/comments.php?m=create');
    xhr.setRequestHeader('X-Misuzu-XHR', 'comments');
    xhr.send(formData);
}

function commentPostSuccess(form: HTMLFormElement, comment: CommentPostInfo): void {
    if (form.classList.contains('comment--reply'))
        (form.parentNode.parentNode.querySelector('label.comment__action') as HTMLLabelElement).click();

    commentInsert(comment, form);
}

function commentPostFail(message: string): void {
    alert(message);
}

function commentsInit(): void {
    const commentDeletes: HTMLCollectionOf<HTMLAnchorElement> = document.getElementsByClassName('comment__action--delete') as HTMLCollectionOf<HTMLAnchorElement>;

    for (let i = 0; i < commentDeletes.length; i++) {
        commentDeletes[i].addEventListener('click', commentDelete);
        commentDeletes[i].dataset.href = commentDeletes[i].href;
        commentDeletes[i].href = 'javascript:void(0);';
    }

    const commentInputs: HTMLCollectionOf<HTMLTextAreaElement> = document.getElementsByClassName('comment__text--input') as HTMLCollectionOf<HTMLTextAreaElement>;

    for (let i = 0; i < commentInputs.length; i++) {
        commentInputs[i].form.action = 'javascript:void(0);';
        commentInputs[i].form.addEventListener('submit', commentPostEventHandler);
        commentInputs[i].addEventListener('keydown', ev => {
            if (ev.keyCode === 13 && ev.ctrlKey && !ev.altKey && !ev.shiftKey) {
                let form = commentInputs[i].form;
                commentPost(
                    ExtractFormData(form, true),
                    info => commentPostSuccess(form, info),
                    message => commentPostFail
                );
            }
        });
    }
}

function commentConstruct(comment: CommentPostInfo, layer: number = 0): HTMLElement {
    const commentElement: HTMLDivElement = document.createElement('div');
    commentElement.className = 'comment';
    commentElement.id = 'comment-' + comment.comment_id;

    // layer 2
    const commentContainer: HTMLDivElement = commentElement.appendChild(document.createElement('div'));
    commentContainer.className = 'comment__container';

    const commentReplies: HTMLDivElement = commentElement.appendChild(document.createElement('div'));
    commentReplies.className = 'comment__replies comment__replies--indent-' + layer;
    commentReplies.id = commentElement.id + '-replies';

    // container
    const commentAvatar: HTMLAnchorElement = commentContainer.appendChild(document.createElement('a'));
    commentAvatar.className = 'avatar comment__avatar';
    commentAvatar.href = '/profile.php?u=' + comment.user_id;
    commentAvatar.style.backgroundImage = `url('/profile.php?m=avatar&u=${comment.user_id}')`;

    const commentContent: HTMLDivElement = commentContainer.appendChild(document.createElement('div'));
    commentContent.className = 'comment__content';

    // content
    const commentInfo = commentContent.appendChild(document.createElement('div'));
    commentInfo.className = 'comment__info';

    const commentText = commentContent.appendChild(document.createElement('div'));
    commentText.className = 'comment__text';

    if (comment.comment_html)
        commentText.innerHTML = comment.comment_html;
    else
        commentText.textContent = comment.comment_text;

    const commentActions = commentContent.appendChild(document.createElement('div'));
    commentActions.className = 'comment__actions';

    // info
    const commentUser: HTMLAnchorElement = commentInfo.appendChild(document.createElement('a'));
    commentUser.className = 'comment__user comment__user--link';
    commentUser.textContent = comment.username;
    commentUser.href = '/profile?u=' + comment.user_id;
    commentUser.style.color = comment.user_colour == null || (comment.user_colour & 0x40000000) > 0
        ? 'inherit'
        : '#' + (comment.user_colour & 0xFFFFFF).toString(16);

    const commentLink: HTMLAnchorElement = commentInfo.appendChild(document.createElement('a'));
    commentLink.className = 'comment__link';
    commentLink.href = '#' + commentElement.id;

    const commentTime: HTMLTimeElement = commentLink.appendChild(document.createElement('time')),
        commentDate = new Date(comment.comment_created + 'Z');
    commentTime.className = 'comment__date';
    commentTime.title = commentDate.toLocaleString();
    commentTime.dateTime = commentDate.toISOString();
    commentTime.textContent = timeago().format(commentDate);
    timeago().render(commentTime);

    // actions
    if (checkUserPerm('comments', CommentPermission.Vote)) {
        const commentLike: HTMLAnchorElement = commentActions.appendChild(document.createElement('a'));
        commentLike.className = 'comment__action comment__action--link comment__action--like';
        commentLike.href = 'javascript:void(0);';
        commentLike.textContent = 'Like';
        commentLike.addEventListener('click', commentVoteEventHandler);

        const commentDislike: HTMLAnchorElement = commentActions.appendChild(document.createElement('a'));
        commentDislike.className = 'comment__action comment__action--link comment__action--dislike';
        commentDislike.href = 'javascript:void(0);';
        commentDislike.textContent = 'Dislike';
        commentLike.addEventListener('click', commentVoteEventHandler);
    }

    // if we're executing this it's fairly obvious that we can reply,
    // so no need to have a permission check on it here
    const commentReply: HTMLLabelElement = commentActions.appendChild(document.createElement('label'));
    commentReply.className = 'comment__action comment__action--link';
    commentReply.htmlFor = 'comment-reply-toggle-' + comment.comment_id;
    commentReply.textContent = 'Reply';

    // reply section
    const commentReplyState: HTMLInputElement = commentReplies.appendChild(document.createElement('input'));
    commentReplyState.id = commentReply.htmlFor;
    commentReplyState.type = 'checkbox';
    commentReplyState.className = 'comment__reply-toggle';

    const commentReplyInput: HTMLFormElement = commentReplies.appendChild(document.createElement('form'));
    commentReplyInput.id = 'comment-reply-' + comment.comment_id;
    commentReplyInput.className = 'comment comment--input comment--reply';
    commentReplyInput.method = 'post';
    commentReplyInput.action = 'javascript:void(0);';
    commentReplyInput.addEventListener('submit', commentPostEventHandler);

    // reply attributes
    const replyCategory: HTMLInputElement = commentReplyInput.appendChild(document.createElement('input'));
    replyCategory.name = 'comment[category]';
    replyCategory.value = comment.category_id.toString();
    replyCategory.type = 'hidden';

    const replyCsrf: HTMLInputElement = commentReplyInput.appendChild(document.createElement('input'));
    replyCsrf.name = 'csrf';
    replyCsrf.value = '{{ csrf_token("comments") }}';
    replyCsrf.type = 'hidden';

    const replyId: HTMLInputElement = commentReplyInput.appendChild(document.createElement('input'));
    replyId.name = 'comment[reply]';
    replyId.value = comment.comment_id.toString();
    replyId.type = 'hidden';

    const replyContainer: HTMLDivElement = commentReplyInput.appendChild(document.createElement('div'));
    replyContainer.className = 'comment__container';

    // reply container
    const replyAvatar: HTMLDivElement = replyContainer.appendChild(document.createElement('div'));
    replyAvatar.className = 'avatar comment__avatar';
    replyAvatar.style.backgroundImage = `url('/profile.php?m=avatar&u=${comment.user_id}')`;

    const replyContent: HTMLDivElement = replyContainer.appendChild(document.createElement('div'));
    replyContent.className = 'comment__content';

    // reply content
    const replyInfo: HTMLDivElement = replyContent.appendChild(document.createElement('div'));
    replyInfo.className = 'comment__info';

    const replyUser: HTMLDivElement = document.createElement('div');
    replyUser.className = 'comment__user';
    replyUser.textContent = comment.username;
    replyUser.style.color = comment.user_colour == null || (comment.user_colour & 0x40000000) > 0
        ? 'inherit'
        : '#' + (comment.user_colour & 0xFFFFFF).toString(16);
    replyInfo.appendChild(replyUser);

    const replyText: HTMLTextAreaElement = replyContent.appendChild(document.createElement('textarea'));
    replyText.className = 'comment__text input__textarea comment__text--input';
    replyText.name = 'comment[text]';
    replyText.placeholder = 'Share your extensive insights...';

    const replyActions: HTMLDivElement = replyContent.appendChild(document.createElement('div'));
    replyActions.className = 'comment__actions';

    const replyButton: HTMLButtonElement = replyActions.appendChild(document.createElement('button'));
    replyButton.className = 'input__button comment__action comment__action--button comment__action--post';
    replyButton.textContent = 'Reply';

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

    if (isReply)
        parent.appendChild(commentElement);
    else
        parent.insertBefore(commentElement, parent.firstElementChild);

    const placeholder: HTMLElement = document.getElementById('_no_comments_notice_' + comment.category_id);

    if (placeholder)
        placeholder.parentNode.removeChild(placeholder);
}

function commentVoteEventHandler(ev: Event): void {
    //
}

function commentVoteV2(
    commentId: number,
    vote: CommentVoteType,
    onSuccess: (voteInfo: CommentVotesInfo, userVote: CommentVoteType) => void,
    onFail: (message: string) => void
): void {
    if (!checkUserPerm('comments', CommentPermission.Vote) || !commentsRequestLock())
        return;

    const xhr: XMLHttpRequest = new XMLHttpRequest;
    xhr.onreadystatechange = () => {
        if (xhr.readyState !== 4)
            return;

        commentsFreeLock();

        const json: CommentVotesInfo = JSON.parse(xhr.responseText),
            message: string = json.error || json.message;

        if (message && onFail)
            onFail(message);
        else if (!message && onSuccess)
            onSuccess(json, vote);
    };
    xhr.open('GET', `/comments.php?m=vote&c=${commentId}&v=${vote}&csrf={{ csrf_token("comments") }}`);
    xhr.setRequestHeader('X-Misuzu-XHR', 'comments');
    xhr.send();
}
