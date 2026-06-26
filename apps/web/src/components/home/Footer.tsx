"use client";

import { useState } from "react";
import Link from "next/link";
import { footerLinks } from "@/lib/home-data";
import { OfficialLogoImage } from "@/components/branding/OfficialLogoImage";
import { ArrowRightIcon } from "./icons";

const socialLinks = [
  { label: "Facebook", href: "#", abbr: "Fb" },
  { label: "LinkedIn", href: "#", abbr: "In" },
  { label: "X", href: "#", abbr: "X" },
  { label: "Instagram", href: "#", abbr: "Ig" },
] as const;

function FooterNewsletter() {
  const [email, setEmail] = useState("");
  const [submitted, setSubmitted] = useState(false);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (email.trim()) {
      setSubmitted(true);
      setEmail("");
    }
  }

  return (
    <div id="order-from-china">
      <h3 className="text-xs font-bold uppercase tracking-[0.2em] text-[#c9a227]">Newsletter</h3>
      <p className="mt-3 text-sm leading-relaxed text-zinc-400">
        Get exclusive deals, new arrivals, and import tips delivered to your inbox.
      </p>

      {submitted ? (
        <div className="mt-5 rounded-2xl border border-[#c9a227]/25 bg-[#c9a227]/10 px-4 py-4">
          <p className="text-sm font-semibold text-[#e8c547]">You&apos;re subscribed!</p>
          <p className="mt-1 text-xs text-zinc-500">Check your inbox for a welcome offer.</p>
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="mt-5">
          <label className="sr-only" htmlFor="footer-newsletter-email">
            Email address
          </label>
          <div className="flex flex-col gap-2 sm:flex-row">
            <input
              id="footer-newsletter-email"
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="Your email address"
              className="min-w-0 flex-1 rounded-xl border border-zinc-800 bg-zinc-900/80 px-4 py-2.5 text-sm text-white outline-none placeholder:text-zinc-600 focus:border-[#c9a227]/50 focus:ring-2 focus:ring-[#c9a227]/15"
            />
            <button
              type="submit"
              className="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#c9a227] px-5 py-2.5 text-sm font-bold text-zinc-900 transition hover:bg-[#e8c547]"
            >
              Subscribe
              <ArrowRightIcon className="h-4 w-4" />
            </button>
          </div>
        </form>
      )}
    </div>
  );
}

export function Footer() {
  return (
    <footer id="contact" className="relative bg-zinc-950 text-zinc-400">
      <div className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-[#c9a227]/40 to-transparent" />

      <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div className="grid gap-12 lg:grid-cols-12 lg:gap-8">
          <div className="lg:col-span-4">
            <OfficialLogoImage variant="footer" height={72} />
            <p className="mt-5 max-w-sm text-sm leading-relaxed">
              Tanzania&apos;s premium platform for importing quality products directly from China.
              Trusted by shoppers and businesses nationwide.
            </p>
            <div className="mt-8 flex gap-2">
              {socialLinks.map((social) => (
                <Link
                  key={social.label}
                  href={social.href}
                  aria-label={social.label}
                  className="flex h-10 w-10 items-center justify-center rounded-xl border border-zinc-800 bg-zinc-900/50 text-[11px] font-bold uppercase text-zinc-500 transition hover:border-[#c9a227]/40 hover:text-[#c9a227]"
                >
                  {social.abbr}
                </Link>
              ))}
            </div>
          </div>

          <div className="grid gap-10 sm:grid-cols-2 lg:col-span-5 lg:grid-cols-3">
            <div>
              <h3 className="text-xs font-bold uppercase tracking-[0.2em] text-[#c9a227]">About</h3>
              <ul className="mt-4 space-y-3">
                {footerLinks.about.map((link) => (
                  <li key={link.label}>
                    <Link href={link.href} className="text-sm transition hover:text-white">
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>

            <div>
              <h3 className="text-xs font-bold uppercase tracking-[0.2em] text-[#c9a227]">Contact</h3>
              <ul className="mt-4 space-y-3">
                {footerLinks.contact.map((link) => (
                  <li key={link.label}>
                    <Link href={link.href} className="text-sm transition hover:text-white">
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>

            <div>
              <h3 className="text-xs font-bold uppercase tracking-[0.2em] text-[#c9a227]">
                Quick Links
              </h3>
              <ul className="mt-4 space-y-3">
                {footerLinks.quickLinks.map((link) => (
                  <li key={link.label}>
                    <Link href={link.href} className="text-sm transition hover:text-white">
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          </div>

          <div className="grid gap-10 sm:grid-cols-2 lg:col-span-3 lg:grid-cols-1">
            <div>
              <h3 className="text-xs font-bold uppercase tracking-[0.2em] text-[#c9a227]">
                Buy From TZ
              </h3>
              <ul className="mt-4 space-y-3">
                {footerLinks.buyFromTz.map((link) => (
                  <li key={link.label}>
                    <Link href={link.href} className="text-sm transition hover:text-white">
                      {link.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>

            <FooterNewsletter />
          </div>
        </div>

        <div className="mt-14 flex flex-col items-center justify-between gap-4 border-t border-zinc-800/80 pt-8 sm:flex-row">
          <p className="text-xs text-zinc-600">
            &copy; {new Date().getFullYear()} CHINA ORDER TZ. All rights reserved.
          </p>
          <div className="flex gap-6 text-xs text-zinc-600">
            <Link href="#" className="transition hover:text-[#c9a227]">
              Terms of Service
            </Link>
            <Link href="#" className="transition hover:text-[#c9a227]">
              Privacy Policy
            </Link>
            <Link href="#" className="transition hover:text-[#c9a227]">
              Cookies
            </Link>
          </div>
        </div>
      </div>
    </footer>
  );
}
