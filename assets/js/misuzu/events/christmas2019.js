Misuzu.Events.Christmas2019 = function() {
    this.propName = propName = 'msz-christmas-' + (new Date).getFullYear().toString();
};
Misuzu.Events.Christmas2019.prototype.changeColour = function() {
    var count = parseInt(localStorage.getItem(this.propName));
    document.body.style.setProperty('--header-accent-colour', (count++ % 2) ? 'green' : 'red');
    localStorage.setItem(this.propName, count.toString());
};
Misuzu.Events.Christmas2019.prototype.isActive = function() {
    var d = new Date;
    return d.getMonth() === 11 && d.getDate() > 5 && d.getDate() < 27;
};
Misuzu.Events.Christmas2019.prototype.dispatch = function() {
    var headerBg = document.querySelector('.header__background'),
        menuBgs = document.querySelectorAll('.header__desktop__submenu__background');
    
    if(!localStorage.getItem(this.propName))
        localStorage.setItem(this.propName, '0');

    if(headerBg)
        headerBg.style.transition = 'background-color .4s';

    setTimeout(function() {
        if(headerBg)
            headerBg.style.transition = 'background-color 1s';

        for(var i = 0; i < menuBgs.length; i++)
            menuBgs[i].style.transition = 'background-color 1s';
    }, 1000);

    this.changeColour();
    setInterval(this.changeColour, 10000);
};
