Misuzu.Forum.Polls = {};
Misuzu.Forum.Polls.init = function() {
    var polls = document.getElementsByClassName('js-forum-poll');
    if(!polls.length)
        return;
    for(var i = 0; i < polls.length; ++i)
        Misuzu.Forum.Polls.initPoll(polls[i]);
};
Misuzu.Forum.Polls.initPoll = function() {
    var options = poll.getElementsByClassName('input__checkbox__input'),
        votesRemaining = poll.querySelector('.js-forum-poll-remaining'),
        votesRemainingCount = poll.querySelector('.js-forum-poll-remaining-count'),
        votesRemainingPlural = poll.querySelector('.js-forum-poll-remaining-plural'),
        maxVotes = parseInt(poll.dataset.pollMaxVotes);

    if(maxVotes < 2)
        return;

    var votes = maxVotes;

    for(var i = 0; i < options.length; ++i) {
        if(options[i].checked) {
            if(votes < 1)
                options[i].checked = false;
            else
                votes--;
        }

        options[i].addEventListener('change', function(ev) {
            if(this.checked) {
                if(votes < 1) {
                    this.checked = false;
                    ev.preventDefault();
                    return;
                }

                votes--;
            } else
                votes++;

            votesRemainingCount.textContent = votes.toString();
            votesRemainingPlural.hidden = votes == 1;
        });
    }

    votesRemaining.hidden = false;
    votesRemainingCount.textContent = votes.toString();
    votesRemainingPlural.hidden = votes == 1;
};
