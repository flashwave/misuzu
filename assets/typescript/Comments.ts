let globalCommentLock = false;

function commentsLocked(): boolean
{
    return globalCommentLock;
}

function commentsRequestLock(): boolean
{
    if (commentsLocked())
        return false;

    globalCommentLock = true;
    return true;
}

function commentsFreeLock(): void
{
    globalCommentLock = false;
}

interface CommentDeletionInfo {
    comment_id: number;
    error: string;
    message: string;
}

function commentDelete(ev: Event)
{
    if (!checkUserPerm('comments', CommentPermission.Delete) || !commentsRequestLock())
        return;

    const xhr: XMLHttpRequest = new XMLHttpRequest(),
        target: HTMLAnchorElement = ev.target as HTMLAnchorElement;

    xhr.addEventListener('readystatechange', () => {
        if (xhr.readyState !== 4)
            return;
        commentsFreeLock();

        var json: CommentDeletionInfo = JSON.parse(xhr.responseText) as CommentDeletionInfo,
            message = json.error || json.message;

        if (message)
            alert(message);
        else {
            var elem = document.getElementById('comment-' + json.comment_id);

            if (elem)
                elem.parentNode.removeChild(elem);
        }
    });
    xhr.open('GET', target.dataset.href);
    xhr.setRequestHeader('X-Misuzu-XHR', 'comments');
    xhr.send();
}

function commentsInit() {
    const commentDeletes: HTMLCollectionOf<HTMLAnchorElement> = document.getElementsByClassName('comment__action--delete') as HTMLCollectionOf<HTMLAnchorElement>;

    for (var i = 0; i < commentDeletes.length; i++) {
        commentDeletes[i].onclick = commentDelete;
        commentDeletes[i].dataset.href = commentDeletes[i].href;
        commentDeletes[i].href = 'javascript:void(0);';
    }
}
