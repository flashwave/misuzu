const MSZ_COLOUR_INHERIT = 0x40000000;

function colourGetInherit(colour: number): boolean {
    return colour === null || (colour & MSZ_COLOUR_INHERIT) > 0;
}

function colourGetHex(colour: number): string {
    return '#' + (colour & 0xFFFFFF).toString(16);
}

function colourGetCSS(colour: number): string {
    if(colourGetInherit(colour)) {
        return 'inherit';
    }

    return colourGetHex(colour);
}

class Colour {
    private static readonly FLAG_INHERIT: number = 0x40000000;

    private static readonly READABILITY_THRESHOLD: number = 186;
    private static readonly LUMINANCE_WEIGHT_RED: number = .299;
    private static readonly LUMINANCE_WEIGHT_GREEN: number = .587;
    private static readonly LUMINANCE_WEIGHT_BLUE: number = .114;

    private raw: number = 0;

    public constructor(raw: number = 0) {
        this.SetRaw(raw);
    }

    public static None(): Colour {
        return new Colour(Colour.FLAG_INHERIT);
    }

    public static FromRgb(red: number, green: number, blue: number): Colour {
        return (new Colour).SetRed(red).SetGreen(green).SetBlue(blue);
    }
    public static FromHex(hex: string): Colour {
        return (new Colour).SetHex(hex);
    }

    public GetRaw(): number {
        return this.raw;
    }
    public SetRaw(raw: number): Colour {
        if(raw < 0 || raw > 0x7FFFFFFF)
            throw 'Invalid raw colour.';
        this.raw = raw;
        return this;
    }

    public GetInherit(): boolean {
        return (this.GetRaw() & Colour.FLAG_INHERIT) > 0;
    }
    public SetInherit(inherit: boolean): Colour {
        let raw: number = this.GetRaw();

        if(inherit)
            raw |= Colour.FLAG_INHERIT;
        else
            raw &= ~Colour.FLAG_INHERIT;

        this.SetRaw(raw);
        return this;
    }

    public GetRed(): number {
        return (this.GetRaw() & 0xFF0000) >> 16;
    }
    public SetRed(red: number): Colour {
        if(red < 0 || red > 0xFF)
            throw 'Invalid red value.';

        let raw: number = this.GetRaw();
        raw &= ~0xFF0000;
        raw |= red << 16;
        this.SetRaw(raw);

        return this;
    }

    public GetGreen(): number {
        return (this.GetRaw() & 0xFF00) >> 8;
    }
    public SetGreen(green: number): Colour {
        if(green < 0 || green > 0xFF)
            throw 'Invalid green value.';

        let raw: number = this.GetRaw();
        raw &= ~0xFF00;
        raw |= green << 8;
        this.SetRaw(raw);

        return this;
    }

    public GetBlue(): number {
        return (this.GetRaw() & 0xFF);
    }
    public SetBlue(blue: number): Colour {
        if(blue < 0 || blue > 0xFF)
            throw 'Invalid blue value.';

        let raw: number = this.GetRaw();
        raw &= ~0xFF;
        raw |= blue;
        this.SetRaw(raw);

        return this;
    }

    public GetLuminance(): number {
        return Colour.LUMINANCE_WEIGHT_RED   * this.GetRed()
             + Colour.LUMINANCE_WEIGHT_GREEN * this.GetGreen()
             + Colour.LUMINANCE_WEIGHT_BLUE  * this.GetBlue();
    }

    public GetHex(): string {
        let hex: string = (this.GetRaw() & 0xFFFFFF).toString(16);

        if(hex.length < 6)
            hex = '000000'.substring(0, 6 - hex.length) + hex;

        return hex;
    }
    public SetHex(hex: string): Colour {
        if(hex[0] === '#')
            hex = hex.substring(1);

        if(/[^A-Fa-f0-9]/g.test(hex))
            throw 'Argument contains invalid characters.';

        if(hex.length === 3) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        } else if(hex.length !== 6) {
            throw 'Argument is not a hex string.';
        }

        return this.SetRaw(parseInt(hex, 16));
    }

    public GetCSS(): string {
        if(this.GetInherit())
            return 'inherit';

        return '#' + this.GetHex();
    }

    public extractCSSContract(
        dark: string = 'dark', light: string = 'light', inheritIsDark: boolean = true
    ): string {
        if(this.GetInherit())
            return inheritIsDark ? dark : light;

        return this.GetLuminance() > Colour.READABILITY_THRESHOLD ? dark : light;
    }
}
