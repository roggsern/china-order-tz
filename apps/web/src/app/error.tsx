"use client";

import Link from "next/link";
import {
  ERROR_HOME_HREF,
  ERROR_HOME_LABEL,
  ERROR_RETRY_LABEL,
  ROUTE_ERROR_MESSAGE,
  ROUTE_ERROR_TITLE,
} from "@/lib/ui/error-recovery";

type ErrorPageProps = {
  error: Error & { digest?: string };
  reset: () => void;
};

export default function ErrorPage({ reset }: ErrorPageProps) {
  return (
    <main className="flex min-h-[70vh] items-center justify-center px-4 py-16">
      <div className="w-full max-w-lg rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm">
        <p className="text-sm font-semibold uppercase tracking-wide text-zinc-500">
          Error
        </p>
        <h1 className="mt-3 text-2xl font-bold text-zinc-900">
          {ROUTE_ERROR_TITLE}
        </h1>
        <p className="mt-3 text-sm leading-6 text-zinc-600">
          {ROUTE_ERROR_MESSAGE}
        </p>
        <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
          <button
            type="button"
            onClick={() => reset()}
            className="inline-flex items-center justify-center rounded-lg bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800"
          >
            {ERROR_RETRY_LABEL}
          </button>
          <Link
            href={ERROR_HOME_HREF}
            className="inline-flex items-center justify-center rounded-lg border border-zinc-300 px-5 py-2.5 text-sm font-medium text-zinc-900 transition hover:bg-zinc-50"
          >
            {ERROR_HOME_LABEL}
          </Link>
        </div>
      </div>
    </main>
  );
}
