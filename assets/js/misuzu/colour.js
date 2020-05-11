Misuzu.Colour = function(raw) {
    this.setRaw(raw || 0);
};
Misuzu.Colour.prototype.raw          = 0;
Misuzu.Colour.FLAG_INHERIT           = 0x40000000;
Misuzu.Colour.READABILITY_THRESHOLD  = 186;
Misuzu.Colour.LUMINANCE_WEIGHT_RED   = .299;
Misuzu.Colour.LUMINANCE_WEIGHT_GREEN = .587;
Misuzu.Colour.LUMINANCE_WEIGHT_BLUE  = .114;
Misuzu.Colour.none = function() { return new Misuzu.Colour(Misuzu.Colour.FLAG_INHERIT); };
Misuzu.Colour.fromRGB = function(red, green, blue) {
    var colour = new Misuzu.Colour;
    colour.setRed(red);
    colour.setGreen(green);
    colour.setBlue(blue);
    return colour;
};
Misuzu.Colour.fromHex = function(hex) {
    var colour = new Misuzu.Colour;
    colour.setHex(hex);
    return colour;
};
Misuzu.Colour.prototype.getRaw = function() { return this.raw; };
Misuzu.Colour.prototype.setRaw = function(raw) {
    this.raw = parseInt(raw) & 0x7FFFFFFF;
};
Misuzu.Colour.prototype.getInherit = function() { return (this.getRaw() & Misuzu.Colour.FLAG_INHERIT) > 0; };
Misuzu.Colour.prototype.setInherit = function(inherit) {
    var raw = this.getRaw();
    if(inherit)
        raw |= Misuzu.Colour.FLAG_INHERIT;
    else
        raw &= ~Misuzu.Colour.FLAG_INHERIT;
    this.setRaw(raw);
};
Misuzu.Colour.prototype.getRed = function() { return (this.getRaw() >> 16) & 0xFF };
Misuzu.Colour.prototype.setRed = function(red) {
    var raw = this.getRaw();
    raw &= ~0xFF0000;
    raw |= (parseInt(red) & 0xFF) << 16;
    this.setRaw(raw);
};
Misuzu.Colour.prototype.getGreen = function() { return (this.getRaw() >> 8) & 0xFF };
Misuzu.Colour.prototype.setGreen = function(green) {
    var raw = this.getRaw();
    raw &= ~0xFF0000;
    raw |= (parseInt(green) & 0xFF) << 8;
    this.setRaw(raw);
};
Misuzu.Colour.prototype.getBlue = function() { return this.getRaw() & 0xFF };
Misuzu.Colour.prototype.setBlue = function(blue) {
    var raw = this.getRaw();
    raw &= ~0xFF0000;
    raw |= parseInt(blue) & 0xFF;
    this.setRaw(raw);
};
Misuzu.Colour.prototype.getLuminance = function() {
    return Misuzu.Colour.LUMINANCE_WEIGHT_RED   * this.getRed()
         + Misuzu.Colour.LUMINANCE_WEIGHT_GREEN * this.getGreen()
         + Misuzu.Colour.LUMINANCE_WEIGHT_BLUE  * this.getBlue();
};
Misuzu.Colour.prototype.getHex = function() {
    var hex = (this.getRaw() & 0xFFFFFF).toString(16);
    if(hex.length < 6)
        hex = '000000'.substring(0, 6 - hex.length) + hex;
    return hex;
};
Misuzu.Colour.prototype.setHex = function(hex) {
    hex = (hex || '').toString();
    if(hex[0] === '#')
        hex = hex.substring(1);
    if(/[^A-Fa-f0-9]/g.test(hex))
        throw 'Argument contains invalid characters.';
    if(hex.length === 3)
        hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    else if(hex.length !== 6)
        throw 'Argument is not a hex string.';
    return this.setRaw(parseInt(hex, 16));
};
Misuzu.Colour.prototype.getCSS = function() {
    if(this.getInherit())
        return 'inherit';
    return '#' + this.getHex();
};
Misuzu.Colour.prototype.getCSSConstrast = function(dark, light, inheritIsDark) {
    dark = dark || 'dark';
    light = light || 'light';

    if(this.getInherit())
        return inheritIsDark ? dark : light;

    return this.getLuminance() > Misuzu.Colour.READABILITY_THRESHOLD ? dark : light;
};
