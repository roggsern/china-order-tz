/**
 * Shared product / variant media upload contract (admin catalog UI).
 *
 * Formats: JPG/JPEG, PNG, WebP — max 10 MB.
 * HEIC/HEIF is rejected with explicit guidance (no client conversion).
 * Laravel content sniffing remains the final upload authority.
 */

export const ADMIN_PRODUCT_MEDIA_ACCEPT =
  "image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp";

/** 10 MB — must stay aligned with Laravel ProductMediaUploadContract::MAX_KILOBYTES. */
export const ADMIN_PRODUCT_MEDIA_MAX_BYTES = 10 * 1024 * 1024;

export const ADMIN_PRODUCT_MEDIA_UPLOAD_HINT =
  "JPG, PNG, WEBP — max 10MB each. HEIC/HEIF is not supported.";

export const ADMIN_PRODUCT_MEDIA_HEIC_MESSAGE =
  "HEIC/HEIF images are not supported yet. Export or save the image as JPG, PNG, or WebP.";

export const ADMIN_PRODUCT_MEDIA_SIZE_MESSAGE =
  "Image exceeds the 10 MB upload limit.";

export const ADMIN_PRODUCT_MEDIA_FORMAT_MESSAGE =
  "Unsupported image format. Use JPG, JPEG, PNG, or WebP.";

export const ADMIN_PRODUCT_MEDIA_SKIPPED_FORMAT_MESSAGE =
  "Some files were skipped. Unsupported image format.";

const ALLOWED_EXTENSIONS = [".jpg", ".jpeg", ".png", ".webp"] as const;

const ALLOWED_MIME_ALIASES = new Set([
  "image/png",
  "image/x-png",
  "image/jpeg",
  "image/jpg",
  "image/pjpeg",
  "image/webp",
]);

const HEIC_MIME_ALIASES = new Set(["image/heic", "image/heif"]);

const HEIC_FTYP_BRANDS = new Set([
  "heic",
  "heix",
  "hevc",
  "hevx",
  "heim",
  "heis",
  "hevm",
  "hevs",
  "mif1",
  "msf1",
  "heif",
]);

export type ProductMediaSniffKind = "png" | "jpeg" | "webp" | "heic";

export type ProductMediaUploadValidation = {
  accepted: File[];
  rejectedCount: number;
  error: string | null;
};

export function normalizeProductMediaMime(type: string | null | undefined): string {
  return (type ?? "").trim().toLowerCase();
}

export function hasAllowedProductMediaExtension(fileName: string): boolean {
  const name = fileName.toLowerCase();
  return ALLOWED_EXTENSIONS.some((ext) => name.endsWith(ext));
}

export function isAmbiguousProductMediaMime(type: string | null | undefined): boolean {
  const mime = normalizeProductMediaMime(type);
  return mime === "" || mime === "application/octet-stream";
}

export function isAllowedProductMediaMime(type: string | null | undefined): boolean {
  return ALLOWED_MIME_ALIASES.has(normalizeProductMediaMime(type));
}

export function isHeicOrHeifFile(file: File): boolean {
  const mime = normalizeProductMediaMime(file.type);
  if (HEIC_MIME_ALIASES.has(mime)) {
    return true;
  }

  const name = file.name.toLowerCase();
  return name.endsWith(".heic") || name.endsWith(".heif");
}

/**
 * Read only the first bytes needed to identify PNG / JPEG / WebP / HEIC containers.
 * Does not decode or transform the full image.
 */
export async function sniffProductMediaKind(
  file: File,
): Promise<ProductMediaSniffKind | null> {
  const headerSize = Math.min(16, file.size);
  if (headerSize < 3) {
    return null;
  }

  const buffer = await file.slice(0, headerSize).arrayBuffer();
  const bytes = new Uint8Array(buffer);

  if (bytes.length >= 3 && bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) {
    return "jpeg";
  }

  if (
    bytes.length >= 8 &&
    bytes[0] === 0x89 &&
    bytes[1] === 0x50 &&
    bytes[2] === 0x4e &&
    bytes[3] === 0x47 &&
    bytes[4] === 0x0d &&
    bytes[5] === 0x0a &&
    bytes[6] === 0x1a &&
    bytes[7] === 0x0a
  ) {
    return "png";
  }

  if (
    bytes.length >= 12 &&
    bytes[0] === 0x52 &&
    bytes[1] === 0x49 &&
    bytes[2] === 0x46 &&
    bytes[3] === 0x46 &&
    bytes[8] === 0x57 &&
    bytes[9] === 0x45 &&
    bytes[10] === 0x42 &&
    bytes[11] === 0x50
  ) {
    return "webp";
  }

  if (looksLikeHeicContainer(bytes)) {
    return "heic";
  }

  return null;
}

