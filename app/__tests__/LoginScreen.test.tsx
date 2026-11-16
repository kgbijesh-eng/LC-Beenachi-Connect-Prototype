import { renderToStaticMarkup } from "react-dom/server";
import { expect, it, vi } from "vitest";
import LoginScreen from "../LoginScreen";

it("keeps native sign-in submission disabled before hydration and uses a POST fallback", () => {
  const html = renderToStaticMarkup(<LoginScreen login={vi.fn()} error={null} submitting={false} />);
  const root = document.createElement("div");
  root.innerHTML = html;
  expect(root.querySelector("form")?.method).toBe("post");
  expect(root.querySelector("button[type=submit]")).toBeDisabled();
});
