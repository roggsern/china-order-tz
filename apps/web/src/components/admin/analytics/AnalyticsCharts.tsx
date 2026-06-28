"use client";

import { motion } from "framer-motion";
import type { DailySalesPoint, StatusDistributionPoint } from "@/lib/admin/analytics";
import { formatPrice } from "@/lib/catalog/utils";

function maxValue(values: number[]): number {
  return Math.max(...values, 1);
}

export function RevenueBarChart({ data }: { data: DailySalesPoint[] }) {
  const peak = maxValue(data.map((point) => point.revenue));

  return (
    <div className="mt-6 flex h-56 items-end gap-1.5 sm:gap-2">
      {data.map((point, index) => {
        const height = `${Math.max(6, (point.revenue / peak) * 100)}%`;

        return (
          <div key={point.date} className="flex min-w-0 flex-1 flex-col items-center gap-2">
            <motion.div
              initial={{ height: 0, opacity: 0 }}
              animate={{ height, opacity: 1 }}
              transition={{ delay: index * 0.03, duration: 0.35, ease: "easeOut" }}
              title={`${point.label}: ${formatPrice(point.revenue)}`}
              className="w-full max-w-8 rounded-t-md bg-gradient-to-t from-[#8b6914] to-[#e8c547] shadow-[0_4px_16px_rgba(201,162,39,0.25)]"
            />
            <span className="truncate text-[9px] font-medium text-zinc-500 sm:text-[10px]">
              {point.label}
            </span>
          </div>
        );
      })}
    </div>
  );
}

export function SalesTrendChart({ data }: { data: DailySalesPoint[] }) {
  if (data.length === 0) {
    return null;
  }

  const peak = maxValue(data.map((point) => point.revenue));
  const width = 640;
  const height = 180;
  const padding = 12;
  const step = (width - padding * 2) / Math.max(data.length - 1, 1);

  const points = data.map((point, index) => {
    const x = padding + index * step;
    const y = height - padding - (point.revenue / peak) * (height - padding * 2);
    return { x, y, point };
  });

  const polyline = points.map((entry) => `${entry.x},${entry.y}`).join(" ");
  const area = `${padding},${height - padding} ${polyline} ${width - padding},${height - padding}`;

  return (
    <div className="mt-4 overflow-x-auto">
      <svg viewBox={`0 0 ${width} ${height}`} className="h-48 w-full min-w-[320px]">
        <defs>
          <linearGradient id="salesTrendFill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="#c9a227" stopOpacity="0.35" />
            <stop offset="100%" stopColor="#c9a227" stopOpacity="0.02" />
          </linearGradient>
        </defs>
        <polygon points={area} fill="url(#salesTrendFill)" />
        <polyline
          points={polyline}
          fill="none"
          stroke="#e8c547"
          strokeWidth="3"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
        {points.map(({ x, y, point }) => (
          <g key={point.date}>
            <circle cx={x} cy={y} r="4" fill="#c9a227" stroke="#18181b" strokeWidth="2">
              <title>{`${point.label}: ${formatPrice(point.revenue)}`}</title>
            </circle>
          </g>
        ))}
      </svg>
      <div className="mt-2 flex justify-between gap-2 text-[10px] text-zinc-500">
        <span>{data[0]?.label}</span>
        <span>{data[data.length - 1]?.label}</span>
      </div>
    </div>
  );
}

export function StatusPieChart({ data }: { data: StatusDistributionPoint[] }) {
  const total = data.reduce((sum, entry) => sum + entry.count, 0);

  if (total === 0) {
    return <p className="mt-8 text-center text-sm text-zinc-500">No order status data yet.</p>;
  }

  let cursor = 0;
  const gradientStops = data
    .map((entry) => {
      const start = (cursor / total) * 100;
      cursor += entry.count;
      const end = (cursor / total) * 100;
      return `${entry.color} ${start}% ${end}%`;
    })
    .join(", ");

  return (
    <div className="mt-6 flex flex-col items-center gap-6 lg:flex-row lg:items-start">
      <motion.div
        initial={{ scale: 0.9, opacity: 0 }}
        animate={{ scale: 1, opacity: 1 }}
        transition={{ duration: 0.35 }}
        className="relative h-44 w-44 shrink-0 rounded-full shadow-[0_8px_32px_rgba(0,0,0,0.18)]"
        style={{ background: `conic-gradient(${gradientStops})` }}
        aria-hidden
      >
        <div className="absolute inset-6 flex flex-col items-center justify-center rounded-full bg-zinc-950 text-center">
          <p className="text-[10px] font-bold uppercase tracking-wide text-zinc-500">Orders</p>
          <p className="text-2xl font-bold text-[#e8c547]">{total}</p>
        </div>
      </motion.div>

      <ul className="grid w-full flex-1 gap-2 sm:grid-cols-2">
        {data.map((entry, index) => (
          <motion.li
            key={entry.status}
            initial={{ opacity: 0, x: -8 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: index * 0.04, duration: 0.25 }}
            className="flex items-center justify-between rounded-xl border border-zinc-800 bg-zinc-900/60 px-3 py-2.5"
          >
            <div className="flex items-center gap-2.5">
              <span
                className="h-2.5 w-2.5 rounded-full"
                style={{ backgroundColor: entry.color }}
                aria-hidden
              />
              <span className="text-sm font-medium text-zinc-200">{entry.label}</span>
            </div>
            <span className="text-sm font-bold text-[#c9a227]">{entry.count}</span>
          </motion.li>
        ))}
      </ul>
    </div>
  );
}
