Misuzu.Comments = {};
Misuzu.Comments.Vote = DefineEnum({
    none:     0,
    like:     1,
    dislike: -1,
});
Misuzu.Comments.init = function() {
    var commentDeletes = document.getElementsByClassName('comment__action--delete');
    for(var i = 0; i < commentDeletes.length; ++i) {
        commentDeletes[i].addEventListener('click', Misuzu.Comments.deleteCommentHandler);
        commentDeletes[i].dataset.href = commentDeletes[i].href;
        commentDeletes[i].href = 'javascript:;';
    }

    var commentInputs = document.getElementsByClassName('comment__text--input');
    for(var i = 0; i < commentInputs.length; ++i) {
        commentInputs[i].form.action = 'javascript:void(0);';
        commentInputs[i].form.addEventListener('submit', Misuzu.Comments.postCommentHandler);
        commentInputs[i].addEventListener('keydown', Misuzu.Comments.inputCommentHandler);
    }

    var voteButtons = document.getElementsByClassName('comment__action--vote');
    for(var i = 0; i < voteButtons.length; ++i) {
        voteButtons[i].href = 'javascript:;';
        voteButtons[i].addEventListener('click', Misuzu.Comments.voteCommentHandler);
    }

    var pinButtons = document.getElementsByClassName('comment__action--pin');
    for(var i = 0; i < pinButtons.length; ++i) {
        pinButtons[i].href = 'javascript:;';
        pinButtons[i].addEventListener('click', Misuzu.Comments.pinCommentHandler);
    }
};
Misuzu.Comments.postComment = function(formData, onSuccess, onFailure) {
    if(!Misuzu.User.isLoggedIn()
        || !Misuzu.User.localUser.perms.canCreateComment()) {
        if(onFailure)
            onFailure("You aren't allowed to post comments.");
        return;
    }

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
    xhr.open('POST', Misuzu.Urls.format('comment-create'));
    xhr.setRequestHeader('X-Misuzu-XHR', 'comments');
    xhr.send(formData);
};
Misuzu.Comments.postCommentHandler = function() {
    if(this.dataset.disabled)
        return;
    this.dataset.disabled = '1';
    this.style.opacity = '0.5';

    Misuzu.Comments.postComment(
        Misuzu.FormUtils.extractFormData(this, true),
        Misuzu.Comments.postCommentSuccess.bind(this),
        Misuzu.Comments.postCommentFailed.bind(this)
    );
};
Misuzu.Comments.inputCommentHandler = function(ev) {
    if(ev.code === 'Enter' && ev.ctrlKey && !ev.altKey && !ev.shiftKey && !ev.metaKey) {
        Misuzu.Comments.postComment(
            Misuzu.FormUtils.extractFormData(this.form, true),
            Misuzu.Comments.postCommentSuccess.bind(this.form),
            Misuzu.Comments.postCommentFailed.bind(this.form)
        );
    }
};
Misuzu.Comments.postCommentSuccess = function(comment) {
    if(this.classList.contains('comment--reply'))
        this.parentNode.parentNode.querySelector('label.comment__action').click();

    Misuzu.Comments.insertComment(comment, this);
    this.style.opacity = '1';
    this.dataset.disabled = '';
};
Misuzu.Comments.postCommentFailed = function(message) {
    Misuzu.showMessageBox(message);
    this.style.opacity = '1';
    this.dataset.disabled = '';
};
Misuzu.Comments.deleteComment = function(commentId, onSuccess, onFailure) {
    if(!Misuzu.User.isLoggedIn()
        || !Misuzu.User.localUser.perms.canDeleteOwnComment()) {
        if(onFailure)
            onFailure('You aren\'t allowed to delete comments.');
        return;
    }

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
    xhr.open('GET', Misuzu.Urls.format('comment-delete', [Misuzu.Urls.v('comment', commentId)]));
    xhr.setRequestHeader('X-Misuzu-XHR', 'comments');
    xhr.send();
};
Misuzu.Comments.deleteCommentHandler = function() {
    var commentId = parseInt(this.dataset.commentId);
    if(commentId < 1)
        return;

    Misuzu.Comments.deleteComment(
        commentId,
        function(info) {
            var elem = document.getElementById('comment-' + info.id);

            if(elem)
                elem.parentNode.removeChild(elem);
        },
        function(message) { Misuzu.showMessageBox(message); }
    );
};
Misuzu.Comments.pinComment = function(commentId, pin, onSuccess, onFailure) {
    if(!Misuzu.User.isLoggedIn()
        || !Misuzu.User.localUser.perms.canPinComment()) {
        if(onFailure)
            onFailure("You aren't allowed to pin comments.");
        return;
    }

    var xhr = new XMLHttpRequest;
    xhr.onreadystatechange = function() {
        if(xhr.readyState !== 4)
            return;

        Misuzu.CSRF.setToken(xhr.getResponseHeader('X-Misuzu-CSRF'));

        var json = JSON.parse(xhr.responseText),
            message = json.error || json.message;

        if(message && onFailure)
            onFailure(message);
        else if(!message && onSuccess)
            onSuccess(json);
    };
    xhr.open('GET', Misuzu.Urls.format('comment-' + (pin ? 'pin' : 'unpin'), [Misuzu.Urls.v('comment', commentId)]));
    xhr.setRequestHeader('X-Misuzu-XHR', 'comments');
    xhr.send();
};
Misuzu.Comments.pinCommentHandler = function() {
    var target = this,
        commentId = parseInt(target.dataset.commentId),
        isPinned = target.dataset.commentPinned !== '0';

    target.textContent = '...';

    Misuzu.Comments.pinComment(
        commentId,
        !isPinned,
        function(info) {
            if(info.comment_pinned === null) {
                target.textContent = 'Pin';
                target.dataset.commentPinned = '0';
                var pinElement = document.querySelector('#comment-' + info.comment_id + ' .comment__pin');
                pinElement.parentElement.removeChild(pinElement);
            } else {
                target.textContent = 'Unpin';
                target.dataset.commentPinned = '1';

                var pinInfo = document.querySelector('#comment-' + info.comment_id + ' .comment__info'),
                    pinElement = document.createElement('div'),
                    pinTime = document.createElement('time'),
                    pinDateTime = new Date(info.comment_pinned + 'Z');

                pinTime.title = pinDateTime.toLocaleString();
                pinTime.dateTime = pinDateTime.toISOString();
                pinTime.textContent = timeago.format(pinDateTime);
                timeago.render(pinTime);

                pinElement.className = 'comment__pin';
                pinElement.appendChild(document.createTextNode('Pinned '));
                pinElement.appendChild(pinTime);
                pinInfo.appendChild(pinElement);
            }
        },
        function(message) {
            target.textContent = isPinned ? 'Unpin' : 'Pin';
            Misuzu.showMessageBox(message);
        }
    );
};
Misuzu.Comments.voteComment = function(commentId, vote, onSuccess, onFailure) {
    if(!Misuzu.User.isLoggedIn()
        || !Misuzu.User.localUser.perms.canVoteOnComment()) {
        if(onFailure)
            onFailure("You aren't allowed to vote on comments.");
        return;
    }

    var xhr = new XMLHttpRequest;
    xhr.onreadystatechange = function() {
        if(xhr.readyState !== 4)
            return;

        Misuzu.CSRF.setToken(xhr.getResponseHeader('X-Misuzu-CSRF'));

        var json = JSON.parse(xhr.responseText),
            message = json.error || json.message;

        if(message && onFailure)
            onFailure(message);
        else if(!message && onSuccess)
            onSuccess(json);
    };
    xhr.open('GET', Misuzu.Urls.format('comment-vote', [Misuzu.Urls.v('comment', commentId), Misuzu.Urls.v('vote', vote)]));
    xhr.setRequestHeader('X-Misuzu-XHR', 'comments');
    xhr.send();
};
Misuzu.Comments.voteCommentHandler = function() {
    var commentId = parseInt(this.dataset.commentId),
        voteType = parseInt(this.dataset.commentVote),
        buttons = document.querySelectorAll('.comment__action--vote[data-comment-id="' + commentId + '"]'),
        likeButton = document.querySelector('.comment__action--like[data-comment-id="' + commentId + '"]'),
        dislikeButton = document.querySelector('.comment__action--dislike[data-comment-id="' + commentId + '"]'),
        classVoted = 'comment__action--voted';

    for(var i = 0; i < buttons.length; ++i) {
        buttons[i].textContent = buttons[i] === this ? '...' : '';
        buttons[i].classList.remove(classVoted);
        buttons[i].dataset.commentVote = buttons[i] === likeButton
            ? (voteType === Misuzu.Comments.Vote.like    ? Misuzu.Comments.Vote.none : Misuzu.Comments.Vote.like   ).toString()
            : (voteType === Misuzu.Comments.Vote.dislike ? Misuzu.Comments.Vote.none : Misuzu.Comments.Vote.dislike).toString();
    }

    Misuzu.Comments.voteComment(
        commentId,
        voteType,
        function(info) {
            switch(voteType) {
                case Misuzu.Comments.Vote.like:
                    likeButton.classList.add(classVoted);
                    break;
                case Misuzu.Comments.Vote.dislike:
                    dislikeButton.classList.add(classVoted);
                    break;
            }

            likeButton.textContent    = info.likes    > 0 ? ('Like ('    + info.likes.toLocaleString()    + ')') : 'Like';
            dislikeButton.textContent = info.dislikes > 0 ? ('Dislike (' + info.dislikes.toLocaleString() + ')') : 'Dislike';
        },
        function(message) {
            likeButton.textContent = 'Like';
            dislikeButton.textContent = 'Dislike';
            Misuzu.showMessageBox(message);
        }
    );
};
Misuzu.Comments.insertComment = function(comment, form) {
    var isReply = form.classList.contains('comment--reply'),
        parent = isReply
            ? form.parentElement
            : form.parentElement.parentElement.getElementsByClassName('comments__listing')[0],
        repliesIndent = isReply
            ? (parseInt(parent.classList[1].substr(25)) + 1)
            : 1,
        commentElement = Misuzu.Comments.buildComment(comment, repliesIndent);

    if(isReply)
        parent.appendChild(commentElement);
    else
        parent.insertBefore(commentElement, parent.firstElementChild);

    var placeholder = document.getElementById('_no_comments_notice_' + comment.category_id);
    if(placeholder)
        placeholder.parentNode.removeChild(placeholder);
};
Misuzu.Comments.buildComment = function(comment, layer) {
    comment = comment || {};
    layer = parseInt(layer || 0);

    var date = new Date(comment.comment_created + 'Z'),
        colour = new Misuzu.Colour(comment.user_colour),
        actions = [],
        commentTime = CreateElement({
        tag: 'time',
        props: {
            className: 'comment__date',
            title: date.toLocaleString(),
            datetime: date.toISOString(),
        },
        children: timeago.format(date),
    });

    if(Misuzu.User.isLoggedIn() && Misuzu.User.localUser.perms.canVoteOnComment()) {
        actions.push(CreateElement({
            tag: 'a',
            props: {
                className: 'comment__action comment__action--link comment__action--vote comment__action--like',
                'data-comment-id': comment.comment_id,
                'data-comment-vote': Misuzu.Comments.Vote.like,
                href: 'javascript:;',
                onclick: Misuzu.Comments.voteCommentHandler,
            },
            children: 'Like',
        }));
        actions.push(CreateElement({
            tag: 'a',
            props: {
                className: 'comment__action comment__action--link comment__action--vote comment__action--dislike',
                'data-comment-id': comment.comment_id,
                'data-comment-vote': Misuzu.Comments.Vote.dislike,
                href: 'javascript:;',
                onclick: Misuzu.Comments.voteCommentHandler,
            },
            children: 'Dislike',
        }));
    }

    actions.push(CreateElement({
        tag: 'label',
        props: {
            className: 'comment__action comment__action--link',
            'for': 'comment-reply-toggle-' + comment.comment_id.toString()
        },
        children: 'Reply',
    }));

    var commentText = CreateBasicElement('comment__text');
    if(comment.comment_html)
        commentText.innerHTML = comment.comment_html;
    else
        commentText.textContent = comment.comment_text;

    var commentElem = CreateElement({
        props: {
            className: 'comment',
            id: 'comment-' + comment.comment_id.toString(),
        },
        children: [
            {
                props: { className: 'comment__container', },
                children: [
                    {
                        tag: 'a',
                        props: {
                            className: 'comment__avatar',
                            href: Misuzu.Urls.format('user-profile', [{name:'user',value:comment.user_id}]),
                        },
                        children: {
                            tag: 'img',
                            props: {
                                className: 'avatar',
                                alt: comment.username,
                                width: (layer <= 1 ? 50 : 40),
                                height: (layer <= 1 ? 50 : 40),
                                src: Misuzu.Urls.format('user-avatar', [
                                    { name: 'user', value: comment.user_id },
                                    { name: 'res', value: layer <= 1 ? 100 : 80 }
                                ]),
                            },
                        },
                    },
                    {
                        props: { className: 'comment__content', },
                        children: [
                            {
                                props: { className: 'comment__info', },
                                children: [
                                    {
                                        tag: 'a',
                                        props: {
                                            className: 'comment__user comment__user--link',
                                            href: Misuzu.Urls.format('user-profile', [{name:'user',value:comment.user_id}]),
                                            style: '--user-colour: ' + colour.getCSS(),
                                        },
                                        children: comment.username,
                                    },
                                    {
                                        tag: 'a',
                                        props: {
                                            className: 'comment__link',
                                            href: '#comment-' + comment.comment_id.toString(),
                                        },
                                        children: commentTime,
                                    },
                                ],
                            },
                            commentText,
                            {
                                props: { className: 'comment__actions', },
                                children: actions,
                            },
                        ],
                    },
                ],
            },
            {
                props: {
                    className: 'comment__replies comment__replies--indent-' + layer.toString(),
                    id: 'comment-' + comment.comment_id.toString() + '-replies',
                },
                children: [
                    {
                        tag: 'input',
                        props: {
                            className: 'comment__reply-toggle',
                            type: 'checkbox',
                            id: ('comment-reply-toggle-' + comment.comment_id.toString()),
                        },
                    },
                    {
                        tag: 'form',
                        props: {
                            className: 'comment comment--input comment--reply',
                            id: 'comment-reply-' + comment.comment_id.toString(),
                            method: 'post',
                            action: 'javascript:;',
                            onsubmit: Misuzu.Comments.postCommentHandler,
                        },
                        children: [
                            { tag: 'input', props: { type: 'hidden', name: 'csrf',              value: Misuzu.CSRF.getToken() } },
                            { tag: 'input', props: { type: 'hidden', name: 'comment[category]', value: comment.category_id } },
                            { tag: 'input', props: { type: 'hidden', name: 'comment[reply]',    value: comment.comment_id } },
                            {
                                props: { className: 'comment__container' },
                                children: [
                                    {
                                        props: { className: 'avatar comment__avatar' },
                                        children: {
                                            tag: 'img',
                                            props: {
                                                className: 'avatar',
                                                width: 40,
                                                height: 40,
                                                src: Misuzu.Urls.format('user-avatar', [{name: 'user', value: comment.user_id}, {name: 'res', value: 80}]),
                                            },
                                        },
                                    },
                                    {
                                        props: { className: 'comment__content' },
                                        children: [
                                            { props: { className: 'comment__info' } },
                                            {
                                                tag: 'textarea',
                                                props: {
                                                    className: 'comment__text input__textarea comment__text--input',
                                                    name: 'comment[text]',
                                                    placeholder: 'Share your extensive insights...',
                                                    onkeydown: Misuzu.Comments.inputCommentHandler,
                                                },
                                            },
                                            {
                                                props: { className: 'comment__actions' },
                                                children: {
                                                    tag: 'button',
                                                    props: {
                                                        className: 'input__button comment__action comment__action--button comment__action--post',
                                                    },
                                                    children: 'Reply',
                                                },
                                            },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                ],
            },
        ],
    });

    timeago.render(commentTime);

    return commentElem;
};