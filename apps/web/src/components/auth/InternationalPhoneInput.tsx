"use client";

import { useEffect, useId, useRef, useState } from "react";
import { formatPhoneCountryLabel } from "@/lib/customer/normalize-phone";
import {
  DEFAULT_PHONE_COUNTRY_ISO,
  getPhoneCountry,
  PHONE_COUNTRIES,
} from "@/lib/customer/phone-countries";

type InternationalPhoneInputProps = {
  id: string;
  value: string;
  countryIso: string;
  onValueChange: (value: string) => void;
  onCountryChange: (iso: string) => void;
  disabled?: boolean;
  error?: string;
  labelClassName?: string;
  inputClassName?: string;
};

const darkFieldClassName =
  "rounded-2xl border border-zinc-700/80 bg-zinc-950/80 text-sm text-white outline-none transition focus:border-[#c9a227] focus:ring-2 focus:ring-[#c9a227]/25 focus:shadow-[0_0_0_1px_rgba(201,162,39,0.35)] disabled:cursor-not-allowed disabled:opacity-60";

function ChevronIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
    </svg>
  );
}

export function InternationalPhoneInput({
  id,
  value,
  countryIso,
  onValueChange,
  onCountryChange,
  disabled,
  error,
  labelClassName = "block text-sm font-medium text-zinc-300",
  inputClassName = darkFieldClassName,
}: InternationalPhoneInputProps) {
  const listboxId = useId();
  const containerRef = useRef<HTMLDivElement>(null);
  const [isOpen, setIsOpen] = useState(false);
  const selectedCountry = getPhoneCountry(countryIso);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    const handlePointerDown = (event: MouseEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setIsOpen(false);
      }
    };

    document.addEventListener("mousedown", handlePointerDown);
    document.addEventListener("keydown", handleEscape);

    return () => {
      document.removeEventListener("mousedown", handlePointerDown);
      document.removeEventListener("keydown", handleEscape);
    };
  }, [isOpen]);

  const handleCountrySelect = (iso: string) => {
    onCountryChange(iso);
    setIsOpen(false);
  };

  return (
    <div>
      <label htmlFor={id} className={labelClassName}>
        Phone Number
      </label>
      <div className="mt-1.5 flex gap-2">
        <div ref={containerRef} className="relative shrink-0">
          <button
            type="button"
            id={`${id}-country`}
            disabled={disabled}
            aria-haspopup="listbox"
            aria-expanded={isOpen}
            aria-controls={listboxId}
            aria-label={`Country code ${selectedCountry.dialCode}`}
            onClick={() => setIsOpen((current) => !current)}
            className={`flex h-full min-w-[6.5rem] items-center justify-between gap-2 px-3 py-3 ${inputClassName}`}
          >
            <span className="flex items-center gap-1.5 whitespace-nowrap">
              <span className="text-lg leading-none" aria-hidden>
                {selectedCountry.flag}
              </span>
              <span className="text-sm font-medium tracking-tight">{selectedCountry.dialCode}</span>
            </span>
            <ChevronIcon
              className={`h-4 w-4 shrink-0 text-zinc-500 transition duration-200 ${isOpen ? "rotate-180" : ""}`}
            />
          </button>

          {isOpen ? (
            <ul
              id={listboxId}
              role="listbox"
              aria-label="Country code"
              className="absolute left-0 top-[calc(100%+0.5rem)] z-20 max-h-64 w-[min(19rem,calc(100vw-3rem))] overflow-auto rounded-2xl border border-zinc-700/90 bg-zinc-900/95 p-1.5 shadow-2xl shadow-black/50 backdrop-blur-md"
            >
              {PHONE_COUNTRIES.map((country) => {
                const isSelected = country.iso === countryIso;

                return (
                  <li key={country.iso} role="option" aria-selected={isSelected}>
                    <button
                      type="button"
                      onClick={() => handleCountrySelect(country.iso)}
                      className={`flex w-full items-center rounded-xl px-3 py-2.5 text-left text-sm transition duration-200 ${
                        isSelected
                          ? "bg-[#c9a227]/15 text-[#e8c547]"
                          : "text-zinc-200 hover:bg-zinc-800/80"
                      }`}
                    >
                      <span className="min-w-0 truncate">{formatPhoneCountryLabel(country)}</span>
                    </button>
                  </li>
                );
              })}
            </ul>
          ) : null}
        </div>

        <input
          id={id}
          name="phone"
          type="tel"
          autoComplete="tel-national"
          inputMode="tel"
          value={value}
          onChange={(event) => onValueChange(event.target.value)}
          disabled={disabled}
          placeholder={
            countryIso === DEFAULT_PHONE_COUNTRY_ISO ? "0712345678" : "Enter phone number"
          }
          className={`min-w-0 flex-1 px-4 py-3 placeholder:text-zinc-500 ${inputClassName}`}
          aria-invalid={Boolean(error)}
          aria-describedby={error ? `${id}-error` : `${id}-hint`}
        />
      </div>
      {error ? (
        <p id={`${id}-error`} className="mt-1 text-xs text-red-300">
          {error}
        </p>
      ) : (
        <p id={`${id}-hint`} className="mt-1 text-xs text-zinc-500">
          Accepts local numbers (0712345678, 0657123456) or international format (+255…, +254…).
        </p>
      )}
    </div>
  );
}
