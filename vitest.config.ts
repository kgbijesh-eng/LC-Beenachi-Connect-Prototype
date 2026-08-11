import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    environment: "jsdom",
    setupFiles: ["./app/__tests__/setup.ts"],
    include: ["app/__tests__/**/*.test.tsx"]
  }
});
