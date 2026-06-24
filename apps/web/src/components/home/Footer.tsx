import Link from "next/link";
import { footerLinks } from "@/lib/home-data";

export function Footer() {
  return (
    <footer id="contact" className="bg-zinc-950 text-zinc-400">
      <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
          <div className="sm:col-span-2 lg:col-span-1">
            <Link href="/" className="inline-flex items-center gap-2">
              <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#c9a227] to-[#8b6914] text-sm font-black text-white">
                C
              </span>
              <div className="leading-tight">
                <span className="block text-[10px] font-semibold uppercase tracking-[0.25em] text-zinc-500">
                  China
                </span>
                <span className="block text-base font-bold text-white">
                  ORDER <span className="text-[#c9a227]">TZ</span>
                </span>
              </div>
            </Link>
            <p className="mt-4 max-w-xs text-sm leading-relaxed">
              Tanzania&apos;s premier platform for importing quality products directly from China.
              Trusted by shoppers and businesses nationwide.
            </p>
            <div className="mt-6 flex gap-3">
              {["f", "in", "x", "ig"].map((social) => (
                <Link
                  key={social}
                  href="#"
                  className="flex h-9 w-9 items-center justify-center rounded-lg border border-zinc-800 text-xs font-bold uppercase text-zinc-500 transition hover:border-[#c9a227]/50 hover:text-[#c9a227]"
                >
                  {social}
                </Link>
              ))}
            </div>
          </div>

          <div>
            <h3 className="text-sm font-bold uppercase tracking-wider text-[#c9a227]">Shop</h3>
            <ul className="mt-4 space-y-3">
              {footerLinks.shop.map((link) => (
                <li key={link.label}>
                  <Link href={link.href} className="text-sm transition hover:text-white">
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h3 className="text-sm font-bold uppercase tracking-wider text-[#c9a227]">Company</h3>
            <ul className="mt-4 space-y-3">
              {footerLinks.company.map((link) => (
                <li key={link.label}>
                  <Link href={link.href} className="text-sm transition hover:text-white">
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h3 className="text-sm font-bold uppercase tracking-wider text-[#c9a227]">Support</h3>
            <ul className="mt-4 space-y-3">
              {footerLinks.support.map((link) => (
                <li key={link.label}>
                  <Link href={link.href} className="text-sm transition hover:text-white">
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
            <div className="mt-6 space-y-2 text-sm">
              <p>
                <span className="text-zinc-500">Email:</span>{" "}
                <a href="mailto:hello@chinaordertz.com" className="hover:text-[#c9a227]">
                  hello@chinaordertz.com
                </a>
              </p>
              <p>
                <span className="text-zinc-500">Phone:</span>{" "}
                <a href="tel:+255123456789" className="hover:text-[#c9a227]">
                  +255 123 456 789
                </a>
              </p>
              <p>
                <span className="text-zinc-500">Location:</span> Dar es Salaam, Tanzania
              </p>
            </div>
          </div>
        </div>

        <div className="mt-12 flex flex-col items-center justify-between gap-4 border-t border-zinc-800 pt-8 sm:flex-row">
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
