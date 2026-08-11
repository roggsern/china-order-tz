/**
 * Decode common HTML entities for plain-text UI.
 * Does not execute HTML and strips tags if present.
 */
export function normalizeCustomerPlainText(value: string | null | undefined): string {
  if (typeof value !== 'string' || value.trim() === '') return '';

  let text = value;
  // Decode a few common entities (including double-encoded &amp;amp;)
  for (let i = 0; i < 3; i += 1) {
    const next = text
      .replace(/&amp;/gi, '&')
      .replace(/&lt;/gi, '<')
      .replace(/&gt;/gi, '>')
      .replace(/&quot;/gi, '"')
      .replace(/&#39;/gi, "'")
      .replace(/&apos;/gi, "'")
      .replace(/&nbsp;/gi, ' ');
    if (next === text) break;
    text = next;
  }

  // Strip tags — display as plain text only.
  text = text.replace(/<[^>]*>/g, ' ');
  return text.replace(/\s+/g, ' ').trim();
}
