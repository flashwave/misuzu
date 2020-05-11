Misuzu.Perms = function(perms) {
    this.perms = perms || {};
};
Misuzu.Perms.prototype.perms = undefined;
Misuzu.Perms.check = function(section, value) {
    return function() { return this.perms[section] && (this.perms[section] & value) > 0; };
};

// Comment permissions
Misuzu.Perms.prototype.canCreateComment      = Misuzu.Perms.check('comments', 0x01);
Misuzu.Perms.prototype.canDeleteOwnComment   = Misuzu.Perms.check('comments', 0x0C);
Misuzu.Perms.prototype.canDeleteAnyComment   = Misuzu.Perms.check('comments', 0x08);
Misuzu.Perms.prototype.canLockCommentSection = Misuzu.Perms.check('comments', 0x10);
Misuzu.Perms.prototype.canPinComment         = Misuzu.Perms.check('comments', 0x20);
Misuzu.Perms.prototype.canVoteOnComment      = Misuzu.Perms.check('comments', 0x40);
