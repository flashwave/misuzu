/// <reference path="../Parser.ts" />

function forumPostingInit(): void
{
    const postingForm: HTMLDivElement = document.querySelector('.js-forum-posting');

    if (!postingForm)
        return;

    const postingButtons: HTMLDivElement = postingForm.querySelector('.js-forum-posting-buttons'),
        postingText: HTMLTextAreaElement = postingForm.querySelector('.js-forum-posting-text'),
        postingParser: HTMLSelectElement = postingForm.querySelector('.js-forum-posting-parser'),
        postingPreview: HTMLDivElement = postingForm.querySelector('.js-forum-posting-preview'),
        postingMode: HTMLSpanElement = postingForm.querySelector('.js-forum-posting-mode'),
        previewButton: HTMLButtonElement = document.createElement('button'),
        bbcodeButtons = document.querySelector('.forum__post__actions--bbcode'),
        markdownButtons = document.querySelector('.forum__post__actions--markdown'),
        markupButtons = document.querySelectorAll('.forum__post__action--tag');

    window.addEventListener("beforeunload", (e) => {
        if (postingText.value.length > 0) {
            const message: string = 'Are you sure you want to close the tab?';
            e.returnValue = 'Are you sure you want to close the tab?';
            return message;
        }
    });

    for(let i = 0; i < markupButtons.length; i++) {
        let currentBtn = markupButtons[i] as HTMLDivElement;
        currentBtn.addEventListener('click', (ev) =>
            forumPostingInputMarkup(currentBtn.dataset.tagOpen, currentBtn.dataset.tagClose));
    }

    forumPostingSwitchButtons(parseInt(postingParser.value));

    let lastPostText: string = '',
        lastPostParser: Parser = null;

    postingParser.addEventListener('change', () => {
        const postParser: Parser = parseInt(postingParser.value);

        forumPostingSwitchButtons(postParser);

        if (postingPreview.hasAttribute('hidden'))
            return;

        // dunno if this is even possible, but ech
        if (postParser === lastPostParser)
            return;

        postingParser.setAttribute('disabled', 'disabled');
        previewButton.setAttribute('disabled', 'disabled');
        previewButton.classList.add('input__button--busy');

        forumPostingPreview(postParser, lastPostText, (success, text) => {
            if (!success) {
                messageBox(text);
                return;
            }

            if (postParser === Parser.Markdown) {
                postingPreview.classList.add('markdown');
            } else {
                postingPreview.classList.remove('markdown');
            }

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
    previewButton.addEventListener('click', () => {
        if (previewButton.value === 'back') {
            postingPreview.setAttribute('hidden', 'hidden');
            postingText.removeAttribute('hidden');
            previewButton.value = 'preview';
            previewButton.textContent = 'Preview';
            postingMode.textContent = postingMode.dataset.original;
            postingMode.dataset.original = null;
        } else {
            const postText: string = postingText.value,
                postParser: Parser = parseInt(postingParser.value);

            if (lastPostText === postText && lastPostParser === postParser) {
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

            forumPostingPreview(postParser, postText, (success, text) => {
                if (!success) {
                    messageBox(text);
                    return;
                }

                if (postParser === Parser.Markdown) {
                    postingPreview.classList.add('markdown');
                } else {
                    postingPreview.classList.remove('markdown');
                }

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
}

function forumPostingPreview(
    parser: Parser,
    text: string,
    callback: (success: boolean, htmlOrMessage: string) => void
): void {
    const xhr: XMLHttpRequest = new XMLHttpRequest,
        formData: FormData = new FormData;

    formData.append('post[mode]', 'preview');
    formData.append('post[text]', text);
    formData.append('post[parser]', parser.toString());

    xhr.addEventListener('readystatechange', () => {
        if (xhr.readyState !== XMLHttpRequest.DONE)
            return;

        if (xhr.status === 200) {
            callback(true, xhr.response);
        } else {
            callback(false, 'Failed to render preview.');
        }
    });
    xhr.open('POST', '/forum/posting.php');
    xhr.withCredentials = true;
    xhr.send(formData);
}

function forumPostingSwitchButtons(parser: Parser): void {
    const bbcodeButtons = document.querySelector('.forum__post__actions--bbcode') as HTMLElement,
        markdownButtons = document.querySelector('.forum__post__actions--markdown') as HTMLElement;

    switch(parser) {
        default:
        case Parser.PlainText:
            bbcodeButtons.hidden = markdownButtons.hidden = true;
            break;
        case Parser.BBCode:
            bbcodeButtons.hidden = false;
            markdownButtons.hidden = true;
            break;
        case Parser.Markdown:
            bbcodeButtons.hidden = true;
            markdownButtons.hidden = false;
            break;
    }
}

declare interface document {
    selection: any;
}

function forumPostingInputMarkup(tagOpen: string, tagClose: string): void {
    const editor: HTMLTextAreaElement = document.querySelector('.js-forum-posting-text'),
        doc = document as any;

    if(doc.selection) {
        editor.focus();
        let selected = doc.selection.createRange();
        selected.text = tagOpen + selected.text + tagClose;
        editor.focus();
    } else if(editor.selectionStart || editor.selectionStart === 0) {
        let startPos = editor.selectionStart,
            endPos = editor.selectionEnd,
            scrollTop = editor.scrollTop;

        editor.value = editor.value.substring(0, startPos)
            + tagOpen
            + editor.value.substring(startPos, endPos)
            + tagClose
            + editor.value.substring(endPos, editor.value.length);

        editor.focus();
        editor.selectionStart = startPos + tagOpen.length;
        editor.selectionEnd = endPos + tagOpen.length;
        editor.scrollTop + scrollTop;
    } else {
        editor.value += tagOpen + tagClose;
        editor.focus();
    }
}
