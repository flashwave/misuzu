class Colour {
    public static readonly INHERIT: number = 0x40000000;
    public static readonly READABILITY_THRESHOLD: number = 186;
    public static readonly LUMINANCE_WEIGHT_RED: number = 0.299;
    public static readonly LUMINANCE_WEIGHT_GREEN: number = 0.587;
    public static readonly LUMINANCE_WEIGHT_BLUE: number = 0.114;

    public raw: number;

    public constructor(rawColour: number = 0)
    {
        this.raw = rawColour === null ? Colour.INHERIT : rawColour;
    }

    public static none(): Colour {
        return new Colour(Colour.INHERIT);
    }

    public get inherit(): boolean {
        return (this.raw & Colour.INHERIT) > 0;
    }

    public set inherit(inherit: boolean) {
        if (inherit) {
            this.raw |= Colour.INHERIT;
        } else {
            this.raw &= ~Colour.INHERIT;
        }
    }

    public get red(): number {
        return (this.raw >> 16) & 0xFF;
    }

    public set red(red: number) {
        red = red & 0xFF;
        this.raw &= ~0xFF0000;
        this.raw |= red << 16;
    }

    public get green(): number {
        return (this.raw >> 8) & 0xFF;
    }

    public set green(green: number) {
        green = green & 0xFF;
        this.raw &= ~0xFF00;
        this.raw |= green << 8;
    }

    public get blue(): number {
        return this.raw & 0xFF;
    }

    public set blue(blue: number) {
        blue = blue & 0xFF;
        this.raw &= ~0xFF;
        this.raw |= blue;
    }

    public get luminance(): number {
        return Colour.LUMINANCE_WEIGHT_RED * this.red
            + Colour.LUMINANCE_WEIGHT_GREEN * this.green
            + Colour.LUMINANCE_WEIGHT_BLUE * this.blue;
    }

    public get hex(): string
    {
        let hex: string = (this.raw & 0xFFFFFF).toString(16);

        if (hex.length < 6)
            for (let i = 0; i < 6 - hex.length; i++)
                hex = '0' + hex;

        return '#' + hex;
    }
}
