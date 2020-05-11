var Misuzu = function() {
    if(Misuzu.initialised)
        throw 'Misuzu script has already initialised.';
    Misuzu.started = true;

    console.log(
        "%cMisuzu",
        'font-size: 48px; color: #8559a5; background: #111;'
        + 'border-radius: 5px; padding: 0 10px; text-shadow: 0 0 1em #fff;',
    );

    timeago.render(document.querySelectorAll('time'));
    hljs.initHighlighting();

    //initCSRF();
    //urlRegistryInit();
    Misuzu.User.refreshLocalUser();
    //userRelationsInit();
    //initDataRequestMethod();

    if(Misuzu.User.isLoggedIn())
        console.log(
            'You are %s with user id %d and colour %s.',
            Misuzu.User.localUser.getUsername(),
            Misuzu.User.localUser.getId(),
            Misuzu.User.localUser.getColour().getCSS()
        );
    else
        console.log('You aren\'t logged in.');

    Misuzu.Events.dispatch();
};
Misuzu.supportsSidewaysText = function() { return CSS.supports('writing-mode', 'sideways-lr'); };
