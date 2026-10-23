// resources/js/components/animated-number.tsx
import { useEffect, useState } from "react";

interface Props {
    value: number;
    format: (n: number) => string;
    /** Animation duration in milliseconds */
    duration?: number;
    className?: string;
}

export default function AnimatedNumber({
    value,
    format,
    duration = 5000,
    className,
}: Props) {
    const [display, setDisplay] = useState(0);

    useEffect(() => {
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            setDisplay(value);
            return;
        }

        setDisplay(0);

        const startTime = performance.now();
        let frame = 0;

        const tick = (now: number) => {
            const progress = Math.min((now - startTime) / duration, 1);

            // Strong initial rise, followed by a very gradual finish.
            const eased =
                progress === 1
                    ? 1
                    : 1 - Math.pow(1 - progress, 7);

            setDisplay(progress === 1 ? value : value * eased);

            if (progress < 1) {
                frame = requestAnimationFrame(tick);
            }
        };

        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [value, duration]);

    return (
        <>
            <span aria-hidden="true" className={className}>
                {format(display)}
            </span>
            <span className="sr-only">{format(value)}</span>
        </>
    );
}