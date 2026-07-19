"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { useDevAuth } from "./DevAuthSession";

type DocumentMetadata = {
  id: number;
  club_id: number;
  member_id: number;
  document_type: string;
  status: string;
  external_reference: string | null;
  remarks: string | null;
  created_by_user_id: number | null;
  updated_by_user_id: number | null;
  created_at: string | null;
  updated_at: string | null;
};

type ViewState = "loading" | "ready" | "empty" | "forbidden" | "malformed" | "error";

const OWN_MEMBER_KEYS = ["member"];
const DOCUMENT_ENVELOPE_KEYS = ["profile_documents"];
const DOCUMENT_KEYS = [
  "id", "club_id", "member_id", "document_type", "status", "external_reference", "remarks",
  "created_by_user_id", "updated_by_user_id", "created_at", "updated_at"
].sort();
const LARAVEL_ISO_TIMESTAMP = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/;
const unavailableDate = "Unavailable";

function exactKeys(value: Record<string, unknown>, expected: string[]) {
  return JSON.stringify(Object.keys(value).sort()) === JSON.stringify([...expected].sort());
}

function isNullableString(value: unknown): value is string | null {
  return value === null || typeof value === "string";
}

function isNullablePositiveInteger(value: unknown): value is number | null {
  return value === null || (Number.isInteger(value) && Number(value) > 0);
}

function validTimestamp(value: unknown): value is string | null {
  return value === null || (typeof value === "string" && LARAVEL_ISO_TIMESTAMP.test(value) && !Number.isNaN(Date.parse(value)));
}

function parseOwnMemberEnvelope(value: unknown, clubId: number, linkedMemberId: number) {
  if (!value || typeof value !== "object" || Array.isArray(value) || !exactKeys(value as Record<string, unknown>, OWN_MEMBER_KEYS)) return null;
  const member = (value as { member?: unknown }).member;
  if (!member || typeof member !== "object" || Array.isArray(member)) return null;
  const record = member as Record<string, unknown>;
  if (record.id !== linkedMemberId || record.club_id !== clubId) return null;
  return linkedMemberId;
}

function parseDocumentEnvelope(value: unknown, clubId: number, memberId: number): DocumentMetadata[] | null {
  if (!value || typeof value !== "object" || Array.isArray(value) || !exactKeys(value as Record<string, unknown>, DOCUMENT_ENVELOPE_KEYS)) return null;
  const rows = (value as { profile_documents?: unknown }).profile_documents;
  if (!Array.isArray(rows)) return null;
  const parsed: DocumentMetadata[] = [];
  for (const row of rows) {
    if (!row || typeof row !== "object" || Array.isArray(row) || !exactKeys(row as Record<string, unknown>, DOCUMENT_KEYS)) return null;
    const item = row as Record<string, unknown>;
    if (!Number.isInteger(item.id) || Number(item.id) <= 0 || item.club_id !== clubId || item.member_id !== memberId) return null;
    if (typeof item.document_type !== "string" || typeof item.status !== "string") return null;
    if (!isNullableString(item.external_reference) || !isNullableString(item.remarks)) return null;
    if (!isNullablePositiveInteger(item.created_by_user_id) || !isNullablePositiveInteger(item.updated_by_user_id)) return null;
    if (!validTimestamp(item.created_at) || !validTimestamp(item.updated_at)) return null;
    parsed.push(item as DocumentMetadata);
  }
  return parsed;
}

function formatTimestamp(value: string | null) {
  if (value === null) return unavailableDate;
  return new Intl.DateTimeFormat("en-IN", {
    dateStyle: "medium", timeStyle: "short", timeZone: "UTC"
  }).format(new Date(value));
}

async function responseMessage(response: Response, fallback: string) {
  try {
    const body = await response.json() as { message?: unknown };
    return typeof body.message === "string" ? body.message : fallback;
  } catch {
    return fallback;
  }
}

