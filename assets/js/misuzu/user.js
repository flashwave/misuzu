Misuzu.User = function(userInfo) {
    this.id = parseInt(userInfo.user_id || 0);
    this.name = (userInfo.username || '').toString();
    this.colour = new Misuzu.Colour(userInfo.user_colour || Misuzu.Colour.FLAG_INHERIT);
    this.perms = new Misuzu.Perms(userInfo.perms || {});
};
Misuzu.User.localUser = undefined;
Misuzu.User.refreshLocalUser = function() {
    var userInfo = document.getElementById('js-user-info');

    if(!userInfo)
        Misuzu.User.localUser = undefined;
    else
        Misuzu.User.localUser = new Misuzu.User(JSON.parse(userInfo.textContent));
};
Misuzu.User.isLoggedIn = function() { return Misuzu.User.localUser !== undefined; };
Misuzu.User.prototype.getId = function() { return this.id || 0; };
Misuzu.User.prototype.getUsername = function() { return this.name || ''; };
Misuzu.User.prototype.getColour = function() { return this.colour || null; };
