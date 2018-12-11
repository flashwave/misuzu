const MSZ_COLOUR_INHERIT = 0x40000000,
    MSZ_COLOUR_READABILITY_THRESHOLD = 186,
    MSZ_COLOUR_LUMINANCE_WEIGHT_RED = 0.299,
    MSZ_COLOUR_LUMINANCE_WEIGHT_GREEN = 0.587,
    MSZ_COLOUR_LUMINANCE_WEIGHT_BLUE = 0.114;

function colourCreate(): number {
    return 0;
}

function colourNone(): number {
    return MSZ_COLOUR_INHERIT;
}

function colourSetInherit(colour: number, enabled: boolean): number {
    if (enabled) {
        colour |= MSZ_COLOUR_INHERIT;
    } else {
        colour &= ~MSZ_COLOUR_INHERIT;
    }

    return colour;
}

function colourGetInherit(colour: number): boolean {
    return colour === null || (colour & MSZ_COLOUR_INHERIT) > 0;
}

function colourGetRed(colour: number): number {
    return (colour >> 16) & 0xFF;
}

function colourSetRed(colour: number, red: number): number {
    red = red & 0xFF;
    colour &= ~0xFF0000;
    colour |= red << 16;
    return colour;
}

function colourGetGreen(colour: number): number {
    return (colour >> 8) & 0xFF;
}

function colourSetGreen(colour: number, green: number): number {
    green = green & 0xFF;
    colour &= ~0xFF00;
    colour |= green << 8;
    return colour;
}

function colourGetBlue(colour: number): number {
    return colour & 0xFF;
}

function colourSetBlue(colour: number, blue: number): number {
    blue = blue & 0xFF;
    colour &= ~0xFF;
    colour |= blue;
    return colour;
}

function colourGetLuminance(colour: number): number {
    return MSZ_COLOUR_LUMINANCE_WEIGHT_RED * colourGetRed(colour)
        + MSZ_COLOUR_LUMINANCE_WEIGHT_GREEN * colourGetGreen(colour)
        + MSZ_COLOUR_LUMINANCE_WEIGHT_BLUE * colourGetBlue(colour);
}

function colourGetHex(colour: number): string {
    return '#' + (colour & 0xFFFFFF).toString(16);
}

function colourGetCSS(colour: number): string {
    if (colourGetInherit(colour)) {
        return 'inherit';
    }

    return colourGetHex(colour);
}

function colourGetCSSContrast(
    colour: number,
    dark: string = 'dark',
    light: string = 'light',
    inheritIsDark: boolean = true
): string {
    if (colourGetInherit(colour)) {
        return inheritIsDark ? dark : light;
    }

    return colourGetLuminance(colour) > MSZ_COLOUR_READABILITY_THRESHOLD
        ? dark
        : light;
}

function colourFromRGB(red: number, green: number, blue: number): number {
    let colour: number = colourCreate();
    colour = colourSetRed(colour, red);
    colour = colourSetGreen(colour, green);
    colour = colourSetBlue(colour, blue);
    return colour;
}

function colourFromHex(hex: string): number {
    if (hex.startsWith('#'))
        hex = hex.substr(1);

    const length: number = hex.length;

    if (length === 3) {
        hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    } else if (length !== 6) {
        return 0;
    }

    return parseInt(hex, 16);
}

class ColourProperties {
    red: number;
    green: number;
    blue: number;
    inherit: boolean;
    luminance: number;
}

function colourGetProperties(colour: number): ColourProperties {
    const props: ColourProperties = new ColourProperties;
    props.red = colourGetRed(colour);
    props.green = colourGetGreen(colour);
    props.blue = colourGetBlue(colour);
    props.inherit = colourGetInherit(colour);
    props.luminance = colourGetLuminance(colour);
    return props;
}
