"use client";
import React, { useEffect, useState } from "react";
import { useDevAuth } from "./DevAuthSession";

type Role = "president" | "secretary" | "treasurer" | "member";

type Row = {
  id: number;
  status: string;
  lock_version?: number;
  rejection_reason?: string | null;
  member: {
    id: number;
    member_code: string;
    display_name: string;
  };
  submitted_at: string;
  changed_fields: string[];
  changes?: {
    field_key: string;
    original_value: any;
    proposed_value: any;
    classification: string;
  }[];
};

const API = "http://127.0.0.1:8000/api/v1";
const RESTRICTED = [
  "birth_date",
  "spouse_name",
  "wedding_anniversary",
  "phone",
  "home_phone",
  "alternate_phone",
  "address_line_1",
  "address_line_2",
  "city_locality",
  "district",
  "state",
  "postal_code",
  "country",
  "blood_group",
  "blood_donor_consent",
];

export default function ProfileUpdatesBackend({
  role,
  mode,
}: {
  role: Role;
  mode: "own" | "review";
}) {
  const canReviewRequests = role === "president" || role === "secretary";
  const canManageRequests = canReviewRequests;
  const { session } = useDevAuth();
  const [token, setToken] = useState("");
  const [club, setClub] = useState(0);
  const [rows, setRows] = useState<Row[]>([]);
  const [meta, setMeta] = useState<{
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  } | null>(null);
  const [selected, setSelected] = useState<Row | null>(null);
  const [state, setState] = useState("loading");
  const [msg, setMsg] = useState("Loading profile update requests...");

  // Form states
  const [phone, setPhone] = useState("");
  const [occupation, setOccupation] = useState("");
  const [currentMember, setCurrentMember] = useState<any | null>(null);

  // Review action state
  const [reason, setReason] = useState("");
  const [showSensitive, setShowSensitive] = useState(false);

  // Filter states
  const [filterStatus, setFilterStatus] = useState("");
  const [filterMemberSearch, setFilterMemberSearch] = useState("");
  const [filterChangedField, setFilterChangedField] = useState("");
  const [filterDateFrom, setFilterDateFrom] = useState("");
  const [filterDateTo, setFilterDateTo] = useState("");

  // Pagination state
  const [page, setPage] = useState(1);
  const perPage = 25;

  function clearListAndSelection() {
    setRows([]);
    setMeta(null);
    setSelected(null);
  }

  async function text(r: Response) {
    try {
      const b = await r.json();
      return Object.values(b.errors ?? {}).flat()[0] as string || b.message || "Request failed";
    } catch {
      return "Request failed";
    }
  }

  async function load(
    t = token,
    c = club,
    p = page,
    statusVal = filterStatus,
    searchVal = filterMemberSearch,
    fieldVal = filterChangedField,
    dateFromVal = filterDateFrom,
    dateToVal = filterDateTo
  ) {
    setRows([]);
    setMeta(null);
    setState("loading");
    try {
      const query = new URLSearchParams();
      if (statusVal) query.append("status", statusVal);
      if (mode === "review") {
        if (searchVal) query.append("member_search", searchVal);
        if (fieldVal) query.append("changed_field", fieldVal);
      }
      if (dateFromVal) query.append("date_from", dateFromVal);
      if (dateToVal) query.append("date_to", dateToVal);
      query.append("page", String(p));
      query.append("per_page", String(perPage));

      const path = mode === "own" ? "/me" : "";
      const r = await fetch(`${API}/clubs/${c}/profile-update-requests${path}?${query.toString()}`, {
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${t}`,
        },
      });

      if (!r.ok) {
        if (r.status === 403) {
          setState("forbidden");
        } else if (r.status === 422) {
          setState("validation");
        } else if (r.status === 409) {
          setState("conflict");
        } else {
          setState("error");
        }
        setMsg(await text(r));
        return;
      }

      const b = await r.json();
      setRows(b.data ?? []);
      setMeta(b.meta ?? null);
      setState((b.data ?? []).length ? "ready" : "empty");
      setMsg((b.data ?? []).length ? "Backend request list loaded." : "No profile update requests found.");
    } catch (err) {
      setState("error");
      setMsg("Profile update requests unavailable. Previous rows were cleared.");
    }
  }

  useEffect(() => {
    let off = false;
    setToken("");
    setClub(0);
    clearListAndSelection();
    setState("loading");
    setCurrentMember(null);
    setPage(1);

    (async () => {
      try {
        if (mode === "review" && !canReviewRequests) {
          if (!off) {
            setState("forbidden");
            setMsg("This role is not authorized to access club-wide profile update review.");
          }
          return;
        }
        if (!session) throw new Error("No verified development session is available.");
        const access = session.token;
        const c = session.membership.club.id;
        if (!off) {
          setToken(access);
          setClub(c);
          const linkedMemberInfo = session.membership.member;
          if (linkedMemberInfo?.id) {
            const profileRes = await fetch(`${API}/clubs/${c}/members/me`, {
              headers: { Accept: "application/json", Authorization: `Bearer ${access}` },
            });
            if (profileRes.ok) {
              const profileData = await profileRes.json();
              setCurrentMember(profileData.member);
            }
          }
          await load(access, c, 1);
        }
      } catch {
        if (!off) {
          clearListAndSelection();
          setState("error");
          setMsg("Profile update requests unavailable.");
        }
      }
    })();

    return () => {
      off = true;
    };
  }, [role, mode, session, canReviewRequests]);

  async function detail(row: Row) {
    setSelected(null);
    setState("loading");
    const path = mode === "own" ? `/me/${row.id}` : `/${row.id}`;
    try {
      const r = await fetch(`${API}/clubs/${club}/profile-update-requests${path}`, {
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
        },
      });
      if (!r.ok) {
        if (r.status === 403) {
          setState("forbidden");
        } else if (r.status === 422) {
          setState("validation");
        } else if (r.status === 409) {
          setState("conflict");
        } else {
          setState("error");
        }
        setMsg(await text(r));
        return;
      }
      const b = await r.json();
      setSelected(b.data);
      setState("ready");
    } catch {
      setState("error");
      setMsg("Unable to load request details.");
    }
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();

    // Client-side validation
    const changes: Record<string, string> = {};
    if (phone.trim()) changes.phone = phone.trim();
    if (occupation.trim()) changes.occupation = occupation.trim();

    if (Object.keys(changes).length === 0) {
      setState("validation");
      setMsg("At least one proposed value must be provided.");
      return;
    }

    // Check if proposed values differ from current values
    let hasDifference = false;
    if (changes.phone && changes.phone !== currentMember?.phone) hasDifference = true;
    if (changes.occupation && changes.occupation !== currentMember?.occupation) hasDifference = true;

    if (!hasDifference) {
      setState("validation");
      setMsg("At least one proposed value must differ from the current profile.");
      return;
    }

    clearListAndSelection();
    setState("loading");
    try {
      const r = await fetch(`${API}/clubs/${club}/profile-update-requests`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ changes }),
      });
      if (!r.ok) {
        if (r.status === 403) {
          setState("forbidden");
        } else if (r.status === 422) {
          setState("validation");
        } else if (r.status === 409) {
          setState("conflict");
        } else {
          setState("error");
        }
        setMsg(await text(r));
        return;
      }
      setPhone("");
      setOccupation("");
      setMsg("Backend confirmed profile update request submission.");
      await load(token, club, 1);
    } catch {
      setState("error");
      setMsg("Submission failed due to network error.");
    }
  }

  async function action(kind: "withdraw" | "approve" | "reject") {
    if (!selected) return;

    if (kind === "reject") {
      if (!reason.trim()) {
        setState("validation");
        setMsg("Rejection reason is required.");
        return;
      }
      if (reason.trim().length < 3 || reason.trim().length > 500) {
        setState("validation");
        setMsg("Rejection reason must be between 3 and 500 characters.");
        return;
      }
    }

    clearListAndSelection();
    setState("loading");
    try {
      const body =
        kind === "reject"
          ? { lock_version: selected.lock_version, reason: reason.trim() }
          : { lock_version: selected.lock_version };

      const r = await fetch(`${API}/clubs/${club}/profile-update-requests/${selected.id}/${kind}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify(body),
      });

      if (!r.ok) {
        if (r.status === 403) {
          setState("forbidden");
        } else if (r.status === 422) {
          setState("validation");
        } else if (r.status === 409) {
          setState("conflict");
        } else {
          setState("error");
        }
        setMsg(await text(r));
        return;
      }

      setReason("");
      setMsg(`Backend confirmed ${kind}.`);
      await load(token, club, 1);
    } catch {
      setState("error");
      setMsg(`Action ${kind} failed due to network error.`);
    }
  }

  function handleFilterChange(
    statusVal: string,
    searchVal: string,
    fieldVal: string,
    dateFromVal: string,
    dateToVal: string
  ) {
    setPage(1);
    void load(token, club, 1, statusVal, searchVal, fieldVal, dateFromVal, dateToVal);
  }

  function handlePageChange(newPage: number) {
    setPage(newPage);
    void load(token, club, newPage);
  }

  function renderValue(fieldKey: string, val: any) {
    if (val === null || val === undefined) return "Not set";
    if (RESTRICTED.includes(fieldKey) && !showSensitive) {
      return "•••••••• (Restricted)";
    }
    return String(val);
  }

  return (
    <section className="stack">
      <div className="compactPanel">
        <h2>{mode === "own" ? "My Profile Update Requests" : "Profile Update Review Queue"}</h2>
        <p>Real backend workflow · exact before/after values · no dummy requests</p>
      </div>

      <div className="notice" id="profile-updates-msg">{msg}</div>

      {state === "error" && (
        <button type="button" onClick={() => void load()}>
          Retry Loading
        </button>
      )}

      {/* Filters Section */}
      <div className="compactPanel">
        <h3>Filters</h3>
        <div className="formGrid" style={{ gap: "0.5rem" }}>
          <label>
            Status
            <select
              value={filterStatus}
              onChange={(e) => {
                setFilterStatus(e.target.value);
                handleFilterChange(e.target.value, filterMemberSearch, filterChangedField, filterDateFrom, filterDateTo);
              }}
            >
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="withdrawn">Withdrawn</option>
            </select>
          </label>

          {mode === "review" && (
            <>
              <label>
                Member Search
                <input
                  type="text"
                  placeholder="Name or Code..."
                  value={filterMemberSearch}
                  onChange={(e) => {
                    setFilterMemberSearch(e.target.value);
                    handleFilterChange(filterStatus, e.target.value, filterChangedField, filterDateFrom, filterDateTo);
                  }}
                />
              </label>

              <label>
                Changed Field
                <select
                  value={filterChangedField}
                  onChange={(e) => {
                    setFilterChangedField(e.target.value);
                    handleFilterChange(filterStatus, filterMemberSearch, e.target.value, filterDateFrom, filterDateTo);
                  }}
                >
                  <option value="">All Fields</option>
                  <option value="phone">Phone</option>
                  <option value="occupation">Occupation</option>
                  <option value="full_name">Full Name</option>
                  <option value="birth_date">Birth Date</option>
                  <option value="spouse_name">Spouse Name</option>
                </select>
              </label>
            </>
          )}

          <label>
            Date From
            <input
              type="date"
              value={filterDateFrom}
              onChange={(e) => {
                setFilterDateFrom(e.target.value);
                handleFilterChange(filterStatus, filterMemberSearch, filterChangedField, e.target.value, filterDateTo);
              }}
            />
          </label>

          <label>
            Date To
            <input
              type="date"
              value={filterDateTo}
              onChange={(e) => {
                setFilterDateTo(e.target.value);
                handleFilterChange(filterStatus, filterMemberSearch, filterChangedField, filterDateFrom, e.target.value);
              }}
            />
          </label>
        </div>
      </div>

      {mode === "own" && currentMember && (
        <form className="compactPanel" onSubmit={submit}>
          <h3>Request Profile Update</h3>
          <div className="notice" style={{ fontSize: "0.85rem", background: "rgba(255,255,255,0.05)" }}>
            <div>Current Phone: <strong>{currentMember.phone || "Not provided"}</strong></div>
            <div>Current Occupation: <strong>{currentMember.occupation || "Not provided"}</strong></div>
          </div>
          <div className="formGrid">
            <label>
              Proposed phone
              <input
                type="text"
                placeholder="Enter new phone..."
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
              />
            </label>
            <label>
              Proposed occupation
              <input
                type="text"
                placeholder="Enter new occupation..."
                value={occupation}
                onChange={(e) => setOccupation(e.target.value)}
              />
            </label>
          </div>
          <button type="submit" className="primaryButton">
            Submit Request
          </button>
        </form>
      )}

      {/* Requests List */}
      <div className="tableLike" style={{ overflowX: "auto" }}>
        {rows.map((r) => (
          <article className="staticRow" key={r.id} style={{ display: "flex", flexWrap: "wrap", justifyContent: "space-between", gap: "0.5rem" }}>
            <div style={{ flex: "1 1 200px" }}>
              <strong>{r.member.display_name} · #{r.member.member_code}</strong>
              <div style={{ fontSize: "0.85rem", opacity: 0.8 }}>
                Status: <span style={{ fontWeight: "bold" }}>{r.status}</span> · Fields: {r.changed_fields.join(", ")}
              </div>
            </div>
            <div style={{ display: "flex", alignItems: "center", gap: "1rem" }}>
              <span style={{ fontSize: "0.8rem", opacity: 0.6 }}>{new Date(r.submitted_at).toLocaleDateString()}</span>
              <button type="button" onClick={() => void detail(r)} className="secondaryButton">
                View Request
              </button>
            </div>
          </article>
        ))}
      </div>

      {/* Pagination Controls */}
      {meta && meta.last_page > 1 && (
        <div className="buttonRow" style={{ justifyContent: "center", alignItems: "center", gap: "1rem", marginTop: "1rem" }}>
          <button
            type="button"
            className="secondaryButton"
            disabled={page === 1}
            onClick={() => handlePageChange(page - 1)}
          >
            Previous
          </button>
          <span>
            Page {meta.current_page} of {meta.last_page} (Total: {meta.total})
          </span>
          <button
            type="button"
            className="secondaryButton"
            disabled={page === meta.last_page}
            onClick={() => handlePageChange(page + 1)}
          >
            Next
          </button>
        </div>
      )}

      {/* Detail Panel */}
      {selected && (
        <div className="compactPanel" style={{ marginTop: "1.5rem" }}>
          <h3>Request #{selected.id} Details</h3>
          <div style={{ display: "flex", flexDirection: "column", gap: "0.5rem", marginBottom: "1rem" }}>
            <div>Member: <strong>{selected.member.display_name} ({selected.member.member_code})</strong></div>
            <div>Status: <strong style={{ textTransform: "capitalize" }}>{selected.status}</strong></div>
            {selected.status === "rejected" && selected.rejection_reason && (
              <div>Rejection Reason: <strong style={{ color: "#f44336" }}>{selected.rejection_reason}</strong></div>
            )}
            {selected.changes && selected.changes.length > 0 && (
              <div style={{ marginTop: "0.5rem" }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "0.5rem" }}>
                  <strong>Changes Proposed:</strong>
                  <button
                    type="button"
                    onClick={() => setShowSensitive(!showSensitive)}
                    style={{ fontSize: "0.8rem", padding: "2px 8px" }}
                    className="secondaryButton"
                  >
                    {showSensitive ? "Hide Restricted" : "Reveal Restricted"}
                  </button>
                </div>
                <div style={{ display: "flex", flexDirection: "column", gap: "0.25rem" }}>
                  {selected.changes.map((c) => (
                    <div
                      key={c.field_key}
                      style={{
                        background: "rgba(255,255,255,0.02)",
                        padding: "0.5rem",
                        borderRadius: "4px",
                        border: "1px solid rgba(255,255,255,0.05)",
                      }}
                    >
                      <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.85rem" }}>
                        <span style={{ fontFamily: "monospace", fontWeight: "bold" }}>{c.field_key}</span>
                        <span style={{ fontSize: "0.75rem", opacity: 0.6, textTransform: "capitalize" }}>
                          ({c.classification})
                        </span>
                      </div>
                      <div style={{ display: "flex", flexWrap: "wrap", gap: "1rem", marginTop: "0.25rem", fontSize: "0.9rem" }}>
                        <div style={{ flex: "1 1 120px" }}>
                          <span style={{ opacity: 0.6 }}>Before:</span>{" "}
                          <span style={{ wordBreak: "break-all" }}>{renderValue(c.field_key, c.original_value)}</span>
                        </div>
                        <div style={{ flex: "1 1 120px" }}>
                          <span style={{ opacity: 0.6 }}>After:</span>{" "}
                          <span style={{ wordBreak: "break-all", color: "#4caf50", fontWeight: "bold" }}>
                            {renderValue(c.field_key, c.proposed_value)}
                          </span>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          {selected.status === "pending" && (
            <div className="buttonRow" style={{ marginTop: "1rem", gap: "0.5rem", flexWrap: "wrap" }}>
              {mode === "own" ? (
                <button type="button" onClick={() => void action("withdraw")} className="secondaryButton">
                  Withdraw Request
                </button>
              ) : canManageRequests ? (
                <div style={{ display: "flex", flexDirection: "column", gap: "0.5rem", width: "100%" }}>
                  <button type="button" onClick={() => void action("approve")} className="primaryButton" style={{ background: "#4caf50" }}>
                    Approve Request
                  </button>
                  <div style={{ display: "flex", gap: "0.5rem", alignItems: "center" }}>
                    <input
                      aria-label="Rejection reason"
                      placeholder="Enter rejection reason..."
                      value={reason}
                      onChange={(e) => setReason(e.target.value)}
                      style={{ flex: 1 }}
                    />
                    <button type="button" onClick={() => void action("reject")} className="secondaryButton" style={{ background: "#f44336", color: "white" }}>
                      Reject
                    </button>
                  </div>
                </div>
              ) : (
                <div className="notice">This role can view only authorized profile update information and cannot approve or reject requests.</div>
              )}
            </div>
          )}
        </div>
      )}
    </section>
  );
}
