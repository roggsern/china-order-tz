/**
 * Shared product / variant media upload contract (admin catalog UI).
 *
 * Formats: JPG/JPEG, PNG, WebP — max 10 MB.
 * HEIC/HEIF is rejected with explicit guidance (no client conversion).
 */

export const ADMIN_PRODUCT_MEDIA_ACCEPT =
  "image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp";

/** 10 MB — must stay aligned with Laravel ProductMediaUploadContract::MAX_KILOBYTES. */
export const ADMIN_PRODUCT_MEDIA_MAX_BYTES = 10 * 1024 * 1024;

export const ADMIN_PRODUCT_MEDIA_UPLOAD_HINT =
  "JPG, PNG, WEBP — max 10MB each. HEIC/HEIF is not supported.";

export const ADMIN_PRODUCT_MEDIA_HEIC_MESSAGE =
  "HEIC/HEIF images are not supported yet. Please export or save the image as JPG, PNG, or WebP.";

export const ADMIN_PRODUCT_MEDIA_SIZE_MESSAGE =
  "Image exceeds the 10 MB upload limit.";

const ALLOWED_TYPES = new Set(["image/jpeg", "image/png", "image/webp"]);
const ALLOWED_EXTENSIONS = [".jpg", ".jpeg", ".png", ".webp"];

export function isHeicOrHeifFile(file: File): boolean {
  const type = (file.type || "").toLowerCase();
  if (type === "image/heic" || type === "image/heif") {
    return true;
  }

  const name = file.name.toLowerCase();
  return name.endsWith(".heic") || name.endsWith(".heif");
}

export function isAcceptedProductMediaFile(file: File): boolean {
  if (isHeicOrHeifFile(file)) {
    return false;
  }

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
  if (files.length === 0) {
    return { accepted: [], rejectedCount: 0, error: null };
  }

  const heicFiles = files.filter(isHeicOrHeifFile);
  const typeAccepted = files.filter(isAcceptedProductMediaFile);
  const tooLarge = typeAccepted.filter((file) => file.size > ADMIN_PRODUCT_MEDIA_MAX_BYTES);
  const valid = typeAccepted.filter((file) => file.size <= ADMIN_PRODUCT_MEDIA_MAX_BYTES);
  const rejectedCount = files.length - valid.length;

  if (heicFiles.length > 0 && valid.length === 0) {
    return {
      accepted: [],
      rejectedCount,
      error: ADMIN_PRODUCT_MEDIA_HEIC_MESSAGE,
    };
  }

  if (tooLarge.length > 0 && valid.length === 0 && heicFiles.length === 0) {
    return {
      accepted: [],
      rejectedCount,
      error: ADMIN_PRODUCT_MEDIA_SIZE_MESSAGE,
    };
  }

  if (valid.length === 0) {
    return {
      accepted: [],
      rejectedCount,
      error: "Use JPG, PNG, or WebP images up to 10 MB. HEIC/HEIF is not supported.",
    };
  }

  if (heicFiles.length > 0) {
    return {
      accepted: valid,
      rejectedCount,
      error: ADMIN_PRODUCT_MEDIA_HEIC_MESSAGE,
    };
  }

  if (tooLarge.length > 0 || rejectedCount > tooLarge.length) {
    return {
      accepted: valid,
      rejectedCount,
      error:
        tooLarge.length > 0 && rejectedCount === tooLarge.length
          ? ADMIN_PRODUCT_MEDIA_SIZE_MESSAGE
          : "Some files were skipped. Use JPG, PNG, or WebP up to 10 MB.",
    };
  }

  return { accepted: valid, rejectedCount: 0, error: null };
}

export function productMediaDropActiveClass(isDragging: boolean): string {
  return isDragging
    ? "border-[#c9a227] bg-amber-50/60"
    : "border-zinc-300 bg-zinc-50/80 hover:border-zinc-400";
}

/**
 * Map HTTP/status + Laravel validation payload to an actionable admin message.
 * Never include stack traces, paths, or credentials.
 */
export function resolveProductMediaUploadError(
  status: number,
  payload: {
    message?: string | null;
    errors?: Record<string, string[]> | null;
  } = {},
  fallback = "Unable to upload image.",
): string {
  const firstError = payload.errors
    ? Object.values(payload.errors).flat().find((msg) => typeof msg === "string" && msg.trim())
    : undefined;
  if (firstError?.trim()) {
    return firstError.trim();
  }

  const message = payload.message?.trim();
  if (message) {
    return message;
  }

  if (status === 413) {
    return "Image exceeds the server upload size limit. Use a JPG, PNG, or WebP under 10 MB.";
  }

  if (status === 415) {
    return "Unsupported image type. Use JPG, PNG, or WebP. HEIC/HEIF is not supported.";
  }

  if (status === 422) {
    return "The image could not be validated. Use JPG, PNG, or WebP under 10 MB and at most 5000×5000 pixels.";
  }

  if (status === 0) {
    return "Network error while uploading the image. Check your connection and try again.";
  }

  return fallback;
}
