import React from "react";
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const auth = vi.hoisted(() => ({
  session: {
    identity: "linked-member",
    membership: { club: { id: 1 }, member: { id: 35, member_code: "LCB-35", full_name: "Linked Member" } }
  },
  authenticatedFetch: vi.fn()
}));

vi.mock("../DevAuthSession", () => ({ useDevAuth: () => auth }));

import PersonalProfileDocuments from "../PersonalProfileDocuments";

function json(payload: unknown, status = 200) {
  return new Response(JSON.stringify(payload), { status, headers: { "Content-Type": "application/json" } });
}

function ownMember(club = 1, id = 35) {
  return { member: { id, club_id: club, member_code: "LCB-35", full_name: "Linked Member" } };
}

function metadata(overrides: Record<string, unknown> = {}) {
  return {
    id: 501,
    club_id: 1,
    member_id: 35,
    document_type: "membership_form",
    status: "verified",
    external_reference: "REFERENCE-501",
    remarks: "Metadata only.",
    created_by_user_id: 7,
    updated_by_user_id: 8,
    created_at: "2026-07-20T10:20:30.000000Z",
    updated_at: "2026-07-20T11:20:30.000000Z",
    ...overrides
  };
}

function installSuccessfulClient(rows = [metadata()]) {
  auth.authenticatedFetch = vi.fn(async (path: string) => {
    if (path === "/clubs/1/members/me") return json(ownMember());
    if (path === "/clubs/1/members/35/profile-documents") return json({ profile_documents: rows });
    return json({ message: "Unexpected endpoint" }, 500);
  });
}

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>(done => { resolve = done; });
  return { promise, resolve };
}

beforeEach(() => {
  auth.session = {
    identity: "linked-member",
    membership: { club: { id: 1 }, member: { id: 35, member_code: "LCB-35", full_name: "Linked Member" } }
  };
  installSuccessfulClient();
});

afterEach(() => cleanup());

