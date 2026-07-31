/**
 * Pure helpers for product media upload UX (drag/drop + file validation).
 * Keeps ProductMediaManager logic testable without DOM coupling.
 */

export const ADMIN_PRODUCT_MEDIA_ACCEPT =
  "image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp";

export const ADMIN_PRODUCT_MEDIA_MAX_BYTES = 5 * 1024 * 1024;

const ALLOWED_TYPES = new Set(["image/jpeg", "image/png", "image/webp"]);
const ALLOWED_EXTENSIONS = [".jpg", ".jpeg", ".png", ".webp"];

export function isAcceptedProductMediaFile(file: File): boolean {
  if (ALLOWED_TYPES.has(file.type)) {
    return true;
  }

  const name = file.name.toLowerCase();
  return ALLOWED_EXTENSIONS.some((ext) => name.endsWith(ext));
}

export function filterAcceptedProductMediaFiles(files: FileList | File[] | null): File[] {
  if (!files) {
    return [];
  }

  return Array.from(files).filter(isAcceptedProductMediaFile);
}

export function validateProductMediaUpload(files: File[]): {
  accepted: File[];
  rejectedCount: number;
  error: string | null;
} {
  const accepted = files.filter(isAcceptedProductMediaFile);
  const tooLarge = accepted.filter((file) => file.size > ADMIN_PRODUCT_MEDIA_MAX_BYTES);
  const valid = accepted.filter((file) => file.size <= ADMIN_PRODUCT_MEDIA_MAX_BYTES);
  const rejectedCount = files.length - valid.length;

  if (files.length === 0) {
    return { accepted: [], rejectedCount: 0, error: null };
  }

  if (valid.length === 0) {
    return {
      accepted: [],
      rejectedCount,
      error: "Use JPG, PNG, or WEBP images up to 5MB.",
    };
  }

  if (tooLarge.length > 0 || rejectedCount > tooLarge.length) {
    return {
      accepted: valid,
      rejectedCount,
      error: "Some files were skipped. Use JPG, PNG, or WEBP up to 5MB.",
    };
  }

  return { accepted: valid, rejectedCount: 0, error: null };
}

export function productMediaDropActiveClass(isDragging: boolean): string {
  return isDragging
    ? "border-[#c9a227] bg-amber-50/60"
    : "border-zinc-300 bg-zinc-50/80 hover:border-zinc-400";
}
