function forumPollsInit(): void {
    const polls: NodeListOf<HTMLFormElement> = document.getElementsByClassName('js-forum-poll');

    if (polls.length < 1) {
        return;
    }

    for (let i = 0; i < polls.length; i++) {
        forumPollInit(polls[i]);
    }
}

function forumPollInit(poll: HTMLFormElement): void {
    console.log(poll);

    const options: HTMLNodeListOf<HTMLInputElement> = poll.getElementsByClassName('input__checkbox__input'),
        votesRemaining: HTMLDivElement = poll.querySelector('.js-forum-poll-remaining'),
        votesRemainingCount: HTMLSpanElement = poll.querySelector('.js-forum-poll-remaining-count'),
        votesRemainingPlural: HTMLSpanElement = poll.querySelector('.js-forum-poll-remaining-plural'),
        maxVotes: number = parseInt(poll.dataset.pollMaxVotes);

    if (maxVotes > 1) {
        let votes: number = maxVotes;

        for (let i = 0; i < options.length; i++) {
            if (options[i].checked) {
                if (votes < 1) {
                    options[i].checked = false;
                } else {
                    votes--;
                }
            }

            options[i].addEventListener('change', ev => {
                if (ev.target.checked) {
                    if (votes < 1) {
                        ev.target.checked = false;
                        ev.preventDefault();
                        return;
                    }

                    votes--;
                } else {
                    votes++;
                }

                votesRemainingCount.textContent = votes;
                votesRemainingPlural.hidden = votes == 1;
            });
        }

        votesRemaining.hidden = false;
        votesRemainingCount.textContent = votes;
        votesRemainingPlural.hidden = votes == 1;
    }
}
