interface CurrentUserInfo {
    user_id: number;
    username: string;
    user_background_settings: number;
    user_colour: number;
    colour: Colour;
}

let userInfo: CurrentUserInfo;

function getRawCurrentUserInfo(): CurrentUserInfo
{
    const userInfoElement: HTMLDivElement = document.getElementById('js-user-info') as HTMLDivElement;

    if (!userInfoElement)
        return null;

    return JSON.parse(userInfoElement.textContent) as CurrentUserInfo;
}

function refreshCurrentUserInfo(): void
{
    userInfo = getRawCurrentUserInfo();

    if (userInfo)
        userInfo.colour = new Colour(userInfo.user_colour);
}

function getCurrentUser(attribute: string = null)
{
    if (attribute) {
        if (!userInfo) {
            return '';
        }

        return userInfo[attribute] || '';
    }

    return userInfo || null;
}

function userInit(): void
{
    refreshCurrentUserInfo();
    console.log(`You are ${getCurrentUser('username')} with user id ${getCurrentUser('user_id')} and colour ${getCurrentUser('colour').hex}.`);
}
