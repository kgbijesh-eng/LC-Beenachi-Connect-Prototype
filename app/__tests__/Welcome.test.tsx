import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, expect, it, vi } from "vitest";
import HomePage from "../page";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

it("renders the private pilot preview and links to existing sign-in without fetching club data", () => {
  const fetch = vi.spyOn(globalThis, "fetch");
  render(<HomePage />);

  expect(screen.getByRole("heading", { level: 1, name: /Your club\.\s*In one place\./ })).toBeVisible();
  expect(screen.getByRole("link", { name: /Member & Officer Sign In/ })).toHaveAttribute("href", "/portal");
  expect(screen.getByRole("link", { name: /Pilot information/ })).toHaveAttribute("href", "#pilot");
  expect(screen.getByRole("region", { name: "The work behind a club." })).toHaveAttribute("id", "features");
  expect(screen.getByRole("region", { name: "One workspace, different views." })).toHaveAttribute("id", "access");
  expect(screen.getByText(/Synthetic sample data only/)).toBeVisible();
  expect(screen.queryByRole("textbox")).toBeNull();
  expect(fetch).not.toHaveBeenCalled();
});
