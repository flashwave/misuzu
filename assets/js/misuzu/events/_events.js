Misuzu.Events = {};
Misuzu.Events.getList = function() {
    return [
        new Misuzu.Events.Christmas2019,
    ];
};
Misuzu.Events.dispatch = function() {
    var list = Misuzu.Events.getList();
    for(var i = 0; i < list.length; ++i)
        if(list[i].isActive())
            list[i].dispatch();
};
