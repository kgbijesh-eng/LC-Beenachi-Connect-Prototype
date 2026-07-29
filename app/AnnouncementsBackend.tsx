"use client";

import { type FormEvent, type ReactNode, useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useDevAuth } from "./DevAuthSession";

type Announcement = {
  id: number;
  title: string;
  body: string;
  priority: "normal" | "high" | "urgent";
  effective_status: "draft" | "published" | "withdrawn" | "archived" | "expired" | "replaced";
  published_at: string | null;
  expires_at: string | null;
  club_timezone: string;
  issued_by: string | null;
  published_by_role: string | null;
  replaced_by_announcement_id?: number;
  replaces_announcement_id?: number | null;
  audience?: "club-members" | "officers";
  stored_status?: "draft" | "published" | "withdrawn" | "archived";
  withdrawn_at?: string | null;
  withdrawal_reason?: string | null;
  archived_at?: string | null;
  lock_version?: number;
  created_at?: string | null;
  updated_at?: string | null;
};

type ListResponse = {
  data: Announcement[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
};

type FieldErrors = Record<string, string[]>;
type FormMode = "create" | "edit" | "replacement";
type ApprovalContext = {
  approvalId: number;
  announcementId: number;
  lockVersion: number;
  sessionKey: string;
};
type FormState = {
  title: string;
  body: string;
  priority: Announcement["priority"];
  audience: "club-members" | "officers";
  expires_at: string;
};

const emptyForm: FormState = {
  title: "",
  body: "",
  priority: "normal",
  audience: "club-members",
  expires_at: ""
};

function hasCompleteMutationProjection(
  resource: Announcement | undefined,
  id: number
): resource is Announcement {
  return Boolean(
    resource &&
    resource.id === id &&
    typeof resource.title === "string" &&
    typeof resource.body === "string" &&
    typeof resource.lock_version === "number" &&
    Object.prototype.hasOwnProperty.call(resource, "expires_at") &&
    Object.prototype.hasOwnProperty.call(resource, "priority") &&
    Object.prototype.hasOwnProperty.call(resource, "audience")
  );
}

function permissionSet(permissions: { slug: string }[] | undefined) {
  return new Set((permissions ?? []).map(permission => permission.slug));
}

function safeDate(value: string | null | undefined, timezone?: string) {
  if (!value) return "Not published";
  try {
    return new Intl.DateTimeFormat("en-IN", {
      dateStyle: "medium",
      timeStyle: "short",
      timeZone: timezone || "UTC"
    }).format(new Date(value));
  } catch {
    return "Date unavailable";
  }
}

async function apiError(response: Response, fallback: string) {
  try {
    const body = await response.json() as { message?: string; errors?: FieldErrors };
    return { message: body.message || fallback, errors: body.errors ?? {} };
  } catch {
    return { message: fallback, errors: {} };
  }
}

function toApiExpiry(value: string) {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toISOString().replace(/\.\d{3}Z$/, "+00:00");
}

export default function AnnouncementsBackend() {
  const { session, authenticatedFetch } = useDevAuth();
  const clubId = session?.membership.club.id;
  const permissions = useMemo(() => permissionSet(session?.membership.permissions), [session?.membership.permissions]);
  const sessionKey = session ? `${session.identity}:${session.membership.id}:${clubId}` : "";
  const management = permissions.has("announcements.management.view");
  const [scope, setScope] = useState<"current" | "history" | "all">("current");
  const [query, setQuery] = useState("");
  const [search, setSearch] = useState("");
  const [priority, setPriority] = useState("");
  const [page, setPage] = useState(1);
  const [items, setItems] = useState<Announcement[]>([]);
  const [meta, setMeta] = useState<ListResponse["meta"] | null>(null);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<Announcement | null>(null);
  const [listState, setListState] = useState<"loading" | "ready" | "forbidden" | "error">("loading");
  const [detailState, setDetailState] = useState<"idle" | "loading" | "ready" | "error">("idle");
  const [message, setMessage] = useState("");
  const [formMode, setFormMode] = useState<FormMode | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [pending, setPending] = useState(false);
  const [approvalContext, setApprovalContext] = useState<ApprovalContext | null>(null);
  const listController = useRef<AbortController | null>(null);
  const detailController = useRef<AbortController | null>(null);

  const loadList = useCallback(async () => {
    if (!session || !clubId) return;
    listController.current?.abort();
    const controller = new AbortController();
    listController.current = controller;
    setListState("loading");
    setMessage("");
    const params = new URLSearchParams({
      scope,
      page: String(page),
      per_page: "10",
      sort: "published_at",
      direction: "desc"
    });
    if (search) params.set("q", search);
    if (priority) params.set("priority", priority);
    try {
      const response = await authenticatedFetch(`/clubs/${clubId}/announcements?${params}`, { signal: controller.signal });
      if (!response.ok) {
        const error = await apiError(response, "Announcements are unavailable.");
        if (!controller.signal.aborted) {
          setItems([]);
          setMeta(null);
          setListState(response.status === 403 ? "forbidden" : "error");
          setMessage(response.status === 403 ? "You are not authorized to view this announcement scope." : error.message);
        }
        return;
      }
      const body = await response.json() as ListResponse;
      if (!controller.signal.aborted) {
        setItems(body.data);
        setMeta(body.meta);
        setListState("ready");
        setSelectedId(current => body.data.some(item => item.id === current) ? current : body.data[0]?.id ?? null);
      }
    } catch (error) {
      if (!controller.signal.aborted) {
        setItems([]);
        setMeta(null);
        setListState("error");
        setMessage(error instanceof Error ? "Announcements could not be loaded." : "Announcements are unavailable.");
      }
    }
  }, [authenticatedFetch, clubId, page, priority, scope, search, session]);

  useEffect(() => {
    let active = true;
    queueMicrotask(() => {
      if (!active) return;
      setItems([]);
      setMeta(null);
      setSelectedId(null);
      setDetail(null);
      setFormMode(null);
      setApprovalContext(null);
      void loadList();
    });
    return () => {
      active = false;
      listController.current?.abort();
    };
  }, [loadList, session?.identity, session?.membership.id, clubId]);

  useEffect(() => {
    detailController.current?.abort();
    if (!selectedId || !clubId || !session) {
      queueMicrotask(() => {
        setDetail(null);
        setDetailState("idle");
        setApprovalContext(null);
      });
      return;
    }
    const controller = new AbortController();
    detailController.current = controller;
    queueMicrotask(() => {
      if (!controller.signal.aborted) {
        setDetail(null);
        setDetailState("loading");
      }
    });
    void authenticatedFetch(`/clubs/${clubId}/announcements/${selectedId}`, { signal: controller.signal })
      .then(async response => {
        if (!response.ok) throw new Error("Announcement detail is unavailable.");
        return response.json() as Promise<{ data: Announcement }>;
      })
      .then(body => {
        if (!controller.signal.aborted) {
          setDetail(body.data);
          setDetailState("ready");
          setApprovalContext(current => current
            && current.sessionKey === sessionKey
            && current.announcementId === body.data.id
            && current.lockVersion === body.data.lock_version
            ? current
            : null);
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) setDetailState("error");
      });
    return () => controller.abort();
  }, [authenticatedFetch, clubId, selectedId, session, sessionKey]);

  function selectAnnouncement(id: number) {
    if (id !== selectedId) setApprovalContext(null);
    setSelectedId(id);
  }

  function openForm(mode: FormMode) {
    setFieldErrors({});
    setMessage("");
    setFormMode(mode);
    if ((mode === "edit" || mode === "replacement") && detail) {
      setForm({
        title: detail.title,
        body: detail.body,
        priority: detail.priority,
        audience: detail.audience ?? "club-members",
        expires_at: detail.expires_at ? detail.expires_at.slice(0, 16) : ""
      });
    } else {
      setForm(emptyForm);
    }
  }

  async function synchronizeMutation(id: number, resource?: Announcement) {
    let authoritative = resource;

    if (!hasCompleteMutationProjection(authoritative, id)) {
      try {
        const response = await authenticatedFetch(`/clubs/${clubId}/announcements/${id}`);

        if (response.ok) {
          authoritative = (await response.json() as { data: Announcement }).data;
        }
      } catch {
        // The list refresh below still reflects the confirmed mutation.
      }
    }

    if (!hasCompleteMutationProjection(authoritative, id)) return;

    setItems(current => current.map(item => item.id === authoritative.id ? authoritative : item));
    setDetail(current => current?.id === authoritative.id ? authoritative : current);
    setForm(current => formMode === "edit" || formMode === "replacement" ? {
      ...current,
      title: authoritative.title,
      body: authoritative.body,
      priority: authoritative.priority,
      audience: authoritative.audience ?? current.audience,
      expires_at: authoritative.expires_at ? authoritative.expires_at.slice(0, 16) : ""
    } : current);
    setApprovalContext(null);
  }

  async function submitForm(event: FormEvent) {
    event.preventDefault();
    if (pending || !clubId || !formMode) return;
    setPending(true);
    setFieldErrors({});
    setMessage("");
    const payload: Record<string, unknown> = {
      title: form.title,
      body: form.body,
      priority: form.priority,
      expires_at: toApiExpiry(form.expires_at)
    };
    let path = `/clubs/${clubId}/announcements`;
    let method = "POST";
    if (formMode === "create") payload.audience = form.audience;
    if (formMode === "edit" && detail) {
      path += `/${detail.id}`;
      method = "PATCH";
      payload.audience = form.audience;
      payload.lock_version = detail.lock_version;
    }
    if (formMode === "replacement" && detail) path += `/${detail.id}/replacements`;
    try {
      const response = await authenticatedFetch(path, {
        method,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });
      if (!response.ok) {
        const error = await apiError(response, "The announcement was not saved.");
        setFieldErrors(error.errors);
        setMessage(response.status === 409 ? "The announcement has changed. Refresh the detail before trying again." : response.status === 403 ? "The backend did not authorize this action." : error.message);
        if (response.status === 409 && selectedId) {
          setSelectedId(null);
          queueMicrotask(() => setSelectedId(detail?.id ?? null));
        }
        return;
      }
      const body = await response.json() as { data: Announcement };
      const mutatedId = body.data.id;
      setFormMode(null);
      await synchronizeMutation(mutatedId, body.data);
      setMessage("");
      setSelectedId(mutatedId);
      await loadList();
      setMessage("Announcement saved.");
    } catch {
      setMessage("The announcement could not be saved. No change was confirmed.");
    } finally {
      setPending(false);
    }
  }

  async function mutate(action: "approve" | "reject" | "revoke" | "publish" | "withdraw" | "archive") {
    if (pending || !clubId || !detail?.lock_version) return;
    let reason: string | null = null;
    if (["reject", "revoke", "withdraw"].includes(action)) {
      reason = window.prompt(`Reason for ${action}:`);
      if (!reason?.trim()) return;
    }
    if (["reject", "revoke", "publish", "withdraw", "archive"].includes(action) && !window.confirm(`Confirm ${action} announcement?`)) return;
    const revocableApproval = approvalContext
      && approvalContext.sessionKey === sessionKey
      && approvalContext.announcementId === detail.id
      && approvalContext.lockVersion === detail.lock_version
      ? approvalContext
      : null;
    if (action === "revoke" && !revocableApproval) return;
    const paths = {
      approve: "approvals",
      reject: "approval-rejections",
      revoke: `approvals/${revocableApproval?.approvalId}/revoke`,
      publish: "publish",
      withdraw: "withdraw",
      archive: "archive"
    };
    setPending(true);
    setMessage("");
    try {
      const response = await authenticatedFetch(`/clubs/${clubId}/announcements/${detail.id}/${paths[action]}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ lock_version: detail.lock_version, ...(reason ? { reason } : {}) })
      });
      if (!response.ok) {
        const error = await apiError(response, "The announcement action failed.");
        if (response.status === 409) {
          setApprovalContext(null);
        }
        setMessage(response.status === 409 ? "The announcement has changed or its lifecycle no longer permits this action. Refresh and try again." : response.status === 403 ? "The backend did not authorize this action." : error.message);
        return;
      }
      const body = await response.json() as { data: Announcement | { id: number; announcement_id: number; announcement_lock_version: number; decision: string } };
      const approval = "decision" in body.data && action === "approve" ? {
        approvalId: body.data.id,
        announcementId: body.data.announcement_id,
        lockVersion: body.data.announcement_lock_version,
        sessionKey
      } : null;
      setApprovalContext(null);
      const mutationResource = "decision" in body.data ? undefined : body.data;
      await synchronizeMutation(detail.id, mutationResource);
      setMessage("");
      await loadList();
      if (action !== "approve") {
        setSelectedId(null);
        queueMicrotask(() => setSelectedId(detail.id));
      }
      if (approval) setApprovalContext(approval);
      setMessage(`Announcement ${action} completed.`);
    } catch {
      setMessage("The announcement action could not be completed. No change was confirmed.");
    } finally {
      setPending(false);
    }
  }

  if (!session || !clubId) return null;

  const storedStatus = detail?.stored_status ?? detail?.effective_status;
  return (
    <section className="announcementWorkspace" aria-label="Announcements workspace">
      <header className="announcementWorkspaceHeader">
        <div><p className="eyebrow">CLUB COMMUNICATIONS</p><h2>Announcements</h2></div>
        {permissions.has("announcements.drafts.manage") && <button onClick={() => openForm("create")} disabled={pending}>Create draft</button>}
      </header>

      <form className="announcementFilters" onSubmit={event => { event.preventDefault(); setPage(1); setSearch(query.trim()); }}>
        <label>View<select value={scope} onChange={event => { setPage(1); setScope(event.target.value as typeof scope); }}>
          <option value="current">Current</option><option value="history">History</option>
          {management && <option value="all">Management / all</option>}
        </select></label>
        <label>Search title<input value={query} onChange={event => setQuery(event.target.value)} /></label>
        <label>Priority<select value={priority} onChange={event => { setPage(1); setPriority(event.target.value); }}>
          <option value="">All priorities</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option>
        </select></label>
        <button type="submit" className="secondaryButton">Search</button>
      </form>

      {message && <div className="notice" role="status">{message}</div>}
      {listState === "loading" && <div className="notice" role="status">Loading announcements…</div>}
      {listState === "forbidden" && <div className="notice">{message}</div>}
      {listState === "error" && <div className="notice">Announcements are unavailable. Please try again.</div>}
      {listState === "ready" && items.length === 0 && <div className="memberEmptyState">No announcements match this view.</div>}

      <div className="announcementSplit">
        <div className="announcementBackendList" aria-label="Announcement list">
          {items.map(item => <button className={`announcementBackendCard ${selectedId === item.id ? "selected" : ""}`} key={item.id} onClick={() => selectAnnouncement(item.id)}>
            <span><strong>{item.title}</strong><small>{item.issued_by || "Club announcement"} · {safeDate(item.published_at, item.club_timezone)}</small></span>
            <span className={`tag announcementStatus-${item.effective_status}`}>{item.effective_status}</span>
            <span className="tag">{item.priority}</span>
            {item.replaced_by_announcement_id && <small>Replaced by #{item.replaced_by_announcement_id}</small>}
          </button>)}
          {meta && meta.last_page > 1 && <div className="announcementPagination">
            <button className="secondaryButton" disabled={page <= 1 || listState === "loading"} onClick={() => setPage(value => value - 1)}>Previous</button>
            <span>Page {meta.current_page} of {meta.last_page}</span>
            <button className="secondaryButton" disabled={page >= meta.last_page || listState === "loading"} onClick={() => setPage(value => value + 1)}>Next</button>
          </div>}
        </div>

        <article className="announcementDetailPanel" aria-label="Announcement detail">
          {detailState === "idle" && <div className="notice">Select an announcement.</div>}
          {detailState === "loading" && <div className="notice">Loading announcement detail…</div>}
          {detailState === "error" && <div className="notice">Announcement detail is unavailable.</div>}
          {detailState === "ready" && detail && <>
            <div className="toolbar"><h3>{detail.title}</h3><span className={`statusPill announcementStatus-${detail.effective_status}`}>{detail.effective_status}</span></div>
            <p className="announcementBody">{detail.body}</p>
            <dl className="announcementMetadata">
              <div><dt>Priority</dt><dd>{detail.priority}</dd></div>
              <div><dt>Published</dt><dd>{safeDate(detail.published_at, detail.club_timezone)}</dd></div>
              <div><dt>Issuer</dt><dd>{detail.issued_by || "Unavailable"}</dd></div>
              {detail.audience && <div><dt>Audience</dt><dd>{detail.audience}</dd></div>}
              {detail.expires_at && <div><dt>Expires</dt><dd>{safeDate(detail.expires_at, detail.club_timezone)}</dd></div>}
              {detail.withdrawal_reason && <div><dt>Withdrawal reason</dt><dd>{detail.withdrawal_reason}</dd></div>}
            </dl>
            <div className="announcementActions">
              {storedStatus === "draft" && permissions.has("announcements.drafts.manage") && <button onClick={() => openForm("edit")} disabled={pending}>Edit draft</button>}
              {["published", "withdrawn"].includes(storedStatus || "") && permissions.has("announcements.replace") && <button onClick={() => openForm("replacement")} disabled={pending}>Create replacement</button>}
              {storedStatus === "draft" && permissions.has("announcements.approve") && <><button onClick={() => void mutate("approve")} disabled={pending}>Approve</button><button onClick={() => void mutate("reject")} disabled={pending}>Reject approval</button></>}
              {storedStatus === "draft" && permissions.has("announcements.approve")
                && approvalContext?.sessionKey === sessionKey
                && approvalContext.announcementId === detail.id
                && approvalContext.lockVersion === detail.lock_version
                && <button onClick={() => void mutate("revoke")} disabled={pending}>Revoke approval</button>}
              {storedStatus === "draft" && permissions.has("announcements.publish") && <button onClick={() => void mutate("publish")} disabled={pending}>Publish</button>}
              {storedStatus === "published" && permissions.has("announcements.withdraw") && <button onClick={() => void mutate("withdraw")} disabled={pending}>Withdraw</button>}
              {["draft", "published", "withdrawn"].includes(storedStatus || "") && permissions.has("announcements.archive") && <button onClick={() => void mutate("archive")} disabled={pending}>Archive</button>}
            </div>
          </>}
        </article>
      </div>

      {formMode && <div className="announcementFormPanel">
        <form onSubmit={submitForm}>
          <div className="toolbar"><h3>{formMode === "create" ? "Create announcement draft" : formMode === "edit" ? "Edit announcement draft" : "Create replacement draft"}</h3><button type="button" className="secondaryButton" onClick={() => setFormMode(null)} disabled={pending}>Close</button></div>
          <AnnouncementField label="Title" error={fieldErrors.title?.[0]}><input aria-label="Announcement title" value={form.title} onChange={event => setForm(value => ({ ...value, title: event.target.value }))} disabled={pending} /></AnnouncementField>
          <AnnouncementField label="Body" error={fieldErrors.body?.[0]}><textarea aria-label="Announcement body" value={form.body} onChange={event => setForm(value => ({ ...value, body: event.target.value }))} disabled={pending} /></AnnouncementField>
          <div className="announcementFormGrid">
            <AnnouncementField label="Priority" error={fieldErrors.priority?.[0]}><select aria-label="Announcement priority" value={form.priority} onChange={event => setForm(value => ({ ...value, priority: event.target.value as Announcement["priority"] }))} disabled={pending}><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></AnnouncementField>
            {formMode !== "replacement" && <AnnouncementField label="Audience" error={fieldErrors.audience?.[0]}><select aria-label="Announcement audience" value={form.audience} onChange={event => setForm(value => ({ ...value, audience: event.target.value as FormState["audience"] }))} disabled={pending}><option value="club-members">Club members</option><option value="officers">Officers</option></select></AnnouncementField>}
            <AnnouncementField label="Expiry (optional)" error={fieldErrors.expires_at?.[0]}><input aria-label="Announcement expiry" type="datetime-local" value={form.expires_at} onChange={event => setForm(value => ({ ...value, expires_at: event.target.value }))} disabled={pending} /></AnnouncementField>
          </div>
          <button type="submit" disabled={pending}>{pending ? "Saving…" : "Save draft"}</button>
        </form>
      </div>}
    </section>
  );
}

function AnnouncementField({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return <label>{label}{children}{error && <span className="fieldError">{error}</span>}</label>;
}

export function AnnouncementDashboardStatus() {
  const { session, authenticatedFetch } = useDevAuth();
  const clubId = session?.membership.club.id;
  const management = permissionSet(session?.membership.permissions).has("announcements.management.view");
  const [state, setState] = useState<"loading" | "ready" | "error">("loading");
  const [total, setTotal] = useState(0);
  useEffect(() => {
    if (!session || !clubId) return;
    const controller = new AbortController();
    queueMicrotask(() => {
      if (!controller.signal.aborted) {
        setState("loading");
        setTotal(0);
      }
    });
    const params = new URLSearchParams({
      scope: "current",
      page: "1",
      per_page: "1",
      sort: "published_at",
      direction: "desc"
    });
    if (management) {
      params.set("status", "published");
      params.set("audience", "club-members");
      params.set("effective_state", "current");
    }
    void Promise.resolve()
      .then(() => authenticatedFetch(`/clubs/${clubId}/announcements?${params}`, { signal: controller.signal }))
      .then(async response => {
        if (!response) throw new Error();
        if (!response.ok) throw new Error();
        return response.json() as Promise<ListResponse>;
      })
      .then(body => {
        if (!controller.signal.aborted) {
          setTotal(body.meta.total);
          setState("ready");
        }
      })
      .catch(() => { if (!controller.signal.aborted) setState("error"); });
    return () => controller.abort();
  }, [authenticatedFetch, clubId, management, session]);
  if (state === "loading") return <>Loading published announcements…</>;
  if (state === "error") return <>Published announcements are unavailable.</>;
  return <>{total} current published member announcement{total === 1 ? "" : "s"}.</>;
}
