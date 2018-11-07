// Collection class for support checks.
class Support {
    public static get sidewaysText(): boolean {
        return CSS.supports('writing-mode', 'sideways-lr');
    }
}
