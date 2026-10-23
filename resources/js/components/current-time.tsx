
import { useEffect, useState } from "react";

interface Props {
    currentTime: string;
    timezone: string;
}

export default function CurrentTime({ currentTime, timezone }: Props) {
    const [time, setTime] = useState(() => new Date(currentTime));

    useEffect(() => {
        const interval = setInterval(() => {
            setTime((previousTime) =>
                new Date(previousTime.getTime() + 1000)
            );
        }, 1000);

        return () => clearInterval(interval);
    }, []);

    const parts = new Intl.DateTimeFormat("en-GB", {
        timeZone: timezone,
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hourCycle: "h23",
    }).formatToParts(time);

    const get = (type: string) =>
        parts.find((part) => part.type === type)?.value ?? "";

    const formattedTime =
        `${get("day")}/${get("month")}/${get("year")} ` +
        `${get("hour")}:${get("minute")}:${get("second")}`;

    return <span className="font-mono">{formattedTime}</span>;
}
