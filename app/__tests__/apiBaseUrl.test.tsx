import { afterEach, describe, expect, it, vi } from "vitest";
import { getApiBaseUrl } from "../apiBaseUrl";

afterEach(() => { vi.unstubAllEnvs(); vi.restoreAllMocks(); });

describe("getApiBaseUrl", () => {
  it("normalizes configured origins", () => { process.env.NEXT_PUBLIC_API_BASE_URL = "  http://127.0.0.1:8017/  "; expect(getApiBaseUrl()).toBe("http://127.0.0.1:8017/api/v1"); });
  it("uses the development fallback", () => { vi.stubEnv("NEXT_PUBLIC_API_BASE_URL", ""); vi.stubEnv("NODE_ENV", "test"); expect(getApiBaseUrl()).toBe("http://127.0.0.1:8000/api/v1"); });
  it("rejects unsafe or credential-bearing origins", () => { for (const value of ["file:///tmp/api", "javascript:alert(1)", "data:text/plain,x", "https://user:pass@example.test"]) { process.env.NEXT_PUBLIC_API_BASE_URL = value; expect(() => getApiBaseUrl()).toThrow("API configuration is unavailable."); } });
  it("requires an explicit production origin in the browser", () => { vi.stubEnv("NEXT_PUBLIC_API_BASE_URL", ""); vi.stubEnv("NODE_ENV", "production"); expect(() => getApiBaseUrl()).toThrow("API configuration is unavailable."); });
});
