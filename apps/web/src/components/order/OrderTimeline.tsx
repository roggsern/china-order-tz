"use client";

import type { OrderTimelineEvent } from "@/lib/types/order";

function formatTimelineDate(timestamp: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(timestamp));
}

interface OrderTimelineProps {
  events: OrderTimelineEvent[];
}

export function OrderTimeline({ events }: OrderTimelineProps) {
  return (
    <ol className="relative space-y-0" aria-label="Order progress">
      {events.map((event, index) => {
        const isLast = index === events.length - 1;
        const stateLabel =
          event.state === "completed"
            ? "Completed"
            : event.state === "current"
              ? "Current step"
              : "Upcoming";

        const connectorClass =
          event.state === "completed"
            ? "bg-emerald-300"
            : event.state === "current"
              ? "bg-gradient-to-b from-[#c9a227] to-zinc-200"
              : "bg-zinc-200";

        return (
          <li key={event.id} className="relative flex gap-4 pb-8 last:pb-0">
            {!isLast && (
              <span
                className={`absolute left-[15px] top-9 h-[calc(100%-18px)] w-0.5 ${connectorClass}`}
                aria-hidden
              />
            )}

            <span
              className={`relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold shadow-sm transition ${
                event.state === "completed"
                  ? "bg-emerald-500 text-white ring-4 ring-emerald-100"
                  : event.state === "current"
                    ? "scale-110 bg-gradient-to-br from-[#c9a227] to-[#e8c547] text-zinc-900 ring-4 ring-[#c9a227]/25"
                    : "bg-white text-zinc-400 ring-2 ring-zinc-200"
              }`}
              aria-hidden
            >
              {event.state === "completed" ? "✓" : index + 1}
            </span>

            <div
              className={`min-w-0 flex-1 rounded-2xl px-3 py-2.5 transition sm:px-4 ${
                event.state === "current"
                  ? "bg-[#c9a227]/8 ring-1 ring-[#c9a227]/20"
                  : event.state === "completed"
                    ? "bg-emerald-50/40"
                    : "bg-transparent"
              }`}
            >
              <div className="flex flex-wrap items-center gap-2">
                <p
                  className={`text-sm font-bold ${
                    event.state === "upcoming" ? "text-zinc-400" : "text-zinc-900"
                  }`}
                >
                  {event.title}
                </p>
                <span className="sr-only">{stateLabel}</span>
                {event.state === "current" ? (
                  <span
                    className="rounded-full bg-[#c9a227]/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#8b6914]"
                    aria-hidden
                  >
                    Current
                  </span>
                ) : null}
              </div>
              {event.description ? (
                <p
                  className={`mt-1 text-sm leading-relaxed ${
                    event.state === "upcoming" ? "text-zinc-400" : "text-zinc-500"
                  }`}
                >
                  {event.description}
                </p>
              ) : null}
              {event.timestamp ? (
                <time
                  dateTime={event.timestamp}
                  className="mt-1.5 block text-xs text-zinc-400"
                >
                  {formatTimelineDate(event.timestamp)}
                </time>
              ) : null}
            </div>
          </li>
        );
      })}
    </ol>
  );
}