export default function PersonalProfileDocuments() {
  const { session, authenticatedFetch } = useDevAuth();
  const [state, setState] = useState<ViewState>("loading");
  const [message, setMessage] = useState("Resolving your authenticated member profile...");
  const [documents, setDocuments] = useState<DocumentMetadata[]>([]);
  const generation = useRef(0);
  const activeController = useRef<AbortController | null>(null);

  const load = useCallback(async () => {
    const requestGeneration = ++generation.current;
    activeController.current?.abort();
    const controller = new AbortController();
    activeController.current = controller;
    setDocuments([]);
    setState("loading");
    setMessage("Resolving your authenticated member profile...");

    const clubId = session?.membership.club.id;
    const linkedMemberId = session?.membership.member?.id;
    if (!clubId || !linkedMemberId) {
      setState("forbidden");
      setMessage("A unique authenticated member link is required to view personal profile-document metadata.");
      return;
    }

    const current = () => requestGeneration === generation.current && !controller.signal.aborted;
    try {
      const ownResponse = await authenticatedFetch(`/clubs/${clubId}/members/me`, { signal: controller.signal });
      if (!current()) return;
      if (!ownResponse.ok) {
        setState(ownResponse.status === 403 ? "forbidden" : "error");
        setMessage(await responseMessage(ownResponse, "Your member identity is unavailable."));
        return;
      }
      const ownMemberId = parseOwnMemberEnvelope(await ownResponse.json(), clubId, linkedMemberId);
      if (!current()) return;
      if (!ownMemberId) {
        setState("malformed");
        setMessage("The authoritative own-member response did not match the verified response contract.");
        return;
      }

      setMessage("Loading your personal profile-document metadata...");
      const documentResponse = await authenticatedFetch(`/clubs/${clubId}/members/${ownMemberId}/profile-documents`, { signal: controller.signal });
      if (!current()) return;
      if (!documentResponse.ok) {
        setState(documentResponse.status === 403 ? "forbidden" : "error");
        setMessage(await responseMessage(documentResponse, "Personal profile-document metadata is unavailable."));
        return;
      }
      const parsed = parseDocumentEnvelope(await documentResponse.json(), clubId, ownMemberId);
      if (!current()) return;
      if (parsed === null) {
        setState("malformed");
        setMessage("The profile-document response did not match the verified metadata contract.");
        return;
      }
      setDocuments(parsed);
      setState(parsed.length ? "ready" : "empty");
      setMessage(parsed.length ? "Personal profile-document metadata loaded." : "No personal profile-document metadata records are available.");
    } catch (error) {
      if (!current() || (error instanceof DOMException && error.name === "AbortError")) return;
      setDocuments([]);
      setState("error");
      setMessage("Personal profile-document metadata is unavailable. No stale records are shown.");
    }
  }, [authenticatedFetch, session]);

  useEffect(() => {
    void load();
    return () => {
      generation.current += 1;
      activeController.current?.abort();
    };
  }, [load]);

  return (
    <section className="stack" style={{ minWidth: 0 }}>
      <div className="memberPanel">
        <div className="toolbar" style={{ gap: "12px", flexWrap: "wrap" }}>
          <div><p className="eyebrow">PERSONAL PROFILE METADATA</p><h2>My Profile Documents</h2></div>
          <span className="statusPill">Read-only metadata</span>
        </div>
        <div className="notice memberHelperText">This screen shows metadata recorded against your member profile. File content, upload, download and preview are not available through this screen.</div>
        <div className="notice" role="status"><strong>{state === "forbidden" ? "Unavailable: " : state === "malformed" ? "Invalid response: " : ""}</strong>{message}</div>
        <button type="button" className="secondaryButton" onClick={() => void load()}>Refresh Metadata</button>
      </div>

      {documents.length > 0 && <div className="financeCardList">
        {documents.map(document => <article className="financeStackCard" key={document.id}>
          <h3>{document.document_type || "Type unavailable"}</h3>
          <span>Status: {document.status || "Unavailable"}</span>
          <span>External reference: <span>{document.external_reference ?? "Unavailable"}</span></span>
          <span>Remarks: <span>{document.remarks ?? "Unavailable"}</span></span>
          <span>Metadata created: {formatTimestamp(document.created_at)}</span>
          <span>Metadata updated: {formatTimestamp(document.updated_at)}</span>
        </article>)}
      </div>}
    </section>
  );
}
