let ctx: AudioContext | null = null;

function getCtx(): AudioContext {
    if (!ctx) ctx = new AudioContext();
    return ctx;
}

function beep(frequency: number, duration: number, volume = 0.3, type: OscillatorType = 'sine') {
    try {
        const ac  = getCtx();
        const osc = ac.createOscillator();
        const gain = ac.createGain();

        osc.connect(gain);
        gain.connect(ac.destination);

        osc.type      = type;
        osc.frequency.setValueAtTime(frequency, ac.currentTime);
        gain.gain.setValueAtTime(volume, ac.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + duration);

        osc.start(ac.currentTime);
        osc.stop(ac.currentTime + duration);
    } catch {
        // AudioContext blocked or unavailable — silent fail
    }
}

export function useSound() {
    // Short scanner-style beep when item added to cart
    function playAddToCart() {
        beep(1200, 0.08, 0.25, 'square');
    }

    // Two-tone success chime when sale is completed
    function playSaleComplete() {
        beep(880, 0.12, 0.3, 'sine');
        setTimeout(() => beep(1320, 0.2, 0.3, 'sine'), 120);
    }

    return { playAddToCart, playSaleComplete };
}