function looksLikeHeicContainer(bytes: Uint8Array): boolean {
  if (bytes.length < 12) {
    return false;
  }

  // ISO BMFF: [size:4][ftyp:4][brand:4]
  if (bytes[4] !== 0x66 || bytes[5] !== 0x74 || bytes[6] !== 0x79 || bytes[7] !== 0x70) {
    return false;
  }

  const brand = String.fromCharCode(bytes[8], bytes[9], bytes[10], bytes[11]).toLowerCase();
  return HEIC_FTYP_BRANDS.has(brand);
}

/**
 * Sync acceptance: MIME aliases + extensions only (no magic-byte sniff).
 * Prefer {@link isAcceptedProductMediaFileAsync} for upload gates.
 */
export function isAcceptedProductMediaFile(file: File): boolean {
  if (isHeicOrHeifFile(file)) {
    return false;
  }

  if (isAllowedProductMediaMime(file.type)) {
    return true;
  }

  return hasAllowedProductMediaExtension(file.name);
}

export async function isAcceptedProductMediaFileAsync(file: File): Promise<boolean> {
  if (isHeicOrHeifFile(file)) {
    return false;
  }

  if (isAllowedProductMediaMime(file.type)) {
    return true;
  }

  if (hasAllowedProductMediaExtension(file.name)) {
    return true;
  }

  if (!isAmbiguousProductMediaMime(file.type)) {
    return false;
  }

  const kind = await sniffProductMediaKind(file);
  return kind === "png" || kind === "jpeg" || kind === "webp";
}

export function filterAcceptedProductMediaFiles(files: FileList | File[] | null): File[] {
  if (!files) {
    return [];
  }

  return Array.from(files).filter(isAcceptedProductMediaFile);
}

export async function filterAcceptedProductMediaFilesAsync(
  files: FileList | File[] | null,
): Promise<File[]> {
  if (!files) {
    return [];
  }

  const incoming = Array.from(files);
  const accepted: File[] = [];
  for (const file of incoming) {
    if (await isAcceptedProductMediaFileAsync(file)) {
      accepted.push(file);
    }
  }
  return accepted;
}

export async function validateProductMediaUpload(
  files: File[],
): Promise<ProductMediaUploadValidation> {
  if (files.length === 0) {
    return { accepted: [], rejectedCount: 0, error: null };
  }

  const heicByNameOrMime = files.filter(isHeicOrHeifFile);
  const candidates = files.filter((file) => !isHeicOrHeifFile(file));

  const heicBySignature: File[] = [];
  const typeAccepted: File[] = [];

  for (const file of candidates) {
    if (isAllowedProductMediaMime(file.type) || hasAllowedProductMediaExtension(file.name)) {
      typeAccepted.push(file);
      continue;
    }

    if (isAmbiguousProductMediaMime(file.type)) {
      const kind = await sniffProductMediaKind(file);
      if (kind === "heic") {
        heicBySignature.push(file);
        continue;
      }
      if (kind === "png" || kind === "jpeg" || kind === "webp") {
        typeAccepted.push(file);
        continue;
      }
    }
  }

  const heicFiles = [...heicByNameOrMime, ...heicBySignature];
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

  if (tooLarge.length > 0 && valid.length === 0) {
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
      error: ADMIN_PRODUCT_MEDIA_FORMAT_MESSAGE,
    };
  }

  if (heicFiles.length > 0) {
    return {
      accepted: valid,
      rejectedCount,
      error: ADMIN_PRODUCT_MEDIA_HEIC_MESSAGE,
    };
  }

  if (tooLarge.length > 0 && rejectedCount === tooLarge.length) {
    return {
      accepted: valid,
      rejectedCount,
      error: ADMIN_PRODUCT_MEDIA_SIZE_MESSAGE,
    };
  }

  if (tooLarge.length > 0 || rejectedCount > tooLarge.length) {
    return {
      accepted: valid,
      rejectedCount,
      error:
        tooLarge.length > 0
          ? ADMIN_PRODUCT_MEDIA_SIZE_MESSAGE
          : ADMIN_PRODUCT_MEDIA_SKIPPED_FORMAT_MESSAGE,
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
    return ADMIN_PRODUCT_MEDIA_FORMAT_MESSAGE;
  }

  if (status === 422) {
    return "The image could not be validated. Use JPG, JPEG, PNG, or WebP under 10 MB and at most 5000×5000 pixels.";
  }

  if (status === 0) {
    return "Network error while uploading the image. Check your connection and try again.";
  }

  return fallback;
}
