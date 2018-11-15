enum CommentPermission {
    Create = 1,
    EditOwn = 1 << 1,
    EditAny = 1 << 2,
    Edit = EditOwn | EditAny,
    DeleteOwn = 1 << 3,
    DeleteAny = 1 << 4,
    Delete = DeleteOwn | DeleteAny,
    Pin = 1 << 5,
    Lock = 1 << 6,
    Vote = 1 << 7,
}

function checkPerm(perms: number, perm: number): boolean {
    return (perms & perm) > 0;
}

function checkUserPerm(set: string, perm: number): boolean {
    const perms: number = getCurrentUser(set + '_perms') as number;

    if (!perms) {
        return false;
    }

    return checkPerm(perms, perm);
}
