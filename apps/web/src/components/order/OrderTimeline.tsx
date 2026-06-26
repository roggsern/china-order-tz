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
    <ol className="space-y-0" aria-label="Order progress">
      {events.map((event, index) => {
        const isLast = index === events.length - 1;
        const stateLabel =
          event.state === "completed"
            ? "Completed"
            : event.state === "current"
              ? "Current step"
              : "Upcoming";

        return (
          <li key={event.id} className="relative flex gap-4 pb-8 last:pb-0">
            {!isLast && (
              <span
                className="absolute left-[15px] top-8 h-[calc(100%-16px)] w-px bg-zinc-200"
                aria-hidden
              />
            )}

            <span
              className={`relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                event.state === "completed"
                  ? "bg-emerald-100 text-emerald-700 ring-2 ring-emerald-200"
                  : event.state === "current"
                    ? "bg-[#c9a227]/20 text-[#8b6914] ring-2 ring-[#c9a227]/40"
                    : "bg-zinc-100 text-zinc-400 ring-2 ring-zinc-200"
              }`}
              aria-hidden
            >
              {event.state === "completed" ? "✓" : index + 1}
            </span>

            <div className="min-w-0 flex-1 pt-0.5">
              <div className="flex flex-wrap items-center gap-2">
                <p className="text-sm font-bold text-zinc-900">{event.title}</p>
                <span className="sr-only">{stateLabel}</span>
                {event.state === "current" && (
                  <span
                    className="rounded-full bg-[#c9a227]/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#8b6914]"
                    aria-hidden
                  >
                    Current
                  </span>
                )}
              </div>
              {event.description && (
                <p className="mt-1 text-sm leading-relaxed text-zinc-500">{event.description}</p>
              )}
              {event.timestamp && (
                <time
                  dateTime={event.timestamp}
                  className="mt-1.5 block text-xs text-zinc-400"
                >
                  {formatTimelineDate(event.timestamp)}
                </time>
              )}
            </div>
          </li>
        );
      })}
    </ol>
  );
}
