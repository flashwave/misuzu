Array.prototype.removeIndex = function(index) {
    this.splice(index, 1);
    return this;
};
Array.prototype.removeItem = function(item) {
    var index;
    while(this.length > 0 && (index = this.indexOf(item)) >= 0)
        this.removeIndex(index);
    return this;
};
Array.prototype.removeFind = function(predicate) {
    var index;
    while(this.length > 0 && (index = this.findIndex(predicate)) >= 0)
        this.removeIndex(index);
    return this;
};

var CreateElement = function(elemInfo) {
    elemInfo = elemInfo || {};
    var elem = document.createElement(elemInfo.tag || 'div');

    if(elemInfo.props) {
        var propKeys = Object.keys(elemInfo.props);

        for(var i = 0; i < propKeys.length; i++) {
            var propKey = propKeys[i];

            if(elemInfo.props[propKey] === undefined
                || elemInfo.props[propKey] === null)
                continue;

            switch(typeof elemInfo.props[propKey]) {
                case 'function':
                    elem.addEventListener(
                        propKey.substring(0, 2) === 'on'
                            ? propKey.substring(2).toLowerCase()
                            : propKey,
                        elemInfo.props[propKey]
                    );
                    break;

                default:
                    elem.setAttribute(propKey === 'className' ? 'class' : propKey, elemInfo.props[propKey]);
                    break;
            }
        }
    }

    if(elemInfo.children) {
        var children = elemInfo.children;

        if(!Array.isArray(children))
            children = [children];

        for(var i = 0; i < children.length; i++) {
            var child = children[i];

            switch(typeof child) {
                case 'string':
                    elem.appendChild(document.createTextNode(child));
                    break;

                case 'object':
                    if(child instanceof Element)
                        elem.appendChild(child);
                    else if(child.getElement)
                        elem.appendChild(child.getElement());
                    else
                        elem.appendChild(CreateElement(child));
                    break;

                default:
                    elem.appendChild(document.createTextNode(child.toString()));
                    break;
            }
        }
    }

    if(elemInfo.created)
        elemInfo.created(elem);

    return elem;
};

var CreateBasicElement = function(className, children, tagName) {
    return CreateElement({
        tag: tagName || null,
        props: {
            'class': className || null,
        },
        'children': children || null,
    });
};

var LoadScript = function(url, loaded, error) {
    if(document.querySelector('script[src="' + encodeURI(url) + '"]')) {
        if(loaded)
            loaded();
        return;
    }

    var script = document.createElement('script');
    script.type = 'text/javascript';
    if(loaded)
        script.addEventListener('load', function() { loaded(); });
    script.addEventListener('error', function() {
        document.body.removeChild(script);
        if(error)
            error();
    });
    script.src = url;
    document.body.appendChild(script);
};

var MakeEventTarget = function(object) {
    object.eventListeners = {};
    object.addEventListener = function(type, callback) {
        if(!(type in this.eventListeners))
            this.eventListeners[type] = [];
        this.eventListeners[type].push(callback);
    };
    object.removeEventListener = function(type, callback) {
        if(!(type in this.eventListeners))
            return;
        this.eventListeners[type].removeItem(callback);
    };
    object.dispatchEvent = function(event) {
        if(!(event.type in this.eventListeners))
            return true;
        var stack = this.eventListeners[event.type].slice();
        for(var i = 0; i < stack.length; ++i)
            stack[i].call(this, event);
        return !event.defaultPrevented;
    };
};

var DefineEnum = function(values) {
    var keys = Object.keys(values);
    for(var i = 0; i < keys.length; ++i)
        values[values[keys[i]]] = keys[i];
    return values;
};
