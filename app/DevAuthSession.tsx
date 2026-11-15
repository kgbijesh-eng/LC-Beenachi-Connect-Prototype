"use client";

import { createContext, Fragment, type ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from "react";
import { getApiBaseUrl } from "./apiBaseUrl";

export type DevRole = "president" | "secretary" | "treasurer" | "member";
export type DevIdentity = string;

export type DevMembership = {
  id: number;
  club_id?: number;
  status: string;
  club: { id: number; name?: string; code?: string };
  role: { slug: string; name?: string };
  permissions?: { id?: number; name?: string; slug: string; group?: string }[];
  effective_office?: { office: string; effective_until: string } | null;
  authority_as_of?: string;
  member: { id: number; member_code: string; full_name: string } | null;
};

type VerifiedDevSession = {
  identity: DevIdentity;
  role: DevRole;
  token: string;
  user: { id: number; name: string; email: string };
  membership: DevMembership;
  organisation: { id: number; code: string; name: string };
  generation: number;
};

type DevAuthContextValue = {
  enabled: boolean;
  status: "unauthenticated" | "authenticating" | "authenticated";
  session: VerifiedDevSession | null;
  error: string | null;
  login: (email: string, password: string) => Promise<boolean>;
  logout: () => Promise<void>;
  authenticatedFetch: (input: string, init?: RequestInit) => Promise<Response>;
};

const GENERIC_LOGIN_ERROR = "Unable to sign in with the provided credentials.";
const CONFIGURATION_ERROR = "The application service is currently unavailable because its API connection is not configured.";
const DevAuthContext = createContext<DevAuthContextValue | null>(null);

async function revokeToken(token: string) {
  try {
    await fetch(`${getApiBaseUrl()}/auth/logout`, {
      method: "POST",
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` }
    });
  } catch {
    // Local session invalidation must not depend on network availability.
  }
}

export function roleFromMembership(membership: DevMembership): DevRole {
  const office = membership.effective_office;
  const asOf = Date.parse(membership.authority_as_of ?? "");
  const end = Date.parse(office?.effective_until ?? "");
  if (!Number.isFinite(asOf) || !Number.isFinite(end) || end <= Math.max(asOf, Date.now())) return "member";
  if (office?.office === "president" || office?.office === "secretary" || office?.office === "treasurer") return office.office;
  return "member";
}

export function hasEffectivePermission(session: VerifiedDevSession | null, slug: string): boolean {
  return session !== null && roleFromMembership(session.membership) !== "member"
    && Array.isArray(session.membership.permissions)
    && session.membership.permissions.some(permission => permission?.slug === slug);
}

function hasSameEffectiveAuthority(active: VerifiedDevSession, refreshed: VerifiedDevSession): boolean {
  if (active.role !== refreshed.role
    || active.membership.id !== refreshed.membership.id
    || active.membership.effective_office?.effective_until !== refreshed.membership.effective_office?.effective_until) {
    return false;
  }

  const activePermissions = new Set(active.membership.permissions?.map(permission => permission.slug));
  const refreshedPermissions = new Set(refreshed.membership.permissions?.map(permission => permission.slug));
  return activePermissions.size === refreshedPermissions.size
    && [...activePermissions].every(slug => refreshedPermissions.has(slug));
}

const ordinaryPermissions = new Set([
  "club.view", "members.view", "events.view", "projects.view", "reminders.view",
  "announcements.view", "finance.self.view", "profile_updates.self.submit", "profile_updates.self.view"
]);

function withoutOfficerAuthority(membership: DevMembership): DevMembership {
  return {
    ...membership,
    effective_office: null,
    permissions: Array.isArray(membership.permissions)
      ? membership.permissions.filter(permission => ordinaryPermissions.has(permission?.slug))
      : []
  };
}

async function verifySession(token: string, signal: AbortSignal, generation: number): Promise<VerifiedDevSession> {
  const API = getApiBaseUrl();
  const response = await fetch(`${API}/auth/me`, {
    headers: { Accept: "application/json", Authorization: `Bearer ${token}` }, signal
  });
  if (!response.ok) throw new Error("Session verification failed.");

  const payload = await response.json();
  const user = payload.user as VerifiedDevSession["user"] | undefined;
  const memberships = payload.club_memberships as DevMembership[] | undefined;
  const organisation = payload.instance_organisation as VerifiedDevSession["organisation"] | undefined;
  const matches = Array.isArray(memberships) && organisation
    ? memberships.filter(item => item.status === "active" && item.club?.id === organisation.id && item.club?.code === organisation.code)
    : [];
  const membership = matches.length === 1 ? matches[0] : undefined;
  if (!user || !Number.isSafeInteger(user.id) || typeof user.email !== "string" || !organisation
    || !Number.isSafeInteger(organisation.id) || organisation.id < 1 || !organisation.code || !membership
    || (membership.club_id !== undefined && membership.club_id !== organisation.id) || !membership.role?.slug) {
    throw new Error("Session verification failed.");
  }

  return {
    identity: user.email,
    role: roleFromMembership(membership),
    token,
    user,
    membership: roleFromMembership(membership) === "member" ? withoutOfficerAuthority(membership) : membership,
    organisation,
    generation
  };
}

export function DevAuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<DevAuthContextValue["status"]>("unauthenticated");
  const [session, setSession] = useState<VerifiedDevSession | null>(null);
  const [error, setError] = useState<string | null>(null);
  const generation = useRef(0);
  const pending = useRef(new Set<AbortController>());
  const liveSession = useRef<VerifiedDevSession | null>(null);

  useEffect(() => {
    const controllers = pending.current;
    return () => {
      generation.current += 1;
      for (const controller of controllers) controller.abort();
      controllers.clear();
      liveSession.current = null;
    };
  }, []);

  const invalidate = useCallback(() => {
    generation.current += 1;
    for (const controller of pending.current) controller.abort();
    pending.current.clear();
    liveSession.current = null;
    setSession(null);
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    if (status === "authenticating") return false;
    invalidate();
    const attempt = generation.current;
    const controller = new AbortController();
    pending.current.add(controller);
    let issuedToken: string | null = null;
    setStatus("authenticating");
    setError(null);
    setSession(null);

    try {
      const API = getApiBaseUrl();
      const response = await fetch(`${API}/auth/login`, {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json" },
        body: JSON.stringify({ email, password }), signal: controller.signal
      });
      if (!response.ok) throw new Error("Authentication failed.");

      const payload = await response.json();
      if (typeof payload.token !== "string" || !payload.token) {
        throw new Error("Authentication failed.");
      }
      issuedToken = payload.token;

      if (generation.current !== attempt) return false;
      const verified = await verifySession(payload.token, controller.signal, attempt);
      if (generation.current !== attempt) return false;
      liveSession.current = verified;
      setSession(verified);
      setStatus("authenticated");
      return true;
    } catch (error) {
      if (generation.current !== attempt) return false;
      setSession(null);
      setStatus("unauthenticated");
      setError(error instanceof Error && error.message === "API configuration is unavailable." ? CONFIGURATION_ERROR : GENERIC_LOGIN_ERROR);
      return false;
    } finally {
      pending.current.delete(controller);
      if (issuedToken && liveSession.current?.token !== issuedToken) void revokeToken(issuedToken);
    }
  }, [invalidate, status]);

  const logout = useCallback(async () => {
    const token = liveSession.current?.token;
    invalidate();
    setError(null);
    setStatus("unauthenticated");
    if (!token) return;

    await revokeToken(token);
  }, [invalidate]);

  const refreshCapabilities = useCallback(async (active: VerifiedDevSession, signal?: AbortSignal) => {
    try {
      const refreshed = await verifySession(active.token, signal ?? new AbortController().signal, active.generation);
      if (liveSession.current !== active || signal?.aborted) return;
      if (refreshed.organisation.id !== active.organisation.id || refreshed.user.id !== active.user.id) {
        invalidate();
        setStatus("unauthenticated");
        return;
      }
      if (hasSameEffectiveAuthority(active, refreshed)) return;
      liveSession.current = refreshed;
      setSession(refreshed);
    } catch {
      if (liveSession.current !== active || signal?.aborted) return;
      const restricted = { ...active, role: "member" as const, membership: withoutOfficerAuthority(active.membership) };
      liveSession.current = restricted;
      setSession(restricted);
    }
  }, [invalidate]);

  useEffect(() => {
    if (!session || session.role === "member") return;
    const expires = Date.parse(session.membership.effective_office?.effective_until ?? "");
    if (!Number.isFinite(expires)) return;
    let timer: number | undefined;
    const checkExpiry = () => {
      window.clearTimeout(timer);
      if (liveSession.current !== session) return;
      const remaining = expires - Date.now();
      if (remaining > 0) {
        timer = window.setTimeout(checkExpiry, Math.min(remaining, 2_147_483_647));
        return;
      }

      const expired = { ...session, role: "member" as const, membership: withoutOfficerAuthority(session.membership) };
      liveSession.current = expired;
      setSession(expired);
      void refreshCapabilities(expired);
    };
    checkExpiry();
    window.addEventListener("focus", checkExpiry);
    document.addEventListener("visibilitychange", checkExpiry);
    return () => {
      window.clearTimeout(timer);
      window.removeEventListener("focus", checkExpiry);
      document.removeEventListener("visibilitychange", checkExpiry);
    };
  }, [refreshCapabilities, session]);

  const authenticatedFetch = useCallback(async (input: string, init: RequestInit = {}) => {
    const active = liveSession.current;
    if (!session || active !== session) throw new Error("No verified session is active.");
    const API = getApiBaseUrl();
    const url = new URL(input.startsWith("/") ? `${API}${input}` : input);
    const base = new URL(API);
    const club = /^\/api\/v1\/clubs\/([^/]+)(?:\/|$)/.exec(url.pathname);
    if (url.origin !== base.origin || url.username || url.password || !url.pathname.startsWith(`${base.pathname}/`)
      || (club && club[1] !== String(active.organisation.id))) {
      throw new Error("The request does not belong to this organisation.");
    }
    const attempt = generation.current;
    const controller = new AbortController();
    const abort = () => controller.abort();
    if (init.signal?.aborted) controller.abort();
    init.signal?.addEventListener("abort", abort, { once: true });
    pending.current.add(controller);
    const headers = new Headers(init.headers);
    headers.set("Accept", "application/json");
    headers.set("Authorization", `Bearer ${active.token}`);
    try {
      const response = await fetch(url.href, { ...init, headers, signal: controller.signal });
      if (generation.current !== attempt) throw new DOMException("Session changed.", "AbortError");
      if (response.status === 403) void refreshCapabilities(active);
      return response;
    } finally {
      pending.current.delete(controller);
      init.signal?.removeEventListener("abort", abort);
    }
  }, [refreshCapabilities, session]);

  const value = useMemo<DevAuthContextValue>(() => ({
    enabled: true,
    status,
    session,
    error,
    login,
    logout,
    authenticatedFetch
  }), [authenticatedFetch, error, login, logout, session, status]);

  // Unmount all organisation-owned state on logout and on every verified new session.
  return <DevAuthContext.Provider value={value}><Fragment key={session ? `${session.organisation.id}:${session.user.id}:${session.generation}` : "signed-out"}>{children}</Fragment></DevAuthContext.Provider>;
}

export function useDevAuth() {
  const context = useContext(DevAuthContext);
  if (!context) throw new Error("useDevAuth must be used inside DevAuthProvider.");
  return context;
}
