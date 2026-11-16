import { afterEach, describe, expect, it, vi } from "vitest";
import { getApiBaseUrl } from "../apiBaseUrl";

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
});

describe("getApiBaseUrl", () => {
  it.each([
    "https://www.lionsclubbeenachi.com",
    "https://web-pilot-f497a737.up.railway.app"
  ])("uses the current hostname when the gateway opts into same origin (%s)", (origin) => {
    vi.stubEnv("NEXT_PUBLIC_API_BASE_URL", "same-origin");
    vi.stubGlobal("window", { location: { origin } });
    expect(getApiBaseUrl()).toBe(origin + "/api/v1");
  });

  it("uses an API path when rendering on the server in same-origin mode", () => {
    vi.stubEnv("NEXT_PUBLIC_API_BASE_URL", "same-origin");
    vi.stubGlobal("window", undefined);
    expect(getApiBaseUrl()).toBe("/api/v1");
  });

  it("continues to honor a configured absolute API origin", () => {
    vi.stubEnv("NEXT_PUBLIC_API_BASE_URL", "https://api.example.test");
    expect(getApiBaseUrl()).toBe("https://api.example.test/api/v1");
  });

  it("continues to reject an unsafe configured API protocol", () => {
    vi.stubEnv("NEXT_PUBLIC_API_BASE_URL", "ftp://api.example.test");
    expect(() => getApiBaseUrl()).toThrow("API configuration is unavailable.");
  });
});
