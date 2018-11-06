import CurrentUserInfo from 'CurrentUserInfo';

declare const timeago: any;
declare const hljs: any;

// Initialisation process.
window.addEventListener('load', () => {
    console.log('a sardine grows from the soil');

    timeago().render(document.querySelectorAll('time'));
    hljs.initHighlighting();

    const userInfoElement: HTMLDivElement = document.getElementById('user-info') as HTMLDivElement;

    // if userInfo isn't defined, just stop the initialisation process for now
    if (!userInfoElement)
        return;

    const userInfo: CurrentUserInfo = JSON.parse(userInfoElement.textContent) as CurrentUserInfo;

    console.log(userInfo);
});
