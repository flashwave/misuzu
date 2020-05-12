Misuzu.Forum.Editor = {};
Misuzu.Forum.Editor.allowWindowClose = false;
Misuzu.Forum.Editor.init = function() {
    var postingForm = document.querySelector('.js-forum-posting');
    if(!postingForm)
        return;

    var postingButtons = postingForm.querySelector('.js-forum-posting-buttons'),
        postingText = postingForm.querySelector('.js-forum-posting-text'),
        postingParser = postingForm.querySelector('.js-forum-posting-parser'),
        postingPreview = postingForm.querySelector('.js-forum-posting-preview'),
        postingMode = postingForm.querySelector('.js-forum-posting-mode'),
        previewButton = document.createElement('button'),
        bbcodeButtons = document.querySelector('.forum__post__actions--bbcode'),
        markdownButtons = document.querySelector('.forum__post__actions--markdown'),
        markupButtons = document.querySelectorAll('.forum__post__action--tag');

    // hack: don't prompt user when hitting submit, really need to make this not stupid.
    postingButtons.firstElementChild.addEventListener('click', function() {
        Misuzu.Forum.Editor.allowWindowClose = true;
    });

    window.addEventListener('beforeunload', function(ev) {
        if(!Misuzu.Forum.Editor.allowWindowClose && postingText.value.length > 0) {
            ev.preventDefault();
            ev.returnValue = '';
        }
    });

    for(var i = 0; i < markupButtons.length; ++i)
        (function(currentBtn) {
            currentBtn.addEventListener('click', function(ev) {
                postingText.insertTags(currentBtn.dataset.tagOpen, currentBtn.dataset.tagClose);
            });
        })(markupButtons[i]);

    Misuzu.Forum.Editor.switchButtons(parseInt(postingParser.value));

    var lastPostText = '',
        lastPostParser = null;

    postingParser.addEventListener('change', function() {
        var postParser = parseInt(postingParser.value);
        Misuzu.Forum.Editor.switchButtons(postParser);

        if(postingPreview.hasAttribute('hidden'))
            return;

        // dunno if this would even be possible, but ech
        if(postParser === lastPostParser)
            return;

        postingParser.setAttribute('disabled', 'disabled');
        previewButton.setAttribute('disabled', 'disabled');
        previewButton.classList.add('input__button--busy');

        Misuzu.Forum.Editor.renderPreview(postParser, lastPostText, function(success, text) {
            if(!success) {
                Misuzu.showMessageBox(text);
                return;
            }

            if(postParser === Misuzu.Parser.markdown)
                postingPreview.classList.add('markdown');
            else
                postingPreview.classList.remove('markdown');

            lastPostParser = postParser;
            postingPreview.innerHTML = text;
            previewButton.removeAttribute('disabled');
            postingParser.removeAttribute('disabled');
            previewButton.classList.remove('input__button--busy');
        });
    });

    previewButton.className = 'input__button';
    previewButton.textContent = 'Preview';
    previewButton.type = 'button';
    previewButton.value = 'preview';
    previewButton.addEventListener('click', function() {
        if(previewButton.value === 'back') {
            postingPreview.setAttribute('hidden', 'hidden');
            postingText.removeAttribute('hidden');
            previewButton.value = 'preview';
            previewButton.textContent = 'Preview';
            postingMode.textContent = postingMode.dataset.original;
            postingMode.dataset.original = null;
        } else {
            var postText = postingText.value,
                postParser = parseInt(postingParser.value);

            if(lastPostText === postText && lastPostParser === postParser) {
                postingPreview.removeAttribute('hidden');
                postingText.setAttribute('hidden', 'hidden');
                previewButton.value = 'back';
                previewButton.textContent = 'Back';
                postingMode.dataset.original = postingMode.textContent;
                postingMode.textContent = 'Previewing';
                return;
            }

            postingParser.setAttribute('disabled', 'disabled');
            previewButton.setAttribute('disabled', 'disabled');
            previewButton.classList.add('input__button--busy');

            Misuzu.Forum.Editor.renderPreview(postParser, postText, function(success, text) {
                if(!success) {
                    Misuzu.showMessageBox(text);
                    return;
                }

                if(postParser === Misuzu.Parser.markdown)
                    postingPreview.classList.add('markdown');
                else
                    postingPreview.classList.remove('markdown');

                lastPostText = postText;
                lastPostParser = postParser;
                postingPreview.innerHTML = text;
                postingPreview.removeAttribute('hidden');
                postingText.setAttribute('hidden', 'hidden');
                previewButton.value = 'back';
                previewButton.textContent = 'Back';
                previewButton.removeAttribute('disabled');
                postingParser.removeAttribute('disabled');
                previewButton.classList.remove('input__button--busy');
                postingMode.dataset.original = postingMode.textContent;
                postingMode.textContent = 'Previewing';
            });
        }
    });

    postingButtons.insertBefore(previewButton, postingButtons.firstChild);
};
Misuzu.Forum.Editor.switchButtons = function(parser) {
    var bbcodeButtons = document.querySelector('.forum__post__actions--bbcode'),
        markdownButtons = document.querySelector('.forum__post__actions--markdown');

    switch(parser) {
        default:
        case Misuzu.Parser.plain:
            bbcodeButtons.hidden = markdownButtons.hidden = true;
            break;
        case Misuzu.Parser.bbcode:
            bbcodeButtons.hidden = false;
            markdownButtons.hidden = true;
            break;
        case Misuzu.Parser.markdown:
            bbcodeButtons.hidden = true;
            markdownButtons.hidden = false;
            break;
    }
};
Misuzu.Forum.Editor.renderPreview = function(parser, text, callback) {
    if(!callback)
        return;
    parser = parseInt(parser);
    text = text || '';

    var xhr = new XMLHttpRequest,
        formData = new FormData;

    formData.append('post[mode]', 'preview');
    formData.append('post[text]', text);
    formData.append('post[parser]', parser.toString());

    xhr.addEventListener('readystatechange', function() {
        if(xhr.readyState !== XMLHttpRequest.DONE)
            return;
        if(xhr.status === 200)
            callback(true, xhr.response);
        else
            callback(false, 'Failed to render preview.');
    });
    xhr.open('POST', Misuzu.Urls.format('forum-topic-new'));
    xhr.withCredentials = true;
    xhr.send(formData);
};
