const DEFAULT_API_ORIGIN = "http://127.0.0.1:8000";

export function getApiBaseUrl(): string {
  const configured = process.env.NEXT_PUBLIC_API_BASE_URL?.trim();
  const origin = configured || (process.env.NODE_ENV === "production" ? "" : DEFAULT_API_ORIGIN);
  if (!origin) {
    if (typeof window === "undefined") return "/api/v1";
    throw new Error("API configuration is unavailable.");
  }
  let parsed: URL;
  try { parsed = new URL(origin); } catch { throw new Error("API configuration is unavailable."); }
  if (!(parsed.protocol === "http:" || parsed.protocol === "https:") || parsed.username || parsed.password || parsed.pathname !== "/" || parsed.search || parsed.hash) {
    throw new Error("API configuration is unavailable.");
  }
  return parsed.origin.replace(/\/$/, "") + "/api/v1";
}
