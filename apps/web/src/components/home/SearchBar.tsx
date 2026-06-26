"use client";

import { useRouter } from "next/navigation";
import { FormEvent, useState } from "react";
import { SearchIcon } from "./icons";

interface SearchBarProps {
  className?: string;
  placeholder?: string;
  size?: "default" | "large";
}

export function SearchBar({
  className = "",
  placeholder = "Search products, brands or categories...",
  size = "default",
}: SearchBarProps) {
  const router = useRouter();
  const [query, setQuery] = useState("");

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    const trimmed = query.trim();
    if (trimmed) {
      router.push(`/products?q=${encodeURIComponent(trimmed)}`);
    } else {
      router.push("/products");
    }
  };

  const isLarge = size === "large";

  return (
    <form onSubmit={handleSubmit} className={className}>
      <label className="group relative block">
        <span className="sr-only">Search products</span>
        <SearchIcon
          className={`pointer-events-none absolute top-1/2 -translate-y-1/2 text-zinc-400 transition-colors group-focus-within:text-[#c9a227] ${
            isLarge ? "left-4 h-5 w-5" : "left-4 h-[18px] w-[18px]"
          }`}
        />
        <input
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={placeholder}
          className={`group w-full border border-zinc-200/90 bg-white text-zinc-900 outline-none transition-all placeholder:text-zinc-400 focus:border-[#c9a227] focus:ring-0 ${
            isLarge
              ? "h-[52px] rounded-xl pl-12 pr-5 text-[15px] shadow-none hover:border-zinc-300 focus:shadow-[0_0_0_3px_rgba(201,162,39,0.12)]"
              : "rounded-full py-2.5 pl-11 pr-4 text-sm shadow-[0_2px_12px_rgba(0,0,0,0.06)] hover:border-zinc-300 hover:shadow-[0_4px_16px_rgba(0,0,0,0.08)] focus:shadow-[0_4px_20px_rgba(201,162,39,0.18),0_0_0_3px_rgba(201,162,39,0.12)]"
          }`}
        />
      </label>
    </form>
  );
}