describe("Milestone 3IIP personal profile documents", () => {
  it("uses only the exact own-member and metadata envelopes with truthful wording", async () => {
    render(<PersonalProfileDocuments />);

    expect(await screen.findByRole("heading", { name: "My Profile Documents" })).toBeInTheDocument();
    expect(screen.getByText(/File content, upload, download and preview are not available through this screen/)).toBeInTheDocument();
    expect(screen.queryByText(/not stored anywhere/i)).not.toBeInTheDocument();
    expect(await screen.findByText("membership_form")).toBeInTheDocument();
    expect(auth.authenticatedFetch.mock.calls.map((call) => call[0])).toEqual([
      "/clubs/1/members/me",
      "/clubs/1/members/35/profile-documents"
    ]);
    expect(auth.authenticatedFetch.mock.calls.some((call) => call[0] === "/clubs/1/members")).toBe(false);
  });

  it("distinguishes a verified empty array from malformed guessed envelopes", async () => {
    installSuccessfulClient([]);
    const { rerender } = render(<PersonalProfileDocuments />);
    expect(await screen.findByText("No personal profile-document metadata records are available.")).toBeInTheDocument();

    auth.authenticatedFetch = vi.fn(async (path: string) => path.endsWith("/members/me")
      ? json({ data: ownMember().member })
      : json({ data: [] }));
    auth.session = { ...auth.session, identity: "new-auth-context" };
    rerender(<PersonalProfileDocuments />);
    expect(await screen.findByText(/own-member response did not match the verified response contract/)).toBeInTheDocument();
    expect(screen.queryByText("No personal profile-document metadata records are available.")).not.toBeInTheDocument();
  });

  it("fails the complete response for cross-context rows and invalid timestamps", async () => {
    installSuccessfulClient([metadata(), metadata({ id: 502, member_id: 99 })]);
    const { rerender } = render(<PersonalProfileDocuments />);
    expect(await screen.findByText(/profile-document response did not match/)).toBeInTheDocument();
    expect(screen.queryByText("membership_form")).not.toBeInTheDocument();

    installSuccessfulClient([metadata({ created_at: "20 July 2026" })]);
    auth.session = { ...auth.session, identity: "timestamp-context" };
    rerender(<PersonalProfileDocuments />);
    expect(await screen.findByText(/profile-document response did not match/)).toBeInTheDocument();
    expect(screen.queryByText("membership_form")).not.toBeInTheDocument();
  });

  it("shows deterministic metadata timestamps and truthful null markers", async () => {
    installSuccessfulClient([metadata({ created_at: null, updated_at: null })]);
    render(<PersonalProfileDocuments />);
    expect(await screen.findByText("Metadata created: Unavailable")).toBeInTheDocument();
    expect(screen.getByText("Metadata updated: Unavailable")).toBeInTheDocument();
    expect(screen.queryByText(/issue date|valid|expiry/i)).not.toBeInTheDocument();
  });

  it("renders external references and remarks as escaped non-clickable text without URL inference", async () => {
    installSuccessfulClient([metadata({
      external_reference: "https://example.test/file.pdf",
      remarks: "<a href='https://evil.test'>Open</a>"
    })]);
    render(<PersonalProfileDocuments />);
    expect(await screen.findByText("https://example.test/file.pdf")).toBeInTheDocument();
    expect(screen.getByText("<a href='https://evil.test'>Open</a>")).toBeInTheDocument();
    expect(screen.queryByRole("link")).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /upload|download|preview|share|open file/i })).not.toBeInTheDocument();
  });

  it("ignores a late response after authenticated club and member context changes", async () => {
    const oldOwn = deferred<Response>();
    auth.authenticatedFetch = vi.fn((path: string) => {
      if (path === "/clubs/1/members/me") return oldOwn.promise;
      if (path === "/clubs/2/members/me") return Promise.resolve(json(ownMember(2, 44)));
      if (path === "/clubs/2/members/44/profile-documents") return Promise.resolve(json({ profile_documents: [metadata({ id: 700, club_id: 2, member_id: 44, document_type: "new-context" })] }));
      return Promise.resolve(json({ message: "Unexpected endpoint" }, 500));
    });
    const { rerender } = render(<PersonalProfileDocuments />);
    auth.session = { identity: "new-context", membership: { club: { id: 2 }, member: { id: 44, member_code: "LCB-44", full_name: "New Member" } } };
    rerender(<PersonalProfileDocuments />);
    expect(await screen.findByText("new-context")).toBeInTheDocument();
    oldOwn.resolve(json(ownMember(1, 35)));
    await waitFor(() => expect(screen.getByText("new-context")).toBeInTheDocument());
    expect(screen.queryByText("membership_form")).not.toBeInTheDocument();
  });

  it("prevents an older refresh from overwriting a newer refresh", async () => {
    let ownCalls = 0;
    const oldDocuments = deferred<Response>();
    auth.authenticatedFetch = vi.fn(async (path: string) => {
      if (path.endsWith("/members/me")) { ownCalls += 1; return json(ownMember()); }
      if (path.endsWith("/profile-documents") && ownCalls === 1) return oldDocuments.promise;
      return json({ profile_documents: [metadata({ id: 800, document_type: "newer-refresh" })] });
    });
    render(<PersonalProfileDocuments />);
    await waitFor(() => expect(auth.authenticatedFetch).toHaveBeenCalledWith("/clubs/1/members/35/profile-documents", expect.anything()));
    fireEvent.click(screen.getByRole("button", { name: "Refresh Metadata" }));
    expect(await screen.findByText("newer-refresh")).toBeInTheDocument();
    oldDocuments.resolve(json({ profile_documents: [metadata({ document_type: "obsolete-refresh" })] }));
    await waitFor(() => expect(screen.queryByText("obsolete-refresh")).not.toBeInTheDocument());
    expect(screen.getByText("newer-refresh")).toBeInTheDocument();
  });

  it("aborts and ignores pending work when unmounted", async () => {
    const pending = deferred<Response>();
    auth.authenticatedFetch = vi.fn(() => pending.promise);
    const { unmount } = render(<PersonalProfileDocuments />);
    unmount();
    pending.resolve(json(ownMember()));
    await Promise.resolve();
    expect(screen.queryByText("membership_form")).not.toBeInTheDocument();
  });
});
