"use client";

import { FormEvent, useEffect, useState } from "react";
import { useDevAuth } from "./DevAuthSession";

type Role = "president" | "secretary" | "treasurer" | "member";
type Reminder = {
  id: number; title: string; description?: string | null;
  priority: "normal" | "high" | "urgent"; visibility?: "members" | "management";
  status: "draft" | "active" | "completed" | "cancelled"; is_all_day: boolean;
  due_date?: string | null; due_at_club_timezone?: string | null;
  overdue: boolean; lock_version?: number;
};
type Meta = { current_page: number; last_page: number; total: number };

const API = "http://127.0.0.1:8000/api/v1";
export default function RemindersBackend({ role, onBack }: { role: Role; onBack: () => void }) {
  const { session } = useDevAuth();
  const canManage = role === "president" || role === "secretary";
  const [token, setToken] = useState("");
  const [clubId, setClubId] = useState(0);
  const [rows, setRows] = useState<Reminder[]>([]);
  const [meta, setMeta] = useState<Meta | null>(null);
  const [selected, setSelected] = useState<Reminder | null>(null);
  const [state, setState] = useState<"loading"|"ready"|"empty"|"error"|"forbidden"|"conflict"|"submitting">("loading");
  const [message, setMessage] = useState("Loading reminders from the verified backend...");
  const [filters, setFilters] = useState({ status: "", priority: "", q: "" });
  const [form, setForm] = useState({ title: "", description: "", priority: "normal", visibility: "members", is_all_day: true, due_date: "", due_at: "" });
  const [reason, setReason] = useState("");

  async function apiError(response: Response, fallback: string) {
    try {
      const body = await response.json();
      return Object.values(body.errors ?? {}).flat()[0] as string || body.message || fallback;
    } catch { return fallback; }
  }

  function fail(code: number, text: string) {
    setRows([]); setMeta(null); setSelected(null);
    setState(code === 403 ? "forbidden" : code === 409 ? "conflict" : "error");
    setMessage(text);
  }

  async function load(accessToken: string, activeClub: number, page = 1, chosen = filters) {
    setRows([]); setMeta(null); setState("loading");
    setMessage("Loading reminders from the verified backend...");
    const query = new URLSearchParams({ page: String(page), per_page: "10" });
    if (chosen.status) query.set("status", chosen.status);
    if (chosen.priority) query.set("priority", chosen.priority);
    if (chosen.q.trim()) query.set("q", chosen.q.trim());
    try {
      const response = await fetch(`${API}/clubs/${activeClub}/reminders?${query}`, {
        headers: { Accept: "application/json", Authorization: `Bearer ${accessToken}` }
      });
      if (!response.ok) return fail(response.status, await apiError(response, "Reminders are unavailable."));
      const body = await response.json();
      const loaded = body.data ?? [];
      setRows(loaded); setMeta(body.meta ?? null);
      setState(loaded.length ? "ready" : "empty");
      setMessage(loaded.length ? "Backend reminders loaded." : "No reminders match the current filters.");
    } catch { fail(0, "Reminders are unavailable. Previous rows were cleared."); }
  }

  useEffect(() => {
    let cancelled = false;
    setToken(""); setClubId(0); setRows([]); setMeta(null); setSelected(null); setState("loading");
    (async () => {
      try {
        if (!session) throw new Error("No verified development session is available.");
        const activeClub = session.membership.club.id;
        if (!cancelled) { setToken(session.token); setClubId(activeClub); await load(session.token, activeClub, 1, { status: "", priority: "", q: "" }); }
      } catch (error) { if (!cancelled) fail(0, error instanceof Error ? error.message : "Reminders are unavailable."); }
    })();
    return () => { cancelled = true; };
  }, [role, session]);

  async function open(id: number) {
    setRows([]); setMeta(null); setState("loading");
    try {
      const response = await fetch(`${API}/clubs/${clubId}/reminders/${id}`, { headers: { Accept: "application/json", Authorization: `Bearer ${token}` } });
      if (!response.ok) return fail(response.status, await apiError(response, "Reminder detail is unavailable."));
      const body = await response.json(); setSelected(body.data); setState("ready"); setMessage(`Loaded reminder #${body.data.id}.`);
    } catch { fail(0, "Reminder detail is unavailable."); }
  }

  async function create(event: FormEvent) {
    event.preventDefault(); setState("submitting");
    const body: Record<string, unknown> = { title: form.title, description: form.description || null, priority: form.priority, visibility: form.visibility, is_all_day: form.is_all_day };
    if (form.is_all_day) body.due_date = form.due_date; else body.due_at = new Date(form.due_at).toISOString();
    try {
      const response = await fetch(`${API}/clubs/${clubId}/reminders`, {
        method: "POST", headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify(body)
      });
      if (!response.ok) return fail(response.status, await apiError(response, "Reminder creation failed."));
      setForm({ title: "", description: "", priority: "normal", visibility: "members", is_all_day: true, due_date: "", due_at: "" });
      await load(token, clubId); setMessage("Draft reminder created by the backend.");
    } catch { fail(0, "Reminder creation is unavailable."); }
  }

  async function transition(action: "activate"|"complete"|"cancel") {
    if (!selected?.lock_version) return;
    setState("submitting");
    const body: Record<string, unknown> = { lock_version: selected.lock_version };
    if (action === "cancel") body.cancellation_reason = reason;
    try {
      const response = await fetch(`${API}/clubs/${clubId}/reminders/${selected.id}/${action}`, {
        method: "POST", headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify(body)
      });
      if (!response.ok) return fail(response.status, await apiError(response, "Reminder lifecycle action failed."));
      const result = await response.json(); setSelected(result.data); setState("ready"); setMessage(`Backend confirmed ${action}.`); setReason("");
    } catch { fail(0, "Reminder lifecycle action is unavailable."); }
  }

  if (selected) return (
    <section className="stack">
      <button className="secondaryButton compactBackButton" onClick={() => { setSelected(null); void load(token, clubId); }}>← Reminders</button>
      <div className="compactPanel"><h2>{selected.title}</h2><p>Stable ID #{selected.id}</p><p>{selected.status} · {selected.priority}</p><p>{selected.is_all_day ? selected.due_date : selected.due_at_club_timezone}</p><p>{selected.overdue ? "Overdue" : "Not overdue"}</p></div>
      {canManage && <div className="buttonRow">
        {selected.status === "draft" && <button onClick={() => void transition("activate")}>Activate</button>}
        {selected.status === "active" && <button onClick={() => void transition("complete")}>Complete</button>}
        {(selected.status === "draft" || selected.status === "active") && <><input value={reason} onChange={e => setReason(e.target.value)} placeholder="Cancellation reason" /><button className="secondaryButton" onClick={() => void transition("cancel")}>Cancel</button></>}
      </div>}
      <div className="notice">{message}</div>
    </section>
  );

  return (
    <section className="stack">
      <div className="compactPanel"><h2>Reminders</h2><p>{canManage ? "Verified backend management" : "Verified backend read-only"}</p></div>
      <div className="notice memberHelperText">Notification delivery and notification read status are not connected.</div>
      <div className="notice">{message}</div>
      <div className="compactPanel"><div className="formGrid">
        <label>Status<select value={filters.status} onChange={e => setFilters({ ...filters, status: e.target.value })}><option value="">All</option><option value="draft">Draft</option><option value="active">Active</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></label>
        <label>Priority<select value={filters.priority} onChange={e => setFilters({ ...filters, priority: e.target.value })}><option value="">All</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
        <label>Search<input value={filters.q} onChange={e => setFilters({ ...filters, q: e.target.value })} /></label>
      </div><div className="buttonRow"><button onClick={() => void load(token, clubId)}>Apply</button><button className="secondaryButton" onClick={() => { const clear = { status: "", priority: "", q: "" }; setFilters(clear); void load(token, clubId, 1, clear); }}>Clear</button>{state === "error" && <button onClick={() => void load(token, clubId)}>Retry</button>}</div></div>
      {canManage && <form className="compactPanel" onSubmit={create}><h3>Create Draft Reminder</h3><div className="formGrid">
        <label>Title<input required value={form.title} onChange={e => setForm({ ...form, title: e.target.value })} /></label>
        <label>Description<textarea value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} /></label>
        <label>Priority<select value={form.priority} onChange={e => setForm({ ...form, priority: e.target.value })}><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
        <label>Visibility<select value={form.visibility} onChange={e => setForm({ ...form, visibility: e.target.value })}><option value="members">Members</option><option value="management">Management</option></select></label>
        <label><input type="checkbox" checked={form.is_all_day} onChange={e => setForm({ ...form, is_all_day: e.target.checked })} /> All day</label>
        {form.is_all_day ? <label>Due date<input required type="date" value={form.due_date} onChange={e => setForm({ ...form, due_date: e.target.value })} /></label> : <label>Due time<input required type="datetime-local" value={form.due_at} onChange={e => setForm({ ...form, due_at: e.target.value })} /></label>}
      </div><button disabled={state === "submitting"}>Create backend Draft</button></form>}
      <div className="tableLike">
        {rows.map(row => <article className="staticRow" key={row.id}><strong>{row.title}</strong><span>{row.priority}</span><span>{row.status}</span><span>{row.overdue ? "Overdue" : row.is_all_day ? row.due_date : row.due_at_club_timezone}</span><button className="filterButton" onClick={() => void open(row.id)}>View #{row.id}</button></article>)}
        {state === "empty" && <div className="memberEmptyState">No real reminders match this view.</div>}
      </div>
      {meta && <div className="buttonRow"><button disabled={meta.current_page <= 1} onClick={() => void load(token, clubId, meta.current_page - 1)}>Previous</button><span>Page {meta.current_page} of {meta.last_page} · {meta.total} records</span><button disabled={meta.current_page >= meta.last_page} onClick={() => void load(token, clubId, meta.current_page + 1)}>Next</button></div>}
      <button className="secondaryButton" onClick={onBack}>← Reports & Tools</button>
    </section>
  );
}
